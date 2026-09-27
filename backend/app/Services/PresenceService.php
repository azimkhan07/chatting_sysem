<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Contracts\PresenceService as PresenceServiceContract;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Services\PresenceStore;
use App\Events\UserPresenceChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Chat presence: who is online, and telling the rest of the room when that
 * flips.
 *
 * Redis is the source of truth (see PresenceStore); `users.last_seen_at` is a
 * durable fallback for profile views and for the window right after a deploy or
 * a Redis blip, so a user is never shown as offline while they are sitting in
 * the thread.
 */
final class PresenceService implements PresenceServiceContract
{
    /**
     * Ordinary API activity is throttled to one presence write per 30s; the
     * explicit client heartbeat is not, since an idle tab is the one case where
     * no other request is coming.
     */
    private const TOUCH_THROTTLE_SECONDS = 30;

    /**
     * `last_seen_at` is durable, so it is written far less often than presence.
     */
    private const LAST_SEEN_WRITE_SECONDS = 300;

    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly PresenceStore $store,
    ) {}

    public function touch(User $user): void
    {
        if (! Cache::add('presence:touch:'.$user->id, true, self::TOUCH_THROTTLE_SECONDS)) {
            return;
        }

        $this->record($user);
    }

    public function heartbeat(User $user): array
    {
        $this->record($user);

        return [
            'is_online' => true,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
    }

    public function signOut(User $user): void
    {
        $this->store->forget((int) $user->id);
        $this->broadcast([(int) $user->id => $user->last_seen_at], false);
    }

    public function isOnline(User $user): bool
    {
        return $this->onlineMap([(int) $user->id => $user])[(int) $user->id] ?? false;
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, bool>
     */
    public function onlineMap(array $users): array
    {
        $userIds = array_map(static fn (User $user): int => (int) $user->id, array_values($users));
        $cached = $this->store->onlineMap($userIds);

        $map = [];
        foreach ($users as $userId => $user) {
            $id = (int) $userId;

            if ($cached !== null && ($cached[$id] ?? false)) {
                $map[$id] = true;

                continue;
            }

            $map[$id] = $this->recentlySeen($user);
        }

        return $map;
    }

    public function sweep(?int $limit = null): int
    {
        $expired = $this->store->expireStale($limit);
        if ($expired === []) {
            return 0;
        }

        $lastSeen = [];
        foreach ($expired as $userId => $timestamp) {
            $lastSeen[$userId] = Carbon::createFromTimestamp($timestamp);
        }

        // Pin the durable marker to when we actually last saw them, so the DB
        // fallback and Redis agree instead of flapping for a minute.
        foreach ($lastSeen as $userId => $seenAt) {
            User::query()->whereKey($userId)->update(['last_seen_at' => $seenAt]);
        }

        $this->broadcast($lastSeen, false);

        return count($lastSeen);
    }

    /**
     * Record the activity and fan out the transition if the user just came back.
     */
    private function record(User $user): void
    {
        $becameOnline = $this->store->touch((int) $user->id);

        $this->touchLastSeen($user);

        if ($becameOnline) {
            $this->broadcast([(int) $user->id => $user->last_seen_at], true);
        }
    }

    private function touchLastSeen(User $user): void
    {
        $now = now();
        $previous = $user->last_seen_at;
        $stale = $previous === null
            || $previous->getTimestamp() < $now->getTimestamp() - self::LAST_SEEN_WRITE_SECONDS;

        $user->forceFill(['last_seen_at' => $now]);

        if ($stale) {
            $user->saveQuietly();
        }
    }

    private function recentlySeen(User $user): bool
    {
        $lastSeen = $user->last_seen_at;

        return $lastSeen !== null
            && $lastSeen->getTimestamp() >= time() - PresenceStore::FALLBACK_SECONDS;
    }

    /**
     * @param  array<int, Carbon|null>  $lastSeenByUser  userId => last seen at
     */
    private function broadcast(array $lastSeenByUser, bool $isOnline): void
    {
        if ($lastSeenByUser === []) {
            return;
        }

        foreach ($this->chatRepository->membershipsFor(array_keys($lastSeenByUser)) as $membership) {
            $userId = $membership['user_id'];

            event(new UserPresenceChanged(
                $membership['conversation_id'],
                $userId,
                ConversationType::from($membership['type']),
                $isOnline,
                $lastSeenByUser[$userId]?->toIso8601String(),
            ));
        }
    }
}
