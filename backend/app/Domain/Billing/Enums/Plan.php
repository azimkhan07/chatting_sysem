<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use InvalidArgumentException;

enum Plan: string
{
    case Basic = 'amtech_basic';
    case Pro = 'amtech_pro';

    public const RENEWAL_DAYS = 30;

    public function amountPaisa(): int
    {
        return match ($this) {
            self::Basic => 100,
            self::Pro => 500,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Amtech Basic',
            self::Pro => 'Amtech Pro',
        };
    }

    public function pricePerMonth(): string
    {
        return '₹'.($this->amountPaisa() / 100);
    }

    public static function fromName(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidArgumentException("Unknown plan: {$value}");
    }
}
