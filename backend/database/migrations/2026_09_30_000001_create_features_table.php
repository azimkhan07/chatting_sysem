<?php

declare(strict_types=1);

use App\Domain\Chat\Enums\ChatFeature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of feature flags shown as checkboxes on the admin subscription
 * form. Adding a row here (a feature keyword + label) makes it appear on the
 * plan editor automatically - no admin-side recompile needed.
 *
 * Initial rows come from the chat entitlement enum plus the product features
 * the admin console shipped with (calls, stories, groups, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 120);
            $table->string('blurb', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $rows = [];
        $sort = 0;

        foreach (ChatFeature::cases() as $feature) {
            $sort += 10;
            $rows[] = [
                'key' => $feature->value,
                'label' => $feature->label(),
                'blurb' => $feature->blurb(),
                'sort_order' => $sort,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ([
            ['calls', 'Voice & video calls', null],
            ['stories', 'Stories', null],
            ['groups', 'Groups & families', null],
            ['archive', 'Archive', null],
            ['saved', 'Saved collections', null],
            ['export', 'JSON export', null],
            ['badge', 'Blue tick (verified)', null],
            ['priority', 'Priority support', null],
        ] as [$key, $label, $blurb]) {
            $sort += 10;
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'blurb' => $blurb,
                'sort_order' => $sort,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('features')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};