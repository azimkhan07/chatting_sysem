<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Enums\MemberRole;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Chat\Models\GroupInvite;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentChatRepository implements ChatRepository
{
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
        $unreadSub = ConversationMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('conversation_messages.conversation_id', 'conversations.id')
            ->whereRaw('conversation_messages.id > conversation_members_last_read.last_read_message_id');

        return Conversation::query()
            ->select('conversations.*')
            ->selectSub($unreadSub, 'unread_count')
            ->join('conversation_members as conversation_members_last_read', function (JoinClause $join) use ($userId): void {
                $join->on('conversation_members_last_read.conversation_id', '=', 'conversations.id')
                    ->where('conversation_members_last_read.user_id', '=', $userId);
            })
            ->with([
                'lastMessage.user',
                'members' => fn ($query) => $query->with('user'),
            ])
            ->orderByDesc('conversations.updated_at')
            ->get();
    }

    public function messagesFor(Conversation $conversation, int $limit, ?string $cursor): CursorPaginator
    {
        $paginator = $conversation->messages()
            ->with(['user', 'conversation.members'])
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
                ->with(['user', 'conversation.members'])
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

        $message->load(['user', 'conversation.members']);
        $conversation->touch();

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
        }

        return $member->refresh();
    }

    public function unreadFor(int $userId, Conversation $conversation): int
    {
        $member = $this->memberFor($userId, $conversation->id);
        if ($member === null) {
            return 0;
        }

        return (int) $conversation->messages()
            ->where('id', '>', (int) $member->last_read_message_id)
            ->count();
    }

    public function unreadTotal(int $userId): int
    {
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
