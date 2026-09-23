<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Threads\Services\ThreadService;
use Illuminate\Console\Command;

final class ExpireThreadsCommand extends Command
{
    protected $signature = 'threads:expire';

    protected $description = 'Expire active group threads and generate their recaps';

    public function handle(ThreadService $threadService): int
    {
        $expired = $threadService->expireDue();

        $this->info("Ended {$expired} thread(s).");

        return self::SUCCESS;
    }
}
