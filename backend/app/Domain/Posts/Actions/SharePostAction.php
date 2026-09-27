<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Services\TrendingRanking;

final class SharePostAction
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly TrendingRanking $trending,
    ) {}

    public function handle(Post $post, int $userId): int
    {
        ['count' => $count, 'created' => $created] = $this->repository->share($post, $userId);

        // Shares are idempotent per (post, user): only the first one counts, so a
        // repeated tap cannot inflate the trending score.
        if ($created) {
            $this->trending->bump((int) $post->id, TrendingRanking::WEIGHT_SHARE);
        }

        return $count;
    }
}
