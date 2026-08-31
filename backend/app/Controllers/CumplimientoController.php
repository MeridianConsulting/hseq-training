<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\CumplimientoService;

class CumplimientoController extends Controller
{
    private CumplimientoService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new CumplimientoService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $personaRaw = $request->query('persona_id');
        $sesionRaw = $request->query('sesion_id');

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            [
                'persona_id' => ($personaRaw !== null && $personaRaw !== '') ? (int)$personaRaw : null,
                'sesion_id' => ($sesionRaw !== null && $sesionRaw !== '') ? (int)$sesionRaw : null,
                'buscar' => nullable_trimmed_string($request->query('buscar')),
            ]
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function previsualizar(Request $request): void
    {
        $sesionId = (int)$request->query('sesion_id', 0);
        $ids = $request->query('asignacion_ids', []);
        $fecha = nullable_trimmed_string($request->query('fecha_realizacion'));
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $this->success(
            $this->service->previsualizar($sesionId, is_array($ids) ? $ids : [], $fecha),
            'Previsualización de vencimiento'
        );
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglasIndividual(), $this->service->mensajes());
        $creado = $this->service->registrar($datos, $request->userId() ?: null);

        $this->auditoria->dePeticion(
            $request,
            'crear',
            'cumplimientos_capacitacion',
            (int)($creado['cumplimiento_id'] ?? 0),
            $creado
        );

        $this->created($creado, 'Cumplimiento registrado');
    }

    public function storeMasivo(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglasMasivo(), $this->service->mensajes());
        $resultado = $this->service->registrarMasivo($datos, $request->userId() ?: null);

        $this->auditoria->dePeticion(
            $request,
            'registrar_masivo',
            'cumplimientos_capacitacion',
            (int)($datos['sesion_id'] ?? 0),
            $resultado
        );

        $this->success($resultado, $this->service->mensajeMasivo($resultado));
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->service->reglasEditar(), $this->service->mensajes());
        $actualizado = $this->service->actualizar((int)$id, $datos, $request->userId() ?: null);

        $this->auditoria->dePeticion(
            $request,
            'actualizar',
            'cumplimientos_capacitacion',
            (int)$id,
            $actualizado
        );

        $this->success($actualizado, 'Cumplimiento actualizado');
    }
}
