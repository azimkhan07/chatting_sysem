<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Chat\Enums\ConversationType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly ConversationType $conversationType,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        $prefix = $this->conversationType === ConversationType::Dm ? 'dm' : 'group';

        return new PrivateChannel($prefix.'.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
        ];
    }
}
