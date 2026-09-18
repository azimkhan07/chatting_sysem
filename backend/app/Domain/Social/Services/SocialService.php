<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Actions\FollowUserAction;
use App\Domain\Social\Actions\UnfollowUserAction;
use App\Domain\Social\Contracts\FollowRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class SocialService
{
    public function __construct(
        private readonly FollowRepository $followRepository,
        private readonly FollowUserAction $followUserAction,
        private readonly UnfollowUserAction $unfollowUserAction,
    ) {}

    /**
     * @return array{following: bool, followers_count: int}
     */
    public function follow(int $followerId, int $followingId): array
    {
        return $this->followUserAction->handle($followerId, $followingId);
    }

    /**
     * @return array{following: bool, followers_count: int}
     */
    public function unfollow(int $followerId, int $followingId): array
    {
        return $this->unfollowUserAction->handle($followerId, $followingId);
    }

    public function followersFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->followRepository->followersFor($userId, $limit, $cursor);
    }

    public function followingFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->followRepository->followingFor($userId, $limit, $cursor);
    }
}
