<?php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'meridian_capacitaciones'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',

    // Personal vive en la misma instancia MySQL, por lo que se consulta calificando
    // el nombre de la base. En cPanel ese nombre lleva el prefijo de la cuenta.
    'personal_database' => env('DB_PERSONAL_DATABASE', 'meridian_personal'),
];
