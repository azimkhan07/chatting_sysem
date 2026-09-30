<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email templates and transport config, both admin-editable via CRUD so that
 * changing an SMTP server or welcome-mail copy never requires a redeploy.
 *
 * `email_config` is a single-row table (id always 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 100)->unique();
            $table->string('subject', 180);
            $table->longText('html_body');
            $table->longText('text_body')->nullable();
            $table->string('from_name', 80)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('email_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('host', 160)->nullable();
            $table->unsignedInteger('port')->default(587);
            $table->string('username', 160)->nullable();
            $table->text('password')->nullable();
            $table->string('encryption', 12)->default('tls');
            $table->string('from_email', 160)->nullable();
            $table->string('from_name', 80)->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_configs');
        Schema::dropIfExists('email_templates');
    }
};