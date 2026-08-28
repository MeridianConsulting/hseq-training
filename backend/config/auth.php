<?php

declare(strict_types=1);

return [
    'jwt_secret' => env('JWT_SECRET', 'change-this-secret'),
    'jwt_expiration' => (int)env('JWT_EXPIRATION', 3600),
    'jwt_algorithm' => 'HS256',
    'max_intentos' => 5,
    'bloqueo_minutos' => 30,
];
