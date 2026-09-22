<?php

declare(strict_types=1);

namespace App\Domain\Posts\Repositories;

use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

final class EloquentPostRepository implements PostRepository
{
    public function create(int $userId, CreatePostData $data): Post
    {
        return Post::query()->create([
            'user_id' => $userId,
            'body' => $data->body,
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
        return Post::query()
            ->whereHas('hashtags', fn (Builder $query): Builder => $query->whereKey($hashtag->id))
            ->with(['user', 'media', 'hashtags'])
            ->withCount(['likes', 'comments'])
            ->withExists([
                'likes as liked_by_me' => fn (Builder $query): Builder => $query->where('user_id', $viewerId),
            ])
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function like(Post $post, int $userId): bool
    {
        $like = $post->likes()->firstOrCreate(['user_id' => $userId]);

        return $like->wasRecentlyCreated;
    }

    public function unlike(Post $post, int $userId): void
    {
        $post->likes()->where('user_id', $userId)->delete();
    }

    public function likeCount(Post $post): int
    {
        $post->loadCount('likes');

        return (int) $post->likes_count;
    }

    public function addComment(Post $post, int $userId, string $body): Comment
    {
        /** @var Comment $comment */
        $comment = $post->comments()->create([
            'user_id' => $userId,
            'body' => $body,
        ]);

        return $comment;
    }

    public function commentsFor(Post $post, int $limit, ?string $cursor): CursorPaginator
    {
        return $post->comments()
            ->with('user')
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    private function baseQuery(int $viewerId): Builder
    {
        return Post::query()
            ->with(['user', 'media', 'hashtags'])
            ->withCount(['likes', 'comments'])
            ->withExists([
                'likes as liked_by_me' => fn (Builder $query): Builder => $query->where('user_id', $viewerId),
            ]);
    }
}
