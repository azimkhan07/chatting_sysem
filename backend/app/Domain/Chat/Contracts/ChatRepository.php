<?php

declare(strict_types=1);

namespace App\Domain\Chat\Contracts;

use App\Domain\Chat\Enums\MemberRole;
use App\Domain\Chat\Enums\MessageReactionType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Chat\Models\GroupInvite;
use App\Domain\Chat\Models\MessageReaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface ChatRepository
{
    /**
     * @param  array{type: string, name?: ?string, created_by: int}  $attributes
     */
    public function createConversation(array $attributes): Conversation;

    /**
     * @param  array{role: MemberRole, muted?: bool}  $attributes
     */
    public function addMember(Conversation $conversation, int $userId, array $attributes = ['role' => MemberRole::Member]): ConversationMember;

    public function findDmBetween(int $firstUserId, int $secondUserId): ?Conversation;

    /**
     * Resolve a conversation for a member, or null when the user is not a
     * member (or it does not exist) so lists never leak hidden rooms.
     */
    public function conversationForUser(int $userId, int $conversationId): ?Conversation;

    /**
     * The user's conversations, eager-loaded with last message + members and a
     * correlated unread count. Faithful to docs/07: the client never counts.
     *
     * @return Collection<int, Conversation>
     */
    public function conversationsFor(int $userId): Collection;

    /**
     * @return CursorPaginator<int, ConversationMessage>
     */
    public function messagesFor(Conversation $conversation, int $limit, ?string $cursor): CursorPaginator;

    /**
     * Persists a message atomically. A duplicate client_id for the same
     * conversation resolves to the already-stored message (idempotent retry).
     *
     * @param  array{type: string, body: ?string, media_url: ?string, client_id: ?string}  $attributes
     */
    public function createMessage(Conversation $conversation, int $userId, array $attributes): ConversationMessage;

    public function memberFor(int $userId, int $conversationId): ?ConversationMember;

    public function updateReadWatermark(ConversationMember $member, int $upToMessageId): ConversationMember;

    public function unreadFor(int $userId, Conversation $conversation): int;

    public function unreadTotal(int $userId): int;

    public function removeMember(Conversation $conversation, int $userId): void;

    public function validInviteFor(int $conversationId): ?GroupInvite;

    public function createInvite(Conversation $conversation, int $createdBy): GroupInvite;

    public function revokeInvite(GroupInvite $invite): void;

    public function inviteByCode(string $code): ?GroupInvite;

    public function messageFor(Conversation $conversation, int $messageId): ?ConversationMessage;

    public function reactionFor(ConversationMessage $message, int $userId): ?MessageReaction;

    public function setReaction(ConversationMessage $message, int $userId, MessageReactionType $reaction): MessageReaction;

    public function deleteReaction(MessageReaction $reaction): void;

    /**
     * @return array<string, int>
     */
    public function reactionCounts(ConversationMessage $message): array;

    public function deleteMessage(ConversationMessage $message): void;
}
