<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PostInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_user_can_like_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.likes_count', 1);

        $this->assertDatabaseHas('likes', ['user_id' => $user->id, 'post_id' => $post->id]);
    }

    public function test_liking_is_idempotent(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->withToken($this->tokenFor($user))->postJson("/api/v1/posts/{$post->id}/like");
        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/posts/{$post->id}/like")
            ->assertJsonPath('data.likes_count', 1);

        $this->assertDatabaseCount('likes', 1);
    }

    public function test_user_can_unlike_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $post->likes()->create(['user_id' => $user->id]);

        $this->withToken($this->tokenFor($user))
            ->deleteJson("/api/v1/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.liked', false)
            ->assertJsonPath('data.likes_count', 0);

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_feed_reports_like_and_comment_counts(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $post->likes()->create(['user_id' => $viewer->id]);
        $post->likes()->create(['user_id' => $owner->id]);
        $post->comments()->create(['user_id' => $owner->id, 'body' => 'nice post']);

        $this->withToken($this->tokenFor($viewer))
            ->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.posts.0.likes_count', 2)
            ->assertJsonPath('data.posts.0.comments_count', 1)
            ->assertJsonPath('data.posts.0.liked_by_me', true);
    }

    public function test_like_requires_authentication(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/v1/posts/{$post->id}/like")->assertStatus(401);
    }

    public function test_user_can_comment_on_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Loving this!'])
            ->assertStatus(201)
            ->assertJsonPath('data.comment.body', 'Loving this!')
            ->assertJsonPath('data.comment.author.username', $user->username);

        $this->assertDatabaseHas('comments', ['post_id' => $post->id, 'user_id' => $user->id]);
    }

    public function test_comments_list_is_newest_first(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        /** @var Comment $first */
        $first = $post->comments()->create(['user_id' => $user->id, 'body' => 'first']);
        /** @var Comment $second */
        $second = $post->comments()->create(['user_id' => $user->id, 'body' => 'second']);

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $second->id)
            ->assertJsonPath('data.comments.1.id', $first->id);
    }

    public function test_blank_comment_is_rejected(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/posts/{$post->id}/comments", ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'body');
    }

    public function test_unknown_post_returns_envelope_404(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts/99999/like')
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'NOT_FOUND');
    }

    public function test_profile_feed_returns_only_own_posts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Post::factory()->count(2)->for($owner)->create();
        Post::factory()->for($other)->create();

        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/v1/posts/me')
            ->assertOk()
            ->assertJsonCount(2, 'data.posts')
            ->assertJsonPath('meta.has_more', false);
    }
}
