<?php

use App\Console\Commands\ExpireThreadsCommand;
use App\Console\Commands\ProcessSubscriptionsCommand;
use App\Console\Commands\RefreshTrendingRankingCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessSubscriptionsCommand::class)->everyMinute();
Schedule::command(ExpireThreadsCommand::class)->everyMinute();
Schedule::command(RefreshTrendingRankingCommand::class)->everyFiveMinutes();
