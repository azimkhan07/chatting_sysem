<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Actions;

use Admin\Domain\Admin\Exceptions\InvalidCredentialsException;
use Admin\Domain\Admin\Exceptions\StaffNotAllowedException;
use Admin\Domain\Admin\Models\StaffUser;
use Admin\Domain\Admin\Services\StaffSessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Console login.
 *
 * Credentials resolve against this app's own database (`staff_users`), not the
 * main app's users table — a brute-forced or leaked app DB cannot sign someone
 * in here, or even confirm, that a console account exists. The token lifecycle
 * is the console's own too: a `staff ·` token, capped at 3 devices, with a
 * "superseded" flag when an older session was replaced.
 */
final class StaffLoginAction
{
    /**
     * Every console role, including the view-only moderator: a moderator is a
     * real staff account whose write access is closed by the `operations`
     * gate, not one that should fail to sign in.
     *
     * @var list<string>
     */
    private const STAFF_ROLES = [
        StaffUser::ROLE_ADMIN,
        StaffUser::ROLE_SUPER_ADMIN,
        StaffUser::ROLE_SUPPORT,
        StaffUser::ROLE_MODERATOR,
    ];

    public function __construct(
        private readonly StaffSessionManager $sessions,
    ) {}

    /**
     * @return array{user: StaffUser, token: string, superseded: bool, active_devices: int, expires_in: int}
     *
     * @throws StaffNotAllowedException
     * @throws InvalidCredentialsException
     */
    public function handle(string $identifier, string $password, Request $request): array
    {
        $user = $this->findByIdentifier($identifier);

        $this->assertCredentialsValid($user, $password);

        if (! $this->isStaff($user)) {
            throw new StaffNotAllowedException;
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        $session = $this->sessions->issueFor($user, $request);

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
