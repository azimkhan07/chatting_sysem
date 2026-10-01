<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

/**
 * Whether a feature is something a subscription has to be bought for.
 *
 * This is the answer to the question asked at registration time: "is this one
 * paid, or is it going out to everyone?" It is a property of the feature, not of
 * a plan, so it is declared once where the feature is declared rather than being
 * inferred later from which plan happens to list it.
 *
 * Free is the default on purpose. A newly added feature that nobody has had a
 * chance to price should reach every existing subscriber immediately, and the
 * failure mode of guessing wrong is the expensive one: marking something premium
 * by accident locks a working feature behind a paywall for every current user,
 * while marking it free just gives it away for a release. Changing one line flips
 * it.
 */
enum FeatureTier: string
{
    /** Unlocked by buying a plan that lists it. */
    case Premium = 'premium';

    /** Unlocked for every account, subscription or not. */
    case Free = 'free';

    public function isPaid(): bool
    {
        return $this === self::Premium;
    }

    public function label(): string
    {
        return match ($this) {
            self::Premium => 'Paid',
            self::Free => 'Free for everyone',
        };
    }
}
