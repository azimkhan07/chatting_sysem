<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Services\BlockService;
use App\Domain\Posts\Contracts\PostService;
use App\Domain\Social\Models\Follow;
use App\Domain\Social\Services\MentionSuggestionService;
use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

final class UserController extends Controller
{
    public function __construct(
        private readonly SocialService $socialService,
        private readonly PostService $postService,
        private readonly MentionSuggestionService $mentionSuggestions,
        private readonly BlockService $blocks,
    ) {}

    /**
     * Who to offer when someone types "@".
     *
     * Separate from `search` on purpose. Search is a person looking for
     * someone they already have in mind and wants a name match; this is a
     * person mid-sentence who wants to see the accounts they already follow
     * before the famous ones. Answering both with one ordering meant the
     * composer opened on strangers.
     *
     * A bare "@" (empty term) is deliberately allowed, and returns the
     * followed accounts first.
     */
    public function mentionSuggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:30'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $rows = $this->mentionSuggestions->suggest(
            (int) $request->user()->id,
            (string) ($validated['query'] ?? ''),
            (int) ($validated['limit'] ?? 8),
        );

        return ApiResponse::success(data: [
            'users' => $rows
                ->map(static fn (array $row): array => [
                    ...(new UserResource($row['user']))->resolve(),
                    // Lets the composer label a suggestion "Following" and keep
                    // the list visually grouped the way the ranking implies.
                    'is_following' => $row['is_following'],
                ])
                ->all(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('query', ''));

        if (mb_strlen($term) < 2) {
            return ApiResponse::success(data: ['users' => []]);
        }

        // LIKE wildcards are escaped so a query of "%" or "_" cannot match the
        // whole table, and only active accounts are discoverable.
        $escaped = addcslashes(mb_strtolower($term), '%_\\');
        $prefix = $escaped.'%';
        $contains = '%'.$escaped.'%';

        $users = User::query()
            ->where('status', UserStatus::Active->value)
            // Someone you blocked must not be one search keystroke away, or the
            // block is a filter you have to remember to apply everywhere.
            ->whereNotIn('id', $this->blocks->blockedIdsFor((int) $request->user()->id))
            ->where(function ($query) use ($prefix, $contains): void {
                $query->whereRaw('lower(username) like ?', [$prefix])
                    ->orWhereRaw('lower(username) like ?', [$contains])
                    ->orWhereRaw('lower(display_name) like ?', [$contains]);
            })
            ->orderByRaw(
                'CASE
                    WHEN lower(username) LIKE ? THEN 0
                    WHEN lower(display_name) LIKE ? THEN 1
                    ELSE 2
                 END',
                [$prefix, $prefix],
            )
            ->orderBy('username')
            ->limit(8)
            ->get();

        return ApiResponse::success(data: ['users' => UserResource::collection($users)->resolve()]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        // A blocked account's profile is a 404, not a 403. "Forbidden" confirms
        // the account exists, which is itself a disclosure — and a blocked
        // person is meant to be unable to tell whether you are around.
        if ($viewer !== null
            && ($this->blocks->isBlocked($viewer, (int) $user->id) || $this->blocks->isBlocked($user, (int) $viewer->id))
        ) {
            return ApiResponse::error('NOT_FOUND', 'Not found', 404);
        }

        $user->loadCount(['posts', 'followers', 'following']);
        $user->loadExists([
            'followers as is_followed_by_me' => fn ($query) => $query->where('follower_id', $viewer?->id),
        ]);

        return ApiResponse::success(['user' => (new UserResource($user))->resolve()]);
    }

    public function posts(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        // Same rule as `show`: a blocked person is unreachable, not "there but
        // with fewer things on it". The profile 404 is only meaningful if the
        // posts page under it 404s too - without this, blocking someone but
        // opening their username directly would hand back their whole feed.
        if ($viewer !== null
            && ($this->blocks->isBlocked($viewer, (int) $user->id) || $this->blocks->isBlocked($user, (int) $viewer->id))
        ) {
            return ApiResponse::error('NOT_FOUND', 'Not found', 404);
        }

        $paginator = $this->postService->postsBy(
            viewer: $viewer,
            owner: $user,
            limit: PageSize::clamp($request->integer('limit', 15), 15),
            cursor: $request->query('cursor'),
        );

        return ApiResponse::success(
            data: [
                'posts' => PostResource::collection($paginator->items()),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            meta: [
                'has_more' => $paginator->hasMorePages(),
                'limit' => $paginator->perPage(),
            ],
        );
    }

    public function followers(Request $request, User $user): JsonResponse
    {
        $paginator = $this->socialService->followersFor(
            $user->id,
            PageSize::clamp($request->integer('limit', 15), 15),
            $request->query('cursor'),
        );

        /** @var CursorPaginator<int, Follow> $paginator */
        $items = $this->hydrateFollowState($paginator, $request->user()?->id, 'follower');

        return ApiResponse::success(
            data: ['users' => $items, 'next_cursor' => $paginator->nextCursor()?->encode()],
            meta: ['has_more' => $paginator->hasMorePages(), 'limit' => $paginator->perPage()],
        );
    }

    public function following(Request $request, User $user): JsonResponse
    {
        $paginator = $this->socialService->followingFor(
            $user->id,
            PageSize::clamp($request->integer('limit', 15), 15),
            $request->query('cursor'),
        );

        /** @var CursorPaginator<int, Follow> $paginator */
        $items = $this->hydrateFollowState($paginator, $request->user()?->id, 'following');

        return ApiResponse::success(
            data: ['users' => $items, 'next_cursor' => $paginator->nextCursor()?->encode()],
            meta: ['has_more' => $paginator->hasMorePages(), 'limit' => $paginator->perPage()],
        );
    }

    /**
     * Resolves each row with `is_followed_by_me` so a follower list can render
     * follow buttons that start from the right state.
     *
     * @param  CursorPaginator<int, Follow>  $paginator
     * @return list<array<string, mixed>>
     */
    private function hydrateFollowState(CursorPaginator $paginator, ?int $viewerId, string $relation): array
    {
        $members = new EloquentCollection(
            collect($paginator->items())
                ->map(fn (Follow $follow): User => $follow->{$relation})
                ->all(),
        );

        $members->loadExists([
            'followers as is_followed_by_me' => fn ($query) => $query->where('follower_id', $viewerId),
        ]);

        return $members
            ->map(fn (User $member) => (new UserResource($member))->resolve())
            ->values()
            ->all();
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        $result = $this->socialService->follow($request->user()->id, $user->id);

        return ApiResponse::success($result);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $result = $this->socialService->unfollow($request->user()->id, $user->id);

        return ApiResponse::success($result);
    }
}
