<?php

declare(strict_types=1);

namespace App\Domain\Posts\Repositories;

use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Moderation\Exceptions\BlockedInteractionException;
use App\Domain\Moderation\Services\BlockService;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Exceptions\PostNotOwnedException;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Services\TrendingRanking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

final class EloquentPostRepository implements PostRepository
{
    /**
     * How many replies are inlined under a root comment. Anything beyond this is
     * reachable from the root comment's own reply count.
     */
    private const REPLIES_PREVIEW = 3;

    public function __construct(
        private readonly BlockService $blocks,
    ) {}

    public function create(int $userId, CreatePostData $data): Post
    {
        return Post::query()->create([
            'user_id' => $userId,
            'body' => $data->body,
            'location' => $data->location,
            'song_id' => $data->songId,
        ]);
    }

    public function attachMedia(Post $post, array $media): void
    {
        $post->media()->createMany($media);
    }

    public function delete(Post $post, int $actorId): void
    {
        if ((int) $post->user_id !== $actorId) {
            throw new PostNotOwnedException('You can only delete your own post.');
        }

        // Soft delete, not a hard one. The row stays so the comment and reply
        // threads below it keep their shape, and so a moderator can still see
        // what was removed and why — the `reports` row that led to the removal
        // points at an id that must still resolve.
        $post->delete();
    }

    public function feedFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->baseQuery($userId)
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function profileFeedFor(int $ownerId, int $viewerId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->baseQuery($viewerId)
            ->where('user_id', $ownerId)
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function reelsFor(int $viewerId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->baseQuery($viewerId)
            ->whereHas('media', fn (Builder $query): Builder => $query->where('type', 'video'))
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function exploreFor(int $viewerId, int $limit, ?string $cursor): CursorPaginator
    {
        return $this->baseQuery($viewerId)
            ->has('media')
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function hashtagFeedFor(Hashtag $hashtag, int $viewerId, int $limit, ?string $cursor): CursorPaginator
    {
        // The one surface that genuinely needs the tag list on each post, so
        // here the relation is worth its query; elsewhere it is not.
        return $this->baseQuery($viewerId)
            ->with('hashtags')
            ->whereHas('hashtags', fn (Builder $query): Builder => $query->whereKey($hashtag->id))
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function like(Post $post, int $userId): bool
    {
        $this->assertReachable($post, $userId, 'like');

        $like = $post->likes()->createOrFirst(['user_id' => $userId]);

        return $like->wasRecentlyCreated;
    }

    public function unlike(Post $post, int $userId): bool
    {
        // Unlike is not guarded, and deliberately: it removes a row the viewer
        // already owns. Refusing it would leave somebody unable to take back
        // their own like after a block, which is a worse outcome than allowing a
        // one-way cleanup.
        return $post->likes()->where('user_id', $userId)->delete() > 0;
    }

    public function likeCount(Post $post): int
    {
        $post->loadCount('likes');

        return (int) $post->likes_count;
    }

    public function addComment(Post $post, int $userId, string $body, ?int $parentId = null): Comment
    {
        // A blocked author must not be reachable through a third party's post.
        // Not checking here would let anyone reply to a comment thread started
        // before a block and keep the conversation going under it.
        $this->assertReachable($post, $userId, 'comment on');

        /** @var Comment $comment */
        $comment = $post->comments()->create([
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
        ]);

        return $comment;
    }

    public function commentsFor(Post $post, int $limit, ?string $cursor, int $viewerId): CursorPaginator
    {
        // Comments from blocked accounts are dropped from the thread, and from
        // the reply count, so blocking someone does not leave a visible wall of
        // their replies under a post.
        $hideBlocked = fn (Builder $query): Builder => $this->blocks->hideFromQuery($query, $viewerId, 'comments.user_id');

        return $post->comments()
            ->whereNull('parent_id')
            ->tap($hideBlocked)
            ->with('user')
            ->withCount([
                'replies as replies_count' => fn ($query) => $query->tap($hideBlocked),
            ])
            ->with([
                'replies' => fn ($query) => $query
                    ->tap($hideBlocked)
                    ->with('user')
                    ->oldest('id')
                    ->limit(self::REPLIES_PREVIEW),
            ])
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function share(Post $post, int $userId): array
    {
        $this->assertReachable($post, $userId, 'share');

        $share = $post->shares()->createOrFirst(['user_id' => $userId]);

        $post->loadCount('shares');

        return ['count' => (int) $post->shares_count, 'created' => $share->wasRecentlyCreated];
    }

    /**
     * A write aimed at this post's author is refused when the two are blocked
     * in either direction.
     *
     * One guard for like, comment and share, because the failure they share is
     * the same: each is a public signal that the pair is still connected, and a
     * block that stopped comments but not likes would still ping the other
     * person with a notification. Written as a single call so a fourth
     * interaction cannot be added without the question being asked again.
     */
    private function assertReachable(Post $post, int $userId, string $verb): void
    {
        if ($this->blocks->blocksEitherWay($userId, (int) $post->user_id)) {
            throw new BlockedInteractionException("You cannot {$verb} this post.");
        }
    }

    public function trendingFor(int $viewerId, int $limit): Collection
    {
        $score = '(select count(*) from likes where likes.post_id = posts.id)'
            .' + '.TrendingRanking::WEIGHT_COMMENT.' * (select count(*) from comments where comments.post_id = posts.id)'
            .' + '.TrendingRanking::WEIGHT_SHARE.' * (select count(*) from post_shares where post_shares.post_id = posts.id)';

        return $this->baseQuery($viewerId)
            ->where('created_at', '>=', now()->subDays(TrendingRanking::WINDOW_DAYS))
            ->orderByRaw($score.' desc')
            // Deterministic tiebreak, otherwise the cut inside the score-0 block
            // can change between two identical requests.
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function byIdsInOrder(array $ids, int $viewerId): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $byId = $this->baseQuery($viewerId)
            ->whereIn('id', $ids)
            // The ranked set has no TTL per member, so the ranking window has to
            // be enforced here or stale ids stay "trending" forever.
            ->where('created_at', '>=', now()->subDays(TrendingRanking::WINDOW_DAYS))
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            $post = $byId->get($id);
            if ($post !== null) {
                $ordered[] = $post;
            }
        }

        return new Collection($ordered);
    }

    /**
     * Every post read path starts here.
     *
     * That is what makes the block filter trustworthy: the feed, reels,
     * explore, a profile, a hashtag page and trending are six different
     * queries, and a block enforced in only some of them is a block that
     * leaks the moment the reader opens a different tab. Filtering in the one
     * shared base means a new read path inherits the rule for free.
     *
     * The check is "the viewer does not block this author", and only that
     * direction — the blocked person losing the ability to comment is
     * enforced on write instead, which is a different question.
     *
     * Every feed surface also shares this projection, so a page costs a fixed
     * number of queries instead of one per post. `hashtags` is intentionally
     * *not* eager loaded: the API payload does not need it and it doubled the
     * query count of a feed page.
     *
     * @return Builder<Post>
     */
    private function baseQuery(int $viewerId): Builder
    {
        $query = Post::query()
            ->with(['user', 'media'])
            ->withCount(['likes', 'comments', 'shares'])
            ->withExists([
                'likes as liked_by_me' => fn (Builder $query): Builder => $query->where('user_id', $viewerId),
            ]);

        // Applied as a statement rather than chained through `tap()`. `tap`
        // is forwarded to the query builder, so it returns a type that erases
        // the `Builder<Post>` generic and every read path built on this loses
        // its element type.
        return $this->blocks->hideFromQuery($query, $viewerId, 'posts.user_id');
    }
}
