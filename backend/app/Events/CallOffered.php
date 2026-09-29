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

final class CallOffered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public readonly int $conversationId,
        public readonly int $callId,
        public readonly ConversationType $conversationType,
        public readonly array $caller,
        public readonly string $kind,
        public readonly string $roomName,
        public readonly string $serverUrl,
        public readonly array $recipientIds,
    ) {}

    /**
     * The conversation channel covers members viewing the thread; the per-user
     * channels reach whoever is elsewhere in the app, so a ring shows up no
     * matter what page they are on.
     *
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        $prefix = $this->conversationType === ConversationType::Dm ? 'dm' : 'group';
        $channels = [new PrivateChannel($prefix.'.'.$this->conversationId)];

        foreach ($this->recipientIds as $userId) {
            if ($userId !== $this->caller['id']) {
                $channels[] = new PrivateChannel('user.'.$userId);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'call.offered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'call_id' => $this->callId,
            'caller' => $this->caller,
            'kind' => $this->kind,
            'room' => $this->roomName,
            'server_url' => $this->serverUrl,
        ];
    }
}
