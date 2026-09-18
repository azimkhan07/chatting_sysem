<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Models\User;

final class LogoutUserAction
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
