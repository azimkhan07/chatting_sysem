<?php

declare(strict_types=1);

namespace Admin\Providers;

use Admin\Domain\Admin\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->assertCorsIsConfiguredForProduction();

        // Console sessions are credentials, so they live in the console
        // database. Without this, Sanctum looks for staff tokens on the default
        // connection — a session would be minted on one database and
        // authenticated against another.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Only the console login is throttled here. The rates are tight because
        // there is no second factor on a staff account: this is the one
        // endpoint worth guessing against.
        RateLimiter::for('staff-auth', static fn (Request $request): array => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perDay(30)->by('ip:'.$request->ip()),
        ]);

        // Keyed by account where there is one. An IP key gives every person
        // behind a single office NAT one shared bucket, so a brute force from
        // one machine would lock out the whole team.
        RateLimiter::for('api', static fn (Request $request): Limit => $request->user() !== null
            ? Limit::perMinutes(1, 120)->by('staff:'.$request->user()->getAuthIdentifier())
            : Limit::perMinutes(1, 30)->by('ip:'.$request->ip()));
    }

    /**
     * Refuses to serve production with the development CORS default left in
     * place.
     *
     * The console SPA is a different origin from this API, so an origin the API
     * does not allow means the browser discards every response and the console
     * shows a network error with no status code — for the whole team, at once,
     * with nothing in this app's logs to explain it.
     *
     * The two ways this gets reached are both worth catching here. A deploy that
     * forgets CORS_ALLOWED_ORIGINS leaves localhost in the list, and the
     * tempting fix from a browser console full of red lines is to set it to `*`.
     * That would be much worse than the outage: this API mints console sessions
     * and applies enforcement decisions to real user accounts, so a wildcard
     * hands both to any site a signed-in developer visits. Failing at boot names
     * the variable to fix instead.
     */
    private function assertCorsIsConfiguredForProduction(): void
    {
        if (app()->environment('production') !== true) {
            return;
        }

        /** @var list<string> $origins */
        $origins = (array) config('cors.allowed_origins', []);

        $local = array_filter(
            $origins,
            static fn (string $origin): bool => str_contains($origin, 'localhost')
                || str_contains($origin, '127.0.0.1')
                || str_starts_with($origin, 'http://'),
        );

        if ($origins === []) {
            throw new RuntimeException(
                'CORS_ALLOWED_ORIGINS is empty in production. Set it to the console origin, '
                .'e.g. https://admin.example.com. A wildcard is not accepted here: this API '
                .'holds console sessions and writes to real user accounts.',
            );
        }

        // Checked before the local-origin test, and separately from it, because a
        // wildcard is not a local origin: it contains no "localhost" and no
        // "http://", so it sails through a check written for the other mistake
        // and grants every origin on the internet. It is also the mistake that
        // actually gets made, from a browser console full of red lines.
        $wild = array_values(array_filter(
            $origins,
            static fn (string $origin): bool => $origin === '*' || str_contains($origin, '*'),
        ));

        if ($wild !== []) {
            throw new RuntimeException(sprintf(
                'CORS_ALLOWED_ORIGINS contains a wildcard in production: %s. Name the console '
                .'origin instead, e.g. https://admin.example.com. A wildcard would let any site a '
                .'signed-in developer visits drive this API with their session.',
                implode(', ', $wild),
            ));
        }

        if ($local !== []) {
            throw new RuntimeException(sprintf(
                'CORS_ALLOWED_ORIGINS still contains a local or plain-http origin in production: %s. '
                .'Set it to the console origin, e.g. https://admin.example.com.',
                implode(', ', $local),
            ));
        }
    }
}
