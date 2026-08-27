<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\HealthController;
use App\Middleware\AuthMiddleware;

/** @var \App\Core\Router $router */

$router->get('/api/ping', [HealthController::class, 'ping']);
$router->post('/api/auth/login', [AuthController::class, 'login']);

$router->group(['prefix' => '/api/auth', 'middleware' => [AuthMiddleware::class]], function ($router) {
    $router->get('/me', [AuthController::class, 'me']);
    $router->post('/logout', [AuthController::class, 'logout']);
});

$router->group(['prefix' => '/api', 'middleware' => [AuthMiddleware::class]], function ($router) {
    $router->get('/catalogs', [CatalogController::class, 'tipos']);
    $router->get('/catalogs/{tipo}', [CatalogController::class, 'index']);
    $router->get('/catalogs/{tipo}/{id}', [CatalogController::class, 'show']);
    $router->post('/catalogs/{tipo}', [CatalogController::class, 'store']);
    $router->put('/catalogs/{tipo}/{id}', [CatalogController::class, 'update']);
    $router->delete('/catalogs/{tipo}/{id}', [CatalogController::class, 'destroy']);
});
