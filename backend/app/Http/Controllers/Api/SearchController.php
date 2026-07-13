<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\EmbeddingService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private EmbeddingService $embeddingService) {}

    /**
     * GET /api/search?q={query}
     *
     * Natural language search across posts using vector similarity.
     * Returns top 10 semantically relevant results.
     *
     * Flow:
     *   1. Send the query string to the Python ML service /search endpoint
     *   2. Python embeds the query and queries Pinecone for top-K similar vectors
     *   3. Python returns ordered post_ids
     *   4. Laravel fetches those posts from MySQL in the returned order
     */
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'required|string|max:500',
        ]);

        $query = $request->query('q');

        // 1. Get ordered post IDs from vector similarity search
        $postIds = $this->embeddingService->search($query, topK: 10);

        if (empty($postIds)) {
            // Fallback: basic LIKE search if vector service is unavailable
            $posts = Post::with('user')
                ->where('content', 'LIKE', '%' . $query . '%')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        } else {
            // Fetch posts from DB preserving the Pinecone-ranked order
            $posts = Post::with('user')
                ->whereIn('id', $postIds)
                ->get()
                ->sortBy(fn($post) => array_search($post->id, $postIds))
                ->values();
        }

        $data = $posts->map(fn($post) => [
            'id'             => $post->id,
            'content'        => $post->content,
            'image_url'      => $post->image_url,
            'created_at'     => $post->created_at->toIso8601String(),
            'time_ago'       => $post->created_at->diffForHumans(),
            'reaction_count' => $post->interactions()->where('type', 'reaction')->count(),
            'author'         => [
                'id'         => $post->user->id,
                'name'       => $post->user->name,
                'avatar_url' => $post->user->avatar_url,
            ],
        ]);

        return response()->json([
            'query' => $query,
            'data'  => $data,
        ]);
    }
}
