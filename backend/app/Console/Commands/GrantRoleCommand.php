<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class GrantRoleCommand extends Command
{
    protected $signature = 'role:grant {email} {role=admin}';

    protected $description = 'Attach a role (default: admin) to a user by e-mail. Use it to bootstrap the first admin.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleName = Str::lower((string) $this->argument('role'));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found for e-mail: {$email}");

            return self::FAILURE;
        }

        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard' => 'web'],
            ['name' => $roleName, 'guard' => 'web'],
        );

        if ($user->roles()->where('roles.id', $role->id)->exists()) {
            $this->line("The user already has the '{$roleName}' role.");

            return self::SUCCESS;
        }

        $user->roles()->attach($role->id);

        $this->info("Granted '{$roleName}' to {$user->email}.");

        return self::SUCCESS;
    }
}
