<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubscriptionRepository
{
    public function create(int $userId, Plan $plan): Subscription;

    public function find(int $id): ?Subscription;

    public function findPendingFor(int $userId, Plan $plan): ?Subscription;

    public function activeFor(int $userId): ?Subscription;

    public function recordPayment(Subscription $subscription, string $token): Subscription;

    public function approve(Subscription $subscription, int $adminId): Subscription;

    public function reject(Subscription $subscription): Subscription;

    public function cancel(Subscription $subscription): Subscription;

    /**
     * Renews (when auto-renew is on) or expires every due subscription.
     *
     * @return array{renewed: int, expired: int}
     */
    public function processDue(): array;

    /**
     * @return LengthAwarePaginator<Subscription>
     */
    public function list(int $perPage, int $page): LengthAwarePaginator;
}