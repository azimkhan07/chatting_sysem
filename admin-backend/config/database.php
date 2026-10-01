<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | This app owns exactly one database of its own: the console. Staff
    | accounts, their tokens and their sessions all live here, so a
    | compromised app deployment cannot read a single credential belonging
    | to the console.
    |
    | The main app's database is reached over the separate `app` connection
    | below, and only through the read/write models in app/Domain/App.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        // The console's own database. Nothing in the main app reads it.
        'admin' => [
            'driver' => 'sqlite',
            'url' => env('ADMIN_DB_URL'),
            'database' => env('ADMIN_DB_DATABASE', database_path('admin.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('ADMIN_DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        // The main app's database. This is a real read/write connection because
        // the console has genuine work to do there (approving a subscription,
        // closing a report, saving gateway keys). Nothing in this app runs a
        // migration against it: schema for these tables is owned by backend/,
        // so the two can never disagree about a migration.
        //
        // The driver is configurable because local development runs the main app
        // on SQLite while production runs MySQL, and a console that cannot
        // follow that switch would be a console nobody runs locally. The
        // connection is still one connection either way: the write policy is
        // enforced by which services touch what, and by database grants in
        // production.
        'app' => [
            'driver' => env('APP_DB_CONNECTION', 'mysql'),
            'url' => env('APP_DB_URL'),
            'host' => env('APP_DB_HOST', '127.0.0.1'),
            'port' => env('APP_DB_PORT', '3306'),
            'database' => env('APP_DB_DATABASE', 'amtechat'),
            'username' => env('APP_DB_USERNAME', 'amtechat'),
            'password' => env('APP_DB_PASSWORD', ''),
            'unix_socket' => env('APP_DB_SOCKET', ''),
            'charset' => env('APP_DB_CHARSET', 'utf8mb4'),
            'collation' => env('APP_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'foreign_key_constraints' => env('APP_DB_FOREIGN_KEYS', true),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('APP_DB_ATTR_SSL_CA'),
            ]) : [],
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => Str::slug((string) env('APP_NAME', 'admin-backend')).'-database-',
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'timeout' => env('REDIS_TIMEOUT', 2),
            'read_timeout' => env('REDIS_READ_TIMEOUT', 2),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'exponential'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '2'),
            'timeout' => env('REDIS_TIMEOUT', 2),
            'read_timeout' => env('REDIS_READ_TIMEOUT', 2),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'exponential'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
