<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Services\BlockService;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who to offer when someone types "@".
 *
 * The ordering is the whole feature, and it is deliberately two queries rather
 * than one clever one. Instagram does not show a person the alphabetically
 * nearest accounts: it shows the people they already know, and only then the
 * famous ones. Encoding that as a single `ORDER BY is_following DESC` over the
 * whole matching set would need a correlated subquery per row, and would still
 * rank a 3-follower account above a 400k one among the people you follow.
 *
 * So: take the people you follow first, then fill the remaining slots by
 * reach. Two small indexed queries beat one honest but slow scan.
 */
final class MentionSuggestionService
{
    /**
     * Matches `@name` in free text.
     *
     * Anchored at a word boundary so an email address (`me@example.com`) is not
     * read as a mention of `example.com`, and limited to the character set
     * usernames actually use.
     */
    private const MENTION_PATTERN = '/(?:^|\s)@([A-Za-z0-9_.]{1,30})/';

    /**
     * The usernames mentioned in a body, lowercased and de-duplicated.
     *
     * @return list<string>
     */
    public static function parse(string $body): array
    {
        if (preg_match_all(self::MENTION_PATTERN, $body, $matches) < 1) {
            return [];
        }

        $names = array_map(
            static fn (string $name): string => mb_strtolower(rtrim($name, " \t\n\r\0\x0B.")),
            $matches[1],
        );

        return array_values(array_unique(array_filter($names, static fn (string $n): bool => $n !== '')));
    }

    public function __construct(
        private readonly BlockService $blocks,
    ) {}

    /**
     * @return Collection<int, array{user: User, is_following: bool}>
     */
    public function suggest(int $viewerId, string $term, int $limit = 8): Collection
    {
        $term = trim($term);
        $limit = max(1, min($limit, 20));

        // 1. People the viewer already follows. Bounded by the limit, so this
        //    can never be the expensive half — the `follows` index does the work.
        /** @var EloquentCollection<int, User> $followed */
        $followed = $this->baseQuery($viewerId)
            ->join('follows', 'follows.following_id', '=', 'users.id')
            ->where('follows.follower_id', $viewerId)
            ->where($this->termFilter($term))
            ->orderBy('users.username')
            ->limit($limit)
            ->get(['users.*']);

        $rows = $this->rows($followed, true);

        if ($rows->count() >= $limit) {
            return $rows->values();
        }

        // 2. Fill the rest by reach. Follower count is computed rather than
        //    stored, so the ordering is a subquery. With a term filter and a
        //    small LIMIT this stays cheap.
        $seen = $followed->modelKeys();

        /** @var EloquentCollection<int, User> $others */
        $others = $this->baseQuery($viewerId)
            ->whereNotIn('users.id', $seen)
            ->where($this->termFilter($term))
            ->orderByDesc($this->followerCountSubquery())
            ->orderBy('users.username')
            ->limit($limit - $rows->count())
            ->get();

        return $rows->concat($this->rows($others, false))->values();
    }

    /**
     * Pairs each account with whether the viewer already follows it, so the
     * composer can label a suggestion without a second request per user.
     *
     * A loop rather than `map()` because the two halves of this list are
     * built with different `is_following` literals, and a closure returning
     * `true` types as `true`, not as `bool`.
     *
     * @param  EloquentCollection<int, User>  $users
     * @return Collection<int, array{user: User, is_following: bool}>
     */
    private function rows(EloquentCollection $users, bool $isFollowing): Collection
    {
        $rows = [];

        foreach ($users as $user) {
            $rows[] = ['user' => $user, 'is_following' => $isFollowing];
        }

        return new Collection($rows);
    }

    /**
     * Active accounts, minus the viewer's own and minus the accounts they
     * blocked.
     *
     * A blocked account surfacing in the tag picker is the smallest possible
     * leak and the easiest one: tagging is the one signal that says "I want
     * your attention", which is precisely what the block refused.
     */
    private function baseQuery(int $viewerId): Builder
    {
        return $this->blocks->hideBlockedFromQuery(
            User::query()
                ->where('users.status', UserStatus::Active->value)
                ->where('users.id', '!=', $viewerId),
            $viewerId,
        );
    }

    /**
     * An empty term matches everyone, which is what makes a bare "@" show the
     * people you follow rather than an empty box.
     */
    private function termFilter(string $term): \Closure
    {
        if ($term === '') {
            return static fn (): bool => true;
        }

        // Escaped so a term of "%" cannot match every account.
        $escaped = addcslashes(mb_strtolower($term), '%_\\');

        return static fn (Builder $query): Builder => $query->where(
            static function (Builder $inner) use ($escaped): void {
                $inner->whereRaw('lower(users.username) like ?', [$escaped.'%'])
                    ->orWhereRaw('lower(users.display_name) like ?', ['%'.$escaped.'%']);
            },
        );
    }

    /**
     * How many accounts follow this one.
     *
     * A correlated subquery rather than a `withCount` alias, because the alias
     * is not reliably orderable in the same statement on every engine we run.
     *
     * Wrapped in `DB::raw` because a plain string is treated as a column name
     * and gets quoted into `"select count(*) ..."`, which is not valid SQL.
     */
    private function followerCountSubquery(): Expression
    {
        return DB::raw(
            '(select count(*) from follows where follows.following_id = users.id)',
        );
    }
}
