<?php

declare(strict_types=1);

return [
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    'allowed_methods' => env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
    'allowed_headers' => env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With'),
    'max_age' => 86400,
];
