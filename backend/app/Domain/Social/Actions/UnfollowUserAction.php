<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Contracts\FollowRepository;

final class UnfollowUserAction
{
    public function __construct(private readonly FollowRepository $followRepository) {}

    /**
     * @return array{following: bool, followers_count: int}
     */
    public function handle(int $followerId, int $followingId): array
    {
        $this->followRepository->unfollow($followerId, $followingId);

        return [
            'following' => false,
            'followers_count' => $this->followRepository->followerCount($followingId),
        ];
    }
}
