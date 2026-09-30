<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Admin\Models\StaffUser;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The support desk owns appeal decisions: it approves/rejects suspension
 * appeals while an admin sees the queue in view mode only. super_admin overrides
 * the support team, so a lead can still act when the desk is short-staffed.
 */
final class EnsureUserIsAppealHandler
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user instanceof StaffUser
            || ! in_array($user->role, [StaffUser::ROLE_SUPPORT, StaffUser::ROLE_SUPER_ADMIN], true)
        ) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Only the support team can resolve appeals.',
                403,
            );
        }

        return $next($request);
    }
}