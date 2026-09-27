<?php

declare(strict_types=1);

namespace App\Domain\Family\Exceptions;

use RuntimeException;

/**
 * The caller asked about a family they are not a member of, or about a member
 * row that is not in their family.
 *
 * 404 rather than 403: "you are not in this family" and "this family does not
 * exist" should be indistinguishable to a stranger, or the endpoint becomes a
 * way to probe for real families.
 */
final class FamilyNotFoundException extends RuntimeException {}
