<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Services;

use Admin\Domain\Admin\Models\StaffUser;
use Admin\Domain\Admin\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Staff token sessions.
 *
 * Rules, per product:
 *  - No more than 3 staff devices per staff user at once.
 *  - Logging in from a new device supersedes the oldest session when at the
 *    cap, so the "signed in elsewhere" alert has something real to show.
 *  - Tokens are named `staff · device`, which self-documents the row and keeps
 *    console sessions identifiable in the table.
 *
 * How long a session lasts comes from `sanctum.expiration`, not from a constant
 * here. When both existed they drifted, and the app spent a while claiming a
 * 12-hour session limit in its config while handing out 12-day tokens - a
 * security control that only existed in the documentation. Sanctum applies its
 * own config when a token is created without an explicit expiry, so the code
 * has to ask for that by not passing one.
 *
 * The tokens live in this app's own database. The main app has no connection
 * to them, so revoking a console session is something only this service can do.
 */
final class StaffSessionManager
{
    /** Fallback only, for when STAFF_MAX_DEVICES is not set in the environment. */
    private const MAX_DEVICES = 3;
    /**
     * Issues a staff token. Returns the plain token and the number of devices
     * now signed in after this login.
     *
     * The request is taken whole rather than a user-agent string, because the
     * session record needs the address and the client as well as the agent, and
     * every one of those is only readable off the request. Passing them as loose
     * arguments was how they would end up in the wrong order at some point.
     *
     * @return array{token?: string, superseded: bool, active_devices: int}
     */
    public function issueFor(StaffUser $user, Request $request): array
    {
        $userAgent = (string) $request->userAgent();
        $existing = $user->tokens()
            ->where('name', 'like', 'staff · %')
            ->orderBy('created_at')
            ->get();

        $superseded = false;

        // Already at the cap: kick the oldest session to make room.
        if ($existing->count() >= $this->maxDevices()) {
            $oldest = $existing->first();
            if ($oldest !== null) {
                $oldest->delete();
                $superseded = true;
            }
        }

        $token = $user->createToken(
            'staff · '.Str::limit(trim($userAgent) === '' ? 'console' : trim($userAgent), 120),
            ['*'],
        );

        // Recorded on the row, not derived on read. A session list is evidence
        // about a sign-in that already happened; re-deriving it at render time
        // means a parser change silently rewrites history.
        $token->accessToken?->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            ...$this->sessionContext($request, $userAgent),
        ])->save();

        $activeDevices = (int) $user->tokens()
            ->where('name', 'like', 'staff · %')
            ->count();

        return [
            'token' => $token->plainTextToken,
            'superseded' => $superseded,
            'active_devices' => $activeDevices,
        ];
    }

    /**
     * @return array{browser: string|null, os: string|null, device_type: string, client: string}
     */
    private function sessionContext(Request $request, string $userAgent): array
    {
        $parsed = UserAgentParser::parse($userAgent);

        return [
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'device_type' => $parsed['device_type'],
            // A native console app identifies itself; a browser cannot, so the
            // absence of the header is itself the answer: this is the web app.
            'client' => self::clientLabel($request, $parsed['is_bot']),
        ];
    }

    private static function clientLabel(Request $request, bool $isBot): string
    {
        $declared = trim((string) $request->header('X-Console-Client', ''));

        if ($declared !== '') {
            return Str::limit($declared, 30, '');
        }

        return $isBot ? 'bot' : 'web';
    }

    public function ttlSeconds(): int
    {
        return max(1, (int) config('sanctum.expiration')) * 60;
    }

    public function maxDevices(): int
    {
        return max(1, (int) env('STAFF_MAX_DEVICES', self::MAX_DEVICES));
    }
}
