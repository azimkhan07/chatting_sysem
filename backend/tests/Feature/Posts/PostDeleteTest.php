<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PostDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_requires_authentication(): void
    {
        $post = $this->postBy(User::factory()->create());

        $this->deleteJson("/api/v1/posts/{$post->id}")->assertStatus(401);
    }

    public function test_the_author_can_delete_their_post(): void
    {
        $author = User::factory()->create();
        $post = $this->postBy($author);

        Sanctum::actingAs($author);

        $this->deleteJson("/api/v1/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            // The id comes back so the client can confirm it dropped the card
            // it optimistically removed.
            ->assertJsonPath('data.id', $post->id);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_a_deleted_post_leaves_the_feed(): void
    {
        $author = User::factory()->create();
        $post = $this->postBy($author);

        Sanctum::actingAs($author);
        $this->getJson('/api/v1/posts/me')
            ->assertJsonCount(1, 'data.posts');

        $this->deleteJson("/api/v1/posts/{$post->id}")->assertOk();

        $this->getJson('/api/v1/posts/me')
            ->assertJsonCount(0, 'data.posts');
    }

    public function test_you_cannot_delete_someone_elses_post(): void
    {
        $author = User::factory()->create();
        $stranger = User::factory()->create();
        $post = $this->postBy($author);

        Sanctum::actingAs($stranger);

        $this->deleteJson("/api/v1/posts/{$post->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_you_cannot_delete_a_post_that_does_not_exist(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/posts/999999')->assertStatus(404);
    }

    public function test_deleting_twice_is_not_found_the_second_time(): void
    {
        $author = User::factory()->create();
        $post = $this->postBy($author);

        Sanctum::actingAs($author);

        $this->deleteJson("/api/v1/posts/{$post->id}")->assertOk();
        // Route model binding does not resolve a soft-deleted row, so the
        // second attempt 404s rather than reporting a second successful
        // deletion.
        $this->deleteJson("/api/v1/posts/{$post->id}")->assertStatus(404);
    }

    public function test_a_soft_deleted_post_keeps_its_comments_and_mentions(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create();
        $post = $this->postBy($author);
        $post->comments()->create(['user_id' => $tagged->id, 'body' => 'still here']);
        $post->mentions()->attach($tagged->id);

        Sanctum::actingAs($author);
        $this->deleteJson("/api/v1/posts/{$post->id}")->assertOk();

        // Hard-deleting would cascade the replies away, and the report that
        // led to the removal would point at an id that no longer resolves.
        $this->assertDatabaseHas('comments', ['post_id' => $post->id]);
        $this->assertDatabaseHas('post_mentions', ['post_id' => $post->id, 'user_id' => $tagged->id]);
    }

    private function postBy(User $author): Post
    {
        return Post::query()->create([
            'user_id' => $author->id,
            'body' => 'a post',
        ]);
    }
}
