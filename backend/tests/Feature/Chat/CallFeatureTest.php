<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Call;
use App\Domain\Chat\Models\Conversation;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallOffered;
use App\Events\CallRejected;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SubscribesUsers;
use Tests\TestCase;

final class CallFeatureTest extends TestCase
{
    use RefreshDatabase;
    use SubscribesUsers;

    private function asUser(User $user): static
    {
        Sanctum::actingAs($this->subscribe($user));

        return $this;
    }

    private function startDm(User $me, User $other): Conversation
    {
        $response = $this->asUser($me)
            ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $other->id])
            ->assertStatus(201);

        return Conversation::query()->findOrFail($response->json('data.conversation.id'));
    }

    public function test_a_call_is_started_and_returns_join_details(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/calls", ['kind' => 'video'])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['call', 'token', 'room', 'server_url'],
            ])
            ->assertJsonPath('data.call.kind', 'video')
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('calls', [
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'video',
            'status' => 'ringing',
        ]);

        $this->assertDatabaseHas('call_participants', [
            'call_id' => Call::firstOrFail()->id,
            'user_id' => $me->id,
            'status' => 'joined',
        ]);

        $this->assertDatabaseHas('call_participants', [
            'call_id' => Call::firstOrFail()->id,
            'user_id' => $them->id,
            'status' => 'ringing',
        ]);
    }

    public function test_start_requires_a_valid_kind(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/calls", ['kind' => 'holo'])
            ->assertStatus(422);
    }

    public function test_a_member_can_answer_a_ringing_call(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'audio',
            'status' => 'ringing',
        ]);

        $this->asUser($them)
            ->postJson("/api/v1/chat/calls/{$call->id}/answer", ['conversation_id' => $conversation->id])
            ->assertOk()
            ->assertJsonStructure(['data' => ['call', 'token', 'room', 'server_url']])
            ->assertJsonPath('data.call.status', 'active');

        $this->assertDatabaseHas('call_participants', [
            'call_id' => $call->id,
            'user_id' => $them->id,
            'status' => 'joined',
        ]);
    }

    public function test_on_answer_the_call_is_marked_active(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'video',
            'status' => 'ringing',
        ]);

        $this->asUser($them)
            ->postJson("/api/v1/chat/calls/{$call->id}/answer", ['conversation_id' => $conversation->id])
            ->assertOk();

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => 'active',
        ]);
        $this->assertNotNull(Call::findOrFail($call->id)->answered_at);
    }

    public function test_a_non_member_cannot_start_or_answer_a_call(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $stranger = $this->subscriber();

        $this->asUser($stranger)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/calls", ['kind' => 'audio'])
            ->assertNotFound();

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'audio',
            'status' => 'ringing',
        ]);

        $this->asUser($stranger)
            ->postJson("/api/v1/chat/calls/{$call->id}/answer", ['conversation_id' => $conversation->id])
            ->assertNotFound();
    }

    public function test_an_answered_or_ended_call_cannot_be_answered_again(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'audio',
            'status' => 'active',
        ]);

        $this->asUser($them)
            ->postJson("/api/v1/chat/calls/{$call->id}/answer", ['conversation_id' => $conversation->id])
            ->assertStatus(403);
    }

    public function test_reject_marks_the_responder_declined(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'video',
            'status' => 'ringing',
        ]);

        $this->asUser($them)
            ->postJson("/api/v1/chat/calls/{$call->id}/reject", ['conversation_id' => $conversation->id])
            ->assertOk();

        $this->assertDatabaseHas('call_participants', [
            'call_id' => $call->id,
            'user_id' => $them->id,
            'status' => 'declined',
        ]);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => 'ringing',
        ]);
    }

    public function test_end_closes_the_call_when_no_one_else_is_connected(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'initiator_id' => $me->id,
            'kind' => 'audio',
            'status' => 'active',
            'answered_at' => now(),
        ]);

        $this->asUser($me)
            ->postJson("/api/v1/chat/calls/{$call->id}/end", ['conversation_id' => $conversation->id])
            ->assertOk();

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => 'ended',
        ]);
    }

    public function test_offer_accept_reject_and_end_are_broadcast(): void
    {
        /** @var list<CallOffered> $offered */
        $offered = [];
        /** @var list<CallAccepted> $accepted */
        $accepted = [];
        /** @var list<CallRejected> $rejected */
        $rejected = [];
        /** @var list<CallEnded> $ended */
        $ended = [];

        Event::listen(CallOffered::class, static function (CallOffered $event) use (&$offered): void {
            $offered[] = $event;
        });
        Event::listen(CallAccepted::class, static function (CallAccepted $event) use (&$accepted): void {
            $accepted[] = $event;
        });
        Event::listen(CallRejected::class, static function (CallRejected $event) use (&$rejected): void {
            $rejected[] = $event;
        });
        Event::listen(CallEnded::class, static function (CallEnded $event) use (&$ended): void {
            $ended[] = $event;
        });

        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/calls", ['kind' => 'video'])
            ->json('data.call');

        $this->asUser($them)
            ->postJson("/api/v1/chat/calls/{$call['id']}/answer", ['conversation_id' => $conversation->id])
            ->assertOk();

        $this->assertCount(1, $offered);
        $this->assertSame($offered[0]->broadcastAs(), 'call.offered');
        $this->assertSame($offered[0]->conversationId, (int) $conversation->id);
        $this->assertSame($offered[0]->kind, 'video');
        // The offer reaches the thread and every recipient's user channel.
        $this->assertContains('dm.'.$conversation->id, $this->channelNames($offered[0]->broadcastOn()));
        $this->assertContains('user.'.$them->id, $this->channelNames($offered[0]->broadcastOn()));
        $this->assertNotContains('user.'.$me->id, $this->channelNames($offered[0]->broadcastOn()));

        $this->assertCount(1, $accepted);
        $this->assertSame($accepted[0]->broadcastAs(), 'call.accepted');
        $this->assertSame($accepted[0]->participant['id'], (int) $them->id);
        $this->assertSame($accepted[0]->initiatorId, (int) $me->id);
        $this->assertContains('dm.'.$conversation->id, $this->channelNames($accepted[0]->broadcastOn()));
        $this->assertContains('user.'.$me->id, $this->channelNames($accepted[0]->broadcastOn()));
        $this->assertCount(0, $rejected);

        $this->asUser($me)
            ->postJson("/api/v1/chat/calls/{$call['id']}/end", ['conversation_id' => $conversation->id])
            ->assertOk();

        $this->assertCount(1, $ended);
        $this->assertSame($ended[0]->broadcastAs(), 'call.ended');
        $this->assertSame($ended[0]->endedBy, (int) $me->id);
    }

    public function test_reject_is_broadcast(): void
    {
        /** @var list<CallRejected> $rejected */
        $rejected = [];

        Event::listen(CallRejected::class, static function (CallRejected $event) use (&$rejected): void {
            $rejected[] = $event;
        });

        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $call = $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/calls", ['kind' => 'audio'])
            ->json('data.call');

        $this->asUser($them)
            ->postJson("/api/v1/chat/calls/{$call['id']}/reject", ['conversation_id' => $conversation->id])
            ->assertOk();

        $this->assertCount(1, $rejected);
        $this->assertSame($rejected[0]->broadcastAs(), 'call.rejected');
        $this->assertSame($rejected[0]->rejectedBy, (int) $them->id);
        $this->assertContains('user.'.$me->id, $this->channelNames($rejected[0]->broadcastOn()));
    }

    /**
     * @param  array<int, Channel>  $channels
     * @return list<string>
     */
    private function channelNames(array $channels): array
    {
        return array_map(static function (Channel $channel): string {
            if ($channel instanceof PrivateChannel) {
                return str_replace('private-', '', (string) $channel);
            }

            return (string) $channel;
        }, $channels);
    }
}
