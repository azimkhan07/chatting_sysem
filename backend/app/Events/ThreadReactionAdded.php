<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ThreadReactionAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, int>  $totals
     */
    public function __construct(
        public readonly Thread $thread,
        public readonly ThreadEntry $entry,
        public readonly ?string $reaction,
        public readonly bool $reacted,
        public readonly int $userId,
        public readonly array $totals,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('group.'.$this->thread->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'thread.reaction.added';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->thread->id,
            'conversation_id' => $this->thread->conversation_id,
            'entry_id' => $this->entry->id,
            'user_id' => $this->userId,
            'reaction' => $this->reaction,
            'reacted' => $this->reacted,
            'totals' => $this->totals,
        ];
    }
}
