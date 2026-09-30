<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-filed support queries/complaints and the support engagement on them.
 *
 * The ticket row carries the user's original message; replies live in
 * `support_messages` (one thread, support→user, inline + optional email).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 160);
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('from_support')->default(false);
            $table->text('body');
            $table->boolean('emailed')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};