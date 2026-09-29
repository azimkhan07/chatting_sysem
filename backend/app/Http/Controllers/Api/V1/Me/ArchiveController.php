<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Domain\Archive\Contracts\ArchiveService;
use App\Domain\Posts\Models\Post;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\StoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ArchiveController extends Controller
{
    public function __construct(
        private readonly ArchiveService $archives,
    ) {}

    public function calendar(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year', (string) Carbon::now()->year));

        return response()->json([
            'year' => $year,
            'days' => $this->archives->calendar((int) $request->user()->id, $year),
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        $day = $this->validatedDay($request);

        return response()->json([
            'date' => $day->toDateString(),
            'posts' => PostResource::collection(
                collect($this->archives->archivedPostsOn((int) $request->user()->id, $day)),
            ),
        ]);
    }

    public function stories(Request $request): JsonResponse
    {
        $day = $this->validatedDay($request);

        return response()->json([
            'date' => $day->toDateString(),
            'stories' => StoryResource::collection(
                collect($this->archives->archivedStoriesOn((int) $request->user()->id, $day)),
            ),
        ]);
    }

    public function archivePost(Request $request, Post $post): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        if ((int) $post->user_id !== $userId) {
            return response()->json(['message' => 'You can only archive your own post.'], 403);
        }

        $this->archives->archivePost($post, $userId);

        return response()->json(['archived' => true, 'id' => $post->id]);
    }

    public function unarchivePost(Request $request, Post $post): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        if ((int) $post->user_id !== $userId) {
            return response()->json(['message' => 'You can only unarchive your own post.'], 403);
        }

        $this->archives->unarchivePost($post, $userId);

        return response()->json(['archived' => false, 'id' => $post->id]);
    }

    private function validatedDay(Request $request): Carbon
    {
        $date = $request->query('date');

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException('A `date` query parameter in Y-m-d form is required.');
        }

        return Carbon::createFromFormat('Y-m-d', $date) ?: throw new InvalidArgumentException('Invalid date.');
    }
}
