<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $archivo = __DIR__ . $ruta;
    if ($ruta !== '/' && is_file($archivo)) {
        return false;
    }
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Application;

$app = Application::getInstance();
$app->run();
