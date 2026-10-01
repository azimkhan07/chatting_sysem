<?php

declare(strict_types=1);

namespace Admin\Http\Middleware;

use Admin\Domain\Admin\Models\StaffUser;
use Admin\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    /**
     * Console team management and configuration: an admin and above.
     *
     * This is deliberately narrower than read access. A moderator already has
     * the whole product read surface through the `staff` gate, but the team
     * roster is not product data - it lists colleague usernames, roles and
     * sign-in times - and a view-only role has no reason to hold it. Including
     * a moderator here would quietly hand out the console's own credential
     * inventory, so the roster stays with the roles that manage the team.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user instanceof StaffUser
            || ! in_array($user->role, [StaffUser::ROLE_ADMIN, StaffUser::ROLE_SUPER_ADMIN], true)
        ) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Admin access is required for this action.',
                403,
            );
        }

        return $next($request);
    }
}
