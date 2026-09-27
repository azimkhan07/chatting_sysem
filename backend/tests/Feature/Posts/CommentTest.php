<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function commentOn(Post $post, User $author, string $body, ?int $parentId = null): Comment
    {
        // Each call authenticates as a different user; the cached guard would
        // otherwise keep the previously resolved user.
        Auth::forgetGuards();

        $this->withToken($this->tokenFor($author))
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'body' => $body,
                'parent_id' => $parentId,
            ])
            ->assertStatus(201);

        return Comment::query()
            ->where('body', $body)
            ->latest('id')
            ->firstOrFail();
    }

    public function test_replies_are_returned_nested_under_their_root_comment(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $root = $this->commentOn($post, $fan, 'Top level');
        $this->commentOn($post, $other, 'A reply', $root->id);
        $this->commentOn($post, $fan, 'Another reply', $root->id);

        $this->withToken($this->tokenFor($author))
            ->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.body', 'Top level')
            ->assertJsonPath('data.comments.0.reply_count', 2)
            ->assertJsonCount(2, 'data.comments.0.replies')
            ->assertJsonPath('data.comments.0.replies.0.body', 'A reply')
            ->assertJsonPath('data.comments.0.replies.0.author.username', $other->username);
    }

    public function test_replies_are_not_listed_as_standalone_comments(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $root = $this->commentOn($post, $fan, 'Top level');
        $this->commentOn($post, $fan, 'A reply', $root->id);

        $this->withToken($this->tokenFor($author))
            ->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data.comments');
    }

    public function test_reply_count_includes_replies_beyond_the_inline_preview(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $root = $this->commentOn($post, $fan, 'Top level');
        foreach (range(1, 5) as $index) {
            $this->commentOn($post, $fan, "Reply {$index}", $root->id);
        }

        $this->withToken($this->tokenFor($author))
            ->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.comments.0.reply_count', 5)
            ->assertJsonCount(3, 'data.comments.0.replies');
    }

    public function test_replying_to_a_reply_is_rejected(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $root = $this->commentOn($post, $fan, 'Top level');
        $reply = $this->commentOn($post, $fan, 'A reply', $root->id);

        $this->withToken($this->tokenFor($fan))
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'body' => 'Too deep',
                'parent_id' => $reply->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_COMMENT')
            ->assertJsonPath('errors.0.field', 'parent_id');
    }

    public function test_replying_to_a_comment_on_another_post_is_rejected(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author)->create();
        $otherPost = Post::factory()->for($author)->create();

        $elsewhere = $this->commentOn($otherPost, $fan, 'Belongs elsewhere');

        $this->withToken($this->tokenFor($fan))
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'body' => 'Wrong thread',
                'parent_id' => $elsewhere->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_COMMENT');
    }

    public function test_reply_notifies_the_comment_author(): void
    {
        $author = User::factory()->create();
        $firstCommenter = User::factory()->create();
        $replier = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $root = $this->commentOn($post, $firstCommenter, 'Nice one');
        $this->commentOn($post, $replier, 'Agreed', $root->id);

        // The reply reaches both the person being replied to and the post author.
        $this->assertSame(1, UserNotification::query()
            ->where('user_id', $firstCommenter->id)
            ->where('type', 'comment')
            ->count());

        $this->assertSame(2, UserNotification::query()
            ->where('user_id', $author->id)
            ->where('type', 'comment')
            ->count());
    }

    public function test_post_author_is_not_notified_when_replying_to_their_own_comment(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $root = $this->commentOn($post, $author, 'My own post');
        $this->commentOn($post, $fan, 'Reply', $root->id);

        $this->assertSame(1, UserNotification::query()
            ->where('user_id', $author->id)
            ->where('type', 'comment')
            ->count());
    }
}
