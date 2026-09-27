<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Chat\Enums\ConversationType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Online/offline transition for a member, pushed to everyone else in the
 * conversation so dots flip live instead of on the next poll.
 */
final class UserPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly ConversationType $conversationType,
        public readonly bool $isOnline,
        public readonly ?string $lastSeenAt = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        $prefix = $this->conversationType === ConversationType::Dm ? 'dm' : 'group';

        return new PrivateChannel($prefix.'.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'is_online' => $this->isOnline,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }
}
