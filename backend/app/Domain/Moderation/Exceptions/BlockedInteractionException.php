<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Exceptions;

use RuntimeException;

/**
 * Something that cannot cross a block.
 *
 * A single exception for follow, DM and comment because the client needs one
 * thing to do with all three: show "you can't do that" and stop. Distinguishing
 * "they blocked you" from "you blocked them" in the response would confirm to
 * a blocked person that the block exists, which is the one thing the block is
 * meant to keep private.
 */
final class BlockedInteractionException extends RuntimeException {}
