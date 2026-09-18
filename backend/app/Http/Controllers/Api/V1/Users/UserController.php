<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Contracts\PostService;
use App\Domain\Social\Models\Follow;
use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    public function __construct(
        private readonly SocialService $socialService,
        private readonly PostService $postService,
    ) {}

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
            limit: $request->integer('limit', 15),
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
            $request->integer('limit', 15),
            $request->query('cursor'),
        );

        $items = $paginator
            ->getCollection()
            ->map(fn (Follow $follow) => (new UserResource($follow->follower))->resolve());

        return ApiResponse::success(
            data: ['users' => $items->values(), 'next_cursor' => $paginator->nextCursor()?->encode()],
            meta: ['has_more' => $paginator->hasMorePages(), 'limit' => $paginator->perPage()],
        );
    }

    public function following(Request $request, User $user): JsonResponse
    {
        $paginator = $this->socialService->followingFor(
            $user->id,
            $request->integer('limit', 15),
            $request->query('cursor'),
        );

        $items = $paginator
            ->getCollection()
            ->map(fn (Follow $follow) => (new UserResource($follow->following))->resolve());

        return ApiResponse::success(
            data: ['users' => $items->values(), 'next_cursor' => $paginator->nextCursor()?->encode()],
            meta: ['has_more' => $paginator->hasMorePages(), 'limit' => $paginator->perPage()],
        );
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
