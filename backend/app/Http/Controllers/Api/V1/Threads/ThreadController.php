<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Threads;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Exceptions\InvalidConversationException;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Threads\Enums\ThreadReactionType;
use App\Domain\Threads\Exceptions\ThreadExpiredException;
use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use App\Domain\Threads\Services\ThreadService;
use App\Events\ThreadEntryAdded;
use App\Events\ThreadReactionAdded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Threads\AddThreadEntryRequest;
use App\Http\Requests\Api\V1\Threads\ToggleThreadReactionRequest;
use App\Http\Resources\ThreadEntryResource;
use App\Http\Resources\ThreadResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ThreadController extends Controller
{
    public function __construct(
        private readonly ThreadService $threadService,
        private readonly ChatService $chatService,
    ) {}

    public function show(Request $request, int $conversation): JsonResponse
    {
        $group = $this->resolveGroup($request->user(), $conversation);
        $thread = $this->threadService->show($request->user(), $group);

        return ApiResponse::success(data: [
            'thread' => (new ThreadResource($thread))->resolve(),
            'entries' => ThreadEntryResource::collection($thread->entries)->resolve(),
        ]);
    }

    public function start(Request $request, int $conversation): JsonResponse
    {
        $group = $this->resolveGroup($request->user(), $conversation);
        $thread = $this->threadService->start($request->user(), $group);

        return ApiResponse::success(
            data: ['thread' => (new ThreadResource($thread))->resolve()],
            status: $thread->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function addEntry(AddThreadEntryRequest $request, int $conversation): JsonResponse
    {
        $group = $this->resolveGroup($request->user(), $conversation);
        $thread = $this->requireActiveThread($group);
        $user = $request->user();

        ['entry' => $entry, 'thread' => $thread] = $this->threadService->addEntry(
            $user,
            $group,
            $thread,
            $request->validated('body'),
            $request->file('media'),
        );

        event(new ThreadEntryAdded($thread, $entry));

        $thread->setRelation('entries', $thread->entries->push($entry));

        return ApiResponse::success(
            data: [
                'thread' => (new ThreadResource($thread))->resolve(),
                'entry' => (new ThreadEntryResource($entry))->resolve(),
            ],
            status: 201,
        );
    }

    public function toggleReaction(
        ToggleThreadReactionRequest $request,
        int $conversation,
        int $entry,
    ): JsonResponse {
        $group = $this->resolveGroup($request->user(), $conversation);
        $thread = $this->requireActiveThread($group);

        $threadEntry = ThreadEntry::query()->find($entry);
        if ($threadEntry === null) {
            throw new ConversationNotFoundException('That thread entry was not found.');
        }

        $reaction = ThreadReactionType::from($request->validated('reaction'));
        $result = $this->threadService->toggleReaction(
            $request->user(),
            $group,
            $thread,
            $threadEntry,
            $reaction->value,
        );

        event(new ThreadReactionAdded(
            $thread,
            $threadEntry,
            $result['reaction'],
            $result['reacted'],
            $request->user()->id,
            $result['totals'],
        ));

        return ApiResponse::success(data: $result);
    }

    private function resolveGroup(User $user, int $conversationId): Conversation
    {
        $conversation = $this->chatService->conversationFor($user, $conversationId);
        if ($conversation === null) {
            throw new ConversationNotFoundException('This conversation is not available.');
        }

        if ($conversation->type !== ConversationType::Group) {
            throw new InvalidConversationException('Group threads are only available in groups.');
        }

        return $conversation;
    }

    private function requireActiveThread(Conversation $conversation): Thread
    {
        $thread = $this->threadService->activeFor($conversation);
        if ($thread !== null) {
            return $thread;
        }

        $latest = Thread::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        if ($latest !== null) {
            throw new ThreadExpiredException('This thread has ended. Start a new one to contribute again.');
        }

        throw new ConversationNotFoundException('No active thread yet. Start one to post the first entry.');
    }
}
