<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Enums;

/**
 * What a report is filed against.
 *
 * Kept as an enum rather than a free string because the same value is written
 * to the database, read back by the moderation queue, and sent by clients. A
 * typo in any one of those three places produces a report nobody can ever
 * triage, and it fails silently rather than loudly.
 */
enum ReportTargetType: string
{
    case User = 'user';
    case Post = 'post';
    case Comment = 'comment';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Account',
            self::Post => 'Post',
            self::Comment => 'Comment',
            self::Message => 'Message',
        };
    }
}
