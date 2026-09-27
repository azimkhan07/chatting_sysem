<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Services\SubscriptionService;
use App\Domain\Chat\Enums\ChatFeature;
use App\Domain\Chat\Exceptions\FeatureLockedException;

/**
 * Single source of truth for "may this user use this chat capability?".
 *
 * The rule is intentionally blunt: any active subscription unlocks the whole
 * premium chat tier. That keeps the paywall legible — one purchase, no
 * per-feature matrix to reason about — while still letting new features be
 * added to the tier by adding a case to {@see ChatFeature}.
 */
final class ChatEntitlements
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function unlockedMap(User $user): array
    {
        $unlocked = $this->subscriptions->isVerified($user->id);

        $map = [];
        foreach (ChatFeature::cases() as $feature) {
            $map[$feature->value] = $unlocked;
        }

        return $map;
    }

    /**
     * @return list<array{key: string, label: string, blurb: string, unlocked: bool}>
     */
    public function catalogue(User $user): array
    {
        $map = $this->unlockedMap($user);

        $items = [];
        foreach (ChatFeature::cases() as $feature) {
            $items[] = [
                'key' => $feature->value,
                'label' => $feature->label(),
                'blurb' => $feature->blurb(),
                'unlocked' => $map[$feature->value],
            ];
        }

        return $items;
    }

    public function allows(User $user, ChatFeature $feature): bool
    {
        return $this->unlockedMap($user)[$feature->value] === true;
    }

    /**
     * @throws FeatureLockedException
     */
    public function authorize(User $user, ChatFeature $feature): void
    {
        if (! $this->allows($user, $feature)) {
            throw new FeatureLockedException($feature);
        }
    }
}
