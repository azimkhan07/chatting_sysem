<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Post;
use App\Support\PageSize;
use Illuminate\Pagination\CursorPaginator;

final class ListPostCommentsAction
{
    public function __construct(private readonly PostRepository $repository) {}

    /**
     * @param  int|null  $viewerId  Falls back to the post author so an
     *                              unauthenticated or internal read still gets
     *                              a correctly filtered list rather than an
     *                              unfiltered one.
     */
    public function handle(Post $post, int $limit, ?string $cursor, ?int $viewerId = null): CursorPaginator
    {
        return $this->repository->commentsFor(
            $post,
            PageSize::clamp($limit),
            $cursor,
            $viewerId ?? (int) $post->user_id,
        );
    }
}
