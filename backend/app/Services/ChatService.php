<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Contracts\ChatService as ChatServiceContract;
use App\Domain\Chat\Data\SendMessageData;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Enums\MemberRole;
use App\Domain\Chat\Enums\MessageReactionType;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Exceptions\ConversationPermissionException;
use App\Domain\Chat\Exceptions\InvalidConversationException;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Chat\Models\GroupInvite;
use App\Events\MemberJoined;
use App\Events\MessageDeleted;
use App\Events\MessageReactionChanged;
use App\Events\MessageSent;
use App\Events\UserTyping;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;

final class ChatService implements ChatServiceContract
{
    public function __construct(private readonly ChatRepository $chatRepository) {}

    public function conversationsFor(User $user): Collection
    {
        return $this->chatRepository->conversationsFor($user->id);
    }

    public function startDm(User $user, int $targetUserId): Conversation
    {
        if ($targetUserId === $user->id) {
            throw new InvalidConversationException('You cannot start a conversation with yourself.');
        }

        $existing = $this->chatRepository->findDmBetween($user->id, $targetUserId);
        if ($existing !== null) {
            return $existing;
        }

        $conversation = $this->chatRepository->createConversation([
            'type' => ConversationType::Dm->value,
            'created_by' => $user->id,
        ]);
        $this->chatRepository->addMember($conversation, $user->id, ['role' => MemberRole::Owner]);
        $this->chatRepository->addMember($conversation, $targetUserId, ['role' => MemberRole::Member]);

        $conversation->load(['members.user', 'lastMessage.user']);

        return $conversation;
    }

    public function createGroup(User $user, string $name, array $memberIds): Conversation
    {
        $conversation = $this->chatRepository->createConversation([
            'type' => ConversationType::Group->value,
            'name' => trim($name),
            'created_by' => $user->id,
        ]);
        $this->chatRepository->addMember($conversation, $user->id, ['role' => MemberRole::Owner]);

        foreach (array_unique(array_map('intval', $memberIds)) as $memberId) {
            if ($memberId === $user->id) {
                continue;
            }
            $this->chatRepository->addMember($conversation, $memberId, ['role' => MemberRole::Member]);
        }

        $conversation->load(['members.user', 'lastMessage.user']);

        return $conversation;
    }

    public function conversationFor(User $user, int $conversationId): ?Conversation
    {
        $conversation = $this->chatRepository->conversationForUser($user->id, $conversationId);
        if ($conversation === null) {
            return null;
        }

        $conversation->setAttribute('unread_count', $this->chatRepository->unreadFor($user->id, $conversation));

        return $conversation;
    }

    public function messagesFor(User $user, int $conversationId, int $limit, ?string $cursor): CursorPaginator
    {
        $conversation = $this->resolveForUser($user, $conversationId);

        return $this->chatRepository->messagesFor($conversation, $limit, $cursor);
    }

    public function sendMessage(User $user, int $conversationId, SendMessageData $data): ConversationMessage
    {
        $conversation = $this->resolveForUser($user, $conversationId);

        $message = $this->chatRepository->createMessage($conversation, $user->id, [
            'type' => $data->type->value,
            'body' => $data->body,
            'media_url' => null,
            'client_id' => $data->clientId,
        ]);

        if ($message->wasRecentlyCreated) {
            event(new MessageSent($conversation->id, $message, $conversation->type));
        }

        return $message;
    }

    public function markRead(User $user, int $conversationId, int $upToMessageId): array
    {
        $member = $this->chatRepository->memberFor($user->id, $conversationId);
        if ($member === null) {
            throw new ConversationNotFoundException('This conversation is not available.');
        }

        $member = $this->chatRepository->updateReadWatermark($member, $upToMessageId);

        /** @var Conversation $conversation */
        $conversation = $member->conversation()->firstOrFail();

        return [
            'read_up_to' => (int) $member->last_read_message_id,
            'unread' => $this->chatRepository->unreadFor($user->id, $conversation),
        ];
    }

    public function unreadTotal(User $user): int
    {
        return $this->chatRepository->unreadTotal($user->id);
    }

    public function setMuted(User $user, int $conversationId, bool $muted): Conversation
    {
        $conversation = $this->resolveForUser($user, $conversationId);

        $conversation->members()
            ->where('user_id', $user->id)
            ->update(['muted' => $muted]);

        $conversation->load(['members.user', 'lastMessage.user']);

        return $conversation;
    }

    public function addMember(User $user, int $conversationId, int $newUserId): Conversation
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        $this->assertModerator($conversation, $user->id);
        $this->assertGroup($conversation);

        $this->chatRepository->addMember($conversation, $newUserId, ['role' => MemberRole::Member]);
        $conversation->load(['members.user', 'lastMessage.user']);

        return $conversation;
    }

    public function removeMember(User $user, int $conversationId, int $memberUserId): Conversation
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        $this->assertGroup($conversation);

        $actor = $this->requireMember($conversation, $user->id);
        $target = $this->requireMember($conversation, $memberUserId);

        if (($actor->role !== MemberRole::Owner && $actor->role !== MemberRole::Admin)
            || ($target->role === MemberRole::Owner && $actor->role !== MemberRole::Owner)) {
            throw new ConversationPermissionException('You cannot remove members from this conversation.');
        }

        $this->chatRepository->removeMember($conversation, $memberUserId);
        $conversation->load(['members.user', 'lastMessage.user']);

        return $conversation;
    }

    public function typing(User $user, int $conversationId): void
    {
        $conversation = $this->resolveForUser($user, $conversationId);

        if (Cache::add("typing:{$conversation->type->value}:{$conversation->id}:{$user->id}", true, 2)) {
            event(new UserTyping($conversation->id, $user->id, $conversation->type));
        }
    }

    public function currentInvite(User $user, int $conversationId): ?GroupInvite
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        $this->assertGroup($conversation);
        $this->assertModerator($conversation, $user->id);

        return $this->chatRepository->validInviteFor($conversation->id)?->load('creator');
    }

    public function inviteFor(User $user, int $conversationId): GroupInvite
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        $this->assertGroup($conversation);
        $this->assertModerator($conversation, $user->id);

        $invite = $this->chatRepository->validInviteFor($conversation->id);
        if ($invite === null) {
            $invite = $this->chatRepository->createInvite($conversation, $user->id);
        }

        return $invite->load('creator');
    }

    public function revokeInvite(User $user, int $conversationId): void
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        $this->assertGroup($conversation);
        $this->assertModerator($conversation, $user->id);

        $invite = $this->chatRepository->validInviteFor($conversation->id);
        if ($invite !== null) {
            $this->chatRepository->revokeInvite($invite);
        }
    }

    public function joinViaInvite(User $user, string $code): Conversation
    {
        $invite = $this->chatRepository->inviteByCode(strtoupper($code));
        if ($invite === null) {
            throw new ConversationNotFoundException('This invite link is no longer valid.');
        }

        $conversation = $this->chatRepository->conversationForUser($user->id, $invite->conversation_id);
        if ($conversation !== null) {
            return $conversation;
        }

        $conversation = Conversation::query()->findOrFail($invite->conversation_id);
        $member = $this->chatRepository->addMember($conversation, $user->id, ['role' => MemberRole::Member]);
        $conversation->load(['members.user', 'lastMessage.user']);

        if ($member->wasRecentlyCreated) {
            event(new MemberJoined($conversation, $user));
        }

        return $conversation;
    }

    public function toggleMessageReaction(User $user, int $conversationId, int $messageId, MessageReactionType $reaction): array
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        /** @var ConversationMessage $message */
        $message = $this->requireMessage($conversation, $messageId);

        $existing = $this->chatRepository->reactionFor($message, $user->id);
        if ($existing !== null && $existing->reaction === $reaction) {
            $this->chatRepository->deleteReaction($existing);
        } else {
            $this->chatRepository->setReaction($message, $user->id, $reaction);
        }

        return $this->reactionOutcome($conversation, $message, $user->id);
    }

    public function removeMessageReaction(User $user, int $conversationId, int $messageId): array
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        /** @var ConversationMessage $message */
        $message = $this->requireMessage($conversation, $messageId);

        $existing = $this->chatRepository->reactionFor($message, $user->id);
        if ($existing !== null) {
            $this->chatRepository->deleteReaction($existing);
        }

        $outcome = $this->reactionOutcome($conversation, $message, $user->id);

        return [
            'message_id' => $outcome['message_id'],
            'reaction' => null,
            'reactions' => $outcome['reactions'],
        ];
    }

    public function deleteMessage(User $user, int $conversationId, int $messageId): void
    {
        $conversation = $this->resolveForUser($user, $conversationId);
        /** @var ConversationMessage $message */
        $message = $this->requireMessage($conversation, $messageId);

        $member = $this->requireMember($conversation, $user->id);
        $ownsMessage = $message->user_id === $user->id;
        $moderator = $conversation->type === ConversationType::Group
            && ($member->role === MemberRole::Owner || $member->role === MemberRole::Admin);

        if (! $ownsMessage && ! $moderator) {
            throw new ConversationPermissionException('You cannot delete this message.');
        }

        $this->chatRepository->deleteMessage($message);
        $conversation->touch();

        event(new MessageDeleted($conversation->id, $message->id, $conversation->type, $user->id));
    }

    /**
     * @return array{message_id: int, reaction: ?string, reactions: array<string, int>}
     */
    private function reactionOutcome(Conversation $conversation, ConversationMessage $message, int $userId): array
    {
        $totals = $this->chatRepository->reactionCounts($message);
        $reaction = $this->chatRepository->reactionFor($message, $userId)?->reaction;

        event(new MessageReactionChanged(
            $conversation->id,
            $message->id,
            $conversation->type,
            $totals,
            $reaction?->value,
            $userId,
        ));

        return [
            'message_id' => $message->id,
            'reaction' => $reaction?->value,
            'reactions' => $totals,
        ];
    }

    private function requireMessage(Conversation $conversation, int $messageId): ConversationMessage
    {
        $message = $this->chatRepository->messageFor($conversation, $messageId);
        if ($message === null) {
            throw new ConversationNotFoundException('This message is not available.');
        }

        return $message;
    }

    private function resolveForUser(User $user, int $conversationId): Conversation
    {
        $conversation = $this->chatRepository->conversationForUser($user->id, $conversationId);
        if ($conversation === null) {
            throw new ConversationNotFoundException('This conversation is not available.');
        }

        return $conversation;
    }

    private function assertModerator(Conversation $conversation, int $userId): void
    {
        $member = $this->requireMember($conversation, $userId);
        if ($member->role !== MemberRole::Owner && $member->role !== MemberRole::Admin) {
            throw new ConversationPermissionException('Only group moderators can change members.');
        }
    }

    private function assertGroup(Conversation $conversation): void
    {
        if ($conversation->type !== ConversationType::Group) {
            throw new ConversationPermissionException('This action is only available for group conversations.');
        }
    }

    private function requireMember(Conversation $conversation, int $userId): ConversationMember
    {
        $member = $conversation->relationLoaded('members')
            ? $conversation->members->firstWhere('user_id', $userId)
            : $this->chatRepository->memberFor($userId, $conversation->id);

        if ($member === null) {
            throw new ConversationNotFoundException('This conversation is not available.');
        }

        return $member;
    }
}
