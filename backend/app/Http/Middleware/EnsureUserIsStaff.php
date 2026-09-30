<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows staff (support or admin) through. The user app can never reach the
 * support console, and a support agent has no admin-only surface, but both
 * staff roles share the ticket reply endpoints.
 */
final class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || (! $user->hasRole('support') && ! $user->hasRole('admin'))) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Staff access is required for this action.',
                403,
            );
        }

        return $next($request);
    }
}