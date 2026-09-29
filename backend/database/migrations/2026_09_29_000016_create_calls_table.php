<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // The member who started the ring. Everyone else hears the offer.
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            // 'audio' or 'video' - the same room serves both; video simply
            // publishes a camera track as well as the microphone.
            $table->string('kind', 10);
            // ringing -> active (first answer) -> ended (someone hung up).
            // A ring nobody answered stays ringing and is closed when the
            // initiator hangs up.
            $table->string('status', 10)->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
