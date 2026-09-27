<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Posts\Contracts\PostService;
use App\Domain\Social\Models\Follow;
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
    ) {}

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
        $user->loadCount(['posts', 'followers', 'following']);
        $user->loadExists([
            'followers as is_followed_by_me' => fn ($query) => $query->where('follower_id', $viewer?->id),
        ]);

        return ApiResponse::success(['user' => (new UserResource($user))->resolve()]);
    }

    public function posts(Request $request, User $user): JsonResponse
    {
        $paginator = $this->postService->postsBy(
            viewer: $request->user(),
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
