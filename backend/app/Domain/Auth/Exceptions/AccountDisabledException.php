<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use RuntimeException;

final class AccountDisabledException extends RuntimeException
{
    public function __construct(public readonly User $user)
    {
        parent::__construct($user->status === UserStatus::Suspended
            ? 'This account has been suspended. Contact support for help.'
            : 'This account is no longer available.');
    }
}
