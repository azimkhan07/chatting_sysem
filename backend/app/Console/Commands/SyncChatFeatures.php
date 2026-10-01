<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chat\FeatureCatalogueSync;
use Illuminate\Console\Command;

/**
 * `php artisan chat:features-sync` - register or re-check the feature catalogue.
 *
 * Adding a feature is one line of code: a case in the `ChatFeature` enum, with a
 * keyword as the backing value, a label, a blurb, and a tier. The service
 * provider already does this work on every boot, so this command exists for the
 * three things a boot cannot do for you:
 *
 *  1. Show the registration. `php artisan chat:features` lists every registered
 *     feature with its keyword, title and tier, which is the thing to read when
 *     asking "what did I just add, and is it going to cost anybody money?"
 *  2. Prove it landed, without waiting for a deploy. Run this after adding a case
 *     and the output tells you whether the row appeared, whether a previously
 *     unknown keyword was retired, and whether a label changed.
 *  3. Repair a catalogue that drifted, or that a restore from an old dump left
 *     pointing at features the code no longer has.
 *
 * `--dry-run` reports exactly what would be written and touches nothing.
 */
final class SyncChatFeatures extends Command
{
    protected $signature = 'chat:features
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Register the ChatFeature enum into the features catalogue, or list what is registered';

    public function handle(FeatureCatalogueSync $sync): int
    {
        if (! $sync->isAvailable()) {
            $this->components->error(
                'The features table is not there yet. Run migrations first: php artisan migrate'
            );

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $dryRun ? $sync->preview() : $sync->sync();

        $this->components->info('Registered features');

        $rows = [];
        foreach ($result['registered'] as $feature) {
            $rows[] = [
                $feature['key'],
                $feature['label'],
                $feature['tier'],
                $feature['is_new'] ? 'new' : 'existing',
            ];
        }

        $this->table(['keyword', 'title', 'tier', 'state'], $rows);

        if ($result['changed'] !== []) {
            $this->components->warn('Updated: '.implode(', ', $result['changed']));
        }

        if ($result['retired'] !== []) {
            $this->components->warn(
                'Retired (no longer in the enum, hidden from the form, kept for existing plan rows): '
                .implode(', ', $result['retired'])
            );
        }

        $this->newLine();
        $this->line('  keyword -> the value a plan stores in its features list');
        $this->line('  premium -> a checkbox on the subscription form; free -> unlocked for everyone');

        if ($dryRun) {
            $this->components->info('Dry run. Nothing was written.');
        } else {
            $this->components->info(
                'The admin console reads this table, so the subscription form is already up to date.'
            );
        }

        return self::SUCCESS;
    }
}
