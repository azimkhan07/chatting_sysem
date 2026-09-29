<?php

declare(strict_types=1);

namespace App\Domain\Stories\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Throwable;

/**
 * The story tray is the same query for every viewer *except* that it is filtered
 * by that viewer's blocks, so it is cached per viewer rather than once for
 * everyone. A single global key would hand the first person's unfiltered tray to
 * the next person who opened the app, which is the exact leak a block exists to
 * prevent.
 *
 * A guest has no blocks, so the guest entry is the shared one and the
 * unauthenticated path stays a single cached query.
 *
 * It is expensive (join + group + resource resolution) and it is hit on every
 * page open, which is why a cold query made the app feel slow to open.
 *
 * Writes invalidate by bumping a version counter rather than by deleting keys,
 * because there is one key per viewer and this class has no way to enumerate
 * them on every cache driver. Bumping orphans the old keys, which expire on
 * their own TTL, and it is one write instead of a scan. So a viewer never has
 * to wait out the TTL to see their own story.
 *
 * If the cache is unreachable the caller still gets data - this class never
 * turns a cache outage into a 500.
 */
final class StoryTrayCache
{
    private const KEY = 'stories:tray:v1';

    private const TTL_SECONDS = 20;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $fresh
     * @return TValue
     */
    public function remember(?int $viewerId, callable $fresh): mixed
    {
        if ($this->disabled()) {
            return $fresh();
        }

        $key = $this->keyFor($viewerId);

        try {
            $hit = $this->cache->get($key);
        } catch (Throwable) {
            return $fresh();
        }

        if (is_array($hit)) {
            return $hit;
        }

        $value = $fresh();

        try {
            $this->cache->put($key, $value, self::TTL_SECONDS);
        } catch (Throwable) {
            // A cache write failure must never surface to the caller.
        }

        return $value;
    }

    /**
     * Drops every viewer's entry, not just the author's.
     *
     * A new story appearing in the tray of somebody who has not opened the app
     * is still a story that appeared, so invalidating only the author's own key
     * would leave every other tray stale for the rest of the TTL.
     */
    public function forget(): void
    {
        if ($this->disabled()) {
            return;
        }

        try {
            $this->cache->forever(self::KEY.':version', $this->version() + 1);
        } catch (Throwable) {
            // Nothing to do: the next read recomputes.
        }
    }

    /**
     * Read outside the try/catch on purpose: a failure here has to fall back to
     * version 0 rather than throw, or a cache outage would become a 500.
     */
    private function version(): int
    {
        try {
            return (int) ($this->cache->get(self::KEY.':version') ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function keyFor(?int $viewerId): string
    {
        $viewer = $viewerId === null ? 'guest' : 'u'.$viewerId;

        return self::KEY.':'.$this->version().':'.$viewer;
    }

    private function disabled(): bool
    {
        return $this->config->get('cache.story_tray', true) !== true;
    }
}
