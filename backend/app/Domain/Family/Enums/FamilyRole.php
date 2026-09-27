<?php

declare(strict_types=1);

namespace App\Domain\Family\Enums;

enum FamilyRole: string
{
    case Guardian = 'guardian';
    case Adult = 'adult';
    case Teen = 'teen';

    public function label(): string
    {
        return match ($this) {
            self::Guardian => 'Guardian',
            self::Adult => 'Adult',
            self::Teen => 'Teen',
        };
    }

    public function blurb(): string
    {
        return match ($this) {
            self::Guardian => 'Can add and remove members, and approve spending requests.',
            self::Adult => 'Can add and remove members. Spending approvals go to a guardian.',
            self::Teen => 'Needs a guardian to approve paid plans.',
        };
    }

    /**
     * Guardians and adults manage the roster; a teen cannot.
     *
     * Spend approval stays guardian-only - see `FamilyService::canApproveSpending()`.
     */
    public function canManageMembers(): bool
    {
        return $this === self::Guardian || $this === self::Adult;
    }

    public function isGuardian(): bool
    {
        return $this === self::Guardian;
    }

    /**
     * Sort weight, derived from the declaration order above.
     *
     * Guardians first, then adults, then teens - the order a household reads
     * top-down. Deriving it from `cases()` means a new role is appended rather
     * than silently landing in the middle of the roster.
     */
    public function rank(): int
    {
        $index = array_search($this, self::cases(), true);

        return $index === false ? count(self::cases()) : $index;
    }
}
