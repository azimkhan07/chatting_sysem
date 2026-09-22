<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutUserAction
{
    public function handle(User $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }
}
