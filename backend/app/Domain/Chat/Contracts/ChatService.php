<?php

declare(strict_types=1);

namespace App\Domain\Chat\Contracts;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Data\SendMessageData;
use App\Domain\Chat\Enums\MessageReactionType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Chat\Models\GroupInvite;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface ChatService
{
    /**
     * @return Collection<int, Conversation>
     */
    public function conversationsFor(User $user): Collection;

    public function startDm(User $user, int $targetUserId): Conversation;

    public function createGroup(User $user, string $name, array $memberIds): Conversation;

    /**
     * Resolves a conversation for the given member, or null when hidden.
     */
    public function conversationFor(User $user, int $conversationId): ?Conversation;

    /**
     * @return CursorPaginator<int, ConversationMessage>
     */
    public function messagesFor(User $user, int $conversationId, int $limit, ?string $cursor): CursorPaginator;

    public function sendMessage(User $user, int $conversationId, SendMessageData $data): ConversationMessage;

    /**
     * @return array{read_up_to: int, unread: int}
     */
    public function markRead(User $user, int $conversationId, int $upToMessageId): array;

    public function unreadTotal(User $user): int;

    public function setMuted(User $user, int $conversationId, bool $muted): Conversation;

    public function addMember(User $user, int $conversationId, int $newUserId): Conversation;

    public function removeMember(User $user, int $conversationId, int $memberUserId): Conversation;

    public function typing(User $user, int $conversationId): void;

    public function currentInvite(User $user, int $conversationId): ?GroupInvite;

    public function inviteFor(User $user, int $conversationId): GroupInvite;

    public function revokeInvite(User $user, int $conversationId): void;

    public function joinViaInvite(User $user, string $code): Conversation;

    /**
     * @return array{message_id: int, reaction: ?string, reactions: array<string, int>}
     */
    public function toggleMessageReaction(User $user, int $conversationId, int $messageId, MessageReactionType $reaction): array;

    /**
     * @return array{message_id: int, reaction: null, reactions: array<string, int>}
     */
    public function removeMessageReaction(User $user, int $conversationId, int $messageId): array;

    public function deleteMessage(User $user, int $conversationId, int $messageId): void;
}
