<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Chat\Enums\ConversationType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageReactionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, int>  $totals
     */
    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly ConversationType $conversationType,
        public readonly array $totals,
        public readonly ?string $reaction,
        public readonly int $userId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        $prefix = $this->conversationType === ConversationType::Dm ? 'dm' : 'group';

        return new PrivateChannel($prefix.'.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.reaction.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'user_id' => $this->userId,
            'reaction' => $this->reaction,
            'totals' => $this->totals,
        ];
    }
}
