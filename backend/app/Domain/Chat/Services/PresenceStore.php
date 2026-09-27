<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed presence for chat: "who is online right now".
 *
 * - `presence:online`  sorted set  member = userId, score = last activity (unix seconds)
 *
 * A sorted set (not a plain set) is what makes presence self-healing: a client
 * that dies without saying goodbye simply ages out of the window instead of
 * leaving a ghost behind, so we never have to trust a disconnect event.
 *
 * Every method degrades gracefully when Redis is unavailable - readers get
 * `null` and fall back to `users.last_seen_at`, writers are best effort. The
 * first failure latches, so a Redis outage costs one failed round trip per
 * request instead of one per lookup.
 */
final class PresenceStore
{
    public const KEY = 'presence:online';

    /**
     * A user stays "online" for this long after their last recorded activity.
     * The client heartbeats every 30s, so 2 missed beats means genuinely away.
     */
    public const WINDOW_SECONDS = 90;

    /**
     * How long `users.last_seen_at` alone is trusted to mean "online". Shorter
     * than the window on purpose: a `last_seen_at` written by the sweeper is
     * already older than this, so Redis and the DB fallback never disagree.
     */
    public const FALLBACK_SECONDS = 60;

    private ?array $window = null;

    private bool $windowLoaded = false;

    private bool $unavailable = false;

    /**
     * Record activity for a user.
     *
     * @return bool true when this call flipped the user from offline to online
     */
    public function touch(int $userId): bool
    {
        if ($this->unavailable) {
            return false;
        }

        $now = time();
        $member = (string) $userId;

        try {
            $redis = Redis::connection();
            $score = $redis->zscore(self::KEY, $member);

            $wasOffline = $score === null
                || $score === false
                || ($now - (int) $score) > self::WINDOW_SECONDS;

            $redis->zadd(self::KEY, (float) $now, $member);
            $this->forgetWindow();

            return $wasOffline;
        } catch (\Throwable) {
            $this->giveUp();

            return false;
        }
    }

    /**
     * Drop a user from the online set (explicit logout / go-offline).
     */
    public function forget(int $userId): void
    {
        if ($this->unavailable) {
            return;
        }

        try {
            Redis::connection()->zrem(self::KEY, (string) $userId);
            $this->forgetWindow();
        } catch (\Throwable) {
            // Best effort - the window would age them out anyway.
            $this->unavailable = true;
        }
    }

    /**
     * The online user ids, or null when Redis is unavailable. Memoised for the
     * lifetime of the instance so a 100+ conversation inbox costs one call.
     *
     * @return list<int>|null
     */
    public function onlineIds(): ?array
    {
        if ($this->windowLoaded) {
            return $this->window;
        }

        if ($this->unavailable) {
            return null;
        }

        try {
            /** @var list<string> $raw */
            $raw = Redis::connection()->zrangebyscore(
                self::KEY,
                (string) (time() - self::WINDOW_SECONDS),
                (string) time(),
            );
        } catch (\Throwable) {
            $this->giveUp();

            return null;
        }

        $this->window = array_map('intval', $raw);
        $this->windowLoaded = true;

        return $this->window;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, bool>|null userId => online, or null when Redis is down
     */
    public function onlineMap(array $userIds): ?array
    {
        $online = $this->onlineIds();
        if ($online === null) {
            return null;
        }

        $onlineLookup = array_fill_keys($online, true);

        $map = [];
        foreach ($userIds as $userId) {
            $map[$userId] = array_key_exists($userId, $onlineLookup);
        }

        return $map;
    }

    /**
     * Entries that just fell out of the window, removed from the set so a
     * single pass can broadcast the transitions exactly once. The score is the
     * user's true last activity, which becomes their `last_seen_at`.
     *
     * @return array<int, int> userId => last activity (unix seconds)
     */
    public function expireStale(?int $limit = null): array
    {
        if ($this->unavailable) {
            return [];
        }

        $now = time();

        try {
            /** @var array<string, string> $stale */
            $stale = Redis::connection()->zrangebyscore(
                self::KEY,
                '0',
                (string) ($now - self::WINDOW_SECONDS - 1),
                ['withscores' => true, 'limit' => [0, $limit ?? 500]],
            );
        } catch (\Throwable) {
            $this->giveUp();

            return [];
        }

        if ($stale === []) {
            return [];
        }

        try {
            Redis::connection()->zrem(self::KEY, ...array_keys($stale));
        } catch (\Throwable) {
            // Best effort.
        }

        $expired = [];
        foreach ($stale as $userId => $score) {
            $expired[(int) $userId] = (int) $score;
        }

        $this->forgetWindow();

        return $expired;
    }

    private function forgetWindow(): void
    {
        $this->window = null;
        $this->windowLoaded = false;
    }

    /**
     * Latch the outage for the rest of the request: a degraded Redis must not
     * cost one failed round trip per lookup.
     */
    private function giveUp(): void
    {
        $this->unavailable = true;
        $this->forgetWindow();
    }
}
