<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Post;

final class UnlikePostAction
{
    public function __construct(private readonly PostRepository $repository) {}

    public function handle(Post $post, int $userId): int
    {
        $this->repository->unlike($post, $userId);

        return $this->repository->likeCount($post);
    }
}
