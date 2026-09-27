<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Story;

use App\Domain\Stories\Models\Story;
use App\Domain\Stories\Services\StoryService;
use App\Domain\Stories\Services\StoryTrayCache;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Story\CreateStoryRequest;
use App\Http\Resources\StoryResource;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoryController extends Controller
{
    public function __construct(
        private readonly StoryService $storyService,
        private readonly StoryTrayCache $trayCache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stories = $this->trayCache->remember(fn (): array => array_values(array_map(
            fn (array $group): array => [
                'user' => (new UserResource($group['user']))->resolve(),
                'stories' => StoryResource::collection($group['stories'])->resolve(),
            ],
            $this->storyService->feed(),
        )));

        return ApiResponse::success(data: ['stories' => $stories]);
    }

    public function store(CreateStoryRequest $request): JsonResponse
    {
        $songId = $request->validated('song_id');

        $story = $this->storyService->create(
            $request->user(),
            $request->file('media'),
            $request->validated('media_url'),
            $request->validated('caption'),
            $request->validated('effects'),
            $songId !== null ? (int) $songId : null,
            $request->validated('text_style'),
        );
        $story->load('song');
        $this->trayCache->forget();

        return ApiResponse::success(
            data: ['story' => (new StoryResource($story))->resolve()],
            status: 201,
        );
    }

    public function destroy(Request $request, Story $story): JsonResponse
    {
        $this->storyService->destroy($request->user(), $story);
        $this->trayCache->forget();

        return ApiResponse::success(data: []);
    }
}
