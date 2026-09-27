<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Data\SendMessageData;
use App\Domain\Chat\Enums\ChatFeature;
use App\Domain\Chat\Enums\MessageType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Social\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SubscribesUsers;
use Tests\TestCase;

final class ChatPinTest extends TestCase
{
    use RefreshDatabase;
    use SubscribesUsers;

    public function test_subscriber_can_pin_and_unpin_a_message(): void
    {
        $me = $this->subscriber();
        $them = $this->subscriber();
        $conversation = $this->dm($me, $them);

        $message = $this->message($conversation->id, $me, 'Book the venue by Friday');

        Sanctum::actingAs($me);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}/pin")
            ->assertOk();

        $this->assertNotNull($message->refresh()->pinned_at);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/pins")
            ->assertOk()
            ->assertJsonPath('data.pinned.0.id', $message->id)
            ->assertJsonPath('data.pinned.0.body', 'Book the venue by Friday');

        $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.message.pinned_at', null);

        $this->assertNull($message->refresh()->pinned_at);
    }

    public function test_pinned_messages_are_listed_newest_pin_first(): void
    {
        $me = $this->subscriber();
        $conversation = $this->dm($me, $this->subscriber());

        $first = $this->message($conversation->id, $me, 'first');
        $second = $this->message($conversation->id, $me, 'second');

        Sanctum::actingAs($me);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$first->id}/pin")->assertOk();
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$second->id}/pin")->assertOk();

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/pins")
            ->assertOk()
            ->assertJsonPath('data.pinned.0.id', $second->id)
            ->assertJsonPath('data.pinned.1.id', $first->id);
    }

    public function test_free_account_cannot_pin(): void
    {
        $free = User::factory()->create();
        $friend = $this->subscriber();
        // A follow edge, so the DM itself is allowed and the *pin* is the only
        // locked thing under test.
        Follow::query()->create([
            'follower_id' => $free->id,
            'following_id' => $friend->id,
            'created_at' => now(),
        ]);

        $conversation = $this->dm($free, $friend);
        $message = $this->message($conversation->id, $free, 'hi');

        Sanctum::actingAs($free);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}/pin")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_LOCKED')
            ->assertJsonPath('errors.0.details.feature', ChatFeature::PinnedMessages->value);

        $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}/pin")
            ->assertForbidden();

        $this->assertNull($message->refresh()->pinned_at);
    }

    public function test_entitlement_catalogue_lists_pinned_messages(): void
    {
        $free = User::factory()->create();
        Sanctum::actingAs($free);

        $response = $this->getJson('/api/v1/chat/entitlements')->assertOk();

        $features = collect($response->json('data.features'));
        $pinned = $features->firstWhere('key', ChatFeature::PinnedMessages->value);

        $this->assertNotNull($pinned, 'the catalogue advertises pinned messages');
        $this->assertFalse($pinned['unlocked'], 'a free account does not get it');
        $this->assertContains(ChatFeature::PinnedMessages->value, ChatFeature::values());
    }

    public function test_a_member_cannot_pin_someone_elses_message_in_a_dm(): void
    {
        $me = $this->subscriber();
        $them = $this->subscriber();
        $conversation = $this->dm($me, $them);
        $theirs = $this->message($conversation->id, $them, 'not yours');

        Sanctum::actingAs($me);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$theirs->id}/pin")
            ->assertForbidden();

        $this->assertNull($theirs->refresh()->pinned_at);
    }

    public function test_a_group_moderator_can_pin_any_message(): void
    {
        $owner = $this->subscriber();
        $member = $this->subscriber();
        $conversation = app(ChatService::class)->createGroup($owner, 'Team', [$member->id]);
        $theirs = $this->message($conversation->id, $member, 'deploy notes');

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$theirs->id}/pin")
            ->assertOk();

        $this->assertNotNull($theirs->refresh()->pinned_at);
    }

    public function test_pinning_is_scoped_to_the_conversation(): void
    {
        $me = $this->subscriber();
        $first = $this->dm($me, $this->subscriber());
        $second = $this->dm($me, $this->subscriber());
        $message = $this->message($first->id, $me, 'only here');

        Sanctum::actingAs($me);
        $this->postJson("/api/v1/chat/conversations/{$first->id}/messages/{$message->id}/pin")->assertOk();

        $this->getJson("/api/v1/chat/conversations/{$second->id}/pins")
            ->assertOk()
            ->assertJsonPath('data.pinned', []);
    }

    public function test_a_non_member_gets_a_404_not_a_pin(): void
    {
        $owner = $this->subscriber();
        $stranger = $this->subscriber();
        $conversation = $this->dm($owner, $this->subscriber());
        $message = $this->message($conversation->id, $owner, 'private');

        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}/pin")
            ->assertNotFound();

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/pins")
            ->assertNotFound();
    }

    public function test_a_deleted_message_cannot_be_pinned(): void
    {
        $me = $this->subscriber();
        $conversation = $this->dm($me, $this->subscriber());
        $message = $this->message($conversation->id, $me, 'oops');

        Sanctum::actingAs($me);
        $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}")->assertOk();

        // Deleting a message removes the row, so pinning it is a 404 rather
        // than a permission error — and the pin bar cannot resurrect it.
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$message->id}/pin")
            ->assertNotFound();

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/pins")
            ->assertOk()
            ->assertJsonPath('data.pinned', []);
    }

    public function test_a_pinned_message_leaves_the_bar_when_it_is_deleted(): void
    {
        $me = $this->subscriber();
        $conversation = $this->dm($me, $this->subscriber());
        $keep = $this->message($conversation->id, $me, 'keep me');
        $drop = $this->message($conversation->id, $me, 'drop me');

        Sanctum::actingAs($me);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$keep->id}/pin")->assertOk();
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$drop->id}/pin")->assertOk();

        $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$drop->id}")->assertOk();

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/pins")
            ->assertOk()
            ->assertJsonCount(1, 'data.pinned')
            ->assertJsonPath('data.pinned.0.id', $keep->id);
    }

    public function test_message_payload_reports_pin_state(): void
    {
        $me = $this->subscriber();
        $them = $this->subscriber();
        $conversation = $this->dm($me, $them);
        $mine = $this->message($conversation->id, $me, 'mine');
        $theirs = $this->message($conversation->id, $them, 'theirs');

        Sanctum::actingAs($me);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")->assertOk();
        $messages = collect($response->json('data.messages'))->keyBy('id');

        $this->assertTrue($messages[$mine->id]['can_pin'], 'own message is pinnable');
        $this->assertFalse($messages[$theirs->id]['can_pin'], 'a peer message is not pinnable in a DM');
        $this->assertNull($messages[$mine->id]['pinned_at']);
    }

    private function dm(User $me, User $them): Conversation
    {
        return app(ChatService::class)->startDm($me, (int) $them->id);
    }

    private function message(int $conversationId, User $user, string $body): ConversationMessage
    {
        return app(ChatService::class)->sendMessage(
            $user,
            $conversationId,
            new SendMessageData(
                type: MessageType::Text,
                body: $body,
                mediaUrl: null,
                clientId: null,
            ),
        );
    }
}
