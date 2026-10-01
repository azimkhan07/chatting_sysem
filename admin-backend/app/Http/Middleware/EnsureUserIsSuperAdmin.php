<?php

declare(strict_types=1);

namespace Admin\Http\Middleware;

use Admin\Domain\Admin\Models\StaffUser;
use Admin\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The credential inventory: super admin only.
 *
 * This is the narrowest gate in the console, and the narrowest on purpose. The
 * other `admin`-gated screens (team roster, plan pricing, email, gateways) show
 * configuration or a list of colleagues. This one shows, for every signed-in
 * staff account, the address it signed in from and the browser it signed in
 * with. That is a map of who holds console access and from where - a target list
 * for anyone who wants to walk the credential list and work out who to phish.
 *
 * An admin does not need it to do the job: they manage the team through the
 * roster, which already shows each account's active device count. Being able to
 * see that there are three sessions, and being able to see that they are three
 * sessions from three countries, are different amounts of authority, and only
 * the second one belongs to a super admin.
 */
final class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user instanceof StaffUser
            || $user->role !== StaffUser::ROLE_SUPER_ADMIN
        ) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Super admin access is required for this action.',
                403,
            );
        }

        return $next($request);
    }
}
