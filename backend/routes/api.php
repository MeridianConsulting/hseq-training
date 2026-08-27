<?php

declare(strict_types=1);

use App\Controllers\CatalogController;
use App\Controllers\HealthController;

/** @var \App\Core\Router $router */

$router->get('/api/ping', [HealthController::class, 'ping']);

// Pendiente: proteger con AuthMiddleware cuando exista el login contra usuarios_sistema.
$router->group(['prefix' => '/api'], function ($router) {
    $router->get('/catalogs', [CatalogController::class, 'tipos']);
    $router->get('/catalogs/{tipo}', [CatalogController::class, 'index']);
    $router->get('/catalogs/{tipo}/{id}', [CatalogController::class, 'show']);
    $router->post('/catalogs/{tipo}', [CatalogController::class, 'store']);
    $router->put('/catalogs/{tipo}/{id}', [CatalogController::class, 'update']);
    $router->delete('/catalogs/{tipo}/{id}', [CatalogController::class, 'destroy']);
});
