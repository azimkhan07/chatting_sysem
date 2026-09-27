<?php

declare(strict_types=1);

namespace App\Domain\Stories\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Throwable;

/**
 * The story tray is identical for every viewer, so it is the one read on the
 * home page that can be shared. It is expensive (join + group + resource
 * resolution) and it is hit on every page open, which is why a cold query made
 * the app feel slow to open.
 *
 * Writes invalidate explicitly, so a viewer never has to wait out the TTL to
 * see their own story. If the cache is unreachable the caller still gets data —
 * this class never turns a cache outage into a 500.
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
    public function remember(callable $fresh): mixed
    {
        if ($this->disabled()) {
            return $fresh();
        }

        try {
            $hit = $this->cache->get(self::KEY);
        } catch (Throwable) {
            return $fresh();
        }

        if (is_array($hit)) {
            return $hit;
        }

        $value = $fresh();

        try {
            $this->cache->put(self::KEY, $value, self::TTL_SECONDS);
        } catch (Throwable) {
            // A cache write failure must never surface to the caller.
        }

        return $value;
    }

    public function forget(): void
    {
        if ($this->disabled()) {
            return;
        }

        try {
            $this->cache->forget(self::KEY);
        } catch (Throwable) {
            // Nothing to do: the next read recomputes.
        }
    }

    private function disabled(): bool
    {
        return $this->config->get('cache.story_tray', true) !== true;
    }
}
