<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeedRankingService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private FeedRankingService $feedRankingService) {}

    /**
     * GET /api/feed?page=1
     * Return a personalized, ranked feed for the authenticated user.
     * Paginated at 20 posts per page.
     */
    public function index(Request $request)
    {
        $page = (int) $request->query('page', 1);

        $result = $this->feedRankingService->getRankedFeed(
            user: $request->user(),
            page: $page,
            perPage: 20
        );

        // Shape each post for the client
        $data = $result['data']->map(function ($post) {
            return $this->formatPost($post);
        });

        $hasNextPage  = ($page * 20) < $result['total'];
        $nextPageUrl  = $hasNextPage
            ? url("/api/feed?page=" . ($page + 1))
            : null;

        return response()->json([
            'data'          => $data,
            'total'         => $result['total'],
            'page'          => $result['page'],
            'per_page'      => $result['per_page'],
            'next_page_url' => $nextPageUrl,
        ]);
    }

    private function formatPost($post): array
    {
        return [
            'id'                 => $post->id,
            'content'            => $post->content,
            'image_url'          => $post->image_url,
            'authenticity_score' => $post->authenticity_score,
            'ranking_score'      => round($post->ranking_score ?? 0, 4),
            'created_at'         => $post->created_at->toIso8601String(),
            'time_ago'           => $post->created_at->diffForHumans(),
            'reaction_count'     => $post->interactions()->where('type', 'reaction')->count(),
            'view_count'         => $post->interactions()->where('type', 'view')->count(),
            'author'             => [
                'id'         => $post->user->id,
                'name'       => $post->user->name,
                'avatar_url' => $post->user->avatar_url,
            ],
        ];
    }
}
