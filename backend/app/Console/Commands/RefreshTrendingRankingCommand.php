<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Posts\Services\TrendingRanking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class RefreshTrendingRankingCommand extends Command
{
    protected $signature = 'posts:refresh-trending';

    protected $description = 'Rebuild the Redis trending ranking from database engagement';

    public function handle(TrendingRanking $trending): int
    {
        try {
            $redis = Redis::connection();
        } catch (\Throwable) {
            $this->warn('Redis unavailable — skipping rebuild.');

            return self::SUCCESS;
        }

        $redis->del(TrendingRanking::KEY);

        $scoreExpression = '(select count(*) from likes where likes.post_id = posts.id)'
            .' + '.TrendingRanking::WEIGHT_COMMENT.' * (select count(*) from comments where comments.post_id = posts.id)'
            .' + '.TrendingRanking::WEIGHT_SHARE.' * (select count(*) from post_shares where post_shares.post_id = posts.id)';

        $scores = DB::table('posts')
            ->selectRaw('id, '.$scoreExpression.' as score')
            ->where('created_at', '>=', now()->subDays(TrendingRanking::WINDOW_DAYS))
            ->whereNull('deleted_at')
            ->orderByDesc('score')
            ->limit(1000)
            ->get();

        if ($scores->isEmpty()) {
            $this->info('No posts in the ranking window — flushed the set.');

            return self::SUCCESS;
        }

        $payload = [];
        foreach ($scores as $row) {
            $payload[] = (float) $row->score;
            $payload[] = (string) $row->id;
        }

        $redis->zadd(TrendingRanking::KEY, ...$payload);
        $redis->expire(TrendingRanking::KEY, 86_400);

        $this->info("Rebuilt trending ranking with {$scores->count()} posts.");

        return self::SUCCESS;
    }
}
