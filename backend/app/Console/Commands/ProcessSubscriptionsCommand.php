<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Contracts\SubscriptionRepository;
use Illuminate\Console\Command;

final class ProcessSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Renew auto-renewing subscriptions and expire the rest, downgrading the blue badge where needed.';

    public function __construct(private readonly SubscriptionRepository $subscriptions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->subscriptions->processDue();

        $this->info(sprintf(
            'Subscriptions processed: %d renewed, %d expired.',
            $result['renewed'],
            $result['expired'],
        ));

        return self::SUCCESS;
    }
}