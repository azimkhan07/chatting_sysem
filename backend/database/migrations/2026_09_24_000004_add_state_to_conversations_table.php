<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct messages from someone you have no follow edge with are not a chat yet,
 * they are a request. Without this every stranger could drop a DM into the
 * primary inbox, which is exactly the spam problem message requests solve.
 *
 * `state` lives on the conversation (not the member row) because a request is
 * a property of the DM as a whole: both members see the same pending thread
 * until the recipient accepts or deletes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('state', 12)->default('active')->after('type');
            $table->foreignId('requested_by')->nullable()->after('state')->constrained('users')->nullOnDelete();

            // Inbox reads filter on state and sort by recency.
            $table->index(['state', 'updated_at'], 'conversations_state_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_state_updated_at_index');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn('state');
        });
    }
};
