<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cursor pagination walks `ORDER BY id DESC` while filtering on the same
 * columns, so a single-column index makes the database sort every page. These
 * composites turn the feed, story tray, hashtag page and comment thread into
 * range scans that stop at the cursor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            // feed/profile: where user_id in (...) order by id desc
            $table->index(['user_id', 'id'], 'posts_user_id_id_index');
        });

        Schema::table('stories', function (Blueprint $table): void {
            // story tray: where expires_at > now() order by id desc
            $table->index(['expires_at', 'id'], 'stories_expires_at_id_index');
            // "my active stories" lookups
            $table->index(['user_id', 'id'], 'stories_user_id_id_index');
        });

        Schema::table('post_media', function (Blueprint $table): void {
            // reels feed: where post_id in (...) and type = video
            $table->index(['type', 'post_id'], 'post_media_type_post_id_index');
        });

        Schema::table('follows', function (Blueprint $table): void {
            // "who do I follow" fan-out, the other direction of the unique pair
            $table->index(['following_id', 'follower_id'], 'follows_following_id_follower_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('follows', function (Blueprint $table): void {
            $table->dropIndex('follows_following_id_follower_id_index');
        });

        Schema::table('post_media', function (Blueprint $table): void {
            $table->dropIndex('post_media_type_post_id_index');
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->dropIndex('stories_user_id_id_index');
            $table->dropIndex('stories_expires_at_id_index');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('posts_user_id_id_index');
        });
    }
};
