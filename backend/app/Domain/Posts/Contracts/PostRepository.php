<?php

declare(strict_types=1);

namespace App\Domain\Posts\Contracts;

use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Database\Eloquent\Collection;
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

    public function unlike(Post $post, int $userId): bool;

    public function likeCount(Post $post): int;

    public function addComment(Post $post, int $userId, string $body): Comment;

    public function commentsFor(Post $post, int $limit, ?string $cursor): CursorPaginator;

    /**
     * Records a share of a post by a user. Idempotent per (post, user).
     * Returns the total unique share count for the post.
     */
    public function share(Post $post, int $userId): int;

    /**
     * Top posts of the last ranking window by engagement, database-ranked
     * fallback used when the Redis-ranked set is unavailable.
     *
     * @return Collection<int, Post>
     */
    public function trendingFor(int $viewerId, int $limit): Collection;

    /**
     * Fetches posts matching the given ids, returned in that exact order.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Post>
     */
    public function byIdsInOrder(array $ids, int $viewerId): Collection;
}
