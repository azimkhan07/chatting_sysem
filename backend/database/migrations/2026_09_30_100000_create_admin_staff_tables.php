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

        Schema::create('support_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_staff');
        Schema::dropIfExists('staff_users');
    }
};