<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SubscribesUsers;
use Tests\TestCase;

final class ChatEntitlementTest extends TestCase
{
    use RefreshDatabase;
    use SubscribesUsers;

    public function test_a_free_account_sees_every_chat_feature_locked(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/chat/entitlements')
            ->assertOk()
            ->assertJsonPath('data.unlocked', [])
            ->assertJsonStructure([
                'data' => [
                    'features' => [
                        ['key', 'label', 'blurb', 'unlocked'],
                    ],
                    'wallpapers',
                ],
            ]);
    }

    public function test_the_entitlement_catalogue_lists_every_known_feature(): void
    {
        $features = $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/chat/entitlements')
            ->json('data.features');

        $keys = array_column($features, 'key');
        sort($keys);

        $this->assertSame([
            'chat_drawing',
            'chat_gif',
            'chat_nickname',
            'chat_wallpaper',
            'message_requests',
        ], $keys);
    }

    public function test_an_active_subscription_unlocks_the_whole_tier(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)
            ->getJson('/api/v1/chat/entitlements')
            ->assertOk()
            ->assertJsonCount(5, 'data.unlocked');
    }

    public function test_personalising_a_chat_is_locked_without_a_subscription(): void
    {
        $conversation = $this->dm();

        $this->actingAs($this->free)
            ->patchJson("/api/v1/chat/conversations/{$conversation->id}/personalize", [
                'nickname' => 'Boss',
                'wallpaper_key' => 'aurora',
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_LOCKED')
            ->assertJsonPath('errors.0.details.feature', 'chat_nickname');
    }

    public function test_a_subscriber_can_set_and_clear_a_nickname_and_wallpaper(): void
    {
        $subscriber = $this->subscriber();
        $conversation = $this->dm($subscriber);

        $this->actingAs($subscriber)
            ->patchJson("/api/v1/chat/conversations/{$conversation->id}/personalize", [
                'nickname' => 'Weekend plans',
                'wallpaper_key' => 'ocean',
            ])
            ->assertOk()
            ->assertJsonPath('data.conversation.my_nickname', 'Weekend plans')
            ->assertJsonPath('data.conversation.my_wallpaper_key', 'ocean');

        $this->actingAs($subscriber)
            ->patchJson("/api/v1/chat/conversations/{$conversation->id}/personalize", [
                'nickname' => null,
                'wallpaper_key' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.conversation.my_nickname', null)
            ->assertJsonPath('data.conversation.my_wallpaper_key', null);
    }

    public function test_an_unknown_wallpaper_is_rejected(): void
    {
        $subscriber = $this->subscriber();
        $conversation = $this->dm($subscriber);

        $this->actingAs($subscriber)
            ->patchJson("/api/v1/chat/conversations/{$conversation->id}/personalize", [
                'nickname' => null,
                'wallpaper_key' => '../../etc/passwd',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'wallpaper_key');
    }

    public function test_a_nickname_is_private_to_the_person_who_set_it(): void
    {
        $subscriber = $this->subscriber();
        $other = $this->subscriber();
        $conversation = $this->dm($subscriber, $other);

        $this->actingAs($subscriber)->patchJson("/api/v1/chat/conversations/{$conversation->id}/personalize", [
            'nickname' => 'Private name',
            'wallpaper_key' => null,
        ])->assertOk();

        $this->actingAs($other)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.my_nickname', null);
    }

    public function test_a_non_member_cannot_personalise_someone_elses_chat(): void
    {
        $conversation = $this->dm();
        $stranger = $this->subscriber();

        // 404, not 403: a stranger must not be able to probe which conversation
        // ids exist by comparing "no such chat" with "not allowed".
        $this->actingAs($stranger)
            ->patchJson("/api/v1/chat/conversations/{$conversation->id}/personalize", [
                'nickname' => 'Not mine',
                'wallpaper_key' => null,
            ])
            ->assertNotFound();
    }

    private ?User $free = null;

    private function dm(?User $first = null, ?User $second = null): Conversation
    {
        $first ??= User::factory()->create();
        $second ??= User::factory()->create();
        $this->free = $first;

        $conversation = Conversation::query()->create([
            'type' => 'dm',
            'created_by' => $first->id,
        ]);
        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $first->id,
        ]);
        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $second->id,
        ]);

        return $conversation;
    }
}
