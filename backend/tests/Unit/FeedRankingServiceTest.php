<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Services\FeedRankingService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * FeedRankingServiceTest
 *
 * Tests the core business logic of the feed ranking algorithm.
 * These tests validate the mathematical correctness and signal weighting.
 */
class FeedRankingServiceTest extends TestCase
{
    private FeedRankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeedRankingService();
    }

    // ---------------------------------------------------------------
    // Test 1: Authenticity score heuristics
    // ---------------------------------------------------------------

    /** @test */
    public function it_gives_higher_authenticity_score_to_text_only_short_posts(): void
    {
        $shortTextScore = $this->service->computeAuthenticityScore('Just tired today.', null);
        $longHashtagScore = $this->service->computeAuthenticityScore(
            '#hustle #grind #motivation woke up at 5am and smashed my goals today!!! Living my best life every single day.',
            'https://example.com/image.jpg'
        );

        $this->assertGreaterThan($longHashtagScore, $shortTextScore);
    }

    /** @test */
    public function it_penalises_posts_with_images_and_hashtags(): void
    {
        $baseScore    = $this->service->computeAuthenticityScore('Plain honest text', null);
        $penaltyScore = $this->service->computeAuthenticityScore('#ad #sponsored', 'https://example.com/img.jpg');

        $this->assertGreaterThan($penaltyScore, $baseScore);
    }

    /** @test */
    public function authenticity_score_is_clamped_between_0_and_1(): void
    {
        // Even with many penalty factors, score should never go below 0
        $score = $this->service->computeAuthenticityScore(
            str_repeat('#tag ', 20) . str_repeat('😀', 10),
            'https://example.com/image.jpg'
        );

        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    // ---------------------------------------------------------------
    // Test 2: Time decay correctness
    // ---------------------------------------------------------------

    /** @test */
    public function it_scores_newer_posts_higher_than_old_ones(): void
    {
        $affinity = []; // No relationship for simplicity

        $newPost          = new Post(['authenticity_score' => 0.8, 'user_id' => 999]);
        $newPost->created_at = Carbon::now()->subHours(1);

        $oldPost          = new Post(['authenticity_score' => 0.8, 'user_id' => 998]);
        $oldPost->created_at = Carbon::now()->subHours(72);

        $newScore = $this->service->score($newPost, $affinity);
        $oldScore = $this->service->score($oldPost, $affinity);

        $this->assertGreaterThan($oldScore, $newScore);
    }

    /** @test */
    public function time_decay_halves_score_at_24_hours(): void
    {
        $affinity = [];

        $freshPost = new Post(['authenticity_score' => 1.0, 'user_id' => 1]);
        $freshPost->created_at = Carbon::now();

        $dayOldPost = new Post(['authenticity_score' => 1.0, 'user_id' => 2]);
        $dayOldPost->created_at = Carbon::now()->subHours(24);

        $freshScore  = $this->service->score($freshPost, $affinity);
        $dayOldScore = $this->service->score($dayOldPost, $affinity);

        // The 24-hour-old post should score approximately 50% of the fresh post
        $this->assertEqualsWithDelta(0.5, $dayOldScore / $freshScore, 0.01);
    }

    // ---------------------------------------------------------------
    // Test 3: Relationship depth boosts score
    // ---------------------------------------------------------------

    /** @test */
    public function it_boosts_score_for_authors_user_interacts_with(): void
    {
        $authorId = 42;
        $affinity = [$authorId => 10]; // 10 past interactions with this author

        $post = new Post(['authenticity_score' => 0.5, 'user_id' => $authorId]);
        $post->created_at = Carbon::now()->subHours(1);

        $noAffinityPost = new Post(['authenticity_score' => 0.5, 'user_id' => 99]);
        $noAffinityPost->created_at = Carbon::now()->subHours(1);

        $boostedScore   = $this->service->score($post, $affinity);
        $unboostedScore = $this->service->score($noAffinityPost, $affinity);

        $this->assertGreaterThan($unboostedScore, $boostedScore);
    }

    /** @test */
    public function relationship_multiplier_is_capped_at_3x(): void
    {
        $authorId   = 42;
        $highAffinity = [$authorId => 1000]; // Extremely high interaction count

        $post = new Post(['authenticity_score' => 1.0, 'user_id' => $authorId]);
        $post->created_at = Carbon::now();

        $score = $this->service->score($post, $highAffinity);

        // Max possible score = 1.0 (auth) × 3.0 (max multiplier) × ~1.0 (time, fresh)
        $this->assertLessThanOrEqual(3.0 + 0.01, $score);
    }
}
