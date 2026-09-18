<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMedia;
use Tests\TestCase;

final class PostTest extends TestCase
{
    use RefreshDatabase;

    private const POST_BODY = 'Hello amteCHAT, this is my first post!';

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_authenticated_user_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => self::POST_BODY])
            ->assertStatus(201)
            ->assertJsonPath('data.post.body', self::POST_BODY)
            ->assertJsonPath('data.post.author.username', $user->username)
            ->assertJsonPath('errors', []);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'body' => self::POST_BODY,
        ]);
    }

    public function test_creating_a_post_requires_authentication(): void
    {
        $this->postJson('/api/v1/posts', ['body' => self::POST_BODY])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_blank_post_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'content');
    }

    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/v1/posts')->assertStatus(401);
    }

    public function test_feed_returns_posts_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Post::factory()->for($user)->create(['body' => 'first post']);
        $newer = Post::factory()->for($user)->create(['body' => 'second post']);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.posts.0.id', $newer->id)
            ->assertJsonPath('data.posts.1.id', $older->id)
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_feed_is_cursor_paginated(): void
    {
        $user = User::factory()->create();
        $posts = Post::factory()->count(5)->for($user)->create();
        $newest = $posts->sortByDesc('id')->take(2)->pluck('id');

        $first = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts?limit=2')
            ->assertOk()
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.limit', 2);

        $ids = collect($first->json('data.posts'))->pluck('id');
        $this->assertSame($newest->values()->all(), $ids->values()->all());

        $nextCursor = $first->json('data.next_cursor');
        $this->assertNotNull($nextCursor);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts?limit=2&cursor='.$nextCursor)
            ->assertOk()
            ->assertJsonCount(2, 'data.posts');
    }

    public function test_author_is_eager_loaded_in_feed(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->create();

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts')
            ->assertOk();

        $response->assertJsonPath('data.posts.0.author.username', $user->username);
    }

    public function test_post_with_image_media_is_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'body' => 'Check out this photo!',
                'media' => [FakeMedia::png(640, 480)],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.post.media.0.type', 'image')
            ->assertJsonPath('data.post.media.0.width', 640)
            ->assertJsonPath('data.post.media.0.height', 480);

        $this->assertDatabaseCount('post_media', 1);
        $this->assertDatabaseHas('post_media', ['type' => 'image', 'sort_order' => 0]);
        $this->assertCount(1, Storage::disk('public')->files('posts/'.$user->id));
    }

    public function test_post_with_video_media_is_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'media' => [UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4')],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.post.body', '')
            ->assertJsonPath('data.post.media.0.type', 'video');

        $this->assertDatabaseHas('post_media', ['type' => 'video']);
    }

    public function test_post_without_text_or_media_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'content');
    }

    public function test_unsupported_media_type_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'body' => 'A doc upload',
                'media' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_MEDIA')
            ->assertJsonPath('errors.0.field', 'media');
    }

    public function test_more_than_five_media_files_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $files = array_map(
            fn (int $i): UploadedFile => FakeMedia::png(100, 100),
            range(1, 6),
        );

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', ['media' => $files])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'body' => 'Too big',
                'media' => [FakeMedia::png(100, 100, 9 * 1024 * 1024)],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_MEDIA');
    }

    public function test_feed_returns_media_attached_to_posts(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();
        $post->media()->create([
            'type' => 'image',
            'file_path' => 'posts/'.$user->id.'/demo.png',
            'mime' => 'image/png',
            'size' => 100,
            'width' => 100,
            'height' => 100,
            'sort_order' => 0,
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.posts.0.media.0.type', 'image')
            ->assertJsonPath('data.posts.0.media.0.url', url('/storage/posts/'.$user->id.'/demo.png'));
    }
}
