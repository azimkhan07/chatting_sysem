<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Exceptions\InvalidCommentException;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Services\TrendingRanking;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use Illuminate\Support\Str;

final class CommentOnPostAction
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly NotificationRepository $notificationRepository,
        private readonly TrendingRanking $trending,
    ) {}

    public function handle(Post $post, int $userId, string $body, ?int $parentId = null): Comment
    {
        $parent = $this->resolveParent($post, $parentId);

        $comment = $this->repository->addComment($post, $userId, $body, $parent?->id);

        $this->trending->bump((int) $post->id, TrendingRanking::WEIGHT_COMMENT);

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

        // Replying also notifies the person being replied to, unless that is
        // already the post author we just notified.
        $parentAuthorId = $parent?->user_id;
        if ($parentAuthorId !== null && $parentAuthorId !== $userId && $parentAuthorId !== $post->user_id) {
            $this->notificationRepository->create(
                $parentAuthorId,
                $userId,
                NotificationType::Comment,
                [
                    'post_id' => $post->id,
                    'comment_id' => $comment->id,
                    'comment_preview' => Str::limit($body, 60),
                ],
            );
        }

        return $comment;
    }

    /**
     * Rejects replies that point at another reply, which keeps the tree one level
     * deep, and at comments belonging to a different post.
     */
    private function resolveParent(Post $post, ?int $parentId): ?Comment
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Comment::query()
            ->whereKey($parentId)
            ->where('post_id', $post->id)
            ->first();

        if ($parent === null) {
            throw new InvalidCommentException('That comment no longer exists on this post.');
        }

        if ($parent->parent_id !== null) {
            throw new InvalidCommentException('Replies can only be added to a top-level comment.');
        }

        return $parent;
    }
}
