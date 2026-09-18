<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

final class ListPostCommentsAction
{
    public function __construct(private readonly PostRepository $repository) {}

    public function handle(Post $post, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->repository->commentsFor($post, $limit, $cursor);
    }
}
