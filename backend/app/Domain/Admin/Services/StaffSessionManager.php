<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Staff (admin/support) token sessions.
 *
 * Rules, per product:
 *  - No more than 3 staff devices per staff user at once.
 *  - Logging in from a new device supersedes the oldest session when at the
 *    cap, so the "signed in elsewhere" alert has something real to show.
 *  - Staff tokens are named `staff`, which both self-documents the row in
 *    personal_access_tokens and keeps staff sessions separable from app
 *    sessions in the same table.
 */
final class StaffSessionManager
{
    private const MAX_DEVICES = 3;

    private const TTL_DAYS = 12;

    /**
     * Issues a staff token. Returns the plain token and the number of devices
     * now signed in after this login.
     *
     * @return array{token?: string, superseded: bool, active_devices: int}
     */
    public function issueFor(User $user, string $device): array
    {
        $existing = $user->tokens()
            ->where('name', 'like', 'staff · %')
            ->orderBy('created_at')
            ->get();

        $superseded = false;

        // Already at the cap: kick the oldest session to make room.
        if ($existing->count() >= self::MAX_DEVICES) {
            $oldest = $existing->first();
            if ($oldest !== null) {
                $oldest->delete();
                $superseded = true;
            }
        }

        $token = $user->createToken(
            'staff · '.Str::limit(trim($device) === '' ? 'console' : trim($device), 120),
            ['*'],
            CarbonImmutable::now()->addDays(self::TTL_DAYS),
        );

        $activeDevices = (int) $user->tokens()
            ->where('name', 'like', 'staff · %')
            ->count();

        return [
            'token' => $token->plainTextToken,
            'superseded' => $superseded,
            'active_devices' => $activeDevices,
        ];
    }

    public function ttlSeconds(): int
    {
        return self::TTL_DAYS * 86400;
    }
}