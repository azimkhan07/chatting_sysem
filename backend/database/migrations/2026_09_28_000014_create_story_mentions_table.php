<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a story names.
 *
 * The same pivot as `post_mentions` rather than a column on `stories`, because
 * the story composer already offers the same tag picker and a story naming
 * three people is three rows, not a string that has to be parsed again on
 * every read.
 *
 * Cascading on both sides: a story expires on its own schedule and a deleted
 * account takes its tags with it, so neither can leave a row pointing at
 * nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // A tag shown twice in the caption is still one tag.
            $table->unique(['story_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_mentions');
    }
};
