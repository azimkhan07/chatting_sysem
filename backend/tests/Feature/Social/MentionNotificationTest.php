<?php

declare(strict_types=1);

namespace Tests\Feature\Social;

use App\Domain\Auth\Models\User;
use App\Domain\Social\Enums\NotificationType;
use App\Domain\Social\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeMedia;
use Tests\TestCase;

/**
 * Tagging someone is a claim that you told them, so it has to actually tell
 * them. The tag is visible on the post; the notification is what makes the
 * person find it, and it is the only signal they get.
 */
final class MentionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tagging_someone_notifies_them(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/posts', ['body' => 'shot with @tanya today'])
            ->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $tagged->id,
            'actor_id' => $author->id,
            'type' => NotificationType::Mention->value,
        ]);
    }

    public function test_each_tagged_person_is_notified_once(): void
    {
        $author = User::factory()->create();
        $first = User::factory()->create(['username' => 'tanya']);
        $second = User::factory()->create(['username' => 'imran']);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/posts', ['body' => '@tanya and @imran came along'])
            ->assertStatus(201);

        // The caption shows one tag line, so a duplicate notification would
        // read as two people being named twice.
        $this->assertSame(1, $this->mentionCountFor($first));
        $this->assertSame(1, $this->mentionCountFor($second));
    }

    public function test_tagging_the_same_person_twice_is_one_notification(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/posts', ['body' => '@tanya @tanya @tanya'])
            ->assertStatus(201);

        $this->assertSame(1, $this->mentionCountFor($tagged));
    }

    public function test_tagging_yourself_does_not_notify_you(): void
    {
        $author = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/posts', ['body' => 'note to @tanya'])
            ->assertStatus(201);

        // "You tagged yourself" on your own post is noise, not news.
        $this->assertSame(0, $this->mentionCountFor($author));
    }

    public function test_a_name_matching_no_account_notifies_nobody(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        // Skipped rather than fatal: someone typing "@to @do" mid-word should
        // not lose the post over it.
        $this->postJson('/api/v1/posts', ['body' => 'ask @nosuchperson about it'])
            ->assertStatus(201);

        $this->assertSame(0, $this->mentionCountFor($tagged));
        $this->assertDatabaseCount('post_mentions', 0);
    }

    public function test_a_suspended_account_is_not_tagged_or_notified(): void
    {
        $author = User::factory()->create();
        $suspended = User::factory()->create(['username' => 'tanya']);
        $suspended->forceFill(['status' => 'suspended'])->save();

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/posts', ['body' => 'with @tanya'])
            ->assertStatus(201);

        $this->assertSame(0, $this->mentionCountFor($suspended));
    }

    public function test_a_blocked_account_is_not_tagged_or_notified(): void
    {
        $author = User::factory()->create();
        $blocked = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}")->assertStatus(201);

        // Tagging is a way of demanding attention, exactly what the block
        // refused. The tag would also leak: the mention row survives to be
        // listed even though the feed hides the author.
        $this->postJson('/api/v1/posts', ['body' => 'with @tanya'])
            ->assertStatus(201);

        $this->assertSame(0, $this->mentionCountFor($blocked));
        $this->assertDatabaseCount('post_mentions', 0);
    }

    public function test_a_story_tag_notifies_the_tagged_person(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/stories', [
            'media' => FakeMedia::png(600, 800),
            'caption' => 'late night with @tanya',
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $tagged->id,
            'actor_id' => $author->id,
            'type' => NotificationType::Mention->value,
        ]);
    }

    public function test_a_story_mention_carries_the_story_id_not_a_post_id(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        $storyId = $this->postJson('/api/v1/stories', [
            'media' => FakeMedia::png(600, 800),
            'caption' => 'with @tanya',
        ])->assertStatus(201)->json('data.story.id');

        // The activity page links a mention to what it tagged. A story that
        // reported a post_id would send the reader to an unrelated post.
        $notification = UserNotification::query()
            ->where('user_id', $tagged->id)
            ->where('type', NotificationType::Mention)
            ->firstOrFail();

        $this->assertSame(['story_id' => $storyId], $notification->data);
    }

    public function test_a_new_story_returns_its_tagged_people(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'tanya']);

        Sanctum::actingAs($author);

        // The create response is what refills the tray, so if it came back
        // without the tagged list the author would see their own story with no
        // tagged line and no way to tell that the tag had been dropped.
        $this->postJson('/api/v1/stories', [
            'media' => FakeMedia::png(600, 800),
            'caption' => 'with @tanya',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.story.tagged_users.0.id', $tagged->id);
    }

    private function mentionCountFor(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', NotificationType::Mention)
            ->count();
    }
}
