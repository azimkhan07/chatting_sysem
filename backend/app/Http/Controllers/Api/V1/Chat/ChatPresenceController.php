<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\PresenceService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatPresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presence) {}

    /**
     * Explicit presence heartbeat for clients that are sitting still with no
     * other traffic to piggyback on.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        return ApiResponse::success($this->presence->heartbeat($request->user()));
    }
}
