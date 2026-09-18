<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use Illuminate\Pagination\CursorPaginator;

final class ListFeedAction
{
    private const MAX_LIMIT = 50;

    public function __construct(private readonly PostRepository $repository) {}

    public function handle(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        return $this->repository->feedFor($userId, $limit, $cursor);
    }
}
