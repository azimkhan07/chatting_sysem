<?php

declare(strict_types=1);

namespace App\Domain\Chat\Contracts;

use App\Domain\Chat\Enums\MemberRole;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
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
}
