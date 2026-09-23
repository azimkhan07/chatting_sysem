<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Events\MemberJoined;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class InviteTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function createGroup(User $owner, array $memberIds = []): Conversation
    {
        return Conversation::query()->findOrFail(
            $this->asUser($owner)
                ->postJson('/api/v1/chat/conversations', [
                    'type' => 'group',
                    'name' => 'Invite Squad',
                    'member_ids' => $memberIds,
                ])
                ->json('data.conversation.id'),
        );
    }

    public function test_owner_can_create_and_reuse_a_stable_invite_link(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->createGroup($owner);

        $this->asUser($owner)
            ->getJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertOk()
            ->assertJsonPath('data.invite', null);

        $first = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertOk()
            ->json('data.invite');

        $this->assertSame($conversation->id, $first['conversation_id']);
        $this->assertSame(10, strlen((string) $first['code']));

        $retry = $this->asUser($owner)
            ->getJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertOk()
            ->json('data.invite');

        $this->assertSame($first['code'], $retry['code']);
        $this->assertDatabaseCount('group_invites', 1);
    }

    public function test_only_moderators_can_fetch_invite_link(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->createGroup($owner, [$member->id]);

        $this->asUser($member)
            ->getJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertStatus(403);

        $this->asUser($outsider)
            ->getJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertStatus(404);
    }

    public function test_invite_links_only_exist_for_groups(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();

        $conversation = Conversation::query()->findOrFail(
            $this->asUser($me)
                ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $them->id])
                ->json('data.conversation.id'),
        );

        $this->asUser($me)
            ->getJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertStatus(403);
    }

    public function test_user_joins_group_via_invite_link(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->createGroup($owner);
        $newcomer = User::factory()->create();

        $code = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->json('data.invite.code');

        $joined = $this->asUser($newcomer)
            ->postJson("/api/v1/chat/invites/{$code}/join")
            ->assertOk()
            ->json('data.conversation');

        $this->assertSame($conversation->id, $joined['id']);
        $this->assertSame(2, $joined['members_count']);
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $newcomer->id,
            'role' => 'member',
        ]);

        $this->asUser($newcomer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk();
    }

    public function test_joining_as_existing_member_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = $this->createGroup($owner, [$member->id]);

        $code = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->json('data.invite.code');

        $this->asUser($member)
            ->postJson("/api/v1/chat/invites/{$code}/join")
            ->assertOk();

        $this->assertDatabaseCount('conversation_members', 2);
    }

    public function test_revoked_and_unknown_codes_are_rejected(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->createGroup($owner);
        $newcomer = User::factory()->create();

        $code = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->json('data.invite.code');

        $this->asUser($owner)
            ->deleteJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->assertOk();

        $this->asUser($newcomer)
            ->postJson("/api/v1/chat/invites/{$code}/join")
            ->assertStatus(404);

        $this->asUser($newcomer)
            ->postJson('/api/v1/chat/invites/UNKNOWNCODE1/join')
            ->assertStatus(404);
    }

    public function test_member_joined_event_broadcasts_on_group_channel(): void
    {
        /** @var list<MemberJoined> $dispatched */
        $dispatched = [];
        Event::listen(MemberJoined::class, static function (MemberJoined $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $owner = User::factory()->create();
        $conversation = $this->createGroup($owner);
        $newcomer = User::factory()->create();

        $code = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$conversation->id}/invite")
            ->json('data.invite.code');

        $this->asUser($newcomer)
            ->postJson("/api/v1/chat/invites/{$code}/join")
            ->assertOk();

        $this->assertCount(1, $dispatched);
        $this->assertSame("private-group.{$conversation->id}", $dispatched[0]->broadcastOn()->name);
    }

    public function test_invite_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/chat/groups/1/invite')->assertStatus(401);
        $this->postJson('/api/v1/chat/invites/ABC123DEF0/join')->assertStatus(401);
    }
}
