<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://healthy-barrio-final.vercel.app','healthy-barrio-final-rogeeeis-projects.vercel.app',
'healthy-barrio-final-git-main-rogeeeis-projects.vercel.app'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
