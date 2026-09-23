<?php

declare(strict_types=1);

namespace Tests\Feature\Threads;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use App\Events\ThreadEntryAdded;
use App\Events\ThreadReactionAdded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ThreadTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function createGroup(User $owner, array $members = []): Conversation
    {
        $response = $this->asUser($owner)
            ->postJson('/api/v1/chat/conversations', [
                'type' => 'group',
                'name' => 'Dev Crew',
                'member_ids' => array_map(static fn (User $u) => $u->id, $members),
            ])
            ->assertStatus(201);

        return Conversation::query()->findOrFail($response->json('data.conversation.id'));
    }

    public function test_thread_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/chat/groups/1/thread')->assertStatus(401);
        $this->getJson('/api/v1/chat/groups/1/thread')->assertStatus(401);
        $this->postJson('/api/v1/chat/groups/1/thread/entries')->assertStatus(401);
    }

    public function test_only_group_members_can_start_or_view_threads(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = $this->createGroup($owner);

        $created = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201)
            ->json('data.thread');

        $this->assertSame('active', $created['status']);
        $this->assertSame($group->id, $created['conversation_id']);
        $this->assertDatabaseHas('threads', ['conversation_id' => $group->id, 'status' => 'active']);

        $this->asUser($outsider)
            ->getJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(404);

        $this->asUser($outsider)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(404);
    }

    public function test_starting_a_thread_is_idempotent_while_active(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $first = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201)
            ->json('data.thread');

        $retry = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(200)
            ->json('data.thread');

        $this->assertSame($first['id'], $retry['id']);
        $this->assertDatabaseCount('threads', 1);
    }

    public function test_threads_are_not_available_in_dm_conversations(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();

        $dm = $this->asUser($me)
            ->postJson('/api/v1/chat/conversations', ['type' => 'dm', 'user_id' => $them->id])
            ->assertStatus(201)
            ->json('data.conversation');

        $this->asUser($me)
            ->postJson("/api/v1/chat/groups/{$dm['id']}/thread")
            ->assertStatus(422);
    }

    public function test_members_can_add_text_and_image_entries_to_an_active_thread(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $member = User::factory()->create(['display_name' => 'Aarav']);
        $group = $this->createGroup($owner, [$member]);

        $thread = Thread::factory()->create([
            'conversation_id' => $group->id,
            'created_by' => $owner->id,
        ]);

        $textEntry = $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'Day one of our launch push!'])
            ->assertStatus(201)
            ->json('data.entry');

        $this->assertSame('Day one of our launch push!', $textEntry['body']);
        $this->assertSame('Aarav', $textEntry['user']['display_name']);
        $this->assertDatabaseHas('thread_entries', ['thread_id' => $thread->id, 'user_id' => $member->id]);

        $imageEntry = $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", [
                'media' => $this->pngFile(),
            ])
            ->assertStatus(201)
            ->json('data.entry');

        $this->assertNotNull($imageEntry['media_url']);
        $this->assertSame('image', $imageEntry['media_type']);
        $this->assertCount(2, ThreadEntry::query()->where('thread_id', $thread->id)->get());
    }

    private function pngFile(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true,
        );
        $path = (string) tempnam(sys_get_temp_dir(), 'thread-').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, 'sprint.png', 'image/png', null, true);
    }

    public function test_empty_or_invalid_entries_are_rejected(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'body');

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => str_repeat('x', 1001)])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'body');
    }

    public function test_non_members_cannot_add_entries(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->asUser($outsider)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'hi'])
            ->assertStatus(404);
    }

    public function test_entries_are_rejected_once_the_thread_expires(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $thread = Thread::factory()->create([
            'conversation_id' => $group->id,
            'created_by' => $owner->id,
            'status' => 'expired',
            'expires_at' => now()->subMinute(),
        ]);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'too late'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'THREAD_EXPIRED');
    }

    public function test_members_can_toggle_and_switch_reactions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->createGroup($owner, [$member]);

        $thread = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201)
            ->json('data.thread');

        $entry = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'lets go'])
            ->assertStatus(201)
            ->json('data.entry');

        $liked = $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries/{$entry['id']}/reactions", ['reaction' => 'like'])
            ->assertOk()
            ->json('data');

        $this->assertTrue($liked['reacted']);
        $this->assertSame('like', $liked['reaction']);
        $this->assertSame(1, $liked['totals']['like']);
        $this->assertDatabaseHas('thread_reactions', [
            'thread_entry_id' => $entry['id'],
            'user_id' => $member->id,
            'reaction' => 'like',
        ]);

        $unliked = $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries/{$entry['id']}/reactions", ['reaction' => 'like'])
            ->assertOk()
            ->json('data');

        $this->assertFalse($unliked['reacted']);
        $this->assertSame(0, $unliked['totals']['like']);

        $switched = $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries/{$entry['id']}/reactions", ['reaction' => 'love'])
            ->assertOk()
            ->json('data');

        $this->assertSame('love', $switched['reaction']);
        $this->assertSame(0, $switched['totals']['like']);
        $this->assertSame(1, $switched['totals']['love']);
    }

    public function test_reaction_must_be_a_supported_type(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201);

        $entry = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'hi'])
            ->assertStatus(201)
            ->json('data.entry');

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries/{$entry['id']}/reactions", ['reaction' => 'fire'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'reaction');
    }

    public function test_add_entry_dispatches_entry_added_on_group_channel(): void
    {
        /** @var list<ThreadEntryAdded> $dispatched */
        $dispatched = [];
        Event::listen(ThreadEntryAdded::class, static function (ThreadEntryAdded $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'hi'])
            ->assertStatus(201);

        $this->assertCount(1, $dispatched);
        $this->assertSame($group->id, $dispatched[0]->thread->conversation_id);
        $this->assertSame("private-group.{$group->id}", $dispatched[0]->broadcastOn()->name);
        $this->assertSame('thread.entry.added', $dispatched[0]->broadcastAs());
    }

    public function test_reaction_dispatches_reaction_event_on_group_channel(): void
    {
        /** @var list<ThreadReactionAdded> $dispatched */
        $dispatched = [];
        Event::listen(ThreadReactionAdded::class, static function (ThreadReactionAdded $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->createGroup($owner, [$member]);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201);

        $entry = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'hi'])
            ->assertStatus(201)
            ->json('data.entry');

        $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries/{$entry['id']}/reactions", ['reaction' => 'haha'])
            ->assertOk();

        $this->assertCount(1, $dispatched);
        $this->assertSame("private-group.{$group->id}", $dispatched[0]->broadcastOn()->name);
        $this->assertSame('thread.reaction.added', $dispatched[0]->broadcastAs());
        $this->assertSame('haha', $dispatched[0]->reaction);
        $this->assertTrue($dispatched[0]->reacted);
    }

    public function test_show_includes_entries_and_my_reaction(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->createGroup($owner, [$member]);

        $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertStatus(201);

        $entry = $this->asUser($owner)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries", ['body' => 'checkpoint 1'])
            ->assertStatus(201)
            ->json('data.entry');

        $this->asUser($member)
            ->postJson("/api/v1/chat/groups/{$group->id}/thread/entries/{$entry['id']}/reactions", ['reaction' => 'wow'])
            ->assertOk();

        $data = $this->asUser($owner)
            ->getJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertOk()
            ->json('data');

        $this->assertSame('active', $data['thread']['status']);
        $this->assertCount(1, $data['entries']);
        $this->assertSame('checkpoint 1', $data['entries'][0]['body']);
        $this->assertNull($data['entries'][0]['my_reaction']);
        $this->assertSame(1, $data['entries'][0]['reactions']['wow']);

        $asMember = $this->asUser($member)
            ->getJson("/api/v1/chat/groups/{$group->id}/thread")
            ->assertOk()
            ->json('data');
        $this->assertSame('wow', $asMember['entries'][0]['my_reaction']);
    }

    public function test_expired_threads_get_a_recap_via_the_expire_command(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['display_name' => 'Neha']);
        $group = $this->createGroup($owner, [$member]);

        $thread = Thread::factory()->create([
            'conversation_id' => $group->id,
            'created_by' => $owner->id,
            'expires_at' => now()->subMinutes(5),
        ]);

        $entry = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'user_id' => $member->id]);
        ThreadEntry::factory()->create(['thread_id' => $thread->id, 'user_id' => $owner->id]);
        $entry->reactions()->create(['user_id' => $member->id, 'reaction' => 'like']);

        $this->artisan('threads:expire')->assertSuccessful();

        $thread->refresh();
        $this->assertSame('expired', $thread->status->value);
        $this->assertSame(2, $thread->recap['entries']);
        $this->assertSame(2, $thread->recap['participants']);
        $this->assertSame(1, $thread->recap['reactions']['like']);
        $this->assertSame($member->username, $thread->recap['top_contributor']['username']);
    }
}
