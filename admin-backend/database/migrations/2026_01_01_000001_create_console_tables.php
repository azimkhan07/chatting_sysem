<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The console database, in full.
 *
 * This app owns three tables and nothing else. The main app's users, posts,
 * subscriptions and moderation tables are read and written over the `app`
 * connection, and their schema is owned by the main app's migrations — this
 * app has no `up()` for any of them and must never be given one.
 *
 * `staff_users` is where every console sign-in resolves. `support_staff` marks
 * which of those accounts staff the support desk. `personal_access_tokens`
 * holds the session, which is why it lives here and not next to the users:
 * a console session is a credential, and this is the only process with a
 * connection string to this database.
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

            // The console lists the team by role, and the team list is the only
            // place the role is filtered on in bulk.
            $table->index(['role', 'created_at']);
        });

        Schema::create('support_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->unique()->constrained('staff_users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            // Always a StaffUser here. The main app keeps its own token table
            // and its own `App\...` tokenable_type values; the two never mix,
            // because neither process can read the other's token rows.
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('support_staff');
        Schema::dropIfExists('staff_users');
    }
};
