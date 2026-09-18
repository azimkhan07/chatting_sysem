<?php

declare(strict_types=1);

namespace Tests\Feature\Social;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Models\Follow;
use App\Domain\Social\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_creates_relationship_and_notification(): void
    {
        $follower = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($follower);
        $this->postJson("/api/v1/users/{$target->id}/follow")
            ->assertOk()
            ->assertJsonPath('data.following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertDatabaseHas('follows', ['follower_id' => $follower->id, 'following_id' => $target->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $target->id,
            'actor_id' => $follower->id,
            'type' => 'follow',
        ]);
    }

    public function test_following_twice_is_idempotent_and_single_notification(): void
    {
        $follower = User::factory()->create();
        $target = User::factory()->create();

        $uri = "/api/v1/users/{$target->id}/follow";
        Sanctum::actingAs($follower);
        $this->postJson($uri)->assertOk();
        $this->postJson($uri)
            ->assertJsonPath('data.following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_user_can_unfollow(): void
    {
        $follower = User::factory()->create();
        $target = User::factory()->create();
        Follow::query()->create(['follower_id' => $follower->id, 'following_id' => $target->id]);

        Sanctum::actingAs($follower);
        $this->deleteJson("/api/v1/users/{$target->id}/follow")
            ->assertOk()
            ->assertJsonPath('data.following', false)
            ->assertJsonPath('data.followers_count', 0);

        $this->assertDatabaseCount('follows', 0);
    }

    public function test_cannot_follow_yourself(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/users/{$user->id}/follow")
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_OPERATION');
    }

    public function test_profile_reports_counts_and_follow_state(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        Post::factory()->count(3)->for($owner)->create();
        Follow::query()->create(['follower_id' => $viewer->id, 'following_id' => $owner->id]);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.user.posts_count', 3)
            ->assertJsonPath('data.user.followers_count', 1)
            ->assertJsonPath('data.user.following_count', 0)
            ->assertJsonPath('data.user.is_followed_by_me', true);
    }

    public function test_followers_and_following_lists(): void
    {
        $owner = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Follow::query()->create(['follower_id' => $alice->id, 'following_id' => $owner->id]);
        Follow::query()->create(['follower_id' => $bob->id, 'following_id' => $owner->id]);
        Follow::query()->create(['follower_id' => $owner->id, 'following_id' => $alice->id]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/users/{$owner->id}/followers")
            ->assertOk()
            ->assertJsonCount(2, 'data.users');

        $this->getJson("/api/v1/users/{$owner->id}/following")
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.username', $alice->username);
    }

    public function test_actor_receives_follow_notification_and_can_read_it(): void
    {
        $follower = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($follower);
        $this->postJson("/api/v1/users/{$target->id}/follow")->assertOk();

        Sanctum::actingAs($target);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.notifications.0.type', 'follow')
            ->assertJsonPath('data.notifications.0.actor.username', $follower->username)
            ->assertJsonPath('meta.unread', 1);
    }

    public function test_marking_notifications_read(): void
    {
        $follower = User::factory()->create();
        $target = User::factory()->create();
        UserNotification::query()->create([
            'user_id' => $target->id,
            'actor_id' => $follower->id,
            'type' => 'follow',
        ]);

        Sanctum::actingAs($target);
        $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread', 1);

        $this->postJson('/api/v1/notifications/read')
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread', 0);
    }

    public function test_like_and_comment_notify_the_post_author(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        Sanctum::actingAs($fan);
        $this->postJson("/api/v1/posts/{$post->id}/like")->assertOk();
        $this->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Great shot!'])
            ->assertStatus(201);

        $notifications = UserNotification::query()->where('user_id', $author->id)->get();

        $this->assertCount(2, $notifications);
        $this->assertTrue($notifications->contains('type', 'like'));
        $this->assertTrue($notifications->contains('type', 'comment'));
    }
}
