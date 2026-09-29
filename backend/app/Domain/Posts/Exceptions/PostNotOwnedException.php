<?php

declare(strict_types=1);

namespace App\Domain\Posts\Exceptions;

use RuntimeException;

/**
 * Someone tried to act on a post that is not theirs.
 *
 * Mapped to 403 rather than 404 on purpose: the post exists and the caller
 * can already see it, so pretending otherwise would only make the error
 * confusing. A caller who genuinely cannot see the post never reaches this
 * path, because the route model binding has already 404'd.
 */
final class PostNotOwnedException extends RuntimeException {}
