<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Events\MessageDeleted;
use App\Events\MessageReactionChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MessageReactionTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function startDm(User $me, User $other): Conversation
    {
        return Conversation::query()->findOrFail(
            $this->asUser($me)
                ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $other->id])
                ->json('data.conversation.id'),
        );
    }

    private function sendText(User $user, Conversation $conversation, string $body): int
    {
        return (int) $this->asUser($user)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'type' => 'text',
                'body' => $body,
                'client_id' => 'm-'.Str::uuid(),
            ])
            ->assertStatus(201)
            ->json('data.message.id');
    }

    private function createGroup(User $owner, int ...$members): Conversation
    {
        return Conversation::query()->findOrFail(
            $this->asUser($owner)
                ->postJson('/api/v1/chat/conversations', [
                    'type' => 'group',
                    'name' => 'React Crew',
                    'member_ids' => $members,
                ])
                ->json('data.conversation.id'),
        );
    }

    public function test_user_can_react_and_toggle_off(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);
        $messageId = $this->sendText($me, $conversation, 'nice one');

        $reacted = $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'like',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('like', $reacted['reaction']);
        $this->assertSame(1, $reacted['reactions']['like']);
        $this->assertDatabaseHas('conversation_message_reactions', [
            'message_id' => $messageId,
            'user_id' => $them->id,
            'reaction' => 'like',
        ]);

        $removed = $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'like',
            ])
            ->assertOk()
            ->json('data');

        $this->assertNull($removed['reaction']);
        $this->assertSame(0, $removed['reactions']['like']);
        $this->assertDatabaseMissing('conversation_message_reactions', [
            'message_id' => $messageId,
            'user_id' => $them->id,
        ]);
    }

    public function test_reaction_switches_between_types(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);
        $messageId = $this->sendText($me, $conversation, 'switch me');

        $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'love',
            ])
            ->assertOk();

        $switched = $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'wow',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('wow', $switched['reaction']);
        $this->assertSame(0, $switched['reactions']['love']);
        $this->assertSame(1, $switched['reactions']['wow']);
        $this->assertDatabaseCount('conversation_message_reactions', 1);
    }

    public function test_messages_list_includes_reaction_summaries(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);
        $messageId = $this->sendText($me, $conversation, 'summary time');

        $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'haha',
            ])
            ->assertOk();

        $messages = $this->asUser($me)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->json('data.messages');

        $target = collect($messages)->firstWhere('id', $messageId);
        $this->assertSame(1, $target['reactions']['haha']);
        $this->assertNull($target['my_reaction']);
    }

    public function test_reaction_requires_membership(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->startDm($me, $them);
        $messageId = $this->sendText($me, $conversation, 'private much');

        $this->asUser($outsider)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'like',
            ])
            ->assertStatus(404);
    }

    public function test_reaction_to_message_in_other_conversation_is_404(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $first = $this->startDm($a, $b);
        $second = $this->startDm($b, $c);
        $messageId = $this->sendText($a, $first, 'not yours');

        $this->asUser($c)
            ->postJson("/api/v1/chat/conversations/{$second->id}/messages/{$messageId}/reactions", [
                'reaction' => 'angry',
            ])
            ->assertStatus(404);
    }

    public function test_sender_can_delete_own_message_for_everyone(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);
        $messageId = $this->sendText($me, $conversation, 'oops');

        $this->asUser($me)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}")
            ->assertOk();

        $this->assertDatabaseMissing('conversation_messages', ['id' => $messageId]);
    }

    public function test_moderator_can_delete_any_message_in_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = $this->createGroup($owner, $member->id);
        $messageId = $this->sendText($member, $conversation, 'delete me');

        $this->asUser($owner)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}")
            ->assertOk();

        $this->assertDatabaseMissing('conversation_messages', ['id' => $messageId]);
    }

    public function test_member_cannot_delete_others_messages_in_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->createGroup($owner, $member->id, $other->id);
        $messageId = $this->sendText($member, $conversation, 'not yours to remove');

        $this->asUser($other)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}")
            ->assertStatus(403);

        $this->assertDatabaseHas('conversation_messages', ['id' => $messageId]);
    }

    public function test_deleting_reaction_and_message_broadcast_events(): void
    {
        /** @var list<MessageReactionChanged> $reactions */
        $reactions = [];
        /** @var list<MessageDeleted> $deleted */
        $deleted = [];
        Event::listen(MessageReactionChanged::class, static function (MessageReactionChanged $event) use (&$reactions): void {
            $reactions[] = $event;
        });
        Event::listen(MessageDeleted::class, static function (MessageDeleted $event) use (&$deleted): void {
            $deleted[] = $event;
        });

        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);
        $messageId = $this->sendText($me, $conversation, 'going going gone');

        $this->asUser($them)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}/reactions", [
                'reaction' => 'sad',
            ])
            ->assertOk();

        $this->assertCount(1, $reactions);
        $this->assertSame("private-dm.{$conversation->id}", $reactions[0]->broadcastOn()->name);
        $this->assertSame('message.reaction.changed', $reactions[0]->broadcastAs());

        $this->asUser($me)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/messages/{$messageId}")
            ->assertOk();

        $this->assertCount(1, $deleted);
        $this->assertSame($messageId, $deleted[0]->messageId);
        $this->assertSame('message.deleted', $deleted[0]->broadcastAs());
    }

    public function test_reaction_and_delete_require_authentication(): void
    {
        $this->postJson('/api/v1/chat/conversations/1/messages/1/reactions', ['reaction' => 'like'])->assertStatus(401);
        $this->deleteJson('/api/v1/chat/conversations/1/messages/1')->assertStatus(401);
    }
}
