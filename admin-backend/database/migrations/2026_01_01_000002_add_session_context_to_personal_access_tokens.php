<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where a console session was opened from.
 *
 * Until now a staff session was a row with a name and a timestamp. The name held
 * a truncated user-agent string, which is enough to tell you *that* a second
 * browser exists and not enough to answer the only question anyone actually has
 * when they look at a credential list: whose account is this, from where, on
 * what. "staff · Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
 * truncated at 120 characters is not a browser name and there was no IP at all,
 * so two sessions from two continents looked identical.
 *
 * The columns are additive and nullable on purpose:
 *  - Every row that predates this migration is real and stays. Backfilling a
 *    guessed IP or browser from a truncated string would put fiction in a
 *    security table, which is worse than an honest blank.
 *  - The user-agent is the raw value, kept alongside the parsed columns. Parsing
 *    is a guess about a string that browsers keep changing, and when it is
 *    wrong the raw value is the only way to work out why.
 *
 * `client` is separate from `device_type` on purpose. "Desktop" and "Windows"
 * describe the machine; they say nothing about *which application* the console
 * was reached through, which is a different question with a different answer -
 * a phone and a desktop app are both "mobile" and "desktop" respectively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('admin')->table('personal_access_tokens', function (Blueprint $table): void {
            // The address the login came from. 45 is the longest an IPv6
            // address with a scope id can render, so this never truncates.
            $table->string('ip_address', 45)->nullable()->after('abilities');

            // Raw, untruncated. The old `name` column kept only the first 120
            // characters, which cut the platform section off the end of most
            // strings; the full value is what the parser actually needs.
            $table->text('user_agent')->nullable()->after('ip_address');

            // Parsed from the user agent at login and frozen. Re-parsing on
            // every read would mean a browser update could retroactively change
            // what a session list claims about a sign-in that already happened.
            $table->string('browser', 40)->nullable()->after('user_agent');
            $table->string('os', 40)->nullable()->after('browser');
            $table->string('device_type', 20)->nullable()->after('os');

            // Which application the console was opened through: `web`, or a
            // native client that identifies itself. Free text rather than an enum
            // because a new app build should not need a migration to appear.
            $table->string('client', 30)->nullable()->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::connection('admin')->table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'browser',
                'os',
                'device_type',
                'client',
            ]);
        });
    }
};
