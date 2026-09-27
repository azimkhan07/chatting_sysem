<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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
    }
}
