<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

use App\Domain\Social\Contracts\FollowRepository;
use App\Domain\Social\Models\Follow;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentFollowRepository implements FollowRepository
{
    public function follow(int $followerId, int $followingId): bool
    {
        $follow = Follow::query()
            ->createOrFirst([
                'follower_id' => $followerId,
                'following_id' => $followingId,
            ]);

        return $follow->wasRecentlyCreated;
    }

    public function unfollow(int $followerId, int $followingId): bool
    {
        return (bool) Follow::query()
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->delete();
    }

    /**
     * No block filter here, and that is deliberate rather than an oversight.
     *
     * `BlockService::block()` deletes both follow edges for the pair, and
     * `FollowUserAction` refuses to create one while a block stands. So a
     * blocked pair cannot have a follow row to return - the invariant is held
     * by the write path instead, which also keeps the counts and the list
     * agreeing without two separate filters to keep in step.
     */
    public function followersFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return Follow::query()
            ->where('following_id', $userId)
            ->with('follower')
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function followingFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return Follow::query()
            ->where('follower_id', $userId)
            ->with('following')
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function followerCount(int $userId): int
    {
        return (int) Follow::query()->where('following_id', $userId)->count();
    }

    public function followingCount(int $userId): int
    {
        return (int) Follow::query()->where('follower_id', $userId)->count();
    }

    public function isFollowing(int $followerId, int $followingId): bool
    {
        return Follow::query()
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->exists();
    }

    public function connected(int $firstUserId, int $secondUserId): bool
    {
        return Follow::query()
            ->where(function (Builder $query) use ($firstUserId, $secondUserId): void {
                $query
                    ->where(fn (Builder $arm): Builder => $arm
                        ->where('follower_id', $firstUserId)
                        ->where('following_id', $secondUserId))
                    ->orWhere(fn (Builder $arm): Builder => $arm
                        ->where('follower_id', $secondUserId)
                        ->where('following_id', $firstUserId));
            })
            ->exists();
    }
}
