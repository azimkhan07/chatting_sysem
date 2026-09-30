<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appeals a suspended user files to get their account reviewed. The user
 * cannot sign in (tokens are revoked), so the appeal is filed with the same
 * identifier + password proof as account reactivation, then handled by the
 * support desk: approve lifts the suspension, reject keeps it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_appeals');
    }
};