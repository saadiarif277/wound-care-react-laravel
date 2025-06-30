<?php

use Illuminate\Support\Str;

return [
<<<<<<< HEAD
    'default' => env('DB_CONNECTION', 'supabase'),

    'connections' => [
        'supabase' => [
            'driver' => 'pgsql',
            'host' => env('SUPABASE_DB_HOST'),
            'port' => env('SUPABASE_DB_PORT', '5432'),
            'database' => env('SUPABASE_DB_DATABASE', 'postgres'),
            'username' => env('SUPABASE_DB_USERNAME'),
            'password' => env('SUPABASE_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => env('SUPABASE_DB_SSL_MODE', 'require'),
        ],

=======
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
>>>>>>> origin/provider-side
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
<<<<<<< HEAD
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                #PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::MYSQL_ATTR_SSL_KEY => env('DB_SSL_KEY'),
            ]) : [],
=======
            'sslmode' => 'require',
            'options' => extension_loaded('pdo_mysql') ? [
                // For Azure MySQL - skip certificate verification
                PDO::MYSQL_ATTR_SSL_CIPHER => 'AES256-SHA',
                1014 => false, // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
            ] : [],
        ],

        'supabase' => [
            'driver' => 'pgsql',
            'host' => env('SUPABASE_DB_HOST'),
            'port' => env('SUPABASE_DB_PORT', '5432'),
            'database' => env('SUPABASE_DB_DATABASE', 'postgres'),
            'username' => env('SUPABASE_DB_USERNAME'),
            'password' => env('SUPABASE_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => env('SUPABASE_DB_SSL_MODE', 'require'),
>>>>>>> origin/provider-side
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

<<<<<<< HEAD
    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

=======
    'migrations' => 'migrations',

>>>>>>> origin/provider-side
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

];