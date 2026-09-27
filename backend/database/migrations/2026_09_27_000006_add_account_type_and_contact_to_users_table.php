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
            $table->string('account_type', 20)->default('personal')->after('status');
            // Public contact details for business/professional accounts. Stored
            // separately from `email`/`mobile`, which stay owner-only forever.
            $table->string('contact_email')->nullable()->after('account_type');
            $table->string('contact_phone', 20)->nullable()->after('contact_email');
            // The owner decides whether visitors see the contact block at all.
            $table->boolean('show_contact')->default(false)->after('contact_phone');

            $table->index(['account_type', 'show_contact'], 'users_account_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_account_contact_idx');
            $table->dropColumn(['account_type', 'contact_email', 'contact_phone', 'show_contact']);
        });
    }
};
