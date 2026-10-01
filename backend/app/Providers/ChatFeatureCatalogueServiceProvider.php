<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Chat\FeatureCatalogueSync;
use Illuminate\Support\ServiceProvider;

/**
 * Keeps the feature catalogue in step with the code on every boot.
 *
 * Boot-time rather than per-request on purpose. A feature is registered by
 * adding a case to the `ChatFeature` enum, and the admin console's subscription
 * form reads the catalogue out of the database from a different service that
 * cannot see the enum at all. Reconciling once at boot means the console's next
 * request is already correct, and it costs one indexed read plus a no-op
 * comparison on a table that is a handful of rows.
 *
 * Every failure here is swallowed, and that is not laziness: this runs on every
 * boot, including `artisan migrate` on a database where `features` does not exist
 * yet, and including a read-only replica or a connection that is not up yet.
 * Booting must never depend on it. If the sync genuinely could not run, the
 * request that needs the catalogue will find a stale table and the admin will see
 * a missing checkbox - a visible, fixable problem, which is a much better
 * failure than an application that will not boot.
 *
 * It runs on web boots too, not only on artisan. The usual way to scope this kind
 * of thing to `runningInConsole()` is wrong here: production serves through
 * php-fpm, where that is false, so scoping it that way would mean the catalogue
 * was only ever reconciled by hand.
 */
final class ChatFeatureCatalogueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $sync = $this->app->make(FeatureCatalogueSync::class);

            if (! $sync->isAvailable()) {
                return;
            }

            $sync->sync();
        } catch (\Throwable) {
            // See the class docblock. A failed catalogue sync must not stop boot.
        }
    }
}
