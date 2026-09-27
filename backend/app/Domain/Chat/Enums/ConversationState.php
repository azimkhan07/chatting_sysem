<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

/**
 * A conversation is either a real chat or, for DMs between strangers, a
 * pending request that lives in its own inbox section until it is accepted.
 */
enum ConversationState: string
{
    case Active = 'active';
    case Requested = 'requested';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Requested => 'Requested',
        };
    }
}
