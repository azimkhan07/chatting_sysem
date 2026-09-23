<?php

declare(strict_types=1);

namespace App\Domain\Social\Contracts;

use App\Domain\Social\Enums\NotificationType;
use App\Domain\Social\Models\UserNotification;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface NotificationRepository
{
    public function create(int $recipientId, int $actorId, NotificationType $type, array $data = []): UserNotification;

    public function listFor(int $userId, int $limit, ?string $cursor): CursorPaginator;

    public function markAllRead(int $userId): int;

    public function unreadCount(int $userId): int;
}
