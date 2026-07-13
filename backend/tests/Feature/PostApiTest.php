<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PostApiTest
 *
 * Feature tests for the Posts API endpoint.
 * Tests authentication, validation, and response structure.
 */
class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /** @test */
    public function authenticated_user_can_create_a_post(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/posts', [
                'content' => 'This is a genuine post about my day.',
            ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id',
                     'status',
                     'authenticity_score',
                     'post' => ['id', 'content', 'user'],
                 ])
                 ->assertJson(['status' => 'created']);

        $this->assertDatabaseHas('posts', [
            'user_id' => $this->user->id,
            'content' => 'This is a genuine post about my day.',
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_a_post(): void
    {
        $this->postJson('/api/posts', ['content' => 'Hello world'])
             ->assertStatus(401);
    }

    /** @test */
    public function post_creation_requires_content_field(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/posts', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['content']);
    }

    /** @test */
    public function post_with_image_url_is_stored_correctly(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/posts', [
                'content'   => 'A photo from my balcony',
                'image_url' => 'https://example.com/photo.jpg',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'image_url' => 'https://example.com/photo.jpg',
        ]);
    }

    /** @test */
    public function text_only_post_has_higher_authenticity_than_hashtagged_post(): void
    {
        $textResponse = $this->withToken($this->token)
            ->postJson('/api/posts', ['content' => 'Just a genuine moment.']);

        $hashtagResponse = $this->withToken($this->token)
            ->postJson('/api/posts', [
                'content'   => '#hustle #motivation living my best life',
                'image_url' => 'https://example.com/img.jpg',
            ]);

        $textScore    = $textResponse->json('authenticity_score');
        $hashtagScore = $hashtagResponse->json('authenticity_score');

        $this->assertGreaterThan($hashtagScore, $textScore);
    }
}
