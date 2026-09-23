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
        $count = $this->repository->share($post, $userId);

        $this->trending->bump((int) $post->id, TrendingRanking::WEIGHT_SHARE);

        return $count;
    }
}
