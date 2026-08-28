<?php

declare(strict_types=1);

use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CapacitacionController;
use App\Controllers\CatalogController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\MatrizController;
use App\Controllers\PersonalController;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermisoMiddleware;

/** @var \App\Core\Router $router */

$router->get('/api/ping', [HealthController::class, 'ping']);
$router->post('/api/auth/login', [AuthController::class, 'login']);

$router->group(['prefix' => '/api/auth', 'middleware' => [AuthMiddleware::class]], function ($router) {
    $router->get('/me', [AuthController::class, 'me']);
    $router->post('/logout', [AuthController::class, 'logout']);
});

$router->group(['prefix' => '/api', 'middleware' => [AuthMiddleware::class]], function ($router) {
    $router->group(['middleware' => [[PermisoMiddleware::class, 'catalogos.ver']]], function ($router) {
        $router->get('/catalogs', [CatalogController::class, 'tipos']);
        $router->get('/catalogs/{tipo}', [CatalogController::class, 'index']);
        $router->get('/catalogs/{tipo}/{id}', [CatalogController::class, 'show']);
    });

    $router->group(['middleware' => [[PermisoMiddleware::class, 'catalogos.gestionar']]], function ($router) {
        $router->post('/catalogs/{tipo}', [CatalogController::class, 'store']);
        $router->put('/catalogs/{tipo}/{id}', [CatalogController::class, 'update']);
        $router->delete('/catalogs/{tipo}/{id}', [CatalogController::class, 'destroy']);
    });

    $router->get('/dashboard', [DashboardController::class, 'show'], [[PermisoMiddleware::class, 'dashboard.ver']]);

    $router->get('/auditoria', [AuditoriaController::class, 'index'], [[PermisoMiddleware::class, 'auditoria.ver']]);

    $router->group(['prefix' => '/personal', 'middleware' => [[PermisoMiddleware::class, 'personal.ver']]], function ($router) {
        $router->get('/cargos', [PersonalController::class, 'cargos']);
        $router->get('', [PersonalController::class, 'index']);
        $router->get('/{id}', [PersonalController::class, 'show']);
    });

    $router->group(['prefix' => '/capacitaciones'], function ($router) {
        $router->get('', [CapacitacionController::class, 'index'], [[PermisoMiddleware::class, 'capacitaciones.ver']]);
        $router->get('/{id}', [CapacitacionController::class, 'show'], [[PermisoMiddleware::class, 'capacitaciones.ver']]);
        $router->post('', [CapacitacionController::class, 'store'], [[PermisoMiddleware::class, 'capacitaciones.crear']]);
        $router->put('/{id}', [CapacitacionController::class, 'update'], [[PermisoMiddleware::class, 'capacitaciones.editar']]);
        $router->delete('/{id}', [CapacitacionController::class, 'destroy'], [[PermisoMiddleware::class, 'capacitaciones.eliminar']]);
    });

    $router->group(['prefix' => '/matriz'], function ($router) {
        $router->get('', [MatrizController::class, 'index'], [[PermisoMiddleware::class, 'matriz.ver']]);
        $router->get('/{id}', [MatrizController::class, 'show'], [[PermisoMiddleware::class, 'matriz.ver']]);
        $router->post('', [MatrizController::class, 'store'], [[PermisoMiddleware::class, 'matriz.crear']]);
        $router->put('/{id}', [MatrizController::class, 'update'], [[PermisoMiddleware::class, 'matriz.editar']]);
        $router->delete('/{id}', [MatrizController::class, 'destroy'], [[PermisoMiddleware::class, 'matriz.eliminar']]);
    });
});
