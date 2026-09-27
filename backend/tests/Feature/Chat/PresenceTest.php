<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Broadcasting\ConversationPresenceChannel;
use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Contracts\PresenceService;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Services\PresenceStore;
use App\Events\UserPresenceChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SubscribesUsers;
use Tests\TestCase;

final class PresenceTest extends TestCase
{
    use RefreshDatabase;
    use SubscribesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // User ids restart from 1 on every test, so a stale presence entry from
        // a previous test would otherwise light up the wrong fixture.
        try {
            Redis::connection()->del(PresenceStore::KEY);
        } catch (\Throwable) {
            // Redis is optional in tests - presence falls back to last_seen_at.
        }
    }

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

    private function createGroup(User $owner, array $memberIds = []): Conversation
    {
        return Conversation::query()->findOrFail(
            $this->asUser($owner)
                ->postJson('/api/v1/chat/conversations', [
                    'type' => 'group',
                    'name' => 'Presence Squad',
                    'member_ids' => $memberIds,
                ])
                ->assertStatus(201)
                ->json('data.conversation.id'),
        );
    }

    private function redisIsAvailable(): bool
    {
        return app(PresenceStore::class)->onlineIds() !== null;
    }

    public function test_presence_heartbeat_requires_authentication(): void
    {
        $this->postJson('/api/v1/chat/presence')->assertStatus(401);
    }

    public function test_heartbeat_marks_the_user_online_for_their_conversation_partner(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $this->asUser($them)
            ->postJson('/api/v1/chat/presence')
            ->assertOk()
            ->assertJsonPath('data.is_online', true);

        $seen = $this->asUser($me)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->json('data.conversation');

        $this->assertSame($them->id, $seen['peer_presence']['user_id']);
        $this->assertTrue($seen['peer_presence']['is_online']);
        $this->assertSame(1, $seen['online_count']);

        $members = collect($seen['members']);
        $this->assertTrue($members->firstWhere('user.id', $them->id)['user']['is_online']);
        $this->assertFalse($members->firstWhere('user.id', $me->id)['user']['is_online']);
    }

    public function test_heartbeat_persists_last_seen_at_for_profile_views(): void
    {
        $me = User::factory()->create(['last_seen_at' => null]);

        $response = $this->asUser($me)
            ->postJson('/api/v1/chat/presence')
            ->assertOk()
            ->json('data');

        $this->assertTrue($response['is_online']);
        $this->assertNotNull($response['last_seen_at']);
        $this->assertNotNull($me->refresh()->last_seen_at);
    }

    public function test_a_user_who_went_quiet_reads_as_offline(): void
    {
        $me = User::factory()->create();
        $quiet = User::factory()->create(['last_seen_at' => now()->subMinutes(30)]);
        $conversation = $this->startDm($me, $quiet);

        $seen = $this->asUser($me)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->json('data.conversation');

        $this->assertFalse($seen['peer_presence']['is_online']);
        $this->assertSame(0, $seen['online_count']);
        $this->assertNotNull($seen['peer_presence']['last_seen_at']);
    }

    public function test_group_conversations_report_how_many_members_are_online(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $asleep = User::factory()->create(['last_seen_at' => now()->subMinutes(45)]);
        $conversation = $this->createGroup($owner, [$member->id, $asleep->id]);

        $this->asUser($member)->postJson('/api/v1/chat/presence')->assertOk();

        $seen = $this->asUser($owner)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->json('data.conversation');

        $this->assertSame(1, $seen['online_count']);
        $this->assertNull($seen['peer_presence']);

        $members = collect($seen['members']);
        $this->assertTrue($members->firstWhere('user.id', $member->id)['user']['is_online']);
        $this->assertFalse($members->firstWhere('user.id', $asleep->id)['user']['is_online']);
    }

    public function test_signing_out_broadcasts_the_offline_transition_to_the_room(): void
    {
        /** @var list<UserPresenceChanged> $dispatched */
        $dispatched = [];
        Event::listen(UserPresenceChanged::class, static function (UserPresenceChanged $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $me = User::factory()->create();
        $them = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        app(PresenceService::class)->signOut($them);

        $this->assertCount(1, $dispatched);
        $this->assertSame($conversation->id, $dispatched[0]->conversationId);
        $this->assertSame($them->id, $dispatched[0]->userId);
        $this->assertFalse($dispatched[0]->isOnline);
        $this->assertSame("private-dm.{$conversation->id}", $dispatched[0]->broadcastOn()->name);
        $this->assertSame('presence.changed', $dispatched[0]->broadcastAs());
    }

    public function test_logout_drops_the_presence_entry(): void
    {
        $me = User::factory()->create();

        $this->asUser($me)
            ->postJson('/api/v1/chat/presence')
            ->assertOk();

        $this->asUser($me)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        $online = app(PresenceStore::class)->onlineMap([(int) $me->id]);

        if ($online !== null) {
            $this->assertFalse($online[(int) $me->id]);
        }
    }

    public function test_presence_channel_only_grants_members_and_identifies_them(): void
    {
        $me = User::factory()->create(['username' => 'me_here']);
        $them = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->startDm($me, $them);

        $channel = new ConversationPresenceChannel(app(ChatRepository::class));

        $this->assertSame([
            'id' => $me->id,
            'username' => 'me_here',
            'display_name' => $me->display_name,
        ], $channel->join($me, (string) $conversation->id));

        $this->assertFalse($channel->join($outsider, (string) $conversation->id));
    }

    public function test_presence_payload_carries_the_transition_to_clients(): void
    {
        $event = new UserPresenceChanged(12, 34, ConversationType::Group, true, '2026-01-01T00:00:00+00:00');

        $this->assertSame('private-group.12', $event->broadcastOn()->name);
        $this->assertSame([
            'conversation_id' => 12,
            'user_id' => 34,
            'is_online' => true,
            'last_seen_at' => '2026-01-01T00:00:00+00:00',
        ], $event->broadcastWith());
    }

    public function test_sweep_expires_users_who_stopped_sending_heartbeats(): void
    {
        if (! $this->redisIsAvailable()) {
            $this->markTestSkipped('Redis is unavailable, so the presence window cannot be exercised.');
        }

        /** @var list<UserPresenceChanged> $dispatched */
        $dispatched = [];
        Event::listen(UserPresenceChanged::class, static function (UserPresenceChanged $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $me = User::factory()->create();
        $quiet = User::factory()->create();
        $conversation = $this->startDm($me, $quiet);

        Redis::connection()->zadd(PresenceStore::KEY, (float) (time() - 3_600), (string) $quiet->id);

        $this->assertSame(1, app(PresenceService::class)->sweep());
        $this->assertSame(0, app(PresenceService::class)->sweep());

        $this->assertCount(1, $dispatched);
        $this->assertSame($conversation->id, $dispatched[0]->conversationId);
        $this->assertSame($quiet->id, $dispatched[0]->userId);
        $this->assertFalse($dispatched[0]->isOnline);
        $this->assertNotNull($dispatched[0]->lastSeenAt);

        $this->assertTrue(
            $quiet->refresh()->last_seen_at?->isBefore(now()->subMinutes(30)) ?? false
        );
    }

    public function test_repeated_heartbeats_do_not_reflood_the_room(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $this->startDm($me, $them);
        $presence = app(PresenceService::class);

        /** @var list<UserPresenceChanged> $dispatched */
        $dispatched = [];
        Event::listen(UserPresenceChanged::class, static function (UserPresenceChanged $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $presence->heartbeat($me);
        $presence->heartbeat($me);
        $presence->heartbeat($me);

        // One broadcast on the offline -> online transition, silence after that.
        $this->assertLessThanOrEqual(1, count($dispatched));
        $this->assertTrue($presence->isOnline($me->fresh() ?? $me));
    }
}
