<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;

final class CommentOnPostAction
{
    public function __construct(private readonly PostRepository $repository) {}

    public function handle(Post $post, int $userId, string $body): Comment
    {
        return $this->repository->addComment($post, $userId, $body);
    }
}
