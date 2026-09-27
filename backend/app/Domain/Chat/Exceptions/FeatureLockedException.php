<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use App\Domain\Chat\Enums\ChatFeature;
use RuntimeException;

/**
 * Thrown when a locked chat capability is used without an active subscription.
 * The feature key travels with the error so the client can point the lock at
 * the right upgrade prompt instead of showing a generic failure.
 */
final class FeatureLockedException extends RuntimeException
{
    public function __construct(public readonly ChatFeature $feature)
    {
        parent::__construct($feature->label().' needs an active subscription.');
    }
}
