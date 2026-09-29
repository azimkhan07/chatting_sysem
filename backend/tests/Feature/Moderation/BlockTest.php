<?php

declare(strict_types=1);

namespace Tests\Feature\Moderation;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use App\Domain\Stories\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What blocking has to actually stop.
 *
 * Each test here corresponds to a way a block gets quietly undone in a real
 * product: a search that still finds them, a profile that still loads, a
 * follow that slips through, a comment that lands anyway. A block is only as
 * good as the least careful of its enforcement points, so these are written
 * as behaviour rather than as unit tests of BlockService.
 */
final class BlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_requires_authentication(): void
    {
        $target = User::factory()->create();

        $this->postJson("/api/v1/moderation/blocks/{$target->id}")
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_a_user_can_block_someone(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/moderation/blocks/{$target->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.blocked', true);

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $user->id,
            'blocked_id' => $target->id,
        ]);
    }

    public function test_blocking_yourself_is_rejected(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/moderation/blocks/{$user->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_OPERATION');
    }

    public function test_blocking_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/moderation/blocks/{$target->id}")->assertStatus(201);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}")->assertStatus(201);

        $this->assertDatabaseCount('user_blocks', 1);
    }

    public function test_unblocking_removes_the_row_so_content_returns(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $post = $this->postBy($target);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        $this->assertSame([], $this->feedIdsFor($user));

        $this->deleteJson("/api/v1/moderation/blocks/{$target->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.blocked', false);

        // The whole reason the row is a hard delete: a soft-deleted block
        // would keep this pair filtered out of each other's feeds forever.
        $this->assertDatabaseMissing('user_blocks', [
            'blocker_id' => $user->id,
            'blocked_id' => $target->id,
        ]);
        $this->assertContains($post->id, $this->feedIdsFor($user));
    }

    public function test_blocking_removes_follows_in_both_directions(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/users/{$target->username}/follow")->assertOk();

        Sanctum::actingAs($target);
        $this->postJson("/api/v1/users/{$user->username}/follow")->assertOk();

        $this->assertDatabaseCount('follows', 2);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        // Leaving either follow behind would let the blocked account keep
        // seeing the blocker in their following list.
        $this->assertDatabaseMissing('follows', ['follower_id' => $user->id, 'following_id' => $target->id]);
        $this->assertDatabaseMissing('follows', ['follower_id' => $target->id, 'following_id' => $user->id]);
    }

    public function test_blocked_posts_leave_the_feed(): void
    {
        $user = User::factory()->create();
        $blocked = User::factory()->create();
        $kept = User::factory()->create();

        $blockedPost = $this->postBy($blocked);
        $keptPost = $this->postBy($kept);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        $ids = $this->feedIdsFor($user);

        $this->assertNotContains($blockedPost->id, $ids);
        $this->assertContains($keptPost->id, $ids);
    }

    public function test_blocked_posts_leave_explore_and_reels(): void
    {
        $user = User::factory()->create();
        $blocked = User::factory()->create();
        $this->postBy($blocked);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        // Feed, explore and reels are three different queries. Checking all
        // three is the point: a filter on the home feed alone still shows the
        // person one tap away on the explore tab.
        $this->assertSame([], $this->idsFrom('/api/v1/posts/explore', $user));
        $this->assertSame([], $this->idsFrom('/api/v1/posts/reels', $user));
    }

    public function test_a_blocked_profile_is_not_found(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        $this->getJson("/api/v1/users/{$target->username}")
            // 404 rather than 403: a 403 confirms the account exists, which is
            // a disclosure the block should not make.
            ->assertStatus(404);
    }

    public function test_a_blocker_is_also_unreachable_to_the_blocked_account(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        Sanctum::actingAs($target);
        $this->getJson("/api/v1/users/{$user->username}")->assertStatus(404);
    }

    public function test_a_blocked_accounts_posts_page_is_not_found_too(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $this->postBy($target);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        // The profile 404 would be theatre if the /posts page under it still
        // answered: direct URL to the username would hand back the whole feed.
        $this->getJson("/api/v1/users/{$target->username}/posts")->assertStatus(404);

        Sanctum::actingAs($target);
        $this->getJson("/api/v1/users/{$user->username}/posts")->assertStatus(404);
    }

    public function test_a_blocked_account_is_not_returned_by_search(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['username' => 'spambot']);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/users/search?query=spambot')
            ->assertJsonPath('data.users.0.id', $target->id);

        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        $this->getJson('/api/v1/users/search?query=spambot')
            ->assertJsonCount(0, 'data.users');
    }

    public function test_you_cannot_follow_someone_who_blocked_you(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        // Target blocks first: the follow attempt must still fail, because the
        // person being protected keeps their block working in both directions.
        Sanctum::actingAs($target);
        $this->postJson("/api/v1/moderation/blocks/{$user->id}");

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/users/{$target->username}/follow")
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'BLOCKED');

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $user->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_you_cannot_follow_someone_you_blocked(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$target->id}");

        $this->postJson("/api/v1/users/{$target->username}/follow")
            ->assertStatus(403);
    }

    public function test_you_cannot_comment_on_a_blocked_authors_post(): void
    {
        $user = User::factory()->create();
        $blocked = User::factory()->create();
        $post = $this->postBy($blocked);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        $this->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'hello'])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'BLOCKED');
    }

    public function test_a_blocked_accounts_comments_are_hidden_from_the_thread(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $blocked = User::factory()->create();
        $kept = User::factory()->create();
        $post = $this->postBy($author);

        $this->commentOn($post, $blocked, 'from the blocked account');
        $this->commentOn($post, $kept, 'from everyone else');

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertJsonCount(2, 'data.comments');

        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        $this->getJson("/api/v1/posts/{$post->id}/comments")
            // Blocked comments are dropped from the thread rather than left
            // under it, and the count follows so the UI cannot show a total
            // that disagrees with the list.
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.body', 'from everyone else');
    }

    public function test_a_blocked_pair_cannot_like_or_share_each_others_posts(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $post = $this->postBy($author);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/moderation/blocks/{$author->id}");

        // Commenting was guarded first; liking and sharing are the same public
        // signal, so a block that stopped one and not the others would still
        // notify the other person.
        $this->postJson("/api/v1/posts/{$post->id}/like")
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'BLOCKED');

        $this->postJson("/api/v1/posts/{$post->id}/share")
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'BLOCKED');
    }

    public function test_someone_who_blocked_you_cannot_like_your_post_either(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->postBy($other);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/moderation/blocks/{$viewer->id}");

        // The write guard has to work in both directions: the person being
        // protected still has to be able to keep their block meaningful.
        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/posts/{$post->id}/like")->assertStatus(403);
    }

    public function test_a_blocked_accounts_stories_leave_the_tray(): void
    {
        $viewer = User::factory()->create();
        $blocked = User::factory()->create();
        $kept = User::factory()->create();

        $this->storyBy($blocked);
        $this->storyBy($kept);

        Sanctum::actingAs($viewer);

        $ids = $this->trayUserIds();
        $this->assertContains($blocked->id, $ids);

        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        // The tray groups by author, so the whole avatar has to go - not just
        // the story rows, which would leave the person one tap away.
        $after = $this->trayUserIds();
        $this->assertNotContains($blocked->id, $after);
        $this->assertContains($kept->id, $after);
    }

    public function test_one_viewers_blocked_accounts_do_not_leak_into_anothers_tray(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $blocked = User::factory()->create();

        $this->storyBy($blocked);

        Sanctum::actingAs($first);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");
        $this->assertNotContains($blocked->id, $this->trayUserIds());

        // The tray is cached, so a shared cache key would have served the first
        // viewer's already-filtered (or unfiltered) tray to the second.
        Sanctum::actingAs($second);
        $this->assertContains($blocked->id, $this->trayUserIds());
    }

    public function test_a_blocked_accounts_notifications_stop_being_delivered(): void
    {
        $viewer = User::factory()->create();
        $noisy = User::factory()->create();
        $other = User::factory()->create();

        $post = $this->postBy($viewer);

        Sanctum::actingAs($noisy);
        $this->postJson("/api/v1/posts/{$post->id}/like")->assertOk();

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/notifications')
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.actor.id', $noisy->id);

        $this->postJson("/api/v1/moderation/blocks/{$noisy->id}");

        $this->getJson('/api/v1/notifications')->assertJsonCount(0, 'data.notifications');

        // The badge is counted through the same filter. A count that still
        // included a blocked account would be a number the list can never reach,
        // and it would keep re-notifying about somebody just cut off.
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('data.unread', 0);
    }

    public function test_the_block_list_shows_only_what_you_blocked(): void
    {
        $user = User::factory()->create();
        $blockedByMe = User::factory()->create();
        $blockedMe = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/moderation/blocks/{$blockedByMe->id}");

        Sanctum::actingAs($blockedMe);
        $this->postJson("/api/v1/moderation/blocks/{$user->id}");

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/moderation/blocks')
            ->assertJsonCount(1, 'data.blocked')
            ->assertJsonPath('data.blocked.0.id', $blockedByMe->id);
    }

    private function postBy(User $author): Post
    {
        return Post::query()->create([
            'user_id' => $author->id,
            'body' => 'a post',
        ]);
    }

    private function commentOn(Post $post, User $author, string $body): void
    {
        $post->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }

    private function storyBy(User $author): Story
    {
        return Story::query()->create([
            'user_id' => $author->id,
            'media_url' => 'https://example.test/story.jpg',
            'type' => 'image',
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * @return list<int>
     */
    private function trayUserIds(): array
    {
        return $this->getJson('/api/v1/stories')
            ->assertOk()
            ->json('data.stories.*.user.id');
    }

    /**
     * @return list<int>
     */
    private function feedIdsFor(User $viewer): array
    {
        return $this->idsFrom('/api/v1/posts', $viewer);
    }

    /**
     * @return list<int>
     */
    private function idsFrom(string $url, User $viewer): array
    {
        Sanctum::actingAs($viewer);

        return $this->getJson($url)
            ->assertOk()
            ->json('data.posts.*.id');
    }
}
