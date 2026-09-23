<?php

declare(strict_types=1);

namespace App\Domain\Threads\Repositories;

use App\Domain\Threads\Contracts\ThreadRepository;
use App\Domain\Threads\Enums\ThreadReactionType;
use App\Domain\Threads\Enums\ThreadStatus;
use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use App\Domain\Threads\Models\ThreadReaction;
use Illuminate\Support\Collection;

final class EloquentThreadRepository implements ThreadRepository
{
    public function activeFor(int $conversationId): ?Thread
    {
        return Thread::query()
            ->where('conversation_id', $conversationId)
            ->where('status', ThreadStatus::Active->value)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function create(int $conversationId, int $createdBy): Thread
    {
        return Thread::query()->create([
            'conversation_id' => $conversationId,
            'created_by' => $createdBy,
            'status' => ThreadStatus::Active,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function load(Thread $thread): Thread
    {
        $thread->load([
            'creator:id,username,display_name,avatar_path',
            'entries' => fn ($query) => $query->orderByDesc('id')->limit(100),
            'entries.author:id,username,display_name,avatar_path',
            'entries.reactions',
        ]);

        return $thread;
    }

    public function addEntry(Thread $thread, int $userId, array $attributes): ThreadEntry
    {
        return ThreadEntry::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $userId,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function reactionTotals(ThreadEntry $entry): array
    {
        $totals = ThreadReaction::query()
            ->where('thread_entry_id', $entry->id)
            ->get(['reaction'])
            ->pluck('reaction')
            ->map(static fn (ThreadReactionType $reaction): string => $reaction->value)
            ->countBy()
            ->all();

        $out = [];
        foreach (ThreadReactionType::cases() as $case) {
            $out[$case->value] = $totals[$case->value] ?? 0;
        }

        return $out;
    }

    public function toggleReaction(ThreadEntry $entry, int $userId, string $reaction): array
    {
        $existing = ThreadReaction::query()
            ->where('thread_entry_id', $entry->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            if ($existing->reaction->value === $reaction) {
                $existing->delete();
                $current = null;
            } else {
                $existing->update(['reaction' => $reaction]);
                $current = $reaction;
            }
        } else {
            ThreadReaction::query()->create([
                'thread_entry_id' => $entry->id,
                'user_id' => $userId,
                'reaction' => $reaction,
            ]);
            $current = $reaction;
        }

        return [
            'reacted' => $current !== null,
            'reaction' => $current,
            'totals' => $this->reactionTotals($entry),
        ];
    }

    public function dueForExpiry(): Collection
    {
        return Thread::query()
            ->with('entries:id,thread_id,user_id')
            ->where('status', ThreadStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->get();
    }

    public function expire(Thread $thread, array $recap): void
    {
        $thread->update([
            'status' => ThreadStatus::Expired,
            'recap' => $recap,
        ]);
    }
}
