<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chat\Contracts\PresenceService;
use Illuminate\Console\Command;

final class SweepPresenceCommand extends Command
{
    protected $signature = 'chat:presence-sweep {--limit=500 : Max users to expire per run}';

    protected $description = 'Expire users who aged out of the presence window and broadcast their offline transition';

    public function handle(PresenceService $presence): int
    {
        $expired = $presence->sweep((int) $this->option('limit'));

        if ($expired === 0) {
            $this->info('Presence: nobody to expire.');

            return self::SUCCESS;
        }

        $this->info("Presence: {$expired} user(s) marked offline.");

        return self::SUCCESS;
    }
}
