<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hashtags;

use App\Domain\Hashtags\Services\HashtagService;
use App\Domain\Posts\Contracts\PostService;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

final class HashtagController extends Controller
{
    public function __construct(
        private readonly HashtagService $hashtags,
        private readonly PostService $posts,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('query', ''));

        if ($query === '') {
            return ApiResponse::success(data: ['hashtags' => []]);
        }

        return ApiResponse::success(data: ['hashtags' => $this->hashtags->suggest(ltrim($query, '#'))]);
    }

    public function show(Request $request, string $name): JsonResponse
    {
        $hashtag = $this->hashtags->findByName($name);

        if ($hashtag === null) {
            return ApiResponse::error('NOT_FOUND', 'Hashtag not found.', 404);
        }

        $posts = $this->posts->hashtagFeedFor(
            $request->user(),
            $hashtag,
            limit: $request->integer('limit', 15),
            cursor: $request->query('cursor'),
        );

        return $this->feedPayload($posts, $hashtag->name, $hashtag->posts_count);
    }

    private function feedPayload(CursorPaginator $paginator, string $name, int $postsCount): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'hashtag' => [
                    'name' => $name,
                    'posts_count' => $postsCount,
                ],
                'posts' => PostResource::collection($paginator->items()),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            meta: [
                'has_more' => $paginator->hasMorePages(),
                'limit' => $paginator->perPage(),
            ],
        );
    }
}
