<?php

declare(strict_types=1);

namespace App\Domain\Posts\Repositories;

use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
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
        $like = $post->likes()->createOrFirst(['user_id' => $userId]);

        return $like->wasRecentlyCreated;
    }

    public function unlike(Post $post, int $userId): bool
    {
        return $post->likes()->where('user_id', $userId)->delete() > 0;
    }

    public function likeCount(Post $post): int
    {
        $post->loadCount('likes');

        return (int) $post->likes_count;
    }

    public function addComment(Post $post, int $userId, string $body, ?int $parentId = null): Comment
    {
        /** @var Comment $comment */
        $comment = $post->comments()->create([
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
        ]);

        return $comment;
    }

    public function commentsFor(Post $post, int $limit, ?string $cursor): CursorPaginator
    {
        return $post->comments()
            ->whereNull('parent_id')
            ->with('user')
            ->withCount('replies')
            ->with([
                'replies' => fn ($query) => $query
                    ->with('user')
                    ->oldest('id')
                    ->limit(self::REPLIES_PREVIEW),
            ])
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function share(Post $post, int $userId): array
    {
        $share = $post->shares()->createOrFirst(['user_id' => $userId]);

        $post->loadCount('shares');

        return ['count' => (int) $post->shares_count, 'created' => $share->wasRecentlyCreated];
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
     * Every feed surface shares this projection so a page costs a fixed number
     * of queries instead of one per post. `hashtags` is intentionally *not*
     * eager loaded: the API payload does not need it and it doubled the query
     * count of a feed page.
     *
     * @return Builder<Post>
     */
    private function baseQuery(int $viewerId): Builder
    {
        return Post::query()
            ->with(['user', 'media'])
            ->withCount(['likes', 'comments', 'shares'])
            ->withExists([
                'likes as liked_by_me' => fn (Builder $query): Builder => $query->where('user_id', $viewerId),
            ]);
    }
}
