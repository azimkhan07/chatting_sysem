<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        // A saved post/story, always visible in "All". One row per item, so a
        // save is idempotent and the same item can sit in any number of
        // collections without duplicating the source row.
        Schema::create('saved_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('saveable');
            $table->timestamps();
            $table->unique(['user_id', 'saveable_type', 'saveable_id']);
        });

        // Which named collections an item belongs to.
        Schema::create('saved_collection_items', function (Blueprint $table): void {
            $table->foreignId('collection_id')->constrained('saved_collections')->cascadeOnDelete();
            $table->foreignId('saved_item_id')->constrained('saved_items')->cascadeOnDelete();
            $table->primary(['collection_id', 'saved_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_collection_items');
        Schema::dropIfExists('saved_items');
        Schema::dropIfExists('saved_collections');
    }
};
