<?php

declare(strict_types=1);

namespace App\Domain\Family\Exceptions;

use RuntimeException;

/**
 * The action is well-formed but cannot apply right now: the target user is
 * already in a family, or the roster change would remove the last guardian.
 */
final class FamilyNotAllowedException extends RuntimeException {}
