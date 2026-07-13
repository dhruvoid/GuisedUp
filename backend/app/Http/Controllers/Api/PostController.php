<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\EmbeddingService;
use App\Services\FeedRankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private FeedRankingService $feedRankingService,
    ) {}

    /**
     * POST /api/posts
     * Create a new post. Accepts text + optional image.
     * Auto-generates a vector embedding and stores it in Pinecone.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content'   => 'required|string|max:5000',
            'image_url' => 'nullable|url',
            'image'     => 'nullable|image|max:5120', // optional file upload (5MB max)
        ]);

        $imageUrl = $validated['image_url'] ?? null;

        // Handle direct image file upload (store to disk / S3)
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('posts', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        // Compute authenticity score heuristically
        $authenticityScore = $this->feedRankingService->computeAuthenticityScore(
            $validated['content'],
            $imageUrl
        );

        // Create post in DB
        $post = Post::create([
            'user_id'           => $request->user()->id,
            'content'           => $validated['content'],
            'image_url'         => $imageUrl,
            'authenticity_score' => $authenticityScore,
        ]);

        // Generate embedding and store in Pinecone (via Python ML service)
        $embedding = $this->embeddingService->embed($validated['content']);

        // The Python /upsert endpoint stores the vector in Pinecone
        // and returns the Pinecone vector ID
        try {
            $mlResponse = \Illuminate\Support\Facades\Http::timeout(10)
                ->post(config('services.ml_service.url', 'http://localhost:8001') . '/upsert', [
                    'post_id'   => $post->id,
                    'author_id' => $post->user_id,
                    'embedding' => $embedding,
                    'created_at'=> $post->created_at->toIso8601String(),
                ]);

            if ($mlResponse->successful()) {
                $post->update(['pinecone_vector_id' => $mlResponse->json('vector_id')]);
            }
        } catch (\Exception $e) {
            // Non-fatal: post is created, embedding will be missing from Pinecone
            // In production, this would be queued via Laravel Horizon
            \Illuminate\Support\Facades\Log::warning(
                "Failed to upsert embedding for post {$post->id}: " . $e->getMessage()
            );
        }

        return response()->json([
            'id'                => $post->id,
            'status'            => 'created',
            'authenticity_score'=> $post->authenticity_score,
            'post'              => $post->load('user'),
        ], 201);
    }

    /**
     * GET /api/posts/{id}
     * Show a single post.
     */
    public function show(Post $post)
    {
        return response()->json($post->load('user'));
    }
}
