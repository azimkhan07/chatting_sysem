<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Contracts\FollowRepository;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use App\Domain\Social\Exceptions\SelfFollowException;

final class FollowUserAction
{
    public function __construct(
        private readonly FollowRepository $followRepository,
        private readonly NotificationRepository $notificationRepository,
    ) {}

    /**
     * @return array{following: bool, followers_count: int}
     */
    public function handle(int $followerId, int $followingId): array
    {
        if ($followerId === $followingId) {
            throw new SelfFollowException('You cannot follow yourself.');
        }

        $created = $this->followRepository->follow($followerId, $followingId);

        if ($created) {
            $this->notificationRepository->create(
                $followingId,
                $followerId,
                NotificationType::Follow,
            );
        }

        return [
            'following' => true,
            'followers_count' => $this->followRepository->followerCount($followingId),
        ];
    }
}
