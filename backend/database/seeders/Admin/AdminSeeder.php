<?php

declare(strict_types=1);

namespace Database\Seeders\Admin;

use App\Domain\Admin\Models\StaffUser;
use Illuminate\Database\Seeder;

/**
 * Idempotent bootstrap for the console database on a live box.
 *
 * Creates the first admin account in `staff_users` (the console database, not
 * the app users table). Every other staff account — including the support team
 * — is created later from the admin console's Settings screen, which writes
 * support accounts to both `staff_users` and `support_staff`.
 *
 * Usage:
 *   php artisan db:seed --class="Database\Seeders\Admin\AdminSeeder" --database=admin
 */
final class AdminSeeder extends Seeder
{
    public const USERNAME = 'admin';

    public const PASSWORD = 'admin123';

    public function run(): void
    {
        $staff = StaffUser::query()->where('username', self::USERNAME)->first();

        if ($staff !== null) {
            $this->command?->info(sprintf('Admin "%s" already exists — skipped.', self::USERNAME));

            return;
        }

        StaffUser::query()->create([
            'username' => self::USERNAME,
            'display_name' => 'Administrator',
            'password' => self::PASSWORD,
            'role' => StaffUser::ROLE_ADMIN,
        ]);

        $this->command?->warn(sprintf('Created admin "%s". Change its password at first login.', self::USERNAME));
    }
}