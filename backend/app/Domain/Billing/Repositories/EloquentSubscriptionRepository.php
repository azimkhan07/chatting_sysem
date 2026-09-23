<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function create(int $userId, Plan $plan): Subscription
    {
        return Subscription::query()->create([
            'user_id' => $userId,
            'plan' => $plan,
            'amount_paisa' => $plan->amountPaisa(),
            'status' => SubscriptionStatus::Pending,
            'auto_renew' => true,
        ]);
    }

    public function find(int $id): ?Subscription
    {
        return Subscription::query()->find($id);
    }

    public function findPendingFor(int $userId, Plan $plan): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $userId)
            ->where('plan', $plan->value)
            ->whereIn('status', [
                SubscriptionStatus::Pending->value,
                SubscriptionStatus::Active->value,
            ])
            ->latest('id')
            ->first();
    }

    public function activeFor(int $userId): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $userId)
            ->where('status', SubscriptionStatus::Active->value)
            ->latest('id')
            ->first();
    }

    public function recordPayment(Subscription $subscription, string $token): Subscription
    {
        $subscription->update([
            'payment_token' => $token,
            'status' => SubscriptionStatus::Pending,
        ]);

        return $subscription->fresh();
    }

    public function approve(Subscription $subscription, int $adminId): Subscription
    {
        $now = now();

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'verified_at' => $now,
            'approved_by' => $adminId,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addDays(Plan::RENEWAL_DAYS),
        ]);

        $subscription->user()->update(['is_verified' => true]);

        return $subscription->fresh();
    }

    public function reject(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Refunded]);

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'auto_renew' => false,
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $stillVerified = Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('status', SubscriptionStatus::Active->value)
            ->exists();

        if (! $stillVerified) {
            $subscription->user()->update(['is_verified' => false]);
        }

        return $subscription->fresh();
    }

    public function processDue(): array
    {
        $due = Subscription::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Cancelled->value,
            ])
            ->get();

        $renewed = 0;
        $expired = 0;

        foreach ($due as $subscription) {
            if ($subscription->isActive() && $subscription->auto_renew) {
                $this->renew($subscription);
                $renewed++;
            } else {
                $this->markExpired($subscription);
                $expired++;
            }
        }

        return ['renewed' => $renewed, 'expired' => $expired];
    }

    public function markExpired(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Expired]);

        $stillVerified = Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('status', SubscriptionStatus::Active->value)
            ->exists();

        if (! $stillVerified) {
            $subscription->user()->update(['is_verified' => false]);
        }

        return $subscription->fresh();
    }

    public function renew(Subscription $subscription): Subscription
    {
        $subscription->update([
            'starts_at' => now(),
            'expires_at' => now()->addDays(Plan::RENEWAL_DAYS),
        ]);

        return $subscription->fresh();
    }

    /**
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function all(int $perPage, ?SubscriptionStatus $status): LengthAwarePaginator
    {
        return Subscription::query()
            ->with('user')
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
