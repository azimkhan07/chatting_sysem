<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Exceptions\CannotBlockException;
use App\Domain\Moderation\Models\UserBlock;
use App\Domain\Social\Models\Follow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Blocking, and every place a block has to be honoured.
 *
 * The hard part of blocking is not the table, it is that every read path has
 * to remember. A block that only hides a profile is theatre: the person is
 * still in your search results, still in your feed, still in the group chat,
 * and can still comment. So the filter lives here as one reusable subquery
 * and every read path that can surface a blocked account calls it, rather
 * than each endpoint re-deriving the rule.
 *
 * Two directions, and they answer different questions. Reads are one-way:
 * `hideFromQuery` only drops accounts the viewer blocked, because filtering
 * symmetrically would delete the blocker's own content from someone else's
 * feed over an action that was never aimed at them. Writes are two-way:
 * `blocksEitherWay` is checked before every follow, comment and message, so
 * neither person can keep the line open after a block.
 *
 * Direct profile access is the one exception to the one-way read rule, and it
 * is deliberate. If A blocks B, A no longer sees B anywhere - but B opening
 * A's profile is a route back to the person who cut them off, so that one
 * lookup answers 404 in both directions. Making it 404 rather than 403 is
 * deliberate too: 403 confirms the account exists, which is a disclosure the
 * block is supposed to prevent.
 */
final class BlockService
{
    /**
     * @return array{blocked: bool}
     */
    public function block(User $blocker, int $blockedId): array
    {
        if ($blockedId === $blocker->id) {
            throw new CannotBlockException('You cannot block yourself.');
        }

        $blocked = User::query()->find($blockedId);
        if ($blocked === null) {
            throw new CannotBlockException('That account does not exist.');
        }

        UserBlock::query()->firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $blockedId,
        ]);

        // Any existing follow edge has to go in both directions, or the blocked
        // account keeps showing the blocker in their following list, and
        // unblocking later would have to re-create a follow nobody agreed to.
        Follow::query()
            ->where(function (Builder $query) use ($blocker, $blockedId): void {
                $query->where('follower_id', $blocker->id)->where('following_id', $blockedId);
            })
            ->orWhere(function (Builder $query) use ($blocker, $blockedId): void {
                $query->where('follower_id', $blockedId)->where('following_id', $blocker->id);
            })
            ->delete();

        return ['blocked' => true];
    }

    public function unblock(User $blocker, int $blockedId): void
    {
        UserBlock::query()
            ->where('blocker_id', $blocker->id)
            ->where('blocked_id', $blockedId)
            ->delete();
    }

    /**
     * The accounts this viewer blocked.
     *
     * @return list<int>
     */
    public function blockedIdsFor(int $viewerId): array
    {
        return UserBlock::query()
            ->where('blocker_id', $viewerId)
            ->pluck('blocked_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * The accounts that blocked this viewer.
     *
     * Needed separately from `blockedIdsFor` because the two directions hide
     * different things: this one decides whether the viewer may still *send*
     * (a DM, a comment, a follow), the other decides whether they may see.
     */
    public function blockerIdsFor(int $viewerId): array
    {
        return UserBlock::query()
            ->where('blocked_id', $viewerId)
            ->pluck('blocker_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function isBlocked(User $viewer, int $otherId): bool
    {
        return UserBlock::query()
            ->where('blocker_id', $viewer->id)
            ->where('blocked_id', $otherId)
            ->exists();
    }

    /**
     * True when the pair may not interact in either direction.
     *
     * This is the check that has to be right: it is the difference between a
     * block meaning something and a block being a button that sets a flag.
     */
    public function blocksEitherWay(int $viewerId, int $otherId): bool
    {
        return UserBlock::query()
            ->where(function (Builder $query) use ($viewerId, $otherId): void {
                $query->where('blocker_id', $viewerId)->where('blocked_id', $otherId);
            })
            ->orWhere(function (Builder $query) use ($viewerId, $otherId): void {
                $query->where('blocker_id', $otherId)->where('blocked_id', $viewerId);
            })
            ->exists();
    }

    /**
     * Applies "hide everyone I blocked" to a query on the users table.
     *
     * Takes the viewer's id rather than a precomputed id list so the caller
     * cannot forget to pass it, and so the subquery is evaluated per row
     * against the index on `blocker_id`.
     *
     * @template TBuilder of Builder<User>
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function hideBlockedFromQuery(Builder $query, int $viewerId): Builder
    {
        return $this->hideFromQuery($query, $viewerId, 'users.id');
    }

    /**
     * The same filter against any table, given the column holding the author id.
     *
     * `@param  Builder<User>` covers users, posts and comments; only the author
     * column differs, and getting that column wrong is the failure mode where
     * a block silently stops working, so it is a parameter rather than a
     * convention each caller re-derives.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function hideFromQuery(Builder $query, int $viewerId, string $authorColumn): Builder
    {
        return $query->whereNotExists(
            // `whereNotExists` hands the subquery to the *query* builder, not
            // the Eloquent one, even though the outer call came from a model.
            // Typing this as the Eloquent builder fails at runtime, and only on
            // the read paths that hit it.
            static function (QueryBuilder $inner) use ($viewerId, $authorColumn): void {
                $inner->selectRaw('1')
                    ->from('user_blocks')
                    ->whereColumn('user_blocks.blocked_id', $authorColumn)
                    ->where('user_blocks.blocker_id', $viewerId);
            },
        );
    }

    /**
     * Accounts the viewer must not be able to write to.
     *
     * @return Collection<int, int>
     */
    public function unreachableIdsFor(int $viewerId): Collection
    {
        return collect($this->blockerIdsFor($viewerId))->merge($this->blockedIdsFor($viewerId))->unique();
    }
}
