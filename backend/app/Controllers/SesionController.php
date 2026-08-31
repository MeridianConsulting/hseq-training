<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\SesionService;

class SesionController extends Controller
{
    private SesionService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new SesionService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $detalleId = (int)$request->query('plan_detalle_id', 0);
        if ($detalleId < 1) {
            $this->error('Debe indicar el detalle del plan anual.', 422);
            return;
        }

        $this->success([
            'sesiones' => $this->service->listarPorDetalle($detalleId),
        ], 'Sesiones del detalle');
    }

    public function convocables(Request $request): void
    {
        $detalleId = (int)$request->query('plan_detalle_id', 0);
        if ($detalleId < 1) {
            $this->error('Debe indicar el detalle del plan anual.', 422);
            return;
        }

        $this->success(
            $this->service->contexto(
                $detalleId,
                null,
                nullable_trimmed_string($request->query('buscar'))
            ),
            'Trabajadores convocables'
        );
    }

    public function convocablesDeSesion(Request $request, string $id): void
    {
        $sesion = $this->service->ver((int)$id);
        if ($sesion['plan_detalle_id'] === null) {
            $this->error('La sesión no está asociada a un detalle del plan anual.', 422);
            return;
        }

        $this->success(
            $this->service->contexto(
                (int)$sesion['plan_detalle_id'],
                (int)$id,
                nullable_trimmed_string($request->query('buscar'))
            ),
            'Trabajadores convocables'
        );
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglasCrear(), $this->service->mensajes());
        $creada = $this->service->crear($datos, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'crear',
            'sesiones_capacitacion',
            (int)$creada['sesion_id'],
            $creada
        );

        $this->created($creada, 'Sesión creada correctamente.');
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->service->reglasEditar(), $this->service->mensajes());
        $anterior = $this->service->ver((int)$id);
        $actualizada = $this->service->actualizar((int)$id, $datos);

        $this->auditoria->dePeticion(
            $request,
            'actualizar',
            'sesiones_capacitacion',
            (int)$id,
            $actualizada,
            $anterior
        );

        $this->success($actualizada, 'Sesión actualizada.');
    }

    public function convocar(Request $request, string $id): void
    {
        $datos = $this->validate($request, [
            'asignacion_ids' => 'required|array',
        ]);
        $actualizada = $this->service->convocar((int)$id, $datos, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'convocar',
            'sesiones_capacitacion',
            (int)$id,
            [
                'asignacion_ids' => $datos['asignacion_ids'],
                'convocados' => $actualizada['convocados'],
                'cupo_maximo' => $actualizada['cupo_maximo'],
            ]
        );

        $this->success($actualizada, 'Trabajadores convocados.');
    }

    public function retirar(Request $request, string $id, string $asignacionId): void
    {
        $actualizada = $this->service->retirar((int)$id, (int)$asignacionId);

        $this->auditoria->dePeticion(
            $request,
            'retirar_convocado',
            'sesiones_capacitacion',
            (int)$id,
            ['asignacion_id' => (int)$asignacionId]
        );

        $this->success($actualizada, 'Trabajador retirado de la sesión.');
    }
}
