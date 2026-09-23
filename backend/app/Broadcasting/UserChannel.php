<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Domain\Auth\Models\User;

final class UserChannel
{
    public function join(User $user, int $id): bool
    {
        return (int) $user->id === $id;
    }
}
