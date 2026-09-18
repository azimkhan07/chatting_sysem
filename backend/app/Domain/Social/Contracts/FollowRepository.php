<?php

declare(strict_types=1);

namespace App\Domain\Social\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;

interface FollowRepository
{
    /**
     * Record a follow. Returns true when the relationship was newly created.
     */
    public function follow(int $followerId, int $followingId): bool;

    /**
     * Remove a follow. Returns true when a record existed and was removed.
     */
    public function unfollow(int $followerId, int $followingId): bool;

    public function followersFor(int $userId, int $limit, ?string $cursor): CursorPaginator;

    public function followingFor(int $userId, int $limit, ?string $cursor): CursorPaginator;

    public function followerCount(int $userId): int;

    public function followingCount(int $userId): int;
}
