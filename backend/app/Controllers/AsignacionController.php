<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AsignacionService;
use App\Services\AuditoriaService;

class AsignacionController extends Controller
{
    private AsignacionService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new AsignacionService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $personaRaw = $request->query('persona_id');
        $capRaw = $request->query('capacitacion_id');

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            ($personaRaw !== null && $personaRaw !== '') ? (int)$personaRaw : null,
            ($capRaw !== null && $capRaw !== '') ? (int)$capRaw : null,
            nullable_trimmed_string($request->query('estado')),
            nullable_trimmed_string($request->query('alerta')),
            nullable_trimmed_string($request->query('buscar'))
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function proximas(Request $request): void
    {
        $this->success($this->service->proximas(), 'Capacitaciones próximas a vencer');
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglas());
        $creado = $this->service->crear($datos, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'crear',
            'asignaciones_capacitacion',
            (int)$creado['asignacion_id'],
            $creado
        );

        $this->created($creado, 'Capacitación asignada');
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->service->reglas(true));
        $actualizado = $this->service->actualizar((int)$id, $datos);

        $this->auditoria->dePeticion(
            $request,
            'actualizar',
            'asignaciones_capacitacion',
            (int)$id,
            $actualizado
        );

        $this->success($actualizado, 'Asignación actualizada');
    }

    public function destroy(Request $request, string $id): void
    {
        $mensaje = $this->service->eliminar((int)$id);

        $this->auditoria->dePeticion(
            $request,
            'eliminar',
            'asignaciones_capacitacion',
            (int)$id,
            ['mensaje' => $mensaje]
        );

        $this->success(null, $mensaje);
    }
}
