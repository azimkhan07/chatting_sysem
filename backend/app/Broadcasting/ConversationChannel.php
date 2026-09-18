<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;

final class ConversationChannel
{
    public function __construct(private readonly ChatRepository $chatRepository) {}

    public function join(User $user, string $conversation): bool
    {
        return $this->chatRepository->conversationForUser($user->id, (int) $conversation) !== null;
    }
}
