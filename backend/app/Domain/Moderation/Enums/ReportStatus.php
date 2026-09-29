<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Enums;

/**
 * Where a report is in staff review.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case Actioned = 'actioned';
    case Dismissed = 'dismissed';
}
