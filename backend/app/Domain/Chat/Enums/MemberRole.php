<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

enum MemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function outranks(self $other): bool
    {
        return $this->rank() < $other->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Owner => 0,
            self::Admin => 1,
            self::Member => 2,
        };
    }
}
