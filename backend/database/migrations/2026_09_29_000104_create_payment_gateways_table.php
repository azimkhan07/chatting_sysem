<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateway credentials, admin-managed via CRUD so buying a new gateway
 * (Razorpay → Stripe → Cashfree) never requires code changes: key, merchant id,
 * secret and endpoint are all config rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('key', 200)->nullable();
            $table->string('merchant_id', 120)->nullable();
            $table->text('secret')->nullable();
            $table->string('endpoint', 255)->nullable();
            $table->string('currency', 8)->default('INR');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};