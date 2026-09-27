<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy and notification preferences, one row per user.
 *
 * Discrete boolean columns rather than a JSON blob: these are read on nearly
 * every request (a `private` user must not appear in Explore), and a column is
 * both indexable and self-documenting for the next reader of the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Privacy
            $table->boolean('discoverable')->default(true);
            $table->boolean('show_activity_status')->default(true);
            $table->boolean('allow_message_requests')->default(true);
            $table->boolean('allow_tagging')->default(true);

            // Notifications
            $table->boolean('notify_messages')->default(true);
            $table->boolean('notify_requests')->default(true);
            $table->boolean('notify_follows')->default(true);
            $table->boolean('notify_likes')->default(true);
            $table->boolean('notify_comments')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
