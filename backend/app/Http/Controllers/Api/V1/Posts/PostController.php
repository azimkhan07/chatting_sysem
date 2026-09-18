<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Posts;

use App\Domain\Posts\Contracts\PostService;
use App\Domain\Posts\Data\CreatePostData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Resources\PostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PostController extends Controller
{
    public function __construct(private readonly PostService $postService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->postService->feedFor(
            $request->user(),
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

    public function store(CreatePostRequest $request): JsonResponse
    {
        $post = $this->postService->create(
            $request->user(),
            new CreatePostData(
                body: (string) $request->validated('body') ?: '',
                media: $request->file('media') ?? [],
            ),
        );
        $post->load(['user', 'media']);

        return ApiResponse::success(
            data: ['post' => (new PostResource($post))->resolve()],
            status: 201,
        );
    }
}
