<?php

declare(strict_types=1);

namespace App\Domain\Threads\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Threads\Contracts\ThreadRepository;
use App\Domain\Threads\Enums\ThreadReactionType;
use App\Domain\Threads\Enums\ThreadStatus;
use App\Domain\Threads\Exceptions\ThreadExpiredException;
use App\Domain\Threads\Exceptions\ThreadNotAuthorizedException;
use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use App\Domain\Threads\Models\ThreadReaction;
use Illuminate\Http\UploadedFile;

final class ThreadService
{
    public function __construct(
        private readonly ThreadRepository $threadRepository,
        private readonly ThreadMediaProcessor $mediaProcessor,
    ) {}

    public function activeFor(Conversation $conversation): ?Thread
    {
        return $this->threadRepository->activeFor($conversation->id);
    }

    public function start(User $user, Conversation $conversation): Thread
    {
        $existing = $this->threadRepository->activeFor($conversation->id);
        if ($existing !== null) {
            return $existing;
        }

        return $this->threadRepository->create($conversation->id, $user->id);
    }

    public function show(User $user, Conversation $conversation): Thread
    {
        /** @var Thread|null $thread */
        $thread = Thread::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        $thread ??= $this->start($user, $conversation);

        return $this->threadRepository->load($thread);
    }

    /**
     * @return array{entry: ThreadEntry, thread: Thread}
     */
    public function addEntry(
        User $user,
        Conversation $conversation,
        Thread $thread,
        ?string $body,
        ?UploadedFile $file,
    ): array {
        $this->assertWritable($conversation, $thread);

        if (trim((string) $body) === '' && $file === null) {
            throw new ThreadNotAuthorizedException('A thread entry needs some content.');
        }

        $attributes = [];
        if ($file !== null) {
            $media = $this->mediaProcessor->process($user->id, $file);
            $attributes['media_path'] = $media['file_path'];
            $attributes['media_mime'] = $media['mime'];
            $attributes['media_type'] = $media['type'];
        }

        $entry = $this->threadRepository->addEntry($thread, $user->id, [
            ...$attributes,
            'body' => $body !== null ? trim($body) : null,
        ]);
        $entry->load('author:id,username,display_name,avatar_path');

        return ['entry' => $entry, 'thread' => $thread];
    }

    /**
     * @return array{reacted: bool, reaction: ?string, totals: array<string, int>}
     */
    public function toggleReaction(
        User $user,
        Conversation $conversation,
        Thread $thread,
        ThreadEntry $entry,
        string $reaction,
    ): array {
        $this->assertWritable($conversation, $thread);

        if ($entry->thread_id !== $thread->id) {
            throw new ThreadNotAuthorizedException('That entry does not belong to this thread.');
        }

        return $this->threadRepository->toggleReaction($entry, $user->id, $reaction);
    }

    public function expireDue(): int
    {
        $expired = 0;
        foreach ($this->threadRepository->dueForExpiry() as $thread) {
            $this->threadRepository->expire($thread, $this->recapFor($thread));
            $expired++;
        }

        return $expired;
    }

    /**
     * @return array<string, mixed>
     */
    private function recapFor(Thread $thread): array
    {
        $entries = $thread->entries;
        $entryIds = $entries->pluck('id');

        $reactionTotals = ThreadReaction::query()
            ->whereIn('thread_entry_id', $entryIds)
            ->get(['reaction'])
            ->pluck('reaction')
            ->map(static fn (ThreadReactionType $reaction): string => $reaction->value)
            ->countBy()
            ->all();

        $perUser = $entries->groupBy('user_id')->map->count();
        $topUserId = $perUser->sortDesc()->keys()->first();

        /** @var User|null $topUser */
        $topUser = $entries->firstWhere('user_id', $topUserId)?->author;

        return [
            'entries' => $entries->count(),
            'participants' => $perUser->count(),
            'reactions' => $reactionTotals,
            'top_contributor' => $topUser !== null ? [
                'id' => $topUser->id,
                'username' => $topUser->username,
                'display_name' => $topUser->display_name,
                'avatar_path' => $topUser->avatar_path,
                'entries' => $perUser[$topUserId] ?? 0,
            ] : null,
        ];
    }

    private function assertWritable(Conversation $conversation, Thread $thread): void
    {
        if ($thread->conversation_id !== $conversation->id) {
            throw new ThreadNotAuthorizedException('That thread does not belong to this group.');
        }

        if ($thread->status !== ThreadStatus::Active) {
            throw new ThreadExpiredException('This thread has ended. Start a new one to contribute again.');
        }
    }
}
