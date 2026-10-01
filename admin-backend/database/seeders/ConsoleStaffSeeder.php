<?php

declare(strict_types=1);

namespace Database\Seeders;

use Admin\Domain\Admin\Models\StaffUser;
use Illuminate\Database\Seeder;

/**
 * Idempotent bootstrap for the console's first accounts.
 *
 * The super_admin is the root of the console: it is the only role that can mint
 * another admin, and it resolves appeals when the support desk is short. The
 * admin and the read-only moderator come from here too, so a fresh box has a
 * working trio from one command — and superadmin is the account you log in with
 * first.
 *
 * Passwords are the only thing in this repository that must never be reused. The
 * seeder is safe to re-run: an existing account keeps its current password, so
 * a deploy never silently resets a password someone has since changed.
 *
 * Usage:
 *   php artisan db:seed
 */
final class ConsoleStaffSeeder extends Seeder
{
    public const SUPER_ADMIN_USERNAME = 'superadmin';

    public const SUPER_ADMIN_PASSWORD = 'super123';

    private const ADMIN_USERNAME = 'admin';

    private const ADMIN_PASSWORD = 'admin123';

    private const MODERATOR_USERNAME = 'moderator';

    private const MODERATOR_PASSWORD = 'moderator123';

    private const SUPPORT_USERNAME = 'support';

    private const SUPPORT_PASSWORD = 'support123';

    public function run(): void
    {
        $this->ensureStaff(
            self::SUPER_ADMIN_USERNAME,
            'Super Administrator',
            self::SUPER_ADMIN_PASSWORD,
            StaffUser::ROLE_SUPER_ADMIN,
        );

        // The admin account predates the super admin, and is created here so one
        // command provisions the whole console.
        $this->ensureStaff(self::ADMIN_USERNAME, 'Administrator', self::ADMIN_PASSWORD, StaffUser::ROLE_ADMIN);

        // View-only: reads the everyday console screens, writes nothing, and
        // sees neither the team roster nor the configuration screens.
        $this->ensureStaff(self::MODERATOR_USERNAME, 'Moderator', self::MODERATOR_PASSWORD, StaffUser::ROLE_MODERATOR);

        // The support desk: the tickets, appeals and subscription queues. It is
        // seeded rather than left to the Settings screen so that a fresh box has
        // one account per role, which is what the role matrix is written
        // against - a role with no account cannot be tested, and an untested role
        // is a role whose permissions are a guess.
        $this->ensureStaff(self::SUPPORT_USERNAME, 'Support Agent', self::SUPPORT_PASSWORD, StaffUser::ROLE_SUPPORT);
    }

    private function ensureStaff(string $username, string $displayName, string $password, string $role): void
    {
        if (StaffUser::query()->where('username', $username)->exists()) {
            $this->command?->info(sprintf('Staff "%s" already exists — skipped.', $username));

            return;
        }

        StaffUser::query()->create([
            'username' => $username,
            'display_name' => $displayName,
            // Plaintext on purpose. The model casts `password` as `hashed`, and
            // the cast skips values that are already hashes — so passing a
            // pre-made Hash::make() here would work by accident, while passing
            // plaintext is the same path StaffManagementController uses. One
            // convention, not two.
            'password' => $password,
            'role' => $role,
        ]);

        $this->command?->warn(sprintf('Created %s "%s". Change its password at first login.', $role, $username));
    }
}
