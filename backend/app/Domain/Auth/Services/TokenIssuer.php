<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class TokenIssuer
{
    private const TOKEN_NAME = 'web';

    private const TTL_DAYS = 30;

    public function issueFor(User $user, ?string $device = null): string
    {
        return $user->createToken(
            $this->label($device),
            ['*'],
            CarbonImmutable::now()->addDays(self::TTL_DAYS),
        )->plainTextToken;
    }

    public function ttlSeconds(): int
    {
        return self::TTL_DAYS * 86400;
    }

    /**
     * A sessions list full of rows called "web" is useless, so the agent string
     * is folded into the label. It is display text only - nothing about it is
     * ever trusted.
     */
    private function label(?string $device): string
    {
        $device = trim((string) $device);

        return $device === '' ? self::TOKEN_NAME : self::TOKEN_NAME.' · '.Str::limit($device, 120);
    }
}
