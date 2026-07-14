<?php

namespace Database\Seeders;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\FeedRankingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // Create 5 test users
        // -------------------------------------------------------
        $usersData = [
            ['email' => 'priya@guisedup.com', 'name' => 'Priya Sharma'],
            ['email' => 'arjun@guisedup.com', 'name' => 'Arjun Mehta'],
            ['email' => 'rahul@guisedup.com', 'name' => 'Rahul Desai'],
            ['email' => 'sneha@guisedup.com', 'name' => 'Sneha Kapoor'],
            ['email' => 'rohan@guisedup.com', 'name' => 'Rohan Singh'],
        ];

        $users = [];
        foreach ($usersData as $ud) {
            $users[] = User::firstOrCreate(
                ['email' => $ud['email']],
                [
                    'name'     => $ud['name'],
                    'password' => Hash::make('password123'),
                ]
            );
        }

        // -------------------------------------------------------
        // Seed some posts for each user
        // -------------------------------------------------------
        $postsData = [
            [
                ['content' => 'Just had the most chaotic day at work — nothing went to plan and honestly I loved it', 'image_url' => null],
                ['content' => 'Late night thought: do birds ever get bored?', 'image_url' => null],
                ['content' => 'A photo from my balcony at sunrise. No filter, no edits.', 'image_url' => 'https://picsum.photos/seed/balcony/800/600'],
            ],
            [
                ['content' => 'Learning Rust. Send help.', 'image_url' => null],
                ['content' => 'Mom made rajma chawal today. I do not deserve this.', 'image_url' => null],
                ['content' => 'New personal record — ran 5k in under 25 minutes', 'image_url' => 'https://picsum.photos/seed/running/800/600'],
            ],
            [
                ['content' => 'I just spent 3 hours debugging only to realize I missed a semicolon.', 'image_url' => null],
                ['content' => 'Why does pizza taste better at 2 AM?', 'image_url' => null],
                ['content' => '#grindset always working never sleeping', 'image_url' => 'https://picsum.photos/seed/grind/800/600'],
            ],
            [
                ['content' => 'Just finished reading the best book of my life. I am in tears.', 'image_url' => null],
                ['content' => 'Here is my dog sleeping perfectly in a sunbeam.', 'image_url' => 'https://picsum.photos/seed/dog/800/600'],
            ],
            [
                ['content' => 'Finally saved up enough to buy my dream guitar!', 'image_url' => 'https://picsum.photos/seed/guitar/800/600'],
                ['content' => 'Is anyone else completely obsessed with the new sci-fi movie?', 'image_url' => null],
            ],
        ];

        $authenticityScorer = app(\App\Services\FeedRankingService::class);

        foreach ($postsData as $index => $userPosts) {
            $user = $users[$index];
            foreach ($userPosts as $postData) {
                Post::create([
                    'user_id'           => $user->id,
                    'content'           => $postData['content'],
                    'image_url'         => $postData['image_url'],
                    'authenticity_score'=> $authenticityScorer->computeAuthenticityScore(
                        $postData['content'],
                        $postData['image_url']
                    ),
                ]);
            }
        }

        // -------------------------------------------------------
        // Seed some random interactions to simulate relationship depth
        // -------------------------------------------------------
        $allPosts = Post::all();
        foreach ($users as $u) {
            $filtered = $allPosts->where('user_id', '!=', $u->id);
            $otherPosts = $filtered->random(min(4, $filtered->count()));
            
            foreach ($otherPosts as $post) {
                Interaction::firstOrCreate(['user_id' => $u->id, 'post_id' => $post->id, 'type' => 'view']);
                // 50% chance to react
                if (rand(0, 1) === 1) {
                    Interaction::firstOrCreate(['user_id' => $u->id, 'post_id' => $post->id, 'type' => 'reaction']);
                }
            }
        }

        $this->command->info('Seeded 5 users, ' . $allPosts->count() . ' posts, and random interactions.');
        foreach ($usersData as $ud) {
            $this->command->info('  ' . $ud['email'] . ' / password123');
        }

        // -------------------------------------------------------
        // Upsert all post embeddings into the Python ML service
        // so that semantic search works immediately after seeding.
        // -------------------------------------------------------
        $this->command->info('Upserting post embeddings into ML service...');
        $embeddingService = app(EmbeddingService::class);
        $mlServiceUrl = config('services.ml_service.url', 'http://localhost:8001');
        $upserted = 0;

        foreach (Post::all() as $post) {
            try {
                $embedding = $embeddingService->embed($post->content);
                Http::timeout(10)->post("{$mlServiceUrl}/upsert", [
                    'post_id'    => $post->id,
                    'author_id'  => $post->user_id,
                    'embedding'  => $embedding,
                    'created_at' => $post->created_at->toIso8601String(),
                ]);
                $upserted++;
            } catch (\Exception $e) {
                $this->command->warn("Could not upsert post {$post->id}: " . $e->getMessage());
            }
        }

        $this->command->info("Upserted {$upserted} post embeddings. Semantic search is ready.");
    }
}
