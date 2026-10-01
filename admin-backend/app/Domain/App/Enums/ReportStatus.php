<?php

declare(strict_types=1);

namespace Admin\Domain\App\Enums;

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
