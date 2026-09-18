<?php

declare(strict_types=1);

namespace App\Domain\Posts\Contracts;

use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

interface PostRepository
{
    public function create(int $userId, CreatePostData $data): Post;

    /**
     * @param  list<array<string, mixed>>  $media  normalized media rows
     */
    public function attachMedia(Post $post, array $media): void;

    public function feedFor(int $userId, int $limit, ?string $cursor): CursorPaginator;

    public function profileFeedFor(int $ownerId, int $viewerId, int $limit, ?string $cursor): CursorPaginator;

    /**
     * Video-only posts — the reels feed.
     */
    public function reelsFor(int $viewerId, int $limit, ?string $cursor): CursorPaginator;

    /**
     * Posts that have media attached — the explore grid.
     */
    public function exploreFor(int $viewerId, int $limit, ?string $cursor): CursorPaginator;

    /**
     * Posts carrying the hashtag, newest first.
     */
    public function hashtagFeedFor(Hashtag $hashtag, int $viewerId, int $limit, ?string $cursor): CursorPaginator;

    public function like(Post $post, int $userId): bool;

    public function unlike(Post $post, int $userId): void;

    public function likeCount(Post $post): int;

    public function addComment(Post $post, int $userId, string $body): Comment;

    public function commentsFor(Post $post, int $limit, ?string $cursor): CursorPaginator;
}
