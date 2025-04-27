<?php


return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'], // Keep these
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://healthy-barrio-final.vercel.app',
        'https://healthy-barrio-final-rogeeeis-projects.vercel.app',
        'https://healthy-barrio-final-git-main-rogeeeis-projects.vercel.app',
        'http://127.0.0.1:5500', // <-- remove the '/' at the end
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // <-- IMPORTANT!! set to true
];
