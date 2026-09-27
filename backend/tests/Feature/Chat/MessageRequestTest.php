<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Services\SubscriptionService;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Social\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class MessageRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_from_a_stranger_opens_as_a_request(): void
    {
        $sender = $this->subscriber();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'dm',
            'user_id' => $recipient->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.conversation.state', 'requested');

        $this->assertDatabaseHas('conversations', [
            'type' => 'dm',
            'state' => 'requested',
            'requested_by' => $sender->id,
        ]);
    }

    public function test_a_free_account_cannot_open_a_request_with_a_stranger(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'dm',
            'user_id' => $recipient->id,
        ])->assertForbidden()->assertJsonPath('errors.0.code', 'FEATURE_LOCKED');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_a_free_account_can_still_dm_someone_it_follows_and_create_groups(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        Follow::query()->create([
            'follower_id' => $sender->id,
            'following_id' => $recipient->id,
        ]);

        $this->actingAs($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'dm',
            'user_id' => $recipient->id,
        ])->assertCreated()->assertJsonPath('data.conversation.state', 'active');

        $this->actingAs($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'name' => 'Free group',
        ])->assertCreated();
    }

    public function test_dm_between_people_who_follow_each_other_opens_as_a_chat(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        Follow::query()->create([
            'follower_id' => $sender->id,
            'following_id' => $recipient->id,
        ]);

        $this->actingAs($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'dm',
            'user_id' => $recipient->id,
        ])->assertCreated()->assertJsonPath('data.conversation.state', 'active');
    }

    public function test_a_reverse_follow_edge_also_opens_a_chat(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        Follow::query()->create([
            'follower_id' => $recipient->id,
            'following_id' => $sender->id,
        ]);

        $this->actingAs($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'dm',
            'user_id' => $recipient->id,
        ])->assertCreated()->assertJsonPath('data.conversation.state', 'active');
    }

    public function test_recipient_can_accept_a_request_and_it_becomes_a_chat(): void
    {
        [$conversation, $sender, $recipient] = $this->pendingRequest();

        $this->asUser($recipient)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/request/accept")
            ->assertOk()
            ->assertJsonPath('data.conversation.state', 'active');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'state' => 'active',
            'requested_by' => null,
        ]);

        $this->asUser($recipient)
            ->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.id', $conversation->id);
    }

    public function test_recipient_can_delete_a_request_and_the_thread_is_gone(): void
    {
        [$conversation, $sender, $recipient] = $this->pendingRequest();

        $this->asUser($recipient)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/request")
            ->assertOk();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('conversation_messages', ['conversation_id' => $conversation->id]);
    }

    public function test_sender_cannot_accept_or_delete_their_own_request(): void
    {
        [$conversation, $sender] = $this->pendingRequest();

        $this->asUser($sender)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/request/accept")
            ->assertForbidden();

        $this->asUser($sender)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/request")
            ->assertForbidden();
    }

    public function test_an_accepted_conversation_can_no_longer_be_rejected(): void
    {
        [$conversation, $sender, $recipient] = $this->pendingRequest();

        $this->asUser($recipient)->postJson("/api/v1/chat/conversations/{$conversation->id}/request/accept");

        $this->asUser($recipient)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/request")
            ->assertForbidden();
    }

    public function test_request_actions_require_authentication(): void
    {
        $this->postJson('/api/v1/chat/conversations/1/request/accept')->assertUnauthorized();
        $this->deleteJson('/api/v1/chat/conversations/1/request')->assertUnauthorized();
    }

    public function test_request_action_buttons_are_only_exposed_to_the_recipient(): void
    {
        [$conversation, $sender, $recipient] = $this->pendingRequest();

        $this->asUser($recipient)
            ->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.is_request_actionable', true);

        $this->asUser($sender)
            ->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.is_request_actionable', false);
    }

    /**
     * @return array{Conversation, User, User}
     */
    private function pendingRequest(): array
    {
        $sender = $this->subscriber();
        $recipient = User::factory()->create();

        $response = $this->asUser($sender)->postJson('/api/v1/chat/conversations', [
            'type' => 'dm',
            'user_id' => $recipient->id,
        ]);

        $id = (int) $response->json('data.conversation.id');

        /** @var Conversation $conversation */
        $conversation = Conversation::query()->findOrFail($id);

        return [$conversation, $sender, $recipient];
    }

    private function subscriber(): User
    {
        $user = User::factory()->create();

        // Go through the real purchase + admin approval so the test proves the
        // entitlement follows the product flow, not a hand-set flag.
        $subscription = $this->service()->verify((int) $user->id, Plan::Basic);
        $this->repository()->approve($subscription, (int) User::factory()->create()->id);

        return $user->refresh();
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function repository(): SubscriptionRepository
    {
        return app(SubscriptionRepository::class);
    }

    private function asUser(User $user): self
    {
        // One test switches users; without dropping the resolved guard the
        // second token would keep the first user's identity.
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
