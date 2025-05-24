<?php

use Illuminate\Support\Str;

return [
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
    ],

    'migrations' => 'migrations',
];