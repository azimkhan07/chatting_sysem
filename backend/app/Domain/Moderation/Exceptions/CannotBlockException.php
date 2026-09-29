<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Exceptions;

use RuntimeException;

/**
 * A block that cannot be made.
 *
 * Separate from a generic validation error because the two cases mean
 * different things to the caller: "you cannot block yourself" is a bad
 * request, and "that account does not exist" may be a soft-deleted user the
 * reporter is not allowed to know about. Both are 4xx.
 */
final class CannotBlockException extends RuntimeException {}
