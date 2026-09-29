<?php

declare(strict_types=1);

namespace App\Domain\Chat\Contracts;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Call;

interface CallService
{
    /**
     * Start a call in a conversation. Returns the caller's join details
     * (token, room, server URL) and persists the call for history.
     *
     * @return array{call: Call, token: string, room: string, server_url: string}
     */
    public function start(User $user, int $conversationId, string $kind): array;

    /**
     * Accept a ringing call and return the responder's join details.
     *
     * @return array{call: Call, token: string, room: string, server_url: string}
     */
    public function answer(User $user, int $conversationId, int $callId): array;

    /**
     * Decline a ringing call.
     */
    public function reject(User $user, int $conversationId, int $callId): void;

    /**
     * End an active call.
     */
    public function end(User $user, int $conversationId, int $callId): void;
}
