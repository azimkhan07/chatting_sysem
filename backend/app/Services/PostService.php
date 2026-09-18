<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Actions\CommentOnPostAction;
use App\Domain\Posts\Actions\CreatePostAction;
use App\Domain\Posts\Actions\LikePostAction;
use App\Domain\Posts\Actions\ListFeedAction;
use App\Domain\Posts\Actions\ListPostCommentsAction;
use App\Domain\Posts\Actions\UnlikePostAction;
use App\Domain\Posts\Contracts\PostService as PostServiceContract;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

final class PostService implements PostServiceContract
{
    public function __construct(
        private readonly CreatePostAction $createPostAction,
        private readonly ListFeedAction $listFeedAction,
        private readonly LikePostAction $likePostAction,
        private readonly UnlikePostAction $unlikePostAction,
        private readonly CommentOnPostAction $commentOnPostAction,
        private readonly ListPostCommentsAction $listPostCommentsAction,
    ) {}

    public function create(User $author, CreatePostData $data): Post
    {
        return $this->createPostAction->handle($author->id, $data);
    }

    public function feedFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listFeedAction->handle($user->id, $limit, $cursor);
    }

    public function postsBy(User $viewer, User $owner, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listFeedAction->handleFor($owner->id, $viewer->id, $limit, $cursor);
    }

    public function reelsFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listFeedAction->handleReels($user->id, $limit, $cursor);
    }

    public function exploreFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listFeedAction->handleExplore($user->id, $limit, $cursor);
    }

    public function hashtagFeedFor(User $viewer, Hashtag $hashtag, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listFeedAction->handleHashtag($hashtag, $viewer->id, $limit, $cursor);
    }

    public function like(User $user, Post $post): int
    {
        return $this->likePostAction->handle($post, $user->id);
    }

    public function unlike(User $user, Post $post): int
    {
        return $this->unlikePostAction->handle($post, $user->id);
    }

    public function addComment(User $user, Post $post, string $body): Comment
    {
        return $this->commentOnPostAction->handle($post, $user->id, $body);
    }

    public function commentsFor(Post $post, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listPostCommentsAction->handle($post, $limit, $cursor);
    }
}
