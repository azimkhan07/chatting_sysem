<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow link-based stories (e.g. GIF picks) alongside uploads, plus
     * caption text styling so stories feel like Instagram/Facebook.
     */
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('media_path')->nullable()->change();
            $table->string('media_url', 2048)->nullable()->after('media_path');
            $table->json('text_style')->nullable()->after('effects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'text_style']);
            $table->string('media_path')->nullable(false)->change();
        });
    }
};
