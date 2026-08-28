<?php

declare(strict_types=1);

$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

return [
    'host' => $host,
    'port' => $port,
    'database' => env('DB_DATABASE', 'meridian_capacitaciones'),
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',

    // Personal vive en la misma instancia MySQL (cPanel anade prefijo de cuenta).
    // Si no se definen, se reutilizan host/usuario/clave de capacitaciones.
    'personal_host' => env('DB_PERSONAL_HOST', $host),
    'personal_port' => env('DB_PERSONAL_PORT', $port),
    'personal_database' => env('DB_PERSONAL_NAME', env('DB_PERSONAL_DATABASE', 'meridian_personal')),
    'personal_username' => env('DB_PERSONAL_USER', env('DB_PERSONAL_USERNAME', $username)),
    'personal_password' => env('DB_PERSONAL_PASSWORD', $password),
];
