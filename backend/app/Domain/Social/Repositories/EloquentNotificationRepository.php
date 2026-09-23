<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use App\Domain\Social\Models\UserNotification;
use App\Events\NotificationCreated;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function create(int $recipientId, int $actorId, NotificationType $type, array $data = []): UserNotification
    {
        $notification = UserNotification::query()->create([
            'user_id' => $recipientId,
            'actor_id' => $actorId,
            'type' => $type->value,
            'data' => $data,
        ]);

        NotificationCreated::dispatch($recipientId, $notification);

        return $notification;
    }

    public function listFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->with('actor')
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function markAllRead(int $userId): int
    {
        return (int) UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
