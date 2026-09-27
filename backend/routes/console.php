<?php

declare(strict_types=1);

use App\Console\Commands\ExpireThreadsCommand;
use App\Console\Commands\ProcessSubscriptionsCommand;
use App\Console\Commands\RefreshTrendingRankingCommand;
use App\Console\Commands\SweepPresenceCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessSubscriptionsCommand::class)->everyMinute();
Schedule::command(ExpireThreadsCommand::class)->everyMinute();
Schedule::command(RefreshTrendingRankingCommand::class)->everyFiveMinutes();

// Presence ages out on a 90s window, so sweeping every 30s keeps the
// offline broadcast prompt without hammering Redis.
Schedule::command(SweepPresenceCommand::class)->everyThirtySeconds()->withoutOverlapping();
