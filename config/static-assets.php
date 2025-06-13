<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Static Asset Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how long static assets should be cached by browsers.
    | The default is one year (31536000 seconds).
    |
    */

    'cache_max_age' => env('STATIC_ASSET_CACHE_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Static Asset Extensions
    |--------------------------------------------------------------------------
    |
    | Define which file extensions should be considered static assets.
    |
    */

    'extensions' => [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
        'css', 'js', 'map',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'mp3', 'wav',
        'pdf', 'zip'
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Security headers to be added to static asset responses.
    |
    */

    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
    ],
];
