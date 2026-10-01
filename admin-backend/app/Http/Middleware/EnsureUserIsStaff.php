<?php

declare(strict_types=1);

namespace Admin\Http\Middleware;

use Admin\Domain\Admin\Models\StaffUser;
use Admin\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows console staff through. This app serves the console and nothing else,
 * so the only account that can be signed in here is a staff one; a main-app
 * user token has nothing to resolve to and is rejected as unauthorised.
 */
final class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user instanceof StaffUser
            || ! in_array($user->role, [
                StaffUser::ROLE_SUPPORT,
                StaffUser::ROLE_MODERATOR,
                StaffUser::ROLE_ADMIN,
                StaffUser::ROLE_SUPER_ADMIN,
            ], true)
        ) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Staff access is required for this action.',
                403,
            );
        }

        return $next($request);
    }
}
