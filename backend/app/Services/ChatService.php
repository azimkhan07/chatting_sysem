<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Contracts\ChatService as ChatServiceContract;
use App\Domain\Chat\Data\SendMessageData;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Enums\MemberRole;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Exceptions\ConversationPermissionException;
use App\Domain\Chat\Exceptions\InvalidConversationException;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
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

        return [
            'read_up_to' => (int) $member->last_read_message_id,
            'unread' => $this->chatRepository->unreadFor($user->id, $member->conversation()->firstOrFail()),
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
