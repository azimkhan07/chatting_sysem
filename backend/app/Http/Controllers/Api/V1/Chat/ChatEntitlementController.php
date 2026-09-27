<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Enums\ChatWallpaper;
use App\Domain\Chat\Services\ChatEntitlements;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells the client which chat controls are locked, so the composer can draw a
 * crown on exactly those and the upgrade prompt can name the right feature.
 */
final class ChatEntitlementController extends Controller
{
    public function __construct(private readonly ChatEntitlements $entitlements) {}

    public function index(Request $request): JsonResponse
    {
        $map = $this->entitlements->unlockedMap($request->user());

        return ApiResponse::success([
            'features' => $this->entitlements->catalogue($request->user()),
            'wallpapers' => ChatWallpaper::keys(),
            'unlocked' => array_keys(array_filter($map)),
        ]);
    }
}
