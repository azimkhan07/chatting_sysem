<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\PresenceService;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutUserAction
{
    public function __construct(private readonly PresenceService $presence) {}

    public function handle(User $user): void
    {
        // Drop the dot before the token dies - the sweeper would catch it
        // 90s later, but a deliberate logout should be instant.
        $this->presence->signOut($user);

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }
}
