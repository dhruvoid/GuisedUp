<?php

namespace Database\Seeders;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // Create 2 required test users
        // -------------------------------------------------------
        $user1 = User::firstOrCreate(
            ['email' => 'priya@guisedup.com'],
            [
                'name'     => 'Priya Sharma',
                'password' => Hash::make('password123'),
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'arjun@guisedup.com'],
            [
                'name'     => 'Arjun Mehta',
                'password' => Hash::make('password123'),
            ]
        );

        // -------------------------------------------------------
        // Seed some posts for each user (with varied authenticity)
        // -------------------------------------------------------
        $posts1 = [
            ['content' => 'Just had the most chaotic day at work — nothing went to plan and honestly I loved it', 'image_url' => null],
            ['content' => 'Late night thought: do birds ever get bored?', 'image_url' => null],
            ['content' => 'A photo from my balcony at sunrise. No filter, no edits.', 'image_url' => 'https://picsum.photos/seed/balcony/800/600'],
            ['content' => 'Hot take: chai from a roadside stall > every specialty cafe in Bangalore', 'image_url' => null],
            ['content' => '#productivity #hustle woke up at 5am and journaled for an hour', 'image_url' => 'https://picsum.photos/seed/morning/800/600'],
        ];

        $posts2 = [
            ['content' => 'Learning Rust. Send help.', 'image_url' => null],
            ['content' => 'Mom made rajma chawal today. I do not deserve this.', 'image_url' => null],
            ['content' => 'Been feeling a bit disconnected lately. Anyone else?', 'image_url' => null],
            ['content' => 'New personal record — ran 5k in under 25 minutes', 'image_url' => 'https://picsum.photos/seed/running/800/600'],
            ['content' => '#fitness #goals #motivation crushing it every single day 💪💪💪💪💪💪', 'image_url' => 'https://picsum.photos/seed/gym/800/600'],
        ];

        $authenticityScorer = app(\App\Services\FeedRankingService::class);

        foreach ($posts1 as $postData) {
            Post::create([
                'user_id'           => $user1->id,
                'content'           => $postData['content'],
                'image_url'         => $postData['image_url'],
                'authenticity_score'=> $authenticityScorer->computeAuthenticityScore(
                    $postData['content'],
                    $postData['image_url']
                ),
            ]);
        }

        foreach ($posts2 as $postData) {
            Post::create([
                'user_id'           => $user2->id,
                'content'           => $postData['content'],
                'image_url'         => $postData['image_url'],
                'authenticity_score'=> $authenticityScorer->computeAuthenticityScore(
                    $postData['content'],
                    $postData['image_url']
                ),
            ]);
        }

        // -------------------------------------------------------
        // Seed some interactions (user1 reacts to user2's posts and vice versa)
        // This populates the relationship-depth signal
        // -------------------------------------------------------
        $user2Posts = Post::where('user_id', $user2->id)->get();
        $user1Posts = Post::where('user_id', $user1->id)->get();

        foreach ($user2Posts->take(3) as $post) {
            Interaction::firstOrCreate(['user_id' => $user1->id, 'post_id' => $post->id, 'type' => 'view']);
            Interaction::firstOrCreate(['user_id' => $user1->id, 'post_id' => $post->id, 'type' => 'reaction']);
        }

        foreach ($user1Posts->take(2) as $post) {
            Interaction::firstOrCreate(['user_id' => $user2->id, 'post_id' => $post->id, 'type' => 'view']);
        }

        $this->command->info('Seeded 2 users, 10 posts, and sample interactions.');
        $this->command->info('Test credentials:');
        $this->command->info('  priya@guisedup.com / password123');
        $this->command->info('  arjun@guisedup.com / password123');
    }
}
