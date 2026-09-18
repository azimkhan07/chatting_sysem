<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

use App\Domain\Social\Contracts\FollowRepository;
use App\Domain\Social\Models\Follow;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class EloquentFollowRepository implements FollowRepository
{
    public function follow(int $followerId, int $followingId): bool
    {
        $follow = Follow::query()
            ->firstOrCreate([
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
}
