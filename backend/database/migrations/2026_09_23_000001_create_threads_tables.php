<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('created_by');
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->json('recap')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('status');
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('thread_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_type')->nullable();
            $table->timestamps();

            $table->index('thread_id');
            $table->foreign('thread_id')->references('id')->on('threads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('thread_reactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('thread_entry_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reaction');
            $table->timestamps();

            $table->unique(['thread_entry_id', 'user_id']);
            $table->foreign('thread_entry_id')->references('id')->on('thread_entries')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_reactions');
        Schema::dropIfExists('thread_entries');
        Schema::dropIfExists('threads');
    }
};
