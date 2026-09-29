<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum NotificationType: string
{
    case Follow = 'follow';
    case Like = 'like';
    case Comment = 'comment';
    /**
     * Named for a post's tagged people.
     *
     * Called `mention` rather than `tag` because the recipient is a person who
     * was named, not a topic that was labelled — the two share a table and a
     * UI but nothing else, and conflating them made the notification unreadable
     * in meaning even though it looked the same.
     */
    case Mention = 'mention';
    case Verified = 'verified';
    case AdminReview = 'admin_review';
}
