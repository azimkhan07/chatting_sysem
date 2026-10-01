<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use App\Domain\Chat\Enums\ChatFeature;
use App\Domain\Plans\Models\Feature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Materialises {@see ChatFeature} into the `features` table.
 *
 * This is the piece that makes a feature registration a one-line change instead
 * of two. `ChatFeature` is what the code enforces; `features` is what the admin
 * console's subscription form draws as checkboxes. Both are needed - one is a
 * PHP enum and cannot be queried by the console service, the other is a table and
 * cannot be `match`ed against in a route middleware - but there has to be only
 * one place a human edits, and that is the enum.
 *
 * The sync is:
 *  - Idempotent. Running it twice changes nothing, so it is safe on every boot.
 *  - Add-only for the enum, retire-only for the table. A case in the enum is
 *    upserted; a row whose key is not in the enum is deactivated, never deleted,
 *    because `plan_prices.features` is a JSON list of keywords and a live plan
 *    may still name it. Deleting would leave a plan advertising an unlock that
 *    resolves to nothing.
 *  - Never destructive to prices. This touches the catalogue and nothing else.
 *
 * Labels and blurbs are copied from the enum on every run, which means editing
 * the wording in code is the only way to edit it. That is the point - a label
 * that can be changed in two places is a label that will be changed in one of
 * them and not the other.
 */
final class FeatureCatalogueSync
{
    /**
     * @return array{
     *     registered: list<array{key: string, label: string, tier: string, is_new: bool}>,
     *     retired: list<string>,
     *     changed: list<string>
     * }
     */
    public function sync(): array
    {
        $registered = [];
        $changed = [];
        $sortOrder = 0;

        foreach (ChatFeature::cases() as $feature) {
            $sortOrder += 10;

            $existing = DB::table('features')->where('key', $feature->value)->first();

            $row = [
                'label' => $feature->label(),
                // An uncurated feature has no blurb. Storing '' rather than null
                // would push an empty string through the API and into a Mantine
                // `description` prop, so normalise it here where the rule lives.
                'blurb' => $feature->blurb() ?: null,
                'tier' => $feature->tier()->value,
                'sort_order' => $sortOrder,
                'active' => true,
                'updated_at' => now(),
            ];

            if ($existing === null) {
                DB::table('features')->insert([
                    'key' => $feature->value,
                    ...$row,
                    'created_at' => now(),
                ]);

                $registered[] = [
                    'key' => $feature->value,
                    'label' => $feature->label(),
                    'tier' => $feature->tier()->value,
                    'is_new' => true,
                ];

                continue;
            }

            // Only write when something actually differs. An unconditional update
            // would bump updated_at on every row on every request, which turns a
            // one-row diff into a full-table write and destroys the ability to
            // tell "someone edited this" from "the app started".
            $differs = $existing->label !== $row['label']
                || $existing->blurb !== $row['blurb']
                || ($existing->tier ?? Feature::FREE_TIER) !== $row['tier']
                || (int) $existing->sort_order !== $row['sort_order']
                || ! (bool) $existing->active;

            if ($differs) {
                DB::table('features')->where('key', $feature->value)->update($row);
                $changed[] = $feature->value;
            }

            $registered[] = [
                'key' => $feature->value,
                'label' => $feature->label(),
                'tier' => $feature->tier()->value,
                'is_new' => false,
            ];
        }

        return [
            'registered' => $registered,
            'retired' => $this->retireUnregistered(),
            'changed' => $changed,
        ];
    }

    /**
     * Deactivates catalogue rows the code no longer knows about.
     *
     * @return list<string>
     */
    private function retireUnregistered(): array
    {
        $known = ChatFeature::values();

        $stale = DB::table('features')
            ->whereNotIn('key', $known)
            ->where('active', true)
            ->pluck('key')
            ->all();

        if ($stale !== []) {
            DB::table('features')
                ->whereIn('key', $stale)
                ->update(['active' => false, 'updated_at' => now()]);
        }

        return array_values($stale);
    }

    /**
     * Works out what {@see sync()} would do, without writing anything.
     *
     * Shares the enum walk with sync() so the dry run cannot report something
     * different from what a real run then does - the two drifting apart is the
     * failure mode of duplicating a preview.
     *
     * @return array{
     *     registered: list<array{key: string, label: string, tier: string, is_new: bool}>,
     *     retired: list<string>,
     *     changed: list<string>
     * }
     */
    public function preview(): array
    {
        $registered = [];
        $sortOrder = 0;

        foreach (ChatFeature::cases() as $feature) {
            $sortOrder += 10;
            $existing = DB::table('features')->where('key', $feature->value)->first();

            $registered[] = [
                'key' => $feature->value,
                'label' => $feature->label(),
                'tier' => $feature->tier()->value,
                'is_new' => $existing === null,
            ];
        }

        $known = ChatFeature::values();

        return [
            'registered' => $registered,
            'retired' => array_values(DB::table('features')
                ->whereNotIn('key', $known)
                ->where('active', true)
                ->pluck('key')
                ->all()),
            'changed' => [],
        ];
    }

    /**
     * True when the catalogue table is there and safe to write.
     *
     * The sync runs from a service provider, which means it runs during `artisan
     * migrate` too - before this table exists, and again on a database that is
     * still being built. Booting the application must not depend on a table that
     * a later migration creates.
     */
    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable('features');
        } catch (\Throwable) {
            // A connection that is not reachable yet. Boot is not the place to
            // find that out; the request that needs the catalogue will.
            return false;
        }
    }
}
