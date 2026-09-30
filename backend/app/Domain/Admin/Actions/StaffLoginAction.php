<?php

declare(strict_types=1);

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Exceptions\StaffNotAllowedException;
use App\Domain\Admin\Models\StaffUser;
use App\Domain\Admin\Services\StaffSessionManager;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;

/**
 * Staff (admin/support) console login.
 *
 * Credentials resolve against the console database (`staff_users`), not the
 * app users table — a brute-forced or leaked app DB cannot sign someone in, or
 * even confirm, a staff account exists. The token lifecycle is the console's
 * own: a `staff ·` token, capped at 3 devices, with a "superseded" flag when
 * an older session was replaced.
 */
final class StaffLoginAction
{
    private const STAFF_ROLES = [StaffUser::ROLE_ADMIN, StaffUser::ROLE_SUPER_ADMIN, StaffUser::ROLE_SUPPORT];

    public function __construct(
        private readonly StaffSessionManager $sessions,
    ) {}

    /**
     * @return array{user: StaffUser, token: string, superseded: bool, active_devices: int, expires_in: int}
     *
     * @throws StaffNotAllowedException
     */
    public function handle(string $identifier, string $password, string $device): array
    {
        $user = $this->findByIdentifier($identifier);

        $this->assertCredentialsValid($user, $password);

        if (! $this->isStaff($user)) {
            throw new StaffNotAllowedException;
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        $session = $this->sessions->issueFor($user, $device);

        return [
            'user' => $user,
            'token' => $session['token'],
            'superseded' => $session['superseded'],
            'active_devices' => $session['active_devices'],
            'expires_in' => $this->sessions->ttlSeconds(),
        ];
    }

    private function findByIdentifier(string $identifier): ?StaffUser
    {
        return StaffUser::query()->where('username', mb_strtolower(trim($identifier)))->first();
    }

    private function isStaff(StaffUser $user): bool
    {
        return in_array($user->role, self::STAFF_ROLES, true);
    }

    private function assertCredentialsValid(?StaffUser $user, string $password): void
    {
        $matches = $user !== null && (
            Hash::check($password, $user->password)
            || Hash::check(trim($password), $user->password)
        );

        if (! $matches) {
            throw new InvalidCredentialsException;
        }
    }
}