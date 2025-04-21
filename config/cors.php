<?php


return [
  'paths' => ['api/*', 'sanctum/csrf-cookie'],
  'allowed_methods'   => ['*'],
  'allowed_origins'   => [
    'https://healthy-barrio-final.vercel.app',
    'https://healthy-barrio-final-dgfrowibh-rogeeeis-projects.vercel.app',
  ],
  'allowed_headers'   => ['*'],
  'supports_credentials' => false,
];
