<?php

declare(strict_types=1);

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Models\User;

final readonly class AuthUserResult
{
    public const TOKEN_TYPE = 'Bearer';

    public function __construct(
        public User $user,
        public string $accessToken,
        public int $expiresInSeconds,
    ) {}
}
