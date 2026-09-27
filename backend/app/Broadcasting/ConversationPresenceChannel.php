<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;

/**
 * Presence variant of the conversation channel (`presence-dm.{id}` /
 * `presence-group.{id}`). Membership is still enforced server-side, and the
 * returned array becomes the joining member's entry in the channel member list
 * so the client can render avatars for whoever is actually here.
 */
final class ConversationPresenceChannel
{
    public function __construct(private readonly ChatRepository $chatRepository) {}

    /**
     * @return array{id: int, username: string, display_name: string}|bool
     */
    public function join(User $user, string $conversation): array|bool
    {
        if ($this->chatRepository->conversationForUser((int) $user->id, (int) $conversation) === null) {
            return false;
        }

        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'display_name' => (string) $user->display_name,
        ];
    }
}
