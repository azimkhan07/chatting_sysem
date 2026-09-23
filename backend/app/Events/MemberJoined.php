<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MemberJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly User $member,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('group.'.$this->conversation->id);
    }

    public function broadcastAs(): string
    {
        return 'member.joined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'user' => [
                'id' => $this->member->id,
                'display_name' => $this->member->display_name,
                'username' => $this->member->username,
            ],
        ];
    }
}
