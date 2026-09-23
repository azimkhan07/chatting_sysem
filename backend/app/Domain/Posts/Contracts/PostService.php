<?php

declare(strict_types=1);

namespace App\Domain\Posts\Contracts;

use App\Domain\Auth\Models\User;
use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface PostService
{
    public function create(User $author, CreatePostData $data): Post;

    /**
     * Feed of posts, newest first, cursor-paginated.
     *
     * @return CursorPaginator<int, Post>
     */
    public function feedFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator;

    /**
     * A user's own posts, newest first — powers the profile grid.
     *
     * @return CursorPaginator<int, Post>
     */
    public function postsBy(User $viewer, User $owner, int $limit = 20, ?string $cursor = null): CursorPaginator;

    /**
     * Video-only posts — the reels feed.
     *
     * @return CursorPaginator<int, Post>
     */
    public function reelsFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator;

    /**
     * Posts that carry media — powers the explore grid.
     *
     * @return CursorPaginator<int, Post>
     */
    public function exploreFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator;

    /**
     * Posts carrying a hashtag, newest first.
     *
     * @return CursorPaginator<int, Post>
     */
    public function hashtagFeedFor(User $viewer, Hashtag $hashtag, int $limit = 20, ?string $cursor = null): CursorPaginator;

    public function like(User $user, Post $post): int;

    public function unlike(User $user, Post $post): int;

    public function addComment(User $user, Post $post, string $body): Comment;

    /**
     * Latest comments on a post, newest first.
     *
     * @return CursorPaginator<int, Comment>
     */
    public function commentsFor(Post $post, int $limit = 20, ?string $cursor = null): CursorPaginator;

    /**
     * Records the viewer sharing a post. Returns the new total share count.
     */
    public function share(User $user, Post $post): int;

    /**
     * Trending posts ranked by engagement (Redis set first, DB fallback).
     *
     * @return Collection<int, Post>
     */
    public function trendingFor(User $user, int $limit = 30): Collection;
}
