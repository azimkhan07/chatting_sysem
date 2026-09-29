<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        // A single test can act as several users. The auth guard caches the first
        // resolved user for the lifetime of the process, so a second token in the
        // same test would silently authenticate as the first user.
        Auth::forgetGuards();

        // ChatInboxCache keeps raw Redis hashes outside the Laravel cache store,
        // so `Cache::flush()` never sees them. When a local Redis is running
        // (docker compose), unread counters from a previous test would otherwise
        // leak into this one; nuke the whole test Redis so every suite run is
        // independent whether or not Redis is reachable. `flushdb` (not a key
        // scan) is deliberate: Laravel prefixes client keys, so `del()` on a
        // scanned key would double-prefix and delete nothing.
        try {
            Redis::connection()->flushdb();
        } catch (\Throwable) {
            // Redis down: the cache degrades to SQL and there is nothing to clear.
        }
    }
}
