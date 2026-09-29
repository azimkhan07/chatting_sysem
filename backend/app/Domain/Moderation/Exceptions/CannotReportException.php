<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Exceptions;

use RuntimeException;

/**
 * A report that cannot be filed.
 *
 * Usually "that thing does not exist" or "you are reporting your own content",
 * neither of which is worth a queue entry.
 */
final class CannotReportException extends RuntimeException {}
