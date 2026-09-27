<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Services\SubscriptionService;

/**
 * Chat is the premium surface: a DM with a stranger needs the request inbox,
 * which is subscription gated. Tests that exercise chat mechanics rather than
 * the paywall run as subscribers so they test the feature, not the plan.
 *
 * Tests specifically about the paywall (`ChatEntitlementTest`,
 * `MessageRequestTest`) deliberately do not use this.
 */
trait SubscribesUsers
{
    protected function subscribe(User $user): User
    {
        $subscription = app(SubscriptionService::class)->verify((int) $user->id, Plan::Basic);
        app(SubscriptionRepository::class)->approve(
            $subscription,
            (int) User::factory()->create()->id,
        );

        return $user->refresh();
    }

    /** A brand new account with the premium chat tier approved by an admin. */
    protected function subscriber(): User
    {
        return $this->subscribe(User::factory()->create());
    }
}
