<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://healthy-barrio-final.vercel.app','healthy-barrio-final-rogeeeis-projects.vercel.app',
'healthy-barrio-final-git-main-rogeeeis-projects.vercel.app', 'http://127.0.0.1:5500/'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
