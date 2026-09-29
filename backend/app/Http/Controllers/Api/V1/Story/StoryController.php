<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Story;

use App\Domain\Stories\Data\CreateStoryData;
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
        $viewerId = $request->user()?->id;

        $stories = $this->trayCache->remember($viewerId, fn (): array => array_values(array_map(
            fn (array $group): array => [
                'user' => (new UserResource($group['user']))->resolve(),
                'stories' => StoryResource::collection($group['stories'])->resolve(),
            ],
            $this->storyService->feed($request->user()),
        )));

        return ApiResponse::success(data: ['stories' => $stories]);
    }

    public function store(CreateStoryRequest $request): JsonResponse
    {
        $songId = $request->validated('song_id');

        $story = $this->storyService->create(
            $request->user(),
            new CreateStoryData(
                file: $request->file('media'),
                mediaUrl: $request->validated('media_url'),
                caption: $request->validated('caption'),
                effects: $request->validated('effects'),
                songId: $songId !== null ? (int) $songId : null,
                textStyle: $request->validated('text_style'),
                location: $request->validated('location'),
            ),
        );
        // `mentions` is loaded here because the composer knows it just wrote a
        // mention and the response is what refills the tray, so the person who
        // was tagged is visible in the story they were tagged in. Without it the
        // create response would carry `tagged_users: null` while the feed, which
        // does load them, showed the tagged line - the same story reading two
        // ways depending on where you saw it first.
        $story->load(['song', 'mentions']);
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
