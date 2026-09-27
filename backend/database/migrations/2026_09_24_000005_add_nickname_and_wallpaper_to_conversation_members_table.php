<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-conversation personalisation. Both columns live on the membership row,
 * not the conversation, because they are private to the person who set them:
 * your nickname for a chat is not your friend's nickname for the same chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->string('nickname', 40)->nullable()->after('role');
            // Preset key ("aurora") or a gallery asset id; null = solid theme.
            $table->string('wallpaper_key', 60)->nullable()->after('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->dropColumn(['nickname', 'wallpaper_key']);
        });
    }
};
