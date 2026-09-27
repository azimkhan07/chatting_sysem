<?php

declare(strict_types=1);

namespace App\Domain\Family\Exceptions;

use RuntimeException;

/**
 * The caller is a member, but their role does not allow this action - a teen
 * trying to add someone, or a non-owner trying to rename the family.
 */
final class FamilyPermissionException extends RuntimeException {}
