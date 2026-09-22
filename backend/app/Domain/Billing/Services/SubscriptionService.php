<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Exceptions\SubscriptionNotAllowedException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly NotificationRepository $notifications,
    ) {}

    /**
     * @return array<int, array{key: string, name: string, price_paisa: int, price_month: string, perks: list<string>}>
     */
    public function tiers(): array
    {
        return [
            [
                'key' => Plan::Basic->value,
                'name' => Plan::Basic->label(),
                'price_paisa' => Plan::Basic->amountPaisa(),
                'price_month' => Plan::Basic->pricePerMonth(),
                'perks' => ['Blue badge', 'Priority support queue', 'Basic analytics'],
            ],
            [
                'key' => Plan::Pro->value,
                'name' => Plan::Pro->label(),
                'price_paisa' => Plan::Pro->amountPaisa(),
                'price_month' => Plan::Pro->pricePerMonth(),
                'perks' => [
                    'Blue badge',
                    'Extra verification checks (KYC)',
                    'Advanced analytics',
                    'Early features',
                ],
            ],
        ];
    }

    /**
     * Starts a verification request for the plan, or returns the in-flight one.
     * Idempotent: an already-active subscription for the same plan is returned as-is.
     */
    public function verify(int $userId, Plan $plan): Subscription
    {
        $active = $this->subscriptions->activeFor($userId);

        if ($active !== null) {
            if ($active->plan === $plan) {
                return $active;
            }

            throw new SubscriptionNotAllowedException(
                'You already have an '.$active->plan->label().' subscription active. Cancel it first to switch plans.'
            );
        }

        return $this->subscriptions->findPendingFor($userId, $plan)
            ?? $this->subscriptions->create($userId, $plan);
    }

    /**
     * @return array{subscription: Subscription, gateway: string, client_token: Stringable}
     */
    public function checkout(int $userId, Plan $plan): array
    {
        $subscription = $this->verify($userId, $plan);

        return [
            'subscription' => $subscription,
            'gateway' => 'mock',
            'client_token' => Str::of('mock_ck_')->append(Str::random(32)),
        ];
    }

    /**
     * Mock payment capture in v1. Records the gateway token and keeps the
     * subscription pending until an admin reviews and approves/rejects it.
     */
    public function recordPayment(User $user, Subscription $subscription, string $gateway, string $token): Subscription
    {
        $this->assertOwns($user, $subscription);

        if (! $subscription->isPending()) {
            throw new SubscriptionNotAllowedException('This subscription cannot accept payment right now.');
        }

        return $this->subscriptions->recordPayment($subscription, $token);
    }

    /**
     * User-facing status snapshot; throws if the subscription is not theirs.
     */
    public function stateFor(User $user, Subscription $subscription): Subscription
    {
        $this->assertOwns($user, $subscription);

        return $subscription;
    }

    /**
     * Cancels auto-renew. The badge stays until the paid period runs out.
     */
    public function cancel(User $user, Subscription $subscription): Subscription
    {
        $this->assertOwns($user, $subscription);

        if ($subscription->isActive() || $subscription->isPending()) {
            return $this->subscriptions->cancel($subscription);
        }

        throw new SubscriptionNotAllowedException('This subscription cannot be cancelled.');
    }

    public function activeFor(int $userId): ?Subscription
    {
        return $this->subscriptions->activeFor($userId);
    }

    /**
     * Advance the clock on every due subscription: auto-renew active ones and
     * expire the rest (flipping is_verified=false). Called by the scheduler.
     *
     * @return array{renewed: int, expired: int}
     */
    public function processDue(): array
    {
        return $this->subscriptions->processDue();
    }

    public function approve(int $adminId, Subscription $subscription): Subscription
    {
        $approved = $this->subscriptions->approve($subscription, $adminId);

        $this->notifications->create((int) $subscription->user_id, $adminId, NotificationType::Verified, [
            'subscription_id' => $subscription->id,
            'plan' => $subscription->plan->value,
        ]);

        return $approved;
    }

    public function reject(int $adminId, Subscription $subscription): Subscription
    {
        $rejected = $this->subscriptions->reject($subscription);

        $this->notifications->create((int) $subscription->user_id, $adminId, NotificationType::Verified, [
            'approved' => false,
            'subscription_id' => $subscription->id,
        ]);

        return $rejected;
    }

    public function isVerified(int $userId): bool
    {
        return $this->activeFor($userId) !== null;
    }

    private function assertOwns(User $user, Subscription $subscription): void
    {
        if ((int) $subscription->user_id !== (int) $user->id) {
            throw new SubscriptionNotAllowedException('You do not own this subscription.');
        }
    }
}
