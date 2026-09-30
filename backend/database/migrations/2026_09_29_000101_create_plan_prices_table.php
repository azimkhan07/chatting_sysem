<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Country-wise price + feature unlocks for each subscription plan.
 *
 * One row per (plan, country). The currency and its symbol are stored verbatim
 * so the admin form's "pick a country → currency auto-fills" works even when
 * the currency mapping grows beyond the client-side lookup table.
 *
 * `features` is a JSON list of feature keys that unlock with the plan, mirroring
 * the ChatFeature keys the in-app lock crown refers to - a feature shown as
 * locked in the price cards must exist here to be unlockable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('plan', 20);
            $table->string('country', 2);
            $table->string('currency', 8)->default('INR');
            $table->string('currency_symbol', 8)->default('₹');
            $table->unsignedInteger('price_month_paisa')->default(0);
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['plan', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};