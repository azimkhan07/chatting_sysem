<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;

final class AdminReviewNotifier
{
    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {}

    /**
     * Tell every admin-role user that a paid verification is waiting for review.
     * Each notification is broadcast in realtime via NotificationCreated.
     */
    public function notifyReviewPending(User $payer, Subscription $subscription): void
    {
        $role = Role::query()->where('name', 'admin')->first();

        if ($role === null) {
            return;
        }

        $adminIds = $role->users()->pluck('users.id')->all();

        foreach ($adminIds as $adminId) {
            $this->notifications->create(
                (int) $adminId,
                (int) $payer->id,
                NotificationType::AdminReview,
                [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan->value,
                    'amount_paisa' => $subscription->amount_paisa,
                ],
            );
        }
    }
}
