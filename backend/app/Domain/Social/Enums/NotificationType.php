<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum NotificationType: string
{
    case Follow = 'follow';
    case Like = 'like';
    case Comment = 'comment';
    case Verified = 'verified';
}
