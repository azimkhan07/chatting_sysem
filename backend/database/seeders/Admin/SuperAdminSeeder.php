<?php

declare(strict_types=1);

namespace Database\Seeders\Admin;

use App\Domain\Admin\Models\StaffUser;
use Illuminate\Database\Seeder;

/**
 * Idempotent bootstrap for the console lead account.
 *
 * A super_admin is the escalation role: it can resolve appeals (which a plain
 * admin cannot) and create staff, so the desk still runs when the admin is
 * unavailable. Lives in the console database with every other staff account.
 *
 * Usage:
 *   php artisan db:seed --class="Database\Seeders\Admin\SuperAdminSeeder" --database=admin
 */
final class SuperAdminSeeder extends Seeder
{
    public const USERNAME = 'superadmin';

    public const PASSWORD = 'super123';

    public function run(): void
    {
        $staff = StaffUser::query()->where('username', self::USERNAME)->first();

        if ($staff !== null) {
            $this->command?->info(sprintf('Super admin "%s" already exists - skipped.', self::USERNAME));

            return;
        }

        StaffUser::query()->create([
            'username' => self::USERNAME,
            'display_name' => 'Super Administrator',
            'password' => self::PASSWORD,
            'role' => StaffUser::ROLE_SUPER_ADMIN,
        ]);

        $this->command?->warn(sprintf('Created super admin "%s". Change its password at first login.', self::USERNAME));
    }
}