<?php

declare(strict_types=1);

namespace Admin\Http\Resources;

use Admin\Domain\App\Models\User;
use Admin\Domain\App\Enums\Plan;
use Admin\Domain\App\Models\Subscription;
use Admin\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
final class SubscriptionReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Plan $plan */
        $plan = $this->plan;

        /** @var User|null $user */
        $user = $this->user;

        return [
            'id' => $this->id,
            'status' => $this->status->label(),
            'status_code' => $this->status->value,
            'plan' => $plan->value,
            'plan_name' => $plan->label(),
            'amount_paisa' => $this->amount_paisa,
            'price_month' => $plan->pricePerMonth(),
            'auto_renew' => (bool) $this->auto_renew,
            'paid' => $this->isPaid(),
            'is_verified' => $user->is_verified ?? false,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'switch_from' => $this->switchFrom !== null ? [
                'subscription_id' => $this->switchFrom->id,
                'plan' => $this->switchFrom->plan->value,
                'plan_name' => $this->switchFrom->plan->label(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $user === null ? null : [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'bio' => $user->bio,
                'avatar_url' => MediaUrl::ofNullable($user->avatar_path),
                'is_verified' => $user->is_verified,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ];
    }
}
