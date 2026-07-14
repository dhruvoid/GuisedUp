<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\Interaction;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * FeedRankingService
 *
 * Implements the "RealConnections" feed ranking algorithm.
 * Combines four signals to score each candidate post:
 *   1. Authenticity Score  — set at post creation, higher = more genuine
 *   2. Relationship Depth  — more past interactions with author → higher multiplier
 *   3. Time Decay          — exponential decay with a 24-hour half-life
 *   4. (Search) Semantic Similarity — used in SearchController, not here
 */
class FeedRankingService
{
    /**
     * Returns a ranked, paginated collection of posts for $user.
     *
     * @param User $user
     * @param int  $page
     * @param int  $perPage
     * @return array{ data: Collection, total: int, page: int, per_page: int }
     */
    public function getRankedFeed(User $user, int $page = 1, int $perPage = 20): array
    {
        // 1. Fetch candidate posts from the last 7 days (not authored by self)
        $candidates = Post::with('user')
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->get();

        // 2. Pre-compute relationship-depth affinity for the current user
        //    affinity[author_id] = number of interactions with that author's posts
        $affinity = $this->computeAffinity($user->id);

        // 3. Score each candidate post
        $scored = $candidates->map(function (Post $post) use ($affinity) {
            $score = $this->score($post, $affinity);
            $post->ranking_score = $score;
            return $post;
        });

        // 4. Sort by score descending
        $sorted = $scored->sortByDesc('ranking_score')->values();

        // 5. Paginate in-memory
        $total   = $sorted->count();
        $offset  = ($page - 1) * $perPage;
        $paged   = $sorted->slice($offset, $perPage)->values();

        return [
            'data'     => $paged,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Compute affinity map: author_id → interaction count
     * based on the current user's past interactions.
     */
    private function computeAffinity(int $userId): array
    {
        // Get all posts the user interacted with, group by author
        $rows = Interaction::query()
            ->join('posts', 'posts.id', '=', 'interactions.post_id')
            ->where('interactions.user_id', $userId)
            ->where('interactions.created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('posts.user_id as author_id, COUNT(interactions.id) as cnt')
            ->groupBy('posts.user_id')
            ->get();

        $affinity = [];
        foreach ($rows as $row) {
            $affinity[$row->author_id] = (int) $row->cnt;
        }

        return $affinity;
    }

    /**
     * Score a single post.
     *
     * Formula:
     *   score = authenticity_score × relationship_multiplier × time_multiplier
     *
     * relationship_multiplier = 1.0 + 0.1 × interaction_count (capped at 3.0)
     * time_multiplier = exp(-λ × hours_old)  where λ = ln(2)/24 gives 50% at 24h
     */
    public function score(Post $post, array $affinity): float
    {
        // Signal 1: Authenticity (0.0 – 1.0)
        $authenticityScore = (float) $post->authenticity_score;

        // Signal 2: Relationship Depth
        $interactionCount       = $affinity[$post->user_id] ?? 0;
        $relationshipMultiplier = min(1.0 + 0.1 * $interactionCount, 3.0);

        // Signal 3: Time Decay — half-life of 24 hours
        $hoursOld       = Carbon::now()->diffInHours($post->created_at, false) * -1;
        $lambda         = log(2) / 24;   // 0.02888...
        $timeMultiplier = exp(-$lambda * max($hoursOld, 0));

        return $authenticityScore * $relationshipMultiplier * $timeMultiplier;
    }

    /**
     * Compute the authenticity score for a new post heuristically.
     *
     * Rules:
     *   - Base score: 0.5
     *   - No image: +0.1 (text-only = more genuine)
     *   - Short content (< 100 chars): +0.1 (not over-crafted)
     *   - Contains hashtags (#): -0.1 (typically more curated)
     *   - Contains emojis in excess (>5): -0.05
     *
     */
    public function computeAuthenticityScore(string $content, ?string $imageUrl): float
    {
        $score = 0.5;

        if (is_null($imageUrl)) {
            $score += 0.1;
        }

        if (strlen($content) < 100) {
            $score += 0.1;
        }

        if (preg_match_all('/#\w+/', $content) > 0) {
            $score -= 0.1;
        }

        // Count emoji-like characters (simplified via Unicode ranges)
        $emojiCount = preg_match_all(
            '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}]/u',
            $content
        );
        if ($emojiCount > 5) {
            $score -= 0.05;
        }

        return round(max(0.0, min(1.0, $score)), 4);
    }
}
