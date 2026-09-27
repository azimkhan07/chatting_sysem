<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended or banned account keeps its Sanctum token until it expires, so
 * every authenticated request has to re-check the status. Without this a ban is
 * only cosmetic.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status !== UserStatus::Active) {
            $user->tokens()->delete();

            return ApiResponse::error(
                'ACCOUNT_DISABLED',
                $user->status === UserStatus::Banned
                    ? 'This account has been banned.'
                    : 'This account has been suspended.',
                403,
            );
        }

        return $next($request);
    }
}
