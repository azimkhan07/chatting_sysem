<?php

declare(strict_types=1);

namespace Admin\Http\Middleware;

use Admin\Domain\Admin\Models\StaffUser;
use Admin\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A moderator has the admin's read access and no write access at all.
 *
 * Every mutating console route carries this gate, so a moderator can open the
 * whole console to watch what is happening - the plans screen, the report
 * queue, the support desk - and still not change a single row. Support keeps
 * its own narrower write surface (tickets and appeals), admins own the rest.
 */
final class EnsureUserCanOperate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user instanceof StaffUser
            || ! in_array($user->role, StaffUser::operatorRoles(), true)
        ) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Your role is view only. A support agent or an admin has to make this change.',
                403,
            );
        }

        return $next($request);
    }
}
