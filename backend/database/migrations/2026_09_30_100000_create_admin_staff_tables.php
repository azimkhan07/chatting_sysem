<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The console database — physically separate from the app database.
 *
 * `staff_users` is where every admin/support sign-in resolves; `support_staff`
 * marks which staff accounts staff the support desk. The app database never
 * references either table by foreign key, so an app-side compromise cannot
 * walk into the console.
 */
return new class extends Migration
{
    protected $connection = 'admin';

    public function up(): void
    {
        // Guarded, because this database is not created by running these
        // migrations.
        //
        // The console database is a SQLite file that predates the migration
        // ledger, and it was never empty: it arrived already holding
        // `staff_users` and `support_staff` with staff in them. With a bare
        // `Schema::create` this migration throws "table staff_users already
        // exists" and takes the whole `php artisan migrate` run down with it -
        // so no later migration can ever be applied, on any environment, until
        // somebody hand-edits a database by hand to work around it.
        //
        // Checking first makes it re-runnable and records it honestly: the tables
        // do exist, so the correct outcome is "nothing to do, mark it applied".
        // Nothing is dropped or rewritten either way, so the staff rows in the
        // retained console database are untouched.
        if (! Schema::hasTable('staff_users')) {
            Schema::create('staff_users', function (Blueprint $table): void {
                $table->id();
                $table->string('username', 30)->unique();
                $table->string('display_name', 60);
                $table->string('password');
                $table->string('role', 20)->default('support');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['role', 'created_at']);
            });
        }

        if (! Schema::hasTable('support_staff')) {
            Schema::create('support_staff', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_staff');
        Schema::dropIfExists('staff_users');
    }
};