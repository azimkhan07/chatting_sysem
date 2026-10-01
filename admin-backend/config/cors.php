<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The console SPA is served from its own origin — a different port in dev,
    | a different host in production — and this API is a different origin from
    | it. Without CORS the browser blocks the response even though the request
    | arrived fine, which reads as a network error in the console and hides the
    | real status code.
    |
    | `allowed_origins` is never `*`. This API mints console sessions and applies
    | enforcement decisions to real user accounts; a wildcard would let any site
    | a signed-in developer visits issue these requests and read the responses.
    | The dev port is listed explicitly for that reason.
    |
    | Note that CORS is not an authentication control. A token is still required
    | on every route but login, and a cross-origin caller cannot read a response
    | it was not granted an origin for.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5175')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
