<?php

declare(strict_types=1);

namespace Tests\Feature\Social;

use App\Broadcasting\UserChannel;
use App\Domain\Auth\Models\User;
use App\Events\NotificationCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class NotificationRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_broadcasts_notification_on_recipient_private_channel(): void
    {
        /** @var list<NotificationCreated> $dispatched */
        $dispatched = [];
        Event::listen(NotificationCreated::class, static function (NotificationCreated $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $follower = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($follower);
        $this->postJson("/api/v1/users/{$target->id}/follow")->assertOk();

        $this->assertCount(1, $dispatched);
        $this->assertSame($target->id, $dispatched[0]->userId);
        $this->assertSame("private-user.{$target->id}", $dispatched[0]->broadcastOn()->name);
        $this->assertSame('notification.created', $dispatched[0]->broadcastAs());
        $this->assertSame('follow', $dispatched[0]->broadcastWith()['notification']['type']);
    }

    public function test_private_user_channel_authorizes_only_the_owner(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $channel = new UserChannel;
        $this->assertTrue($channel->join($owner, (int) $owner->id));
        $this->assertFalse($channel->join($outsider, (int) $owner->id));
    }

    public function test_channel_auth_requires_authentication(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.1',
            'socket_id' => '1234.5678',
        ])->assertStatus(401);
    }
}
