<?php


return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'], // Keep these
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://healthy-barrio.vercel.app/',
        'https://healthy-barrio-git-main-rogeeeis-projects.vercel.app/',
        'https://healthy-barrio-rogeeeis-projects.vercel.app/',
        'http://127.0.0.1:5500', // <-- remove the '/' at the end
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // <-- IMPORTANT!! set to true
];
