<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * A self-deactivated account tried to sign in.
 *
 * Kept distinct from `AccountDisabledException` (an admin suspension) because
 * the client's next move is different: a suspension is final, whereas a
 * deactivation can be undone with a reactivation call.
 */
final class AccountDeactivatedException extends RuntimeException {}
