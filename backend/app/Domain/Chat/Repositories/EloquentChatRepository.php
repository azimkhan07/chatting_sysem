<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Enums\MemberRole;
use App\Domain\Chat\Enums\MessageReactionType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Chat\Models\GroupInvite;
use App\Domain\Chat\Models\MessageReaction;
use App\Domain\Chat\Services\ChatInboxCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentChatRepository implements ChatRepository
{
    public function __construct(private readonly ChatInboxCache $inboxCache) {}

    public function createConversation(array $attributes): Conversation
    {
        $conversation = Conversation::query()->create($attributes);
        $conversation->load(['members.user', 'lastMessage.user']);

        return $conversation;
    }

    public function addMember(Conversation $conversation, int $userId, array $attributes = ['role' => MemberRole::Member]): ConversationMember
    {
        /** @var ConversationMember $member */
        $member = $conversation->members()->updateOrCreate(
            ['user_id' => $userId],
            ['role' => $attributes['role'], 'muted' => $attributes['muted'] ?? false],
        );

        return $member;
    }

    public function findDmBetween(int $firstUserId, int $secondUserId): ?Conversation
    {
        $first = (int) Conversation::query()
            ->where('type', ConversationType::Dm->value)
            ->whereHas('members', fn ($query) => $query->where('user_id', $firstUserId))
            ->whereHas('members', fn ($query) => $query->where('user_id', $secondUserId))
            ->orderByDesc('id')
            ->value('id');

        if ($first === 0) {
            return null;
        }

        return $this->loadForMember($first);
    }

    public function conversationForUser(int $userId, int $conversationId): ?Conversation
    {
        $found = Conversation::query()
            ->whereKey($conversationId)
            ->whereHas('members', fn ($query) => $query->where('user_id', $userId))
            ->exists();

        if (! $found) {
            return null;
        }

        return $this->loadForMember($conversationId);
    }

    public function conversationsFor(int $userId): Collection
    {
        $conversations = Conversation::query()
            ->select('conversations.*')
            ->whereHas('members', fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->with([
                'lastMessage.user',
                'members' => fn ($query) => $query->with('user'),
            ])
            ->orderByDesc('conversations.updated_at')
            ->get();

        $cached = $this->inboxCache->unreadMap($userId);
        $uncachedIds = $cached === null
            ? $conversations->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
            : $conversations->pluck('id')
                ->filter(fn ($id): bool => ! array_key_exists((int) $id, $cached))
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

        $sqlCounts = $uncachedIds === []
            ? []
            : $this->unreadCountsFor($userId, $uncachedIds);

        $counts = [];
        foreach ($conversations as $conversation) {
            $id = (int) $conversation->id;
            $unread = $cached[$id] ?? $sqlCounts[$id] ?? 0;
            $conversation->setAttribute('unread_count', $unread);
            $counts[$id] = $unread;
        }

        $this->inboxCache->warmUnread($userId, $counts);

        return $conversations;
    }

    public function messagesFor(Conversation $conversation, int $limit, ?string $cursor): CursorPaginator
    {
        $paginator = $conversation->messages()
            ->with(['user', 'conversation.members.user', 'reactions'])
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);

        /** @var CursorPaginator<int, ConversationMessage> $paginator */

        return $paginator;
    }

    public function createMessage(Conversation $conversation, int $userId, array $attributes): ConversationMessage
    {
        if ($attributes['client_id'] !== null) {
            /** @var ConversationMessage|null $existing */
            $existing = $conversation->messages()
                ->where('client_id', $attributes['client_id'])
                ->with(['user', 'conversation.members.user'])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        /** @var ConversationMessage $message */
        $message = $conversation->messages()->create([
            'user_id' => $userId,
            'type' => $attributes['type'],
            'body' => $attributes['body'],
            'media_url' => $attributes['media_url'],
            'client_id' => $attributes['client_id'],
        ]);

        $this->advanceWatermark($conversation, $userId, $message->id);

        $message->load(['user', 'conversation.members.user']);
        $conversation->touch();

        $this->inboxCache->noteNewMessage($conversation, $message);

        return $message;
    }

    public function memberFor(int $userId, int $conversationId): ?ConversationMember
    {
        return ConversationMember::query()
            ->where('user_id', $userId)
            ->where('conversation_id', $conversationId)
            ->first();
    }

    public function updateReadWatermark(ConversationMember $member, int $upToMessageId): ConversationMember
    {
        if ($upToMessageId > (int) $member->last_read_message_id) {
            $member->update(['last_read_message_id' => $upToMessageId]);
            $this->inboxCache->markRead((int) $member->user_id, (int) $member->conversation_id);
        }

        return $member->refresh();
    }

    public function unreadFor(int $userId, Conversation $conversation): int
    {
        $cached = $this->inboxCache->unreadMap($userId);
        if ($cached !== null && array_key_exists((int) $conversation->id, $cached)) {
            return $cached[(int) $conversation->id];
        }

        $member = $this->memberFor($userId, $conversation->id);
        if ($member === null) {
            return 0;
        }

        $unread = (int) $conversation->messages()
            ->where('id', '>', (int) $member->last_read_message_id)
            ->count();

        $this->inboxCache->warmUnread($userId, [(int) $conversation->id => $unread]);

        return $unread;
    }

    public function unreadTotal(int $userId): int
    {
        $cached = $this->inboxCache->unreadTotal($userId);
        if ($cached !== null) {
            return $cached;
        }

        return (int) DB::table('conversation_messages')
            ->join('conversation_members', 'conversation_members.conversation_id', '=', 'conversation_messages.conversation_id')
            ->where('conversation_members.user_id', $userId)
            ->whereColumn('conversation_messages.id', '>', 'conversation_members.last_read_message_id')
            ->count();
    }

    public function removeMember(Conversation $conversation, int $userId): void
    {
        $conversation->members()->where('user_id', $userId)->delete();
    }

    public function validInviteFor(int $conversationId): ?GroupInvite
    {
        return GroupInvite::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public function createInvite(Conversation $conversation, int $createdBy): GroupInvite
    {
        $invite = $conversation->invites()->create([
            'created_by' => $createdBy,
            'code' => strtoupper(Str::random(10)),
        ]);

        /** @var GroupInvite $invite */
        $invite = $invite->refresh();

        return $invite;
    }

    public function revokeInvite(GroupInvite $invite): void
    {
        $invite->update(['revoked_at' => now()]);
    }

    public function inviteByCode(string $code): ?GroupInvite
    {
        return GroupInvite::query()
            ->where('code', $code)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function messageFor(Conversation $conversation, int $messageId): ?ConversationMessage
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereKey($messageId)
            ->first();
    }

    public function reactionFor(ConversationMessage $message, int $userId): ?MessageReaction
    {
        return MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $userId)
            ->first();
    }

    public function setReaction(ConversationMessage $message, int $userId, MessageReactionType $reaction): MessageReaction
    {
        /** @var MessageReaction $existing */
        $existing = MessageReaction::query()->updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $userId],
            ['reaction' => $reaction->value],
        );

        return $existing;
    }

    public function deleteReaction(MessageReaction $reaction): void
    {
        $reaction->delete();
    }

    public function reactionCounts(ConversationMessage $message): array
    {
        $rows = MessageReaction::query()->where('message_id', $message->id)->get(['reaction']);

        $totals = $rows->pluck('reaction')
            ->map(fn (MessageReactionType $reaction): string => $reaction->value)
            ->countBy()
            ->all();

        $defaults = [];
        foreach (MessageReactionType::cases() as $case) {
            $defaults[$case->value] = 0;
        }

        return array_merge($defaults, $totals);
    }

    public function deleteMessage(ConversationMessage $message): void
    {
        $conversationId = (int) $message->conversation_id;
        $message->delete();

        $this->inboxCache->forgetLast($conversationId);
    }

    /**
     * Single-pass unread counts for a set of conversations belonging to the
     * user — avoids one correlated subquery per conversation.
     *
     * @param  list<int>  $conversationIds
     * @return array<int, int>
     */
    private function unreadCountsFor(int $userId, array $conversationIds): array
    {
        $rows = DB::table('conversation_members as members')
            ->leftJoin('conversation_messages as messages', 'messages.conversation_id', '=', 'members.conversation_id')
            ->where('members.user_id', $userId)
            ->whereIn('members.conversation_id', $conversationIds)
            ->whereColumn('messages.id', '>', 'members.last_read_message_id')
            ->select('members.conversation_id', DB::raw('count(messages.id) as unread_count'))
            ->groupBy('members.conversation_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->conversation_id] = (int) $row->unread_count;
        }

        return $counts;
    }

    private function advanceWatermark(Conversation $conversation, int $userId, int $upToMessageId): void
    {
        $conversation->members()
            ->where('user_id', $userId)
            ->where('last_read_message_id', '<', $upToMessageId)
            ->update(['last_read_message_id' => $upToMessageId]);
    }

    private function loadForMember(int $conversationId): Conversation
    {
        return Conversation::query()
            ->whereKey($conversationId)
            ->with(['members.user', 'lastMessage.user'])
            ->firstOrFail();
    }
}
