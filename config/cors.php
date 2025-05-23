<?php


return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
      'https://healthy-barrio.vercel.app',
      'https://healthy-barrio-git-main-rogeeeis-projects.vercel.app',
      'https://healthy-barrio-475zlgjhj-rogeeeis-projects.vercel.app',
      'http://127.0.0.1:5500'
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

