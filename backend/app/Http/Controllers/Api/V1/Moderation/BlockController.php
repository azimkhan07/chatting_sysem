<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Services\BlockService;
use App\Domain\Stories\Services\StoryTrayCache;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlockController extends Controller
{
    public function __construct(
        private readonly BlockService $blocks,
        private readonly StoryTrayCache $trayCache,
    ) {}

    public function store(Request $request, int $userId): JsonResponse
    {
        $this->blocks->block($request->user(), $userId);
        $this->trayCache->forget();

        return ApiResponse::success(data: ['blocked' => true], status: 201);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $this->blocks->unblock($request->user(), $userId);
        // Unblocking has to invalidate too, otherwise their stories stay gone
        // until the TTL runs out and the account looks like the block is
        // slower to undo than to apply.
        $this->trayCache->forget();

        return ApiResponse::success(data: ['blocked' => false]);
    }

    /**
     * The viewer's own block list, so the account can see and undo what it did.
     *
     * "Blocked by" is never returned. Telling a viewer which accounts blocked
     * them turns the block into a way to probe who is avoiding them.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->whereIn('id', $this->blocks->blockedIdsFor((int) $request->user()->id))
            ->orderBy('username')
            ->get();

        return ApiResponse::success(data: [
            'blocked' => UserResource::collection($users)->resolve(),
        ]);
    }
}
