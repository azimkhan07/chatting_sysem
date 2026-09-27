<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\ChatFeature;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Exceptions\ConversationPermissionException;
use App\Domain\Chat\Exceptions\FeatureLockedException;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMessage;
use Illuminate\Support\Collection;

/**
 * Pinned messages — the newest premium chat capability.
 *
 * A pin is shared: everyone in the conversation sees it, so unlike a nickname
 * or a wallpaper it is *not* per-member. Only members can pin, only their own
 * message or a group moderator for anyone, and the whole surface is
 * subscription-gated through {@see ChatEntitlements}.
 */
final class MessagePinService
{
    public function __construct(private readonly ChatEntitlements $entitlements) {}

    public function isUnlockedFor(User $user): bool
    {
        return $this->entitlements->allows($user, ChatFeature::PinnedMessages);
    }

    /**
     * @throws FeatureLockedException
     * @throws ConversationPermissionException
     */
    public function pin(User $user, Conversation $conversation, ConversationMessage $message): void
    {
        $this->entitlements->authorize($user, ChatFeature::PinnedMessages);
        $this->authorizePin($user, $conversation, $message);

        $message->forceFill(['pinned_at' => now(), 'pinned_by' => $user->id])->save();
    }

    /**
     * @throws FeatureLockedException
     * @throws ConversationPermissionException
     */
    public function unpin(User $user, Conversation $conversation, ConversationMessage $message): void
    {
        $this->entitlements->authorize($user, ChatFeature::PinnedMessages);
        $this->authorizePin($user, $conversation, $message);

        $message->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();
    }

    /**
     * The pinned bar: every live pin in the conversation, newest pin first.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function forConversation(Conversation $conversation): Collection
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotNull('pinned_at')
            ->orderByDesc('pinned_at')
            ->get();
    }

    public function isPinned(ConversationMessage $message): bool
    {
        return $message->pinned_at !== null;
    }

    /**
     * A member may pin their own message; in a group a moderator may pin any.
     *
     * @throws ConversationPermissionException
     */
    private function authorizePin(User $user, Conversation $conversation, ConversationMessage $message): void
    {
        $ownsMessage = (int) $message->user_id === (int) $user->id;

        if ($ownsMessage) {
            return;
        }

        $isModerator = $conversation->type === ConversationType::Group
            && $conversation->members()
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();

        if (! $isModerator) {
            throw new ConversationPermissionException(
                'You can only pin your own messages.'
            );
        }
    }
}
