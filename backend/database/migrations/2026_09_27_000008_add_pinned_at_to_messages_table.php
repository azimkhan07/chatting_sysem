<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            // A pin is shared state, so it lives on the message itself. The
            // unique pin bar read is served by the existing
            // (conversation_id, id) index plus a partial-style where on
            // pinned_at being non-null.
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['conversation_id', 'pinned_at'], 'conversation_messages_pinned_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropIndex('conversation_messages_pinned_idx');
            $table->dropForeign(['pinned_by']);
            $table->dropColumn(['pinned_at', 'pinned_by']);
        });
    }
};
