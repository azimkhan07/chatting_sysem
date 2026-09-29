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
use App\Domain\Posts\Actions\SharePostAction;
use App\Domain\Posts\Actions\UnlikePostAction;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Contracts\PostService as PostServiceContract;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Services\TrendingRanking;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly SharePostAction $sharePostAction,
        private readonly PostRepository $repository,
        private readonly TrendingRanking $trending,
    ) {}

    public function create(User $author, CreatePostData $data): Post
    {
        return $this->createPostAction->handle($author->id, $data);
    }

    public function delete(User $author, Post $post): void
    {
        // The ownership check lives in the repository rather than here so that
        // every path to the row - this service, a job, an admin tool - has the
        // same answer to "may this account delete this post".
        $this->repository->delete($post, (int) $author->id);
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

    public function addComment(User $user, Post $post, string $body, ?int $parentId = null): Comment
    {
        return $this->commentOnPostAction->handle($post, $user->id, $body, $parentId);
    }

    public function commentsFor(Post $post, int $limit = 20, ?string $cursor = null, ?int $viewerId = null): CursorPaginator
    {
        return $this->listPostCommentsAction->handle($post, $limit, $cursor, $viewerId);
    }

    public function share(User $user, Post $post): int
    {
        return $this->sharePostAction->handle($post, (int) $user->id);
    }

    public function trendingFor(User $user, int $limit = 30): Collection
    {
        $ids = $this->trending->topIds($limit);

        // A thin ranked set (fresh install, low engagement) would otherwise show
        // a 2-post "Trending" tab while the database has plenty of candidates.
        if (count($ids) >= $limit) {
            return $this->repository->byIdsInOrder($ids, (int) $user->id);
        }

        return $this->repository->trendingFor((int) $user->id, $limit);
    }
}
