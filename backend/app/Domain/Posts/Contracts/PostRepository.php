<?php

declare(strict_types=1);

namespace App\Domain\Posts\Contracts;

use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

interface PostRepository
{
    public function create(int $userId, CreatePostData $data): Post;

    public function feedFor(int $userId, int $limit, ?string $cursor): CursorPaginator;
}
