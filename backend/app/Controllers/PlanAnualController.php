<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\PlanAnualService;

class PlanAnualController extends Controller
{
    private PlanAnualService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new PlanAnualService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $anioRaw = $request->query('anio');
        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            ($anioRaw !== null && $anioRaw !== '') ? (int)$anioRaw : null,
            nullable_trimmed_string($request->query('estado'))
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, [
            'anio' => 'required|integer|min:2000|max:2100',
        ]);
        $creado = $this->service->crear($datos, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'crear',
            'planes_anuales',
            (int)$creado['plan_anual_id'],
            $creado
        );

        $this->created($creado, 'Plan anual creado en borrador');
    }

    public function disponibles(Request $request, string $id): void
    {
        $resultado = $this->service->disponibles(
            (int)$id,
            nullable_trimmed_string($request->query('buscar'))
        );

        $this->success($resultado, 'Asignaciones disponibles para el plan');
    }

    public function incluir(Request $request, string $id): void
    {
        $datos = $this->validate($request, [
            'asignacion_ids' => 'required|array',
            'mes_programado' => 'required|integer|min:1|max:12',
        ]);
        $resultado = $this->service->incluirAsignaciones((int)$id, $datos);

        $this->auditoria->dePeticion(
            $request,
            'incluir_asignaciones',
            'planes_anuales',
            (int)$id,
            $resultado
        );

        $this->success($resultado, $this->service->mensajeInclusion($resultado));
    }

    public function quitarAsignacion(Request $request, string $id, string $asignacionId): void
    {
        $actualizado = $this->service->quitarAsignacion((int)$id, (int)$asignacionId);

        $this->auditoria->dePeticion(
            $request,
            'quitar_asignacion',
            'planes_anuales',
            (int)$id,
            ['asignacion_id' => (int)$asignacionId]
        );

        $this->success($actualizado, 'Asignación retirada del plan');
    }

    public function moverAsignacion(Request $request, string $id, string $asignacionId): void
    {
        $datos = $this->validate($request, [
            'mes_programado' => 'required|integer|min:1|max:12',
        ]);
        $actualizado = $this->service->moverAsignacion(
            (int)$id,
            (int)$asignacionId,
            (int)$datos['mes_programado']
        );

        $this->auditoria->dePeticion(
            $request,
            'mover_asignacion',
            'planes_anuales',
            (int)$id,
            ['asignacion_id' => (int)$asignacionId, 'mes_programado' => (int)$datos['mes_programado']]
        );

        $this->success($actualizado, 'Mes de programación actualizado');
    }

    public function enviarRevision(Request $request, string $id): void
    {
        $plan = $this->service->enviarRevision((int)$id);

        $this->auditoria->dePeticion(
            $request,
            'enviar_revision',
            'planes_anuales',
            (int)$id,
            ['estado_anterior' => 'BORRADOR', 'estado_nuevo' => 'EN_REVISION']
        );

        $this->success($plan, 'Plan enviado a revisión');
    }

    public function aprobar(Request $request, string $id): void
    {
        $plan = $this->service->aprobar((int)$id, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'aprobar',
            'planes_anuales',
            (int)$id,
            ['estado_anterior' => 'EN_REVISION', 'estado_nuevo' => 'APROBADO']
        );

        $this->success($plan, 'Plan anual aprobado');
    }
}
