<?php

declare(strict_types=1);

namespace App\Domain\Posts\Contracts;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

interface PostService
{
    public function create(User $author, CreatePostData $data): Post;

    /**
     * Feed of posts, newest first, cursor-paginated.
     *
     * @return CursorPaginator<int, Post>
     */
    public function feedFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator;
}
