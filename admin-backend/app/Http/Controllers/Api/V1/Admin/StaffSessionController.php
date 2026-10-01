<?php

declare(strict_types=1);

namespace Admin\Http\Controllers\Api\V1\Admin;

use Admin\Domain\Admin\Models\StaffUser;
use Admin\Domain\Admin\Services\StaffSessionManager;
use Admin\Http\Controllers\Controller;
use Admin\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who is signed in to the console right now, and from where.
 *
 * Super admin only, enforced by the `super_admin` middleware on the route rather
 * than by a check in here - see EnsureUserIsSuperAdmin for why this is the
 * console's most sensitive read.
 *
 * Two things this deliberately does not do:
 *
 *  - It never returns the token. Only the id, the context and the timestamps, so
 *    a leaked screenshot of this screen is not a set of working credentials.
 *  - It does not geolocate. Turning an address into "Karachi, Pakistan" needs a
 *    GeoIP database, which is tens of megabytes and a periodic update nobody
 *    asked for; the address is shown instead. That is the honest version of
 *    "which area" - it is the thing an operator can paste into a log and act on.
 *
 * Rows that predate the session-context migration are included with nulls rather
 * than filtered out. A blank browser name means "signed in before we recorded
 * one", and hiding those sessions would make the list quietly wrong - it would
 * show an admin as having one device when they had three.
 */
final class StaffSessionController extends Controller
{
    /**
     * Expired-but-not-yet-purged tokens are excluded. Sanctum expires them
     * logically, so a row can outlive its usefulness by however long the
     * cleanup interval is, and listing it as a live session is a false alarm.
     */
    public function index(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->getKey();

        $staff = StaffUser::query()
            ->orderBy('created_at')
            ->get();

        $sessions = [];

        foreach ($staff as $member) {
            foreach ($member->tokens()->orderByDesc('last_used_at')->get() as $token) {
                if ($token->expires_at !== null && $token->expires_at->isPast()) {
                    continue;
                }

                $sessions[] = [
                    'id' => (int) $token->id,
                    'staff_id' => (int) $member->id,
                    'username' => $member->username,
                    'display_name' => $member->display_name,
                    'role' => $member->role,
                    'browser' => $token->browser,
                    'os' => $token->os,
                    'device_type' => $token->device_type,
                    'client' => $token->client,
                    'ip_address' => $token->ip_address,
                    'user_agent' => $token->user_agent,
                    'is_current' => $currentTokenId !== null
                        && (int) $currentTokenId === (int) $token->id,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ];
            }
        }

        // Newest activity first, so the session a super admin is looking for -
        // the one that is not theirs - is at the top rather than buried by their
        // own three devices.
        usort($sessions, static function (array $a, array $b): int {
            $left = $a['last_used_at'] ?? $a['created_at'] ?? '';
            $right = $b['last_used_at'] ?? $b['created_at'] ?? '';

            return strcmp((string) $right, (string) $left);
        });

        return ApiResponse::success([
            'sessions' => $sessions,
            'summary' => [
                'total' => count($sessions),
                'staff_with_sessions' => count(array_unique(array_column($sessions, 'staff_id'))),
                // Sent rather than hard-coded in the console: the cap lives in
                // StaffSessionManager and is env-driven, so a copy in the UI
                // would quietly start lying the day someone raised it.
                'device_cap' => (new StaffSessionManager())->maxDevices(),
                // Sessions whose context was recorded after the migration. Rows
                // without it are shown, not hidden - see the class docblock.
                'unrecorded_context' => count(array_filter(
                    $sessions,
                    static fn (array $s): bool => $s['ip_address'] === null,
                )),
            ],
        ]);
    }
}
