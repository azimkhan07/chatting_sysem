<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Posts;

use App\Domain\Posts\Contracts\PostService;
use App\Domain\Posts\Models\Post;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PostInteractionController extends Controller
{
    public function __construct(private readonly PostService $postService) {}

    public function like(Post $post, Request $request): JsonResponse
    {
        $count = $this->postService->like($request->user(), $post);

        return ApiResponse::success(['liked' => true, 'likes_count' => $count]);
    }

    public function unlike(Post $post, Request $request): JsonResponse
    {
        $count = $this->postService->unlike($request->user(), $post);

        return ApiResponse::success(['liked' => false, 'likes_count' => $count]);
    }

    public function comments(Post $post, Request $request): JsonResponse
    {
        $paginator = $this->postService->commentsFor(
            $post,
            limit: $request->integer('limit', 20),
            cursor: $request->query('cursor'),
        );

        return ApiResponse::success(
            data: [
                'comments' => CommentResource::collection($paginator->items()),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            meta: [
                'has_more' => $paginator->hasMorePages(),
                'limit' => $paginator->perPage(),
            ],
        );
    }

    public function storeComment(Post $post, StoreCommentRequest $request): JsonResponse
    {
        $comment = $this->postService->addComment(
            $request->user(),
            $post,
            $request->validated('body'),
        );

        return ApiResponse::success(
            data: ['comment' => (new CommentResource($comment->load('user')))->resolve()],
            status: 201,
        );
    }
}
