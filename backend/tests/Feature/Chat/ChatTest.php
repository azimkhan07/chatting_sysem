<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Broadcasting\ConversationChannel;
use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Models\Conversation;
use App\Events\MessageSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function startDm(User $me, User $other): Conversation
    {
        $response = $this->asUser($me)
            ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $other->id])
            ->assertStatus(201);

        return Conversation::query()->findOrFail($response->json('data.conversation.id'));
    }

    public function test_user_can_start_a_dm_which_is_reused_on_retry(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['display_name' => 'Riya']);

        $first = $this->asUser($me)
            ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $other->id])
            ->assertStatus(201)
            ->json('data.conversation');

        $this->assertSame('dm', $first['type']);
        $this->assertSame('Riya', $first['display_name']);

        $retry = $this->asUser($me)
            ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $other->id])
            ->assertStatus(201)
            ->json('data.conversation');

        $this->assertSame($first['id'], $retry['id']);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_members', 2);
    }

    public function test_dm_with_self_is_rejected(): void
    {
        $me = User::factory()->create();

        $this->asUser($me)
            ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $me->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'user_id');
    }

    public function test_user_can_create_a_group_with_members(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $response = $this->asUser($owner)
            ->postJson('/api/v1/chat/conversations', [
                'type' => 'group',
                'name' => 'Weekend Plans',
                'member_ids' => [$a->id, $b->id, $a->id],
            ])
            ->assertStatus(201)
            ->json('data.conversation');

        $this->assertSame('group', $response['type']);
        $this->assertSame('Weekend Plans', $response['display_name']);
        $this->assertSame(3, $response['members_count']);
        $this->assertSame('owner', collect($response['members'])->firstWhere('user.id', $owner->id)['role']);
        $this->assertSame('member', collect($response['members'])->firstWhere('user.id', $a->id)['role']);
        $this->assertDatabaseCount('conversation_members', 3);
    }

    public function test_sending_a_message_shows_in_inbox_with_unread_and_read_receipts(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $sent = $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'type' => 'text',
                'body' => 'Hey! Planning for tonight?',
                'client_id' => 'm-'.Str::uuid(),
            ])
            ->assertStatus(201)
            ->json('data.message');

        $this->assertSame('Hey! Planning for tonight?', $sent['body']);
        $this->assertFalse($sent['read']);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'body' => 'Hey! Planning for tonight?',
        ]);

        $this->assertSame(0, $this->asUser($me)
            ->getJson('/api/v1/chat/unread-total')
            ->json('data.unread'));

        $inbox = $this->asUser($them)
            ->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->json('data.conversations');

        $this->assertCount(1, $inbox);
        $this->assertSame(1, $inbox[0]['unread_count']);
        $this->assertSame('Hey! Planning for tonight?', $inbox[0]['last_message']['body']);

        $read = $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/read", [
                'up_to_message_id' => $sent['id'],
            ])
            ->assertOk()
            ->json();

        $this->assertSame($sent['id'], $read['data']['read_up_to']);
        $this->assertSame(0, $read['data']['unread']);

        $this->assertSame(0, $this->asUser($them)
            ->getJson('/api/v1/chat/unread-total')
            ->json('data.unread'));

        $messages = $this->asUser($me)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->json('data.messages');

        $this->assertTrue($messages[0]['read']);
        $this->assertSame([$them->id], array_column($messages[0]['read_by'], 'id'));
    }

    public function test_message_send_is_idempotent_by_client_id(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $clientId = 'm-'.Str::uuid();

        $first = $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Hello world',
                'client_id' => $clientId,
            ])
            ->assertStatus(201)
            ->json('data.message');

        $retry = $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Hello world',
                'client_id' => $clientId,
            ])
            ->assertStatus(200)
            ->json('data.message');

        $this->assertSame($first['id'], $retry['id']);
        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_non_members_are_shut_out_of_conversations(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $this->asUser($outsider)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'NOT_FOUND');

        $this->asUser($outsider)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['body' => 'let me in'])
            ->assertStatus(404);

        $this->asUser($outsider)
            ->getJson('/api/v1/chat/unread-total')
            ->assertOk()
            ->assertJsonPath('data.unread', 0);
    }

    public function test_group_member_management_requires_moderator_privileges(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $plain = User::factory()->create();
        $newcomer = User::factory()->create();

        $conversation = Conversation::query()->findOrFail(
            $this->asUser($owner)
                ->postJson('/api/v1/chat/conversations', [
                    'type' => 'group',
                    'name' => 'Design Crew',
                    'member_ids' => [$member->id],
                ])
                ->json('data.conversation.id'),
        );

        $this->asUser($member)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/members", ['user_id' => $plain->id])
            ->assertStatus(403);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/members", ['user_id' => $plain->id])
            ->assertOk()
            ->assertJsonPath('data.conversation.members_count', 3);

        $this->asUser($owner)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/members/{$plain->id}")
            ->assertOk();

        $this->assertDatabaseMissing('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $plain->id,
        ]);

        $this->asUser($plain)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertStatus(404);
    }

    public function test_typing_endpoint_is_members_only_and_throttled_by_cache(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/typing")
            ->assertOk();

        $this->asUser($outsider)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/typing")
            ->assertStatus(404);
    }

    public function test_private_channel_auth_only_grants_members(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $channel = new ConversationChannel(app(ChatRepository::class));
        $this->assertTrue($channel->join($them, (string) $conversation->id));
        $this->assertFalse($channel->join($outsider, (string) $conversation->id));
    }

    public function test_message_sent_event_broadcasts_on_conversation_channel(): void
    {
        /** @var list<MessageSent> $dispatched */
        $dispatched = [];
        Event::listen(MessageSent::class, static function (MessageSent $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $this->asUser($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'ping',
                'client_id' => 'm-'.Str::uuid(),
            ])
            ->assertStatus(201);

        $this->assertCount(1, $dispatched);
        $this->assertSame($conversation->id, $dispatched[0]->conversationId);
        $this->assertSame("private-dm.{$conversation->id}", $dispatched[0]->broadcastOn()->name);
    }

    public function test_chat_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/chat/conversations')->assertStatus(401);
        $this->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => 1])->assertStatus(401);
        $this->getJson('/api/v1/chat/unread-total')->assertStatus(401);
    }
}
