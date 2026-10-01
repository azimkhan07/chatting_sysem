<?php

declare(strict_types=1);

namespace Admin\Support;

/**
 * Single source of truth for page sizes. Every cursor-paginated read clamps
 * through here so a caller can never ask the database for an unbounded page.
 */
final class PageSize
{
    public const DEFAULT = 20;

    public const MAX = 50;

    public const MIN = 1;

    public static function clamp(int $limit, int $default = self::DEFAULT, int $max = self::MAX): int
    {
        if ($limit <= 0) {
            $limit = $default;
        }

        return max(self::MIN, min($limit, $max));
    }
}
