<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use Illuminate\Support\Str;

final class CommentOnPostAction
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function handle(Post $post, int $userId, string $body): Comment
    {
        $comment = $this->repository->addComment($post, $userId, $body);

        if ($post->user_id !== $userId) {
            $this->notificationRepository->create(
                $post->user_id,
                $userId,
                NotificationType::Comment,
                [
                    'post_id' => $post->id,
                    'comment_preview' => Str::limit($body, 60),
                ],
            );
        }

        return $comment;
    }
}
