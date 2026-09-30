<?php

declare(strict_types=1);

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Exceptions\StaffNotAllowedException;
use App\Domain\Admin\Services\StaffSessionManager;
use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AccountDeactivatedException;
use App\Domain\Auth\Exceptions\AccountDisabledException;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Staff (admin/support) console login.
 *
 * Credential + status checks mirror the app login, but the token lifecycle is
 * the console's own: a `staff ·` token, capped at 3 devices, with a
 * "superseded" flag when an older session was replaced.
 */
final class StaffLoginAction
{
    private const STAFF_ROLES = ['admin', 'super_admin', 'support'];

    public function __construct(
        private readonly AuthRepository $repository,
        private readonly StaffSessionManager $sessions,
    ) {}

    /**
     * @return array{user: User, token: string, superseded: bool, active_devices: int, expires_in: int}
     *
     * @throws StaffNotAllowedException
     */
    public function handle(string $identifier, string $password, string $device): array
    {
        $user = $this->repository->findByIdentifier($identifier);

        $this->assertCredentialsValid($user, $password);

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        if ($user->status !== UserStatus::Active) {
            $user->tokens()->delete();
            throw new AccountDisabledException($user);
        }

        if ($user->isDeactivated()) {
            $user->tokens()->delete();
            throw new AccountDeactivatedException;
        }

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

    private function isStaff(User $user): bool
    {
        foreach (self::STAFF_ROLES as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    private function assertCredentialsValid(?User $user, string $password): void
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