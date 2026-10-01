<?php

declare(strict_types=1);

namespace Admin\Domain\App\Services;

use Admin\Domain\App\Enums\Plan;
use Admin\Domain\App\Enums\SubscriptionStatus;
use Admin\Domain\App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The console's half of the subscription lifecycle.
 *
 * Only the four operations staff actually perform live here: list, stats,
 * approve, reject. Everything a user does (buy, switch plan, cancel, renew on
 * the scheduler) stays in backend/, which owns the schema and the money
 * lifecycle. Duplicating that logic here would mean two places to fix when a
 * renewal rule changes, and only one of them would get the fix.
 */
final class SubscriptionReviewService
{
    public function __construct(
        private readonly AppNotificationWriter $notifications,
    ) {}

    public function find(int $id): ?Subscription
    {
        return Subscription::query()->find($id);
    }

    /**
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function all(int $perPage, ?SubscriptionStatus $status): LengthAwarePaginator
    {
        return Subscription::query()
            ->with(['user', 'switchFrom'])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{counts: array<string, int>, revenue_paisa: int}
     */
    public function stats(): array
    {
        $counts = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $revenue = Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Expired->value,
            ])
            ->sum('amount_paisa');

        return [
            'counts' => $counts,
            'revenue_paisa' => (int) $revenue,
        ];
    }

    /**
     * Approves a paid verification: the blue badge goes live, the term starts,
     * and any subscription this one replaces is expired so the badge is never
     * held by two rows at once.
     *
     * `$staffName` is the console account's display name, recorded on the
     * notification so the app can attribute the decision to the desk rather
     * than to whichever user happens to share the staff id.
     */
    public function approve(int $staffId, Subscription $subscription, ?string $staffName = null): Subscription
    {
        $now = now();

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'verified_at' => $now,
            'approved_by_staff' => $staffId,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addDays(Plan::RENEWAL_DAYS),
        ]);

        $this->setVerified($subscription, true);

        if ($subscription->switch_from_subscription_id !== null) {
            Subscription::query()
                ->whereKey($subscription->switch_from_subscription_id)
                ->where('status', SubscriptionStatus::Active->value)
                ->update([
                    'status' => SubscriptionStatus::Expired->value,
                    'auto_renew' => false,
                ]);
        }

        $this->notifications->verifiedDecision(
            recipientId: (int) $subscription->user_id,
            staffId: $staffId,
            approved: true,
            subscriptionId: (int) $subscription->id,
            plan: $subscription->plan instanceof Plan ? $subscription->plan->value : null,
            staffName: $staffName,
        );

        return $subscription->fresh();
    }

    /**
     * Rejects a paid verification. The row moves to `refunded` rather than
     * being deleted: it is the audit trail for a payment that was taken.
     *
     * The deciding staff id is taken as an argument because the row has nowhere
     * to record it: `approved_by_staff` names the *approver* of a successful
     * verification, and reading it on a rejection would attribute the refusal to
     * whoever approved an earlier purchase.
     */
    public function reject(int $staffId, Subscription $subscription, ?string $staffName = null): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Refunded]);

        $this->notifications->verifiedDecision(
            recipientId: (int) $subscription->user_id,
            staffId: $staffId,
            approved: false,
            subscriptionId: (int) $subscription->id,
            staffName: $staffName,
        );

        return $subscription->fresh();
    }

    /**
     * The badge is a privilege column, so it is written explicitly rather than
     * through mass assignment — a non-fillable `is_verified` means no request or
     * payload can ever flip it.
     */
    private function setVerified(Subscription $subscription, bool $verified): void
    {
        $user = $subscription->user()->first();

        if ($user === null) {
            return;
        }

        $user->forceFill(['is_verified' => $verified])->save();
    }
}
