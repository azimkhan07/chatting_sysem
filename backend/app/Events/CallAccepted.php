<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Chat\Enums\ConversationType;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CallAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $callId,
        public readonly ConversationType $conversationType,
        public readonly array $participant,
        public readonly string $roomName,
        public readonly int $initiatorId,
    ) {}

    /**
     * The conversation channel reaches members viewing the thread; the
     * initiator's user channel reaches them wherever they are in the app.
     *
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        $prefix = $this->conversationType === ConversationType::Dm ? 'dm' : 'group';

        return [
            new PrivateChannel($prefix.'.'.$this->conversationId),
            new PrivateChannel('user.'.$this->initiatorId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.accepted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'call_id' => $this->callId,
            'participant' => $this->participant,
            'room' => $this->roomName,
        ];
    }
}