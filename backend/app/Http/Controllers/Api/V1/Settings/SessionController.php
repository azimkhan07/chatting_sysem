<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success([
            // `collection()->resolve()` returns a plain array; wrapping the
            // collection in a single resource would serialise the wrapper.
            'sessions' => SessionResource::collection($sessions)->resolve(),
        ]);
    }

    public function destroy(Request $request, int $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($session === $user->currentTokenId()) {
            // Signing out of the session you are using is `logout`, not a
            // revoke - saying so beats a confusing 404.
            return ApiResponse::error(
                'CURRENT_SESSION',
                'This is the session you are using. Sign out instead.',
                409,
                'session',
            );
        }

        $token = $user->tokens()->whereKey($session)->first();

        if ($token === null) {
            return ApiResponse::error('NOT_FOUND', 'Session not found.', 404);
        }

        $token->delete();

        return ApiResponse::success(['revoked' => true]);
    }

    /**
     * Sign out everywhere else, keeping the current session alive.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentId = $user->currentTokenId();

        $revoked = (int) $user->tokens()
            ->when($currentId !== null, fn ($query) => $query->whereKeyNot($currentId))
            ->delete();

        return ApiResponse::success([
            'revoked' => true,
            'other_sessions_revoked' => $revoked,
        ]);
    }
}
