<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\ConversationMessage;
use App\Http\Resources\MessageResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly ConversationMessage $message,
        public readonly ConversationType $conversationType,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        $prefix = $this->conversationType === ConversationType::Dm ? 'dm' : 'group';

        return new PrivateChannel($prefix.'.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
