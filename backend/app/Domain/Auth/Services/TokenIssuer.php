<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\User;
use Carbon\CarbonImmutable;

final class TokenIssuer
{
    private const TOKEN_NAME = 'web';

    private const TTL_DAYS = 30;

    public function issueFor(User $user): string
    {
        return $user->createToken(
            self::TOKEN_NAME,
            ['*'],
            CarbonImmutable::now()->addDays(self::TTL_DAYS),
        )->plainTextToken;
    }

    public function ttlSeconds(): int
    {
        return self::TTL_DAYS * 86400;
    }
}
