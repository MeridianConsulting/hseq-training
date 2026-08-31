<?php

declare(strict_types=1);

use App\Controllers\AsignacionController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CapacitacionController;
use App\Controllers\CatalogController;
use App\Controllers\CronogramaController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\MatrizController;
use App\Controllers\PersonalController;
use App\Controllers\PlanAnualController;
use App\Controllers\CumplimientoController;
use App\Controllers\SesionController;
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

    $router->get('/cronograma', [CronogramaController::class, 'show'], [[PermisoMiddleware::class, 'planes.ver']]);

    $router->group(['prefix' => '/sesiones'], function ($router) {
        $router->get('/convocables', [SesionController::class, 'convocables'], [[PermisoMiddleware::class, 'sesiones.ver']]);
        $router->get('/historial', [SesionController::class, 'historial'], [[PermisoMiddleware::class, 'sesiones.ver']]);
        $router->get('', [SesionController::class, 'index'], [[PermisoMiddleware::class, 'sesiones.ver']]);
        $router->post('', [SesionController::class, 'store'], [[PermisoMiddleware::class, 'sesiones.crear']]);
        $router->get('/{id}/convocables', [SesionController::class, 'convocablesDeSesion'], [[PermisoMiddleware::class, 'sesiones.ver']]);
        $router->post('/{id}/participantes', [SesionController::class, 'convocar'], [[PermisoMiddleware::class, 'sesiones.editar']]);
        $router->delete('/{id}/participantes/{asignacionId}', [SesionController::class, 'retirar'], [[PermisoMiddleware::class, 'sesiones.editar']]);
        $router->put('/{id}/asistencia', [SesionController::class, 'asistencia'], [[PermisoMiddleware::class, 'sesiones.editar']]);
        $router->post('/{id}/reprogramar', [SesionController::class, 'reprogramar'], [[PermisoMiddleware::class, 'sesiones.editar']]);
        $router->get('/{id}', [SesionController::class, 'show'], [[PermisoMiddleware::class, 'sesiones.ver']]);
        $router->put('/{id}', [SesionController::class, 'update'], [[PermisoMiddleware::class, 'sesiones.editar']]);
    });

    $router->group(['prefix' => '/planes-anuales'], function ($router) {
        $router->get('', [PlanAnualController::class, 'index'], [[PermisoMiddleware::class, 'planes.ver']]);
        $router->post('', [PlanAnualController::class, 'store'], [[PermisoMiddleware::class, 'planes.crear']]);
        $router->get('/{id}/asignaciones-disponibles', [PlanAnualController::class, 'disponibles'], [[PermisoMiddleware::class, 'planes.ver']]);
        $router->post('/{id}/asignaciones', [PlanAnualController::class, 'incluir'], [[PermisoMiddleware::class, 'planes.editar']]);
        $router->delete('/{id}/asignaciones/{asignacionId}', [PlanAnualController::class, 'quitarAsignacion'], [[PermisoMiddleware::class, 'planes.editar']]);
        $router->put('/{id}/asignaciones/{asignacionId}', [PlanAnualController::class, 'moverAsignacion'], [[PermisoMiddleware::class, 'planes.editar']]);
        $router->post('/{id}/enviar-revision', [PlanAnualController::class, 'enviarRevision'], [[PermisoMiddleware::class, 'planes.editar']]);
        $router->post('/{id}/aprobar', [PlanAnualController::class, 'aprobar'], [[PermisoMiddleware::class, 'planes.aprobar']]);
        $router->get('/{id}', [PlanAnualController::class, 'show'], [[PermisoMiddleware::class, 'planes.ver']]);
    });

    $router->get('/auditoria', [AuditoriaController::class, 'index'], [[PermisoMiddleware::class, 'auditoria.ver']]);

    $router->group(['prefix' => '/personal'], function ($router) {
        $router->get('/cargos', [PersonalController::class, 'cargos'], [[PermisoMiddleware::class, 'personal.ver']]);
        $router->get('/tipos-documento', [PersonalController::class, 'tiposDocumento'], [[PermisoMiddleware::class, 'personal.ver']]);
        $router->get('/plantilla', [PersonalController::class, 'plantilla'], [[PermisoMiddleware::class, 'personal.importar']]);
        $router->post('/importar', [PersonalController::class, 'importar'], [[PermisoMiddleware::class, 'personal.importar']]);
        $router->get('', [PersonalController::class, 'index'], [[PermisoMiddleware::class, 'personal.ver']]);
        $router->post('', [PersonalController::class, 'store'], [[PermisoMiddleware::class, 'personal.crear']]);
        $router->get('/{id}', [PersonalController::class, 'show'], [[PermisoMiddleware::class, 'personal.ver']]);
        $router->put('/{id}', [PersonalController::class, 'update'], [[PermisoMiddleware::class, 'personal.editar']]);
    });

    $router->group(['prefix' => '/asignaciones'], function ($router) {
        $router->get('/proximas', [AsignacionController::class, 'proximas'], [[PermisoMiddleware::class, 'asignaciones.ver']]);
        $router->post('/generar-automaticas', [AsignacionController::class, 'generarAutomaticas'], [[PermisoMiddleware::class, 'asignaciones.crear']]);
        $router->post('/masivo', [AsignacionController::class, 'storeMasivo'], [[PermisoMiddleware::class, 'asignaciones.crear']]);
        $router->get('', [AsignacionController::class, 'index'], [[PermisoMiddleware::class, 'asignaciones.ver']]);
        $router->get('/{id}', [AsignacionController::class, 'show'], [[PermisoMiddleware::class, 'asignaciones.ver']]);
        $router->post('', [AsignacionController::class, 'store'], [[PermisoMiddleware::class, 'asignaciones.crear']]);
        $router->put('/{id}', [AsignacionController::class, 'update'], [[PermisoMiddleware::class, 'asignaciones.editar']]);
        $router->delete('/{id}', [AsignacionController::class, 'destroy'], [[PermisoMiddleware::class, 'asignaciones.eliminar']]);
    });

    $router->group(['prefix' => '/cumplimientos'], function ($router) {
        $router->get('/previsualizar', [CumplimientoController::class, 'previsualizar'], [[PermisoMiddleware::class, 'cumplimientos.ver']]);
        $router->get('', [CumplimientoController::class, 'index'], [[PermisoMiddleware::class, 'cumplimientos.ver']]);
        $router->post('/masivo', [CumplimientoController::class, 'storeMasivo'], [[PermisoMiddleware::class, 'cumplimientos.crear']]);
        $router->post('', [CumplimientoController::class, 'store'], [[PermisoMiddleware::class, 'cumplimientos.crear']]);
        $router->put('/{id}', [CumplimientoController::class, 'update'], [[PermisoMiddleware::class, 'cumplimientos.editar']]);
        $router->get('/{id}', [CumplimientoController::class, 'show'], [[PermisoMiddleware::class, 'cumplimientos.ver']]);
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
        $router->get('/aplicables', [MatrizController::class, 'aplicables'], [[PermisoMiddleware::class, 'matriz.ver']]);
        $router->post('/asociar-masivo', [MatrizController::class, 'asociarMasivo'], [[PermisoMiddleware::class, 'matriz.crear']]);
        $router->get('/{id}', [MatrizController::class, 'show'], [[PermisoMiddleware::class, 'matriz.ver']]);
        $router->post('', [MatrizController::class, 'store'], [[PermisoMiddleware::class, 'matriz.crear']]);
        $router->put('/{id}', [MatrizController::class, 'update'], [[PermisoMiddleware::class, 'matriz.editar']]);
        $router->delete('/{id}', [MatrizController::class, 'destroy'], [[PermisoMiddleware::class, 'matriz.eliminar']]);
    });
});
