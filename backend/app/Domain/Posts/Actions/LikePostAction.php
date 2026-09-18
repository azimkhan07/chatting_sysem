<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;

final class LikePostAction
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function handle(Post $post, int $userId): int
    {
        $created = $this->repository->like($post, $userId);

        if ($created && $post->user_id !== $userId) {
            $this->notificationRepository->create(
                $post->user_id,
                $userId,
                NotificationType::Like,
                ['post_id' => $post->id],
            );
        }

        return $this->repository->likeCount($post);
    }
}
