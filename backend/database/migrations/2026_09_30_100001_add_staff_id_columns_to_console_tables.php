<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which staff member acted, without a foreign key into the app users
 * table. Staff ids now belong to the console database, so "acted by staff"
 * columns on app tables must be plain integers — a cross-database FK is
 * impossible by design.
 *
 * The legacy `approved_by` / `handled_by` / `user_id` columns stay untouched:
 * they own the FK to the app users table, and staff ids move into the new
 * `*_staff` columns below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('approved_by_staff')->nullable()->after('approved_by');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('handled_by_staff')->nullable()->after('handled_by');
        });

        Schema::table('account_appeals', function (Blueprint $table): void {
            $table->unsignedBigInteger('handled_by_staff')->nullable()->after('handled_by');
        });

        Schema::table('support_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('staff_user_id')->nullable()->after('user_id');
        });

        // Subscription approval/rejection still writes the actor (the staff id)
        // into notifications.actor_id for the user's activity feed, so the FK
        // to the app users table has to go.
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropForeign(['actor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreign('actor_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('support_messages', function (Blueprint $table): void {
            $table->dropColumn('staff_user_id');
        });

        Schema::table('account_appeals', function (Blueprint $table): void {
            $table->dropColumn('handled_by_staff');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('handled_by_staff');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('approved_by_staff');
        });
    }
};