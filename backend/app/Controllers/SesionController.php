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

    public function historial(Request $request): void
    {
        $personaId = (int)$request->query('persona_id', 0);
        $this->success(
            ['items' => $this->service->historialPersona($personaId)],
            'Historial de sesiones del trabajador'
        );
    }

    public function asistencia(Request $request, string $id): void
    {
        $datos = $this->validate($request, [
            'items' => 'required|array',
        ], [
            'items.required' => 'Debe enviar los resultados de asistencia.',
            'items.array' => 'Debe enviar los resultados de asistencia.',
        ]);
        $anterior = $this->service->ver((int)$id);
        $actualizada = $this->service->guardarAsistencia((int)$id, $datos, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'asistencia',
            'sesiones_capacitacion',
            (int)$id,
            [
                'resumen' => $actualizada['resumen'] ?? null,
                'items' => $datos['items'],
            ],
            ['participantes' => $anterior['participantes'] ?? []]
        );

        $this->success($actualizada, 'Control de asistencia registrado correctamente.');
    }

    public function reprogramar(Request $request, string $id): void
    {
        $datos = $this->validate($request, [
            'origen_sesion_id' => 'required|integer|min:1',
            'asignacion_ids' => 'required|array',
        ], [
            'origen_sesion_id.required' => 'Debe indicar la sesión de origen.',
            'asignacion_ids.required' => 'Seleccione al menos un trabajador ausente.',
        ]);
        $actualizada = $this->service->reprogramar((int)$id, $datos, $request->userId());
        $resumen = $actualizada['reprogramacion'] ?? [];

        $this->auditoria->dePeticion(
            $request,
            'reprogramar',
            'sesiones_capacitacion',
            (int)$id,
            [
                'origen_sesion_id' => (int)$datos['origen_sesion_id'],
                'asignacion_ids' => $datos['asignacion_ids'],
                'reprogramacion' => $resumen,
            ]
        );

        $seleccionados = (int)($resumen['seleccionados'] ?? 0);
        $reprogramados = (int)($resumen['reprogramados'] ?? 0);
        $this->success(
            $actualizada,
            "Seleccionados: {$seleccionados}. Reprogramados: {$reprogramados}. Errores: 0."
        );
    }
}
