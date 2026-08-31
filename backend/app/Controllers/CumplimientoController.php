<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditoriaService;
use App\Services\CumplimientoService;
use App\Services\SoporteService;

class CumplimientoController extends Controller
{
    private CumplimientoService $service;
    private SoporteService $soportes;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new CumplimientoService();
        $this->soportes = new SoporteService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $personaRaw = $request->query('persona_id');
        $sesionRaw = $request->query('sesion_id');
        $ev = $request->query('evidencia_faltante');
        $faltante = $ev === '1' || $ev === 1 || $ev === true || $ev === 'true';

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            [
                'persona_id' => ($personaRaw !== null && $personaRaw !== '') ? (int)$personaRaw : null,
                'sesion_id' => ($sesionRaw !== null && $sesionRaw !== '') ? (int)$sesionRaw : null,
                'buscar' => nullable_trimmed_string($request->query('buscar')),
                'evidencia_faltante' => $faltante ? 1 : null,
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

    public function soportes(Request $request, string $id): void
    {
        $this->success($this->soportes->listar((int)$id), 'Soportes del cumplimiento');
    }

    public function storeSoporte(Request $request, string $id): void
    {
        $archivo = $request->file('archivo');
        if ($archivo === null) {
            $this->error('Debe seleccionar un archivo.', 422);
            return;
        }

        $tipo = nullable_trimmed_string($request->input('tipo_soporte'));
        $creado = $this->soportes->cargar((int)$id, $archivo, $tipo, $request->userId() ?: null);

        $this->auditoria->dePeticion(
            $request,
            'cargar',
            'soportes_cumplimiento',
            (int)($creado['soporte_id'] ?? 0),
            [
                'cumplimiento_id' => (int)$id,
                'nombre_archivo' => $creado['nombre_archivo'] ?? null,
                'tipo_soporte' => $creado['tipo_soporte'] ?? null,
            ]
        );

        $this->created($creado, 'Archivo cargado');
    }

    public function descargarSoporte(Request $request, string $id): void
    {
        $archivo = $this->soportes->descargar((int)$id);

        $this->auditoria->dePeticion(
            $request,
            'descargar',
            'soportes_cumplimiento',
            (int)$id,
            ['nombre_archivo' => $archivo['nombre']]
        );

        Response::download($archivo['contenido'], $archivo['nombre'], $archivo['mime']);
    }

    public function destroySoporte(Request $request, string $id): void
    {
        $resultado = $this->soportes->eliminar((int)$id);

        $this->auditoria->dePeticion(
            $request,
            'eliminar',
            'soportes_cumplimiento',
            (int)$id,
            $resultado
        );

        $this->success($resultado, 'Archivo eliminado');
    }
}
