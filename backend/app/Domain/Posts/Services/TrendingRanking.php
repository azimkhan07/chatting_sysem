<?php

declare(strict_types=1);

namespace App\Domain\Posts\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed engagement ranking for trending posts.
 *
 * Scores are bumped in real time by like/comment/share actions. A scheduled
 * refresh rebuilds the set from the database so rankings stay correct even if
 * Redis is flushed. When Redis is unavailable every read falls back to a
 * database-ranked query, so trending never breaks.
 */
final class TrendingRanking
{
    public const KEY = 'posts:trending';

    public const WINDOW_DAYS = 7;

    private const TTL_SECONDS = 86_400;

    /**
     * Score weights used when ranking a post from database engagement.
     */
    public const WEIGHT_LIKE = 1;

    public const WEIGHT_COMMENT = 2;

    public const WEIGHT_SHARE = 4;

    public function bump(int $postId, int $delta): void
    {
        try {
            $redis = Redis::connection();
            $redis->zincrby(self::KEY, (float) $delta, (string) $postId);
            $redis->expire(self::KEY, self::TTL_SECONDS);
        } catch (\Throwable) {
            // Redis unreachable: reads fall back to the DB-ranked query.
        }
    }

    public function remove(int $postId): void
    {
        try {
            Redis::connection()->zrem(self::KEY, (string) $postId);
        } catch (\Throwable) {
            // Best effort.
        }
    }

    /**
     * Highest-scoring post ids in descending order, capped at $limit.
     *
     * @return list<int>
     */
    public function topIds(int $limit): array
    {
        try {
            /** @var list<string> $ids */
            $ids = Redis::connection()->zrevrange(self::KEY, 0, max(0, $limit - 1));
        } catch (\Throwable) {
            $ids = [];
        }

        if ($ids === []) {
            return [];
        }

        return array_values(array_map('intval', $ids));
    }
}
