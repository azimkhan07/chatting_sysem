<?php

declare(strict_types=1);

namespace App\Domain\Threads\Contracts;

use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use Illuminate\Support\Collection;

interface ThreadRepository
{
    public function activeFor(int $conversationId): ?Thread;

    public function create(int $conversationId, int $createdBy): Thread;

    public function load(Thread $thread): Thread;

    public function addEntry(Thread $thread, int $userId, array $attributes): ThreadEntry;

    /**
     * @return array<string, int>
     */
    public function reactionTotals(ThreadEntry $entry): array;

    /**
     * @return array{reacted: bool, reaction: ?string, totals: array<string, int>}
     */
    public function toggleReaction(ThreadEntry $entry, int $userId, string $reaction): array;

    /**
     * @return Collection<int, Thread>
     */
    public function dueForExpiry(): Collection;

    public function expire(Thread $thread, array $recap): void;
}
