<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Moderation\Models\AccountAppeal;
use App\Domain\Moderation\Services\AppealService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppealRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Submitting a suspension appeal.
 *
 * Lives in the auth namespace because the filer is signed out: suspension
 * revoked every token, so this is an unauthenticated route that proves
 * identity with identifier + password (mirroring account reactivation).
 */
final class AppealController extends Controller
{
    public function __construct(private readonly AppealService $appeals) {}

    public function __invoke(AppealRequest $request): JsonResponse
    {
        $appeal = $this->appeals->file(
            identifier: (string) $request->validated('identifier'),
            password: (string) $request->validated('password'),
            message: (string) $request->validated('message'),
        );

        return ApiResponse::success([
            'appeal' => [
                'id' => $appeal->id,
                'status' => $appeal->status,
                'message' => $appeal->message,
                'created_at' => $appeal->created_at?->toIso8601String(),
            ],
        ], status: 201);
    }
}