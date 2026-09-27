<?php

declare(strict_types=1);

namespace App\Domain\Auth\Enums;

/**
 * What kind of account this is. Only the owner controls it, and it changes the
 * action row a visitor gets on the profile: a business or professional account
 * offers a Contact action (WhatsApp / email) instead of only Message.
 */
enum AccountType: string
{
    case Personal = 'personal';
    case Professional = 'professional';
    case Business = 'business';

    /** Personal accounts are message + follow only. */
    public function offersContact(): bool
    {
        return $this !== self::Personal;
    }

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Professional => 'Professional',
            self::Business => 'Business',
        };
    }
}
