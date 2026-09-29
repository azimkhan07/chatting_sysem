<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

use App\Domain\Moderation\Services\BlockService;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use App\Domain\Social\Models\UserNotification;
use App\Events\NotificationCreated;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function __construct(
        private readonly BlockService $blocks,
    ) {}

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
        return $this->visibleTo($userId)
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
        // Counted through the same filter as the list. A badge that keeps
        // counting a blocked account is a way to be notified about somebody the
        // account has decided to stop hearing from, and the number would never
        // match the list below it.
        return $this->visibleTo($userId)->whereNull('read_at')->count();
    }

    /**
     * The recipient's notifications, minus the ones from accounts they blocked.
     *
     * The rows stay. Deleting them would be a lie about history - unblocking
     * cannot bring back a notification, and it should not be able to. Filtering
     * here is what makes "block" a thing about now rather than a deletion.
     *
     * @return Builder<UserNotification>
     */
    private function visibleTo(int $userId): Builder
    {
        return $this->blocks->hideFromQuery(
            UserNotification::query()->where('user_id', $userId),
            $userId,
            'notifications.actor_id',
        );
    }
}
