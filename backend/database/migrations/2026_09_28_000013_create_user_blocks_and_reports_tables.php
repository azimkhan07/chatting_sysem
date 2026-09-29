<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blocking and reporting.
 *
 * Two tables because they answer different questions and have different
 * lifetimes. A block is a private setting between two people and is invisible
 * to everyone else; a report is an appeal to staff about content and is the
 * opposite. Sharing a table would mean either leaking blocks into the admin
 * queue or hiding reports from it.
 *
 * Blocking is not soft-deleted. Unblocking must actually remove the row, or the
 * pair stays filtered out of each other's feeds forever with no way back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Blocking someone twice is one block, so re-tapping the button is
            // idempotent. Note this does NOT prevent A-blocking-B and
            // B-blocking-A at the same time - the two rows have different
            // (blocker, blocked) tuples, and both are legitimate: they are the
            // same mutual decision reached from two accounts.
            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();

            // Polymorphic rather than four nullable columns: a report is always
            // about exactly one thing, and a single set of constraints can say
            // so. The four (type, id) pairs below cannot.
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 40);
            $table->string('details', 1000)->nullable();

            // 'pending' until a moderator acts. Kept as a string with a check
            // in the app layer because the enum lives in the domain package.
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution', 1000)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);

            // The same person reporting the same thing twice is one report.
            // Without this, holding down a button fills the moderator queue
            // with N copies of one complaint and hides every other one.
            $table->unique(['reporter_id', 'target_type', 'target_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('user_blocks');
    }
};
