<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Moderation\Exceptions\BlockedInteractionException;
use App\Domain\Moderation\Services\BlockService;
use App\Domain\Social\Contracts\FollowRepository;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use App\Domain\Social\Exceptions\SelfFollowException;

final class FollowUserAction
{
    public function __construct(
        private readonly FollowRepository $followRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly BlockService $blocks,
    ) {}

    /**
     * @return array{following: bool, followers_count: int}
     */
    public function handle(int $followerId, int $followingId): array
    {
        if ($followerId === $followingId) {
            throw new SelfFollowException('You cannot follow yourself.');
        }

        // A block in either direction. Following someone who blocked you is
        // the way a block gets circumvented without ever messaging them: their
        // profile is a public page and a follow is a subscription to it.
        if ($this->blocks->blocksEitherWay($followerId, $followingId)) {
            throw new BlockedInteractionException('You cannot follow this account.');
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
