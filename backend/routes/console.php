<?php

use App\Console\Commands\ProcessSubscriptionsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessSubscriptionsCommand::class)->everyMinute();
