<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Public accounts are visible to everyone; private accounts only
            // to approved followers. Defaults to public so existing rows and
            // fresh signups are not locked down by accident.
            $table->boolean('is_private')->default(false)->after('status');
            // The creator/business category shown on the profile badge, e.g.
            // Artist / Entertainment / Sport. Null for personal accounts.
            $table->string('category', 30)->nullable()->after('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_private', 'category']);
        });
    }
};
