<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditoriaService;
use App\Services\ReporteService;

class ReporteController extends Controller
{
    private ReporteService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new ReporteService();
        $this->auditoria = new AuditoriaService();
    }

    public function opciones(Request $request): void
    {
        $this->success($this->service->opciones());
    }

    public function evidenciasFaltantes(Request $request): void
    {
        $resultado = $this->service->evidenciasFaltantes(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            $this->filtros($request)
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function show(Request $request, string $tipo): void
    {
        $resultado = $this->service->consultar(
            $tipo,
            $this->filtros($request),
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20)
        );

        $this->success([
            'items' => $resultado['items'],
            'pagination' => [
                'total' => $resultado['total'],
                'per_page' => $resultado['per_page'],
                'current_page' => $resultado['page'],
                'last_page' => max(1, (int)ceil($resultado['total'] / max(1, $resultado['per_page']))),
            ],
            'totales' => $resultado['totales'],
            'titulo' => $resultado['titulo'],
            'filtros_etiqueta' => $resultado['filtros_etiqueta'],
        ]);
    }

    public function excel(Request $request, string $tipo): void
    {
        $filtros = $this->filtros($request);
        $usuario = $request->user()['nombre_usuario'] ?? null;
        $archivo = $this->service->excel($tipo, $filtros, is_string($usuario) ? $usuario : null);

        $this->auditoria->dePeticion(
            $request,
            'exportar',
            'reportes',
            null,
            [
                'tipo' => $this->service->exigirTipo($tipo),
                'formato' => 'xlsx',
                'filtros' => $filtros,
            ]
        );

        Response::download(
            $archivo['contenido'],
            $archivo['nombre'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /** @return array<string,mixed> */
    private function filtros(Request $request): array
    {
        $procesoRaw = $request->query('proceso_id');
        $cargoRaw = $request->query('cargo_id_ext');
        $personaRaw = $request->query('persona_id');

        return [
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
            'proceso_id' => ($procesoRaw !== null && $procesoRaw !== '') ? (int)$procesoRaw : null,
            'proyecto' => nullable_trimmed_string($request->query('proyecto')),
            'cargo_id_ext' => ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null,
            'persona_id' => ($personaRaw !== null && $personaRaw !== '') ? (int)$personaRaw : null,
            'buscar' => nullable_trimmed_string($request->query('buscar')),
            'estado' => nullable_trimmed_string($request->query('estado')),
            'asistencia' => nullable_trimmed_string($request->query('asistencia')),
        ];
    }
}
