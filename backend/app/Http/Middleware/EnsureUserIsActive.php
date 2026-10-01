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
 *
 * Console staff authenticate against their own database and guard in
 * admin-backend, so nothing resolved by this app's `users` provider is ever a
 * staff record. There is deliberately no staff branch here: reaching for
 * App\Domain\Admin\Models\StaffUser from the app's request pipeline would
 * resolve against the old admin database, whose id space does not match the
 * console's, and would silently attribute audit rows to the wrong person.
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
