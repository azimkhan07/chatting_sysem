<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
final class SubscriptionResource extends JsonResource
{
    public static function tiers(array $tiers): array
    {
        return array_map(
            static fn (array $tier): array => [
                'key' => $tier['key'],
                'name' => $tier['name'],
                'price_month' => $tier['price_month'],
                'perks' => $tier['perks'],
            ],
            $tiers,
        );
    }

    public function toArray(Request $request): array
    {
        /** @var Plan $plan */
        $plan = $this->plan;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan' => $plan->value,
            'plan_name' => $plan->label(),
            'amount_paisa' => $this->amount_paisa,
            'price_month' => $plan->pricePerMonth(),
            'status' => $this->status->label(),
            'status_code' => $this->status->value,
            'payment_token' => $this->when($this->isPaid(), $this->payment_token),
            'auto_renew' => (bool) $this->auto_renew,
            'is_verified' => $this->user !== null ? $this->user->is_verified : false,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'switch_from' => $this->switchFrom !== null ? [
                'subscription_id' => $this->switchFrom->id,
                'plan' => $this->switchFrom->plan->value,
                'plan_name' => $this->switchFrom->plan->label(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
