<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Deliberately empty.
    |
    | This list is not the guard used by the `auth:` middleware — it is the set
    | of session guards Sanctum falls back to for *stateful* requests (a cookie
    | session from an SPA on the same site). Listing `admin:staff` here, which
    | is this app's own Sanctum guard, makes Sanctum call itself to resolve a
    | bearer token: each attempt re-enters the same guard, and the process
    | exhausts memory and dies. The symptom is a container that answers the
    | first request and then stops responding, which looks like an
    | infrastructure fault rather than a one-line config mistake.
    |
    | There is no cookie session in this app. Every authenticated request
    | carries a bearer token, which Sanctum reads directly once this list is
    | empty.
    |
    */

    'guard' => [],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Console sessions expire on their own. A staff token left in a browser
    | that was never closed is a live credential with write access to user
    | accounts, and the console has no second factor to notice a stolen one.
    | Signing out is a habit, not a control.
    |
    | Change/Password endpoints are re-authenticated by the current password,
    | so an expired session costs a sign-in and nothing else.
    |
    | This is the single source of truth for how long a staff session lasts.
    | StaffSessionManager reads it rather than keeping its own number, because
    | two definitions of "how long is a session" is how an app ends up claiming
    | a 12-hour policy in its config while actually issuing 12-day tokens - the
    | documented control quietly not being in force.
    |
    | 720 minutes: long enough to cover a working day and a short handover, short
    | enough that a token left on a shared machine is dead by the next morning.
    |
    */

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 720),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
