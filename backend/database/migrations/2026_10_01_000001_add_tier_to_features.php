<?php

declare(strict_types=1);

use App\Domain\Chat\Enums\ChatFeature;
use App\Domain\Chat\Enums\FeatureTier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the paid/free tier, and retires the features that were hard-coded into
 * the original `create_features_table` migration.
 *
 * That migration seeded fourteen rows: six that exist in {@see ChatFeature} and
 * eight more - calls, stories, groups, archive, saved, export, badge, priority -
 * written as a literal array. They were the start of the drift this column ends.
 * A keyword in that array appeared as a checkbox on the plan editor with no code
 * anywhere enforcing it, and a real ChatFeature case added later appeared in code
 * but not in the table. Two lists, neither derived from the other, and the
 * console rendered whichever one it was pointed at.
 *
 * They are deactivated, not deleted. `plan_prices.features` is a JSON list of
 * keywords, and at least one live row still references these keys; deleting the
 * catalogue row would leave a plan advertising an unlock that resolves to
 * nothing. Deactivating takes them off the form and stops the sync re-asserting
 * them, while leaving the history intact.
 *
 * `tier` defaults to 'free' on purpose - see FeatureTier. Everything that exists
 * today was handed out to everyone during the free launch, so retroactively
 * marking any of it paid would lock working features behind a paywall for
 * current subscribers. That is a deliberate product decision to be made per
 * feature, not a default to be applied in bulk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table): void {
            $table->string('tier', 10)->default(FeatureTier::Free->value)
                ->after('blurb')
                ->index();
        });

        // Anything the code does not know about is a leftover from the hard-coded
        // seed list. Computed from the enum rather than written out again, so this
        // migration cannot become a third list to keep in sync.
        $known = array_map(
            static fn (ChatFeature $feature): string => $feature->value,
            ChatFeature::cases(),
        );

        $retired = DB::table('features')
            ->whereNotIn('key', $known)
            ->update(['active' => false, 'updated_at' => now()]);

        // The real features keep their seeded order and are made explicit about
        // being free, so a later tier change has somewhere to change from.
        foreach (ChatFeature::cases() as $index => $feature) {
            DB::table('features')
                ->where('key', $feature->value)
                ->update([
                    'tier' => $feature->tier()->value,
                    'active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('features', function (Blueprint $table): void {
            $table->dropColumn('tier');
        });
    }
};
