<?php

declare(strict_types=1);

namespace Admin\Domain\App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Writes the user-facing notification row for a console decision.
 *
 * The console already writes to the app database for the decision itself, and
 * the notification is a row in the same database - so it is written here rather
 * than skipped. Dropping it would be a quiet regression: an account whose
 * verification is approved or refused would simply never be told, and the user
 * would find out by noticing the badge did or did not appear.
 *
 * On the actor, because `notifications.actor_id` is NOT NULL and has no foreign
 * key, the column cannot simply be left empty for a decision made by staff
 * rather than by a user. The tempting shortcut is to put the console staff id
 * there and let the app fail to resolve it - but staff ids and user ids are
 * both small integers from 1, so the id resolves to a real, unrelated account
 * and the notification renders that person's name and photo. The failure is
 * silent and it misattributes a support decision to a user who had nothing to
 * do with it, which is the kind of bug that is only noticed after someone is
 * wrongly accused of something.
 *
 * So the row carries an explicit `console_staff` block instead. The app's
 * NotificationResource keys off that marker, not off whether the id happens to
 * match, and a console action can then never be confused with a user action no
 * matter how the two id sequences drift. `actor_id` still holds the staff id
 * because the column is NOT NULL, and because it keeps the decision traceable
 * back to the console account that made it.
 *
 * It does not dispatch the main app's `NotificationCreated` event. That event
 * fans out to the user's realtime channel, and the console is a separate process
 * with no presence or Reverb connection of its own - the notification lands in
 * the list on the user's next fetch, which is where a badge update is read from
 * anyway. Coupling to the app's broadcast stack to save a poll would give the
 * console a realtime dependency it has no other use for.
 */
final class AppNotificationWriter
{
    /**
     * Mirrors the main app's `NotificationType::Verified`. Spelled out as a
     * string because the enum itself lives in backend/; the value is the
     * contract, not the class.
     */
    private const TYPE_VERIFIED = 'verified';

    /**
     * The key the app looks for to know the actor was a console account and not
     * a user. Part of the contract between the two apps, so it is a constant on
     * both sides rather than a string repeated at each use.
     */
    public const CONSOLE_STAFF_KEY = 'console_staff';

    /**
     * @param  array<string, mixed>  $data
     */
    public function verifiedDecision(
        int $recipientId,
        int $staffId,
        bool $approved,
        int $subscriptionId,
        ?string $plan = null,
        ?string $staffName = null,
    ): void {
        DB::connection('app')->table('notifications')->insert([
            'user_id' => $recipientId,
            // Required by the column, not meaningful as a user reference. See the
            // class comment: the app ignores this for console-originated rows.
            'actor_id' => $staffId,
            'type' => self::TYPE_VERIFIED,
            'data' => json_encode([
                'approved' => $approved,
                'subscription_id' => $subscriptionId,
                ...($plan !== null ? ['plan' => $plan] : []),
                // Present for every console decision, and the thing the app
                // checks. Carrying the name as well as the id means the app can
                // say "refused by the support desk" without a second lookup into
                // a database it has no credentials for.
                self::CONSOLE_STAFF_KEY => [
                    'id' => $staffId,
                    'name' => $staffName,
                ],
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
