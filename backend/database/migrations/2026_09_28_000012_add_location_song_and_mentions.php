<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location, soundtrack and tagged people for posts and stories.
 *
 * Three separate additions because they answer three unrelated questions:
 * where it happened, what it is set to, and who is in it. Bundling them into
 * one "composer metadata" table would have made every one of them nullable for
 * the other two, and the index for the feed would have covered nothing.
 *
 * `location` is a free-text place name rather than coordinates. There is no
 * geocoding provider in this project, and storing raw lat/lng without a
 * reverse lookup would mean the UI had to render "19.07, 72.87" where a person
 * expects "Mumbai". A name is what the user typed, so it round-trips honestly;
 * a `places` table can be added later without changing this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('location', 255)->nullable()->after('body');
            $table->foreignId('song_id')->nullable()->after('location')->constrained()->nullOnDelete();
            $table->index('location');
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->string('location', 255)->nullable()->after('effects');
        });

        // Who a post says it is about. Separate from `hashtags` on purpose: a
        // tag is a word, a mention is a person, and they have different owners
        // (nobody vs the mentioned account) and different lifetimes.
        Schema::create('post_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // The same person typed twice is one mention.
            $table->unique(['post_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_mentions');

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['location']);
            $table->dropConstrainedForeignId('song_id');
            $table->dropColumn('location');
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->dropColumn('location');
        });
    }
};
