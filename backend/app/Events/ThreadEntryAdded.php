<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use App\Http\Resources\ThreadEntryResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ThreadEntryAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Thread $thread,
        public readonly ThreadEntry $entry,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('group.'.$this->thread->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'thread.entry.added';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->thread->id,
            'conversation_id' => $this->thread->conversation_id,
            'entry' => (new ThreadEntryResource($this->entry))->resolve(),
        ];
    }
}
