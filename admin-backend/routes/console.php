<?php

declare(strict_types=1);

use Admin\Domain\Admin\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Work
|--------------------------------------------------------------------------
|
| This app schedules one thing. Everything else the platform does every minute
| — expiring threads, processing subscriptions, sweeping presence, refreshing
| trending — belongs to the main app, and duplicating the schedule here would
| mean two processes racing to write the same tables.
|
*/

// Expired console tokens are inert but not gone: they keep `last_used_at` rows
// that make the active-device count lie, and a table that only grows is a table
// nobody reads. Deleting by `expires_at` also leaves a postmortem trail, since a
// revoked session vanishes immediately and cannot be distinguished from never
// having existed.
Schedule::call(static function (): void {
    PersonalAccessToken::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->hourly()->name('console:prune-expired-tokens')->withoutOverlapping();
