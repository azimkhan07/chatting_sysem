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
        return $this->repository->feedFor($userId, $this->sanitizeLimit($limit), $cursor);
    }

    public function handleFor(int $ownerId, int $viewerId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->repository->profileFeedFor($ownerId, $viewerId, $this->sanitizeLimit($limit), $cursor);
    }

    private function sanitizeLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_LIMIT));
    }
}
