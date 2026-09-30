<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff roles for the admin/support console. `support` covers the ticket desk
 * and subscription activation; `super_admin` is the escalation/billing role.
 * Both are needed by the `EnsureUserIsStaff` middleware path.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['support', 'super_admin'] as $role) {
            if (DB::table('roles')->where('name', $role)->doesntExist()) {
                DB::table('roles')->insert([
                    'name' => $role,
                    'guard' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['support', 'super_admin'])->delete();
    }
};