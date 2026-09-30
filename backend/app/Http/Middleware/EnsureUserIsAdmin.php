<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Admin\Models\StaffUser;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user instanceof StaffUser || $user->role !== StaffUser::ROLE_ADMIN) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Admin access is required for this action.',
                403,
            );
        }

        return $next($request);
    }
}
