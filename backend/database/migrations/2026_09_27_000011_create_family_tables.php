<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Family Center.
 *
 * A user belongs to at most one family - the unique index on
 * `family_members.user_id` is the whole rule, enforced by the database rather
 * than by a check that could race. Members are added by username, so there is
 * no pending-invite table to expire, moderate or leak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();

            $table->index('owner_id');
        });

        Schema::create('family_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // guardian | adult | teen
            $table->string('role', 12)->default('adult');
            $table->timestamps();

            // One family per user, enforced here rather than in a service check.
            $table->unique('user_id');
            $table->unique(['family_group_id', 'user_id']);
            $table->index(['family_group_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('family_groups');
    }
};
