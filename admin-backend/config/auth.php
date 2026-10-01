<?php

declare(strict_types=1);

use Admin\Domain\Admin\Models\StaffUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | There is no `web` guard here. This app serves an API and holds no
    | browser session: `admin:staff` is the Sanctum guard and every route is
    | authenticated with a bearer token. The `web` default is kept only
    | because the framework's auth service provider expects it to resolve.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin:staff'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'staff'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'admin:staff' => [
            'driver' => 'sanctum',
            'provider' => 'staff',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | The one provider in this app points at console accounts. An app user
    | cannot be authenticated here at all: they do not exist on the `admin`
    | connection and this process has no user model bound to a guard.
    |
    */

    'providers' => [
        'staff' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', StaffUser::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Console passwords are changed in-app by someone who already holds a
    | session, so there is no reset broker. A staff account that forgets its
    | password is fixed by another super_admin, or by seeding it directly.
    | Keeping a reset table here would only be a write target with no route.
    |
    */

    'passwords' => [
        'staff' => [
            'provider' => 'staff',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
