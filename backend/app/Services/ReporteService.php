<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\HistorialContextoRepository;
use App\Repositories\ReporteRepository;
use App\Repositories\SoporteRepository;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteService
{
    public const MENSAJE_VACIO = 'No se encontraron registros para los filtros seleccionados.';
    public const MAX_EXPORT = 20000;

    public const TIPOS = [
        'cumplimiento_general',
        'cumplimiento_trabajador',
        'cumplimiento_cargo',
        'cumplimiento_proceso',
        'cumplimiento_proyecto',
        'vencidas',
        'proximas',
        'pendientes',
        'horas',
        'asistencia',
        'inducciones',
        'reinducciones',
        'tareas_criticas',
        'evidencias_faltantes',
        'historial_trabajador',
    ];

    private ReporteRepository $repo;
    private AlertaService $alertas;
    private AlertaRepository $alertaRepo;
    private DashboardRepository $dashboard;
    private PersonalService $personal;
    private HistorialContextoRepository $historial;
    private SoporteRepository $soportes;

    public function __construct()
    {
        $this->repo = new ReporteRepository();
        $this->alertas = new AlertaService();
        $this->alertaRepo = new AlertaRepository();
        $this->dashboard = new DashboardRepository();
        $this->personal = new PersonalService();
        $this->historial = new HistorialContextoRepository();
        $this->soportes = new SoporteRepository();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{
     *   items:list<array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   totales:array<string,mixed>,
     *   titulo:string,
     *   filtros_etiqueta:array<string,string>
     * }
     */
    public function consultar(string $tipo, array $filtros, int $pagina, int $porPagina): array
    {
        $tipo = $this->exigirTipo($tipo);
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $limpios = $this->normalizarFiltros($filtros);
        $offset = ($pagina - 1) * $porPagina;

        if ($tipo === 'historial_trabajador') {
            return $this->consultarHistorial($limpios, $pagina, $porPagina);
        }

        if ($tipo === 'proximas') {
            $resultado = $this->alertas->listar($pagina, $porPagina, $limpios);
            $totales = $this->repo->empaquetarTotales((int)$resultado['total'], 0, 0, 0, (int)$resultado['total'], 0.0);

            return [
                'items' => $resultado['items'],
                'total' => $resultado['total'],
                'page' => $resultado['page'],
                'per_page' => $resultado['per_page'],
                'totales' => $totales,
                'titulo' => $this->titulo($tipo),
                'filtros_etiqueta' => $this->etiquetasFiltro($limpios),
            ];
        }

        $filas = $this->repo->listar($tipo, $limpios, $porPagina, $offset);
        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizarFila($tipo, $fila);
        }

        return [
            'items' => $items,
            'total' => $this->repo->contar($tipo, $limpios),
            'page' => $pagina,
            'per_page' => $porPagina,
            'totales' => $this->totalesParaTipo($tipo, $limpios),
            'titulo' => $this->titulo($tipo),
            'filtros_etiqueta' => $this->etiquetasFiltro($limpios),
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{contenido:string,nombre:string}
     */
    public function excel(string $tipo, array $filtros, ?string $usuario): array
    {
        $tipo = $this->exigirTipo($tipo);
        $limpios = $this->normalizarFiltros($filtros);

        if ($tipo === 'historial_trabajador') {
            return $this->excelHistorial($limpios, $usuario);
        }

        if ($tipo === 'proximas') {
            $todo = $this->alertas->listarTodos($limpios);
            $items = $todo['items'];
            $totales = $this->repo->empaquetarTotales((int)$todo['total'], 0, 0, 0, (int)$todo['total'], 0.0);
            $total = (int)$todo['total'];
        } else {
            $total = $this->repo->contar($tipo, $limpios);
            $filas = $total === 0 ? [] : $this->repo->listar($tipo, $limpios, min(self::MAX_EXPORT, max(1, $total)), 0);
            $items = [];
            foreach ($filas as $fila) {
                $items[] = $this->normalizarFila($tipo, $fila);
            }
            $totales = $this->totalesParaTipo($tipo, $limpios);
        }

        if ($total === 0) {
            throw new HttpException(self::MENSAJE_VACIO, 422);
        }

        $titulo = $this->titulo($tipo);
        $etiquetas = $this->etiquetasFiltro($limpios);
        $columnas = $this->columnas($tipo);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(substr($titulo, 0, 31));

        $fila = 1;
        $hoja->setCellValue('A' . $fila, 'REPORTE');
        $hoja->setCellValue('B' . $fila, $titulo);
        $fila++;
        $hoja->setCellValue('A' . $fila, 'FECHA DE GENERACIÓN');
        $hoja->setCellValue('B' . $fila, (new DateTimeImmutable('now'))->format('d/m/Y H:i'));
        $fila++;
        if ($usuario !== null && $usuario !== '') {
            $hoja->setCellValue('A' . $fila, 'USUARIO');
            $hoja->setCellValue('B' . $fila, $usuario);
            $fila++;
        }
        foreach ($etiquetas as $clave => $valor) {
            $hoja->setCellValue('A' . $fila, strtoupper($clave));
            $hoja->setCellValue('B' . $fila, $valor);
            $fila++;
        }
        $fila++;

        $colIndex = 1;
        foreach ($columnas as $col) {
            $hoja->setCellValueByColumnAndRow($colIndex, $fila, $col['etiqueta']);
            $colIndex++;
        }
        $ultimaCol = Coordinate::stringFromColumnIndex(count($columnas));
        $hoja->getStyle('A' . $fila . ':' . $ultimaCol . $fila)->getFont()->setBold(true);
        $inicioDatos = $fila + 1;

        foreach ($items as $item) {
            $fila++;
            $colIndex = 1;
            foreach ($columnas as $col) {
                $clave = $col['clave'];
                $valor = $item[$clave] ?? '';
                if (is_bool($valor)) {
                    $valor = $valor ? 'Sí' : 'No';
                }
                if (($col['tipo'] ?? '') === 'fecha') {
                    $valor = $this->fechaExcel(is_string($valor) ? $valor : null);
                }
                if (($col['tipo'] ?? '') === 'numero' && ($valor === null || $valor === '')) {
                    $valor = null;
                }
                $hoja->setCellValueByColumnAndRow($colIndex, $fila, $valor);
                if (($col['tipo'] ?? '') === 'numero') {
                    $coord = Coordinate::stringFromColumnIndex($colIndex) . $fila;
                    $hoja->getStyle($coord)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                }
                $colIndex++;
            }
        }

        $fila += 2;
        $hoja->setCellValue('A' . $fila, 'TOTAL REGISTROS');
        $hoja->setCellValue('B' . $fila, $total);
        $fila++;
        if ($tipo === 'horas') {
            $hoja->setCellValue('A' . $fila, 'TOTAL HORAS');
            $hoja->setCellValue('B' . $fila, $totales['horas']);
            $hoja->getStyle('B' . $fila)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        } elseif ($tipo === 'asistencia') {
            $hoja->setCellValue('A' . $fila, 'ASISTIERON');
            $hoja->setCellValue('B' . $fila, $totales['asistieron'] ?? 0);
            $fila++;
            $hoja->setCellValue('A' . $fila, 'TARDE');
            $hoja->setCellValue('B' . $fila, $totales['tarde'] ?? 0);
            $fila++;
            $hoja->setCellValue('A' . $fila, 'AUSENTES');
            $hoja->setCellValue('B' . $fila, $totales['ausentes'] ?? 0);
            $fila++;
            $hoja->setCellValue('A' . $fila, 'CONVOCADOS');
            $hoja->setCellValue('B' . $fila, $totales['convocados'] ?? 0);
        } elseif (!in_array($tipo, ['evidencias_faltantes', 'proximas'], true)) {
            $hoja->setCellValue('A' . $fila, 'PROGRAMADAS');
            $hoja->setCellValue('B' . $fila, $totales['programadas'] ?? $totales['asignadas']);
            $fila++;
            $hoja->setCellValue('A' . $fila, 'EJECUTADAS');
            $hoja->setCellValue('B' . $fila, $totales['ejecutadas'] ?? $totales['completadas']);
            $fila++;
            $hoja->setCellValue('A' . $fila, 'PENDIENTES');
            $hoja->setCellValue('B' . $fila, $totales['pendientes']);
            $fila++;
            $hoja->setCellValue('A' . $fila, 'VENCIDAS');
            $hoja->setCellValue('B' . $fila, $totales['vencidas']);
            $fila++;
            $hoja->setCellValue('A' . $fila, '% CUMPLIMIENTO');
            $hoja->setCellValue('B' . $fila, $totales['porcentaje'] === null ? '—' : $totales['porcentaje']);
        }

        $hoja->getColumnDimension('A')->setWidth(28);
        $hoja->getColumnDimension('B')->setWidth(36);
        for ($i = 3; $i <= max(3, count($columnas)); $i++) {
            $hoja->getColumnDimensionByColumn($i)->setWidth(18);
        }

        unset($inicioDatos);
        $escritor = new Xlsx($libro);
        ob_start();
        $escritor->save('php://output');
        $contenido = (string)ob_get_clean();
        $libro->disconnectWorksheets();

        return [
            'contenido' => $contenido,
            'nombre' => $tipo . '_' . (new DateTimeImmutable('today'))->format('Y-m-d') . '.xlsx',
        ];
    }

    /**
     * @return array{procesos:list<array<string,mixed>>,proyectos:list<string>,cargos:list<array<string,mixed>>}
     */
    public function opciones(): array
    {
        $base = $this->alertas->opciones();
        $capacitaciones = [];
        foreach ($this->repo->catalogoCapacitaciones() as $fila) {
            $capacitaciones[] = [
                'capacitacion_id' => (int)$fila['capacitacion_id'],
                'codigo' => (string)$fila['codigo'],
                'nombre' => (string)$fila['nombre'],
            ];
        }
        $tipos = [];
        foreach ($this->repo->catalogoTiposCapacitacion() as $fila) {
            $tipos[] = [
                'tipo_capacitacion_id' => (int)$fila['tipo_capacitacion_id'],
                'nombre' => (string)$fila['nombre'],
            ];
        }
        $base['capacitaciones'] = $capacitaciones;
        $base['tipos_capacitacion'] = $tipos;

        return $base;
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function buscarTrabajadores(?string $buscar): array
    {
        $texto = $buscar !== null ? trim($buscar) : '';
        if ($texto === '') {
            return ['items' => [], 'total' => 0];
        }

        $resultado = $this->personal->listar(1, 20, $texto, null, null);

        return [
            'items' => $resultado['items'],
            'total' => (int)$resultado['total'],
        ];
    }

    public function titulo(string $tipo): string
    {
        return match ($tipo) {
            'cumplimiento_general' => 'Cumplimiento general',
            'cumplimiento_trabajador' => 'Cumplimiento por trabajador',
            'cumplimiento_cargo' => 'Cumplimiento por cargo',
            'cumplimiento_proceso' => 'Cumplimiento por proceso',
            'cumplimiento_proyecto' => 'Cumplimiento por proyecto',
            'vencidas' => 'Capacitaciones vencidas',
            'proximas' => 'Capacitaciones próximas a vencer',
            'pendientes' => 'Capacitaciones pendientes',
            'horas' => 'Horas de capacitación',
            'asistencia' => 'Asistencia',
            'inducciones' => 'Inducciones',
            'reinducciones' => 'Reinducciones',
            'tareas_criticas' => 'Tareas críticas',
            'evidencias_faltantes' => 'Evidencias faltantes',
            'historial_trabajador' => 'Historial del trabajador',
            default => 'Reporte',
        };
    }

    public function exigirTipo(string $tipo): string
    {
        $tipo = str_replace('-', '_', trim($tipo));
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new HttpException('El tipo de reporte no es válido.', 422);
        }

        return $tipo;
    }

    /**
     * Conserva el contrato del endpoint legado.
     *
     * @param array<string,mixed> $filtros
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function evidenciasFaltantes(int $pagina, int $porPagina, array $filtros): array
    {
        $resultado = $this->consultar('evidencias_faltantes', $filtros, $pagina, $porPagina);
        $items = [];
        foreach ($resultado['items'] as $fila) {
            $items[] = [
                'cumplimiento_id' => $fila['cumplimiento_id'] ?? null,
                'asignacion_id' => $fila['asignacion_id'] ?? null,
                'persona_id_ext' => $fila['persona_id_ext'] ?? null,
                'trabajador' => $fila['trabajador'] ?? null,
                'documento' => $fila['documento'] ?? null,
                'capacitacion' => $fila['capacitacion'] ?? null,
                'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
                'estado' => $fila['estado'] ?? null,
                'requiere_certificado' => true,
                'soportes_count' => $fila['soportes_count'] ?? 0,
            ];
        }

        return [
            'items' => $items,
            'total' => $resultado['total'],
            'page' => $resultado['page'],
            'per_page' => $resultado['per_page'],
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    private function normalizarFiltros(array $filtros): array
    {
        $desde = $this->fechaONulo($filtros['desde'] ?? null);
        $hasta = $this->fechaONulo($filtros['hasta'] ?? null);
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw new HttpException('La fecha inicial no puede ser posterior a la fecha final.', 422);
        }

        $procesoId = isset($filtros['proceso_id']) && (int)$filtros['proceso_id'] > 0
            ? (int)$filtros['proceso_id']
            : null;
        $proyectoRaw = isset($filtros['proyecto']) ? trim((string)$filtros['proyecto']) : '';
        $proyecto = null;
        if ($proyectoRaw !== '' && $this->alertaRepo->procesoEsGestionProyectos($procesoId)) {
            $proyecto = $proyectoRaw;
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'proceso_id' => $procesoId,
            'proyecto' => $proyecto,
            'cargo_id_ext' => isset($filtros['cargo_id_ext']) && (int)$filtros['cargo_id_ext'] > 0
                ? (int)$filtros['cargo_id_ext']
                : null,
            'persona_id' => isset($filtros['persona_id']) && (int)$filtros['persona_id'] > 0
                ? (int)$filtros['persona_id']
                : null,
            'buscar' => isset($filtros['buscar']) && trim((string)$filtros['buscar']) !== ''
                ? trim((string)$filtros['buscar'])
                : null,
            'estado' => isset($filtros['estado']) && trim((string)$filtros['estado']) !== ''
                ? trim((string)$filtros['estado'])
                : null,
            'asistencia' => isset($filtros['asistencia']) && trim((string)$filtros['asistencia']) !== ''
                ? trim((string)$filtros['asistencia'])
                : null,
            'capacitacion_id' => isset($filtros['capacitacion_id']) && (int)$filtros['capacitacion_id'] > 0
                ? (int)$filtros['capacitacion_id']
                : null,
            'tipo_capacitacion_id' => isset($filtros['tipo_capacitacion_id']) && (int)$filtros['tipo_capacitacion_id'] > 0
                ? (int)$filtros['tipo_capacitacion_id']
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,string>
     */
    private function etiquetasFiltro(array $filtros): array
    {
        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;
        $periodo = 'Todos';
        if (is_string($desde) && is_string($hasta)) {
            $periodo = $this->fechaExcel($desde) . ' - ' . $this->fechaExcel($hasta);
        } elseif (is_string($desde)) {
            $periodo = 'Desde ' . $this->fechaExcel($desde);
        } elseif (is_string($hasta)) {
            $periodo = 'Hasta ' . $this->fechaExcel($hasta);
        }

        $procesoId = $filtros['proceso_id'] ?? null;
        $proceso = 'Todos';
        if ($procesoId !== null) {
            foreach ($this->alertaRepo->procesosActivos() as $fila) {
                if ((int)$fila['proceso_id'] === (int)$procesoId) {
                    $proceso = (string)$fila['nombre'];
                    break;
                }
            }
        }

        $proyecto = isset($filtros['proyecto']) && is_string($filtros['proyecto']) && $filtros['proyecto'] !== ''
            ? $filtros['proyecto']
            : 'Todos';

        $etiquetas = [
            'Periodo' => $periodo,
            'Proceso' => $proceso,
            'Proyecto' => $proyecto,
        ];

        if (isset($filtros['persona_id']) && (int)$filtros['persona_id'] > 0) {
            try {
                $persona = $this->personal->ver((int)$filtros['persona_id']);
                $etiquetas['Trabajador'] = trim(
                    (string)($persona['numero_documento'] ?? '') . ' ' . (string)($persona['nombre_completo'] ?? '')
                );
            } catch (HttpException $e) {
                $etiquetas['Trabajador'] = (string)$filtros['persona_id'];
            }
        }

        return $etiquetas;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizarFila(string $tipo, array $fila): array
    {
        if ($tipo === 'cumplimiento_trabajador') {
            $programadas = (int)($fila['asignadas'] ?? 0);
            $ejecutadas = (int)($fila['completadas'] ?? 0);

            return [
                'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
                'documento' => $fila['numero_documento'] ?? null,
                'trabajador' => $fila['persona_nombre'] ?? null,
                'cargo' => $fila['nombre_cargo'] ?? null,
                'proceso' => $fila['proceso_nombre'] ?? null,
                'proyecto' => $fila['proyecto'] ?? null,
                'programadas' => $programadas,
                'ejecutadas' => $ejecutadas,
                'asignadas' => $programadas,
                'completadas' => $ejecutadas,
                'pendientes' => (int)($fila['pendientes'] ?? 0),
                'vencidas' => (int)($fila['vencidas'] ?? 0),
                'porcentaje' => $programadas > 0 ? round($ejecutadas / $programadas * 100, 1) : null,
            ];
        }

        if ($this->repo->esAgrupado($tipo)) {
            $programadas = (int)($fila['asignadas'] ?? 0);
            $ejecutadas = (int)($fila['completadas'] ?? 0);

            return [
                'grupo' => (string)($fila['grupo'] ?? ''),
                'programadas' => $programadas,
                'ejecutadas' => $ejecutadas,
                'asignadas' => $programadas,
                'completadas' => $ejecutadas,
                'pendientes' => (int)($fila['pendientes'] ?? 0),
                'vencidas' => (int)($fila['vencidas'] ?? 0),
                'porcentaje' => $programadas > 0 ? round($ejecutadas / $programadas * 100, 1) : null,
            ];
        }

        $codigo = $fila['capacitacion_codigo'] ?? null;
        $nombre = $fila['capacitacion_nombre'] ?? null;
        $capacitacion = $codigo && $nombre ? "{$codigo} — {$nombre}" : ($nombre ?? $codigo);

        if ($tipo === 'horas') {
            return [
                'documento' => $fila['numero_documento'] ?? null,
                'trabajador' => $fila['persona_nombre'] ?? null,
                'capacitacion' => $capacitacion,
                'proceso' => $fila['proceso_nombre'] ?? null,
                'proyecto' => $fila['proyecto'] ?? null,
                'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
                'horas_efectivas' => isset($fila['horas_efectivas']) ? (float)$fila['horas_efectivas'] : 0.0,
                'duracion' => isset($fila['duracion_estimada_horas']) ? (float)$fila['duracion_estimada_horas'] : null,
            ];
        }

        if ($tipo === 'asistencia') {
            $fechaHora = isset($fila['fecha_hora']) ? (string)$fila['fecha_hora'] : '';

            return [
                'documento' => $fila['numero_documento'] ?? null,
                'trabajador' => $fila['persona_nombre'] ?? null,
                'capacitacion' => $capacitacion,
                'sesion_id' => isset($fila['sesion_id']) ? (int)$fila['sesion_id'] : null,
                'fecha' => $fechaHora !== '' ? substr($fechaHora, 0, 10) : null,
                'hora' => $fechaHora !== '' && strlen($fechaHora) >= 16 ? substr($fechaHora, 11, 5) : null,
                'modalidad' => $fila['modalidad_nombre'] ?? null,
                'estado_asistencia' => $fila['estado_asistencia'] ?? null,
                'motivo_ausencia' => $fila['motivo_ausencia'] ?? null,
                'proceso' => $fila['proceso_nombre'] ?? null,
                'proyecto' => $fila['proyecto'] ?? null,
            ];
        }

        if ($tipo === 'evidencias_faltantes') {
            $resultado = strtoupper((string)($fila['cumplimiento_resultado'] ?? ''));

            return [
                'cumplimiento_id' => isset($fila['cumplimiento_id']) ? (int)$fila['cumplimiento_id'] : null,
                'asignacion_id' => isset($fila['asignacion_id']) ? (int)$fila['asignacion_id'] : null,
                'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
                'trabajador' => $fila['persona_nombre'] ?? null,
                'documento' => $fila['numero_documento'] ?? null,
                'capacitacion' => $capacitacion,
                'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
                'estado' => $resultado === CumplimientoService::RESULTADO_APROBADO ? 'Completado' : 'Pendiente',
                'requiere_certificado' => true,
                'soportes_count' => 0,
                'proceso' => $fila['proceso_nombre'] ?? null,
                'proyecto' => $fila['proyecto'] ?? null,
            ];
        }

        $soportes = (int)($fila['soportes_count'] ?? 0);
        $requiereSoporte = !empty($fila['capacitacion_certificado']);

        return [
            'asignacion_id' => isset($fila['asignacion_id']) ? (int)$fila['asignacion_id'] : null,
            'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
            'cargo_id_ext' => isset($fila['cargo_id_ext']) ? (int)$fila['cargo_id_ext'] : null,
            'cumplimiento_id' => isset($fila['cumplimiento_id']) && $fila['cumplimiento_id'] !== null
                ? (int)$fila['cumplimiento_id']
                : null,
            'documento' => $fila['numero_documento'] ?? null,
            'trabajador' => $fila['persona_nombre'] ?? null,
            'cargo' => $fila['nombre_cargo'] ?? null,
            'proceso' => $fila['proceso_nombre'] ?? null,
            'proyecto' => $fila['proyecto'] ?? null,
            'capacitacion' => $capacitacion,
            'tipo' => $fila['tipo_nombre'] ?? null,
            'origen' => $fila['origen'] ?? null,
            'estado' => $fila['estado_calculado'] ?? null,
            'fecha_asignacion' => $fila['fecha_asignacion'] ?? null,
            'fecha_limite_cumplimiento' => $fila['fecha_limite_cumplimiento'] ?? null,
            'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
            'fecha_vencimiento' => $fila['fecha_vencimiento'] ?? null,
            'fecha_ingreso' => $fila['fecha_ingreso'] ?? null,
            'periodicidad' => $fila['periodicidad_nombre'] ?? null,
            'nota_evaluacion' => isset($fila['nota_evaluacion']) && $fila['nota_evaluacion'] !== null
                ? (float)$fila['nota_evaluacion']
                : null,
            'resultado' => $fila['cumplimiento_resultado'] ?? null,
            'es_tarea_critica' => !empty($fila['es_tarea_critica']),
            'soportes_count' => $soportes,
            'tiene_soporte' => $soportes > 0,
            'requiere_soporte' => $requiereSoporte,
        ];
    }

    /**
     * @return list<array{clave:string,etiqueta:string,tipo?:string}>
     */
    public function columnas(string $tipo): array
    {
        if ($tipo === 'cumplimiento_trabajador') {
            return [
                ['clave' => 'documento', 'etiqueta' => 'Cédula'],
                ['clave' => 'trabajador', 'etiqueta' => 'Trabajador'],
                ['clave' => 'cargo', 'etiqueta' => 'Cargo'],
                ['clave' => 'proceso', 'etiqueta' => 'Proceso'],
                ['clave' => 'proyecto', 'etiqueta' => 'Proyecto'],
                ['clave' => 'programadas', 'etiqueta' => 'Programadas', 'tipo' => 'numero'],
                ['clave' => 'ejecutadas', 'etiqueta' => 'Ejecutadas', 'tipo' => 'numero'],
                ['clave' => 'pendientes', 'etiqueta' => 'Pendientes', 'tipo' => 'numero'],
                ['clave' => 'vencidas', 'etiqueta' => 'Vencidas', 'tipo' => 'numero'],
                ['clave' => 'porcentaje', 'etiqueta' => '% cumplimiento', 'tipo' => 'numero'],
            ];
        }

        if ($this->repo->esAgrupado($tipo)) {
            $grupo = match ($tipo) {
                'cumplimiento_cargo' => 'Cargo',
                'cumplimiento_proceso' => 'Proceso',
                default => 'Proyecto',
            };

            return [
                ['clave' => 'grupo', 'etiqueta' => $grupo],
                ['clave' => 'programadas', 'etiqueta' => 'Programadas', 'tipo' => 'numero'],
                ['clave' => 'ejecutadas', 'etiqueta' => 'Ejecutadas', 'tipo' => 'numero'],
                ['clave' => 'pendientes', 'etiqueta' => 'Pendientes', 'tipo' => 'numero'],
                ['clave' => 'vencidas', 'etiqueta' => 'Vencidas', 'tipo' => 'numero'],
                ['clave' => 'porcentaje', 'etiqueta' => '% cumplimiento', 'tipo' => 'numero'],
            ];
        }

        if ($tipo === 'horas') {
            return [
                ['clave' => 'documento', 'etiqueta' => 'Documento'],
                ['clave' => 'trabajador', 'etiqueta' => 'Trabajador'],
                ['clave' => 'capacitacion', 'etiqueta' => 'Capacitación'],
                ['clave' => 'proceso', 'etiqueta' => 'Proceso'],
                ['clave' => 'proyecto', 'etiqueta' => 'Proyecto'],
                ['clave' => 'fecha_realizacion', 'etiqueta' => 'Fecha de realización', 'tipo' => 'fecha'],
                ['clave' => 'horas_efectivas', 'etiqueta' => 'Horas', 'tipo' => 'numero'],
            ];
        }

        if ($tipo === 'asistencia') {
            return [
                ['clave' => 'documento', 'etiqueta' => 'Documento'],
                ['clave' => 'trabajador', 'etiqueta' => 'Trabajador'],
                ['clave' => 'capacitacion', 'etiqueta' => 'Capacitación'],
                ['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'fecha'],
                ['clave' => 'hora', 'etiqueta' => 'Hora'],
                ['clave' => 'modalidad', 'etiqueta' => 'Modalidad'],
                ['clave' => 'estado_asistencia', 'etiqueta' => 'Asistencia'],
                ['clave' => 'motivo_ausencia', 'etiqueta' => 'Motivo de ausencia'],
            ];
        }

        if ($tipo === 'evidencias_faltantes') {
            return [
                ['clave' => 'documento', 'etiqueta' => 'Documento'],
                ['clave' => 'trabajador', 'etiqueta' => 'Trabajador'],
                ['clave' => 'capacitacion', 'etiqueta' => 'Capacitación'],
                ['clave' => 'fecha_realizacion', 'etiqueta' => 'Fecha de realización', 'tipo' => 'fecha'],
                ['clave' => 'estado', 'etiqueta' => 'Estado'],
                ['clave' => 'requiere_certificado', 'etiqueta' => 'Requiere certificado'],
            ];
        }

        if ($tipo === 'proximas') {
            return [
                ['clave' => 'documento', 'etiqueta' => 'Documento'],
                ['clave' => 'trabajador', 'etiqueta' => 'Trabajador'],
                ['clave' => 'cargo', 'etiqueta' => 'Cargo'],
                ['clave' => 'proceso', 'etiqueta' => 'Proceso'],
                ['clave' => 'proyecto', 'etiqueta' => 'Proyecto'],
                ['clave' => 'capacitacion_nombre', 'etiqueta' => 'Capacitación'],
                ['clave' => 'fecha_realizacion', 'etiqueta' => 'Fecha de realización', 'tipo' => 'fecha'],
                ['clave' => 'fecha_vencimiento', 'etiqueta' => 'Fecha de vencimiento', 'tipo' => 'fecha'],
                ['clave' => 'dias_restantes', 'etiqueta' => 'Días restantes', 'tipo' => 'numero'],
            ];
        }

        if ($tipo === 'historial_trabajador') {
            return [
                ['clave' => 'proyecto', 'etiqueta' => 'Proyecto'],
                ['clave' => 'capacitacion', 'etiqueta' => 'Capacitación'],
                ['clave' => 'tipo', 'etiqueta' => 'Tipo'],
                ['clave' => 'origen', 'etiqueta' => 'Origen'],
                ['clave' => 'cargo', 'etiqueta' => 'Cargo'],
                ['clave' => 'proceso', 'etiqueta' => 'Proceso'],
                ['clave' => 'fecha_asignacion', 'etiqueta' => 'Fecha de asignación', 'tipo' => 'fecha'],
                ['clave' => 'fecha_realizacion', 'etiqueta' => 'Fecha de realización', 'tipo' => 'fecha'],
                ['clave' => 'fecha_sesion', 'etiqueta' => 'Fecha de sesión', 'tipo' => 'fecha'],
                ['clave' => 'estado', 'etiqueta' => 'Estado'],
                ['clave' => 'fecha_vencimiento', 'etiqueta' => 'Fecha de vencimiento', 'tipo' => 'fecha'],
                ['clave' => 'resultado', 'etiqueta' => 'Resultado'],
                ['clave' => 'horas_efectivas', 'etiqueta' => 'Horas', 'tipo' => 'numero'],
                ['clave' => 'evaluacion_requerida', 'etiqueta' => 'Evaluación'],
                ['clave' => 'nota_evaluacion', 'etiqueta' => 'Nota', 'tipo' => 'numero'],
                ['clave' => 'nota_minima', 'etiqueta' => 'Nota mínima', 'tipo' => 'numero'],
                ['clave' => 'evaluacion_resultado', 'etiqueta' => 'Resultado evaluación'],
                ['clave' => 'evidencia', 'etiqueta' => 'Evidencia'],
                ['clave' => 'soportes_nombres', 'etiqueta' => 'Soportes'],
            ];
        }

        $cols = [
            ['clave' => 'documento', 'etiqueta' => 'Documento'],
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador'],
            ['clave' => 'cargo', 'etiqueta' => 'Cargo'],
            ['clave' => 'proceso', 'etiqueta' => 'Proceso'],
            ['clave' => 'proyecto', 'etiqueta' => 'Proyecto'],
            ['clave' => 'capacitacion', 'etiqueta' => 'Capacitación'],
            ['clave' => 'tipo', 'etiqueta' => 'Tipo'],
            ['clave' => 'origen', 'etiqueta' => 'Origen'],
            ['clave' => 'estado', 'etiqueta' => 'Estado'],
            ['clave' => 'fecha_asignacion', 'etiqueta' => 'Fecha de asignación', 'tipo' => 'fecha'],
            ['clave' => 'fecha_realizacion', 'etiqueta' => 'Fecha de realización', 'tipo' => 'fecha'],
            ['clave' => 'fecha_vencimiento', 'etiqueta' => 'Fecha de vencimiento', 'tipo' => 'fecha'],
        ];

        if ($tipo === 'inducciones') {
            array_splice($cols, 2, 0, [[
                'clave' => 'fecha_ingreso',
                'etiqueta' => 'Fecha de ingreso',
                'tipo' => 'fecha',
            ]]);
        }

        if ($tipo === 'reinducciones') {
            $cols[] = ['clave' => 'periodicidad', 'etiqueta' => 'Periodicidad'];
        }

        if ($tipo === 'tareas_criticas' || $tipo === 'cumplimiento_general') {
            $cols[] = ['clave' => 'es_tarea_critica', 'etiqueta' => 'Tarea crítica'];
        }

        $cols[] = ['clave' => 'tiene_soporte', 'etiqueta' => 'Soporte'];

        return $cols;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    private function totalesParaTipo(string $tipo, array $filtros): array
    {
        $base = $this->repo->totales($tipo, $filtros);

        if ($tipo !== 'cumplimiento_general') {
            return $base;
        }

        return $this->totalesPanelCumplimientoGeneral($filtros, $base);
    }

    /**
     * Totales de cumplimiento general con la misma fórmula del Panel:
     * programadas = plan anual APROBADO; ejecutadas = cumplimientos en rango.
     *
     * @param array<string,mixed> $filtros
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private function totalesPanelCumplimientoGeneral(array $filtros, array $base): array
    {
        $desde = is_string($filtros['desde'] ?? null) ? $filtros['desde'] : (date('Y') . '-01-01');
        $hasta = is_string($filtros['hasta'] ?? null) ? $filtros['hasta'] : (date('Y') . '-12-31');
        $alcance = $this->alcancePanel($filtros);

        $programadas = $this->programadoEnRango($desde, $hasta, $alcance);
        $ejecutadas = $this->dashboard->ejecutado(
            [
                'anio' => (int)substr($desde, 0, 4),
                'meses' => [],
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'general',
            $alcance
        );

        $pendientes = (int)($base['pendientes'] ?? 0);
        $vencidas = (int)($base['vencidas'] ?? 0);
        $proximas = (int)($base['proximas'] ?? 0);
        $porcentaje = $programadas > 0 ? round($ejecutadas / $programadas * 100, 1) : null;

        $totales = $this->repo->empaquetarTotales(
            $programadas,
            $ejecutadas,
            $pendientes,
            $vencidas,
            $proximas,
            0.0,
            $porcentaje
        );
        $totales['programadas'] = $programadas;
        $totales['ejecutadas'] = $ejecutadas;

        return $totales;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{modo:string,proceso_id:?int,proyecto:?string}
     */
    private function alcancePanel(array $filtros): array
    {
        $procesoId = isset($filtros['proceso_id']) ? (int)$filtros['proceso_id'] : 0;
        if ($procesoId > 0) {
            return [
                'modo' => 'proceso',
                'proceso_id' => $procesoId,
                'proyecto' => isset($filtros['proyecto']) && is_string($filtros['proyecto'])
                    ? $filtros['proyecto']
                    : null,
            ];
        }

        return ['modo' => 'todos', 'proceso_id' => null, 'proyecto' => null];
    }

    /**
     * @param array{modo:string,proceso_id:?int,proyecto:?string} $alcance
     */
    private function programadoEnRango(string $desde, string $hasta, array $alcance): int
    {
        try {
            $inicio = new DateTimeImmutable($desde);
            $fin = new DateTimeImmutable($hasta);
        } catch (\Exception $e) {
            return 0;
        }

        if ($inicio > $fin) {
            return 0;
        }

        $porAnio = [];
        $cursor = $inicio->modify('first day of this month');
        $limite = $fin->modify('first day of this month');
        while ($cursor <= $limite) {
            $anio = (int)$cursor->format('Y');
            $mes = (int)$cursor->format('n');
            $porAnio[$anio][] = $mes;
            $cursor = $cursor->modify('+1 month');
        }

        $total = 0;
        foreach ($porAnio as $anio => $meses) {
            $mesesUnicos = array_values(array_unique($meses));
            $total += $this->dashboard->programado(
                [
                    'anio' => $anio,
                    'meses' => $mesesUnicos,
                    'desde' => $desde,
                    'hasta' => $hasta,
                ],
                'general',
                $alcance
            );
        }

        return $total;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    private function consultarHistorial(array $filtros, int $pagina, int $porPagina): array
    {
        $personaId = (int)($filtros['persona_id'] ?? 0);
        if ($personaId < 1) {
            throw new HttpException('Debe seleccionar un trabajador.', 422);
        }

        $trabajador = $this->fichaTrabajador($this->personal->ver($personaId));
        $total = $this->repo->contar('historial_trabajador', $filtros);
        $limite = min(self::MAX_EXPORT, max(1, $total > 0 ? $total : 1));
        $filas = $total === 0 ? [] : $this->repo->listar('historial_trabajador', $filtros, $limite, 0);
        $items = $this->normalizarItemsHistorial($filas);

        return [
            'items' => $items,
            'total' => $total,
            'page' => 1,
            'per_page' => max(1, $total),
            'totales' => $this->repo->totales('historial_trabajador', $filtros),
            'titulo' => $this->titulo('historial_trabajador'),
            'filtros_etiqueta' => $this->etiquetasFiltro($filtros),
            'trabajador' => $trabajador,
            'historial_cargo' => $this->historialCargo($personaId),
            'historial_proyecto' => $this->historialProyecto($personaId),
            'historial_proceso' => $this->historialProceso($personaId),
            'grupos' => $this->agruparPorProyecto($items),
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{contenido:string,nombre:string}
     */
    private function excelHistorial(array $filtros, ?string $usuario): array
    {
        $resultado = $this->consultarHistorial($filtros, 1, self::MAX_EXPORT);
        if ((int)$resultado['total'] === 0) {
            throw new HttpException(self::MENSAJE_VACIO, 422);
        }

        $titulo = (string)$resultado['titulo'];
        $etiquetas = $resultado['filtros_etiqueta'];
        $columnas = $this->columnas('historial_trabajador');
        $trabajador = $resultado['trabajador'];
        $documento = is_string($trabajador['documento'] ?? null) && $trabajador['documento'] !== ''
            ? preg_replace('/[^A-Za-z0-9_-]/', '', (string)$trabajador['documento'])
            : 'trabajador';

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(substr($titulo, 0, 31));

        $fila = 1;
        $hoja->setCellValue('A' . $fila, 'REPORTE');
        $hoja->setCellValue('B' . $fila, $titulo);
        $fila++;
        $hoja->setCellValue('A' . $fila, 'FECHA DE GENERACIÓN');
        $hoja->setCellValue('B' . $fila, (new DateTimeImmutable('now'))->format('d/m/Y H:i'));
        $fila++;
        if ($usuario !== null && $usuario !== '') {
            $hoja->setCellValue('A' . $fila, 'USUARIO');
            $hoja->setCellValue('B' . $fila, $usuario);
            $fila++;
        }
        $hoja->setCellValue('A' . $fila, 'TRABAJADOR');
        $hoja->setCellValue('B' . $fila, (string)($trabajador['nombre'] ?? ''));
        $fila++;
        $hoja->setCellValue('A' . $fila, 'DOCUMENTO');
        $hoja->setCellValue('B' . $fila, (string)($trabajador['documento'] ?? ''));
        $fila++;
        $hoja->setCellValue('A' . $fila, 'CARGO ACTUAL');
        $hoja->setCellValue('B' . $fila, (string)($trabajador['cargo'] ?? '—'));
        $fila++;
        $hoja->setCellValue('A' . $fila, 'PROYECTO ACTUAL');
        $hoja->setCellValue('B' . $fila, (string)($trabajador['proyecto'] ?? '—'));
        $fila++;
        foreach ($etiquetas as $clave => $valor) {
            $hoja->setCellValue('A' . $fila, strtoupper((string)$clave));
            $hoja->setCellValue('B' . $fila, $valor);
            $fila++;
        }
        $fila++;

        $hoja->setCellValue('A' . $fila, 'HISTORIAL DE CARGO');
        $hoja->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach ($resultado['historial_cargo'] as $periodo) {
            $hoja->setCellValue('A' . $fila, $this->rangoPeriodo($periodo));
            $hoja->setCellValue('B' . $fila, (string)($periodo['cargo'] ?? '—'));
            $fila++;
        }
        $fila++;
        $hoja->setCellValue('A' . $fila, 'HISTORIAL DE PROYECTO');
        $hoja->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach ($resultado['historial_proyecto'] as $periodo) {
            $hoja->setCellValue('A' . $fila, $this->rangoPeriodo($periodo));
            $hoja->setCellValue('B' . $fila, (string)($periodo['proyecto'] ?? '—'));
            $fila++;
        }
        $fila++;
        $hoja->setCellValue('A' . $fila, 'HISTORIAL DE PROCESO');
        $hoja->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach ($resultado['historial_proceso'] as $periodo) {
            $hoja->setCellValue('A' . $fila, $this->rangoPeriodo($periodo));
            $hoja->setCellValue('B' . $fila, (string)($periodo['proceso'] ?? '—'));
            $fila++;
        }
        $fila++;

        $colIndex = 1;
        foreach ($columnas as $col) {
            $hoja->setCellValueByColumnAndRow($colIndex, $fila, $col['etiqueta']);
            $colIndex++;
        }
        $ultimaCol = Coordinate::stringFromColumnIndex(count($columnas));
        $hoja->getStyle('A' . $fila . ':' . $ultimaCol . $fila)->getFont()->setBold(true);

        foreach ($resultado['items'] as $item) {
            $fila++;
            $colIndex = 1;
            foreach ($columnas as $col) {
                $clave = $col['clave'];
                $valor = $item[$clave] ?? '';
                if (is_bool($valor)) {
                    $valor = $valor ? 'Sí' : 'No';
                }
                if (($col['tipo'] ?? '') === 'fecha') {
                    $valor = $this->fechaExcel(is_string($valor) ? $valor : null);
                }
                if (($col['tipo'] ?? '') === 'numero' && ($valor === null || $valor === '')) {
                    $valor = null;
                }
                $hoja->setCellValueByColumnAndRow($colIndex, $fila, $valor);
                if (($col['tipo'] ?? '') === 'numero') {
                    $coord = Coordinate::stringFromColumnIndex($colIndex) . $fila;
                    $hoja->getStyle($coord)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                }
                $colIndex++;
            }
        }

        $totales = $resultado['totales'];
        $fila += 2;
        $hoja->setCellValue('A' . $fila, 'TOTAL REGISTROS');
        $hoja->setCellValue('B' . $fila, $totales['asignadas']);
        $fila++;
        $hoja->setCellValue('A' . $fila, 'PROGRAMADAS');
        $hoja->setCellValue('B' . $fila, $totales['programadas'] ?? $totales['asignadas']);
        $fila++;
        $hoja->setCellValue('A' . $fila, 'EJECUTADAS');
        $hoja->setCellValue('B' . $fila, $totales['ejecutadas'] ?? $totales['completadas']);
        $fila++;
        $hoja->setCellValue('A' . $fila, 'PENDIENTES');
        $hoja->setCellValue('B' . $fila, $totales['pendientes']);
        $fila++;
        $hoja->setCellValue('A' . $fila, 'VENCIDAS');
        $hoja->setCellValue('B' . $fila, $totales['vencidas']);
        $fila++;
        $hoja->setCellValue('A' . $fila, '% CUMPLIMIENTO');
        $hoja->setCellValue('B' . $fila, $totales['porcentaje'] === null ? '—' : $totales['porcentaje']);

        $hoja->getColumnDimension('A')->setWidth(28);
        $hoja->getColumnDimension('B')->setWidth(36);
        for ($i = 3; $i <= max(3, count($columnas)); $i++) {
            $hoja->getColumnDimensionByColumn($i)->setWidth(18);
        }

        $escritor = new Xlsx($libro);
        ob_start();
        $escritor->save('php://output');
        $contenido = (string)ob_get_clean();
        $libro->disconnectWorksheets();

        return [
            'contenido' => $contenido,
            'nombre' => 'historial_trabajador_' . $documento . '_' . (new DateTimeImmutable('today'))->format('Y-m-d') . '.xlsx',
        ];
    }

    /**
     * @param list<array<string,mixed>> $filas
     * @return list<array<string,mixed>>
     */
    private function normalizarItemsHistorial(array $filas): array
    {
        $cumplimientoIds = [];
        foreach ($filas as $fila) {
            $cid = isset($fila['cumplimiento_id']) ? (int)$fila['cumplimiento_id'] : 0;
            if ($cid > 0) {
                $cumplimientoIds[] = $cid;
            }
        }
        $porCumplimiento = [];
        foreach ($this->soportes->listarPorCumplimientos($cumplimientoIds) as $soporte) {
            $cid = (int)$soporte['cumplimiento_id'];
            $porCumplimiento[$cid][] = [
                'soporte_id' => (int)$soporte['soporte_id'],
                'nombre_archivo' => (string)$soporte['nombre_archivo'],
                'tipo_soporte' => (string)$soporte['tipo_soporte'],
            ];
        }

        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizarFilaHistorial($fila, $porCumplimiento);
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $fila
     * @param array<int,list<array<string,mixed>>> $porCumplimiento
     * @return array<string,mixed>
     */
    private function normalizarFilaHistorial(array $fila, array $porCumplimiento): array
    {
        $codigo = $fila['capacitacion_codigo'] ?? null;
        $nombre = $fila['capacitacion_nombre'] ?? null;
        $capacitacion = $codigo && $nombre ? "{$codigo} — {$nombre}" : ($nombre ?? $codigo);
        $requiereEval = (int)($fila['capacitacion_evaluacion'] ?? 0) === 1;
        $requiereCert = (int)($fila['capacitacion_certificado'] ?? 0) === 1;
        $minima = isset($fila['capacitacion_nota_minima']) ? round((float)$fila['capacitacion_nota_minima'], 2) : 0.0;
        $nota = isset($fila['nota_evaluacion']) && $fila['nota_evaluacion'] !== null
            ? (float)$fila['nota_evaluacion']
            : null;
        $aprobada = $this->evaluacionAprobada($nota, $requiereEval, $minima);
        $cid = isset($fila['cumplimiento_id']) ? (int)$fila['cumplimiento_id'] : 0;
        $soportes = $cid > 0 ? ($porCumplimiento[$cid] ?? []) : [];
        $nombres = [];
        foreach ($soportes as $s) {
            $nombres[] = (string)$s['nombre_archivo'];
        }
        $evidencia = 'No aplica';
        if ($requiereCert) {
            $evidencia = $soportes === [] ? 'Faltante' : 'Disponible';
        } elseif ($soportes !== []) {
            $evidencia = 'Disponible';
        }
        $fechaSesion = isset($fila['fecha_sesion']) && is_string($fila['fecha_sesion']) && $fila['fecha_sesion'] !== ''
            ? substr($fila['fecha_sesion'], 0, 10)
            : null;

        return [
            'asignacion_id' => isset($fila['asignacion_id']) ? (int)$fila['asignacion_id'] : null,
            'cumplimiento_id' => $cid > 0 ? $cid : null,
            'capacitacion_id' => isset($fila['capacitacion_id']) ? (int)$fila['capacitacion_id'] : null,
            'documento' => $fila['numero_documento'] ?? null,
            'trabajador' => $fila['persona_nombre'] ?? null,
            'capacitacion' => $capacitacion,
            'tipo' => $fila['tipo_nombre'] ?? null,
            'origen' => $fila['origen'] ?? null,
            'cargo' => $fila['nombre_cargo'] ?? null,
            'proceso' => $fila['proceso_nombre'] ?? null,
            'proyecto' => $fila['proyecto'] ?? null,
            'fecha_asignacion' => $fila['fecha_asignacion'] ?? null,
            'fecha_limite_cumplimiento' => $fila['fecha_limite_cumplimiento'] ?? null,
            'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
            'fecha_sesion' => $fechaSesion,
            'fecha_vencimiento' => $fila['fecha_vencimiento'] ?? null,
            'estado' => $fila['estado_calculado'] ?? null,
            'resultado' => $fila['cumplimiento_resultado'] ?? null,
            'horas_efectivas' => isset($fila['horas_efectivas']) && $fila['horas_efectivas'] !== null
                ? (float)$fila['horas_efectivas']
                : null,
            'evaluacion_requerida' => $requiereEval ? 'Requerida' : 'No',
            'nota_evaluacion' => $nota,
            'nota_minima' => $requiereEval ? $minima : null,
            'evaluacion_resultado' => $aprobada === null ? null : ($aprobada ? 'Aprobado' : 'No aprobado'),
            'requiere_certificado' => $requiereCert,
            'evidencia' => $evidencia,
            'soportes' => $soportes,
            'soportes_nombres' => implode(', ', $nombres),
        ];
    }

    /**
     * @param array<string,mixed> $persona
     * @return array<string,mixed>
     */
    private function fichaTrabajador(array $persona): array
    {
        return [
            'persona_id' => (int)$persona['persona_id'],
            'documento' => $persona['numero_documento'] ?? null,
            'nombre' => $persona['nombre_completo'] ?? null,
            'correo' => $persona['correo_corporativo'] ?? null,
            'cargo' => $persona['cargo'] ?? null,
            'proyecto' => $persona['proyecto'] ?? null,
            'fecha_ingreso' => $persona['contrato_fecha_inicio'] ?? null,
            'estado' => $persona['estado'] ?? null,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function historialCargo(int $personaId): array
    {
        $laboral = $this->historial->listarPorPersona($personaId);
        if ($laboral !== []) {
            $items = [];
            foreach ($laboral as $fila) {
                $items[] = [
                    'cargo' => $fila['nombre_cargo'] ?? null,
                    'vigente_desde' => $fila['vigente_desde'] ?? null,
                    'vigente_hasta' => $fila['vigente_hasta'] ?? null,
                    'fuente' => 'laboral',
                    'origen' => $fila['origen'] ?? null,
                ];
            }

            return $items;
        }

        $items = [];
        foreach ($this->historial->cargosDesdeAsignaciones($personaId) as $fila) {
            $items[] = [
                'cargo' => $fila['nombre_cargo'] ?? null,
                'vigente_desde' => $fila['primera_asignacion'] ?? null,
                'vigente_hasta' => $fila['ultima_asignacion'] ?? null,
                'fuente' => 'asignaciones',
                'origen' => null,
            ];
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function historialProyecto(int $personaId): array
    {
        $laboral = $this->historial->listarPorPersona($personaId);
        if ($laboral !== []) {
            $items = [];
            foreach ($laboral as $fila) {
                $items[] = [
                    'proyecto' => $fila['proyecto'] ?? null,
                    'vigente_desde' => $fila['vigente_desde'] ?? null,
                    'vigente_hasta' => $fila['vigente_hasta'] ?? null,
                    'fuente' => 'laboral',
                    'origen' => $fila['origen'] ?? null,
                ];
            }

            return $items;
        }

        $items = [];
        foreach ($this->historial->proyectosDesdeAsignaciones($personaId) as $fila) {
            $items[] = [
                'proyecto' => $fila['proyecto'] ?? null,
                'vigente_desde' => $fila['primera_asignacion'] ?? null,
                'vigente_hasta' => $fila['ultima_asignacion'] ?? null,
                'fuente' => 'asignaciones',
                'origen' => null,
            ];
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function historialProceso(int $personaId): array
    {
        $items = [];
        foreach ($this->historial->procesosDesdeAsignaciones($personaId) as $fila) {
            $items[] = [
                'proceso' => $fila['proceso_nombre'] ?? null,
                'vigente_desde' => $fila['primera_asignacion'] ?? null,
                'vigente_hasta' => $fila['ultima_asignacion'] ?? null,
                'fuente' => 'asignaciones',
                'origen' => null,
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array{proyecto:string,asignadas:int,items:list<array<string,mixed>>}>
     */
    private function agruparPorProyecto(array $items): array
    {
        $grupos = [];
        foreach ($items as $item) {
            $clave = isset($item['proyecto']) && is_string($item['proyecto']) && $item['proyecto'] !== ''
                ? $item['proyecto']
                : '(Sin proyecto)';
            if (!isset($grupos[$clave])) {
                $grupos[$clave] = ['proyecto' => $clave, 'asignadas' => 0, 'items' => []];
            }
            $grupos[$clave]['items'][] = $item;
            $grupos[$clave]['asignadas']++;
        }

        return array_values($grupos);
    }

    /** @param array<string,mixed> $periodo */
    private function rangoPeriodo(array $periodo): string
    {
        $desde = $this->fechaExcel(isset($periodo['vigente_desde']) && is_string($periodo['vigente_desde'])
            ? $periodo['vigente_desde']
            : null);
        $hasta = isset($periodo['vigente_hasta']) && is_string($periodo['vigente_hasta']) && $periodo['vigente_hasta'] !== ''
            ? $this->fechaExcel($periodo['vigente_hasta'])
            : 'Actual';
        $desde = $desde !== '' ? $desde : '—';

        return $desde . ' – ' . $hasta;
    }

    private function evaluacionAprobada(?float $nota, bool $requiere, float $minima): ?bool
    {
        if (!$requiere || $nota === null) {
            return null;
        }

        return $nota >= $minima;
    }

    private function fechaONulo(mixed $valor): ?string
    {
        if (!is_string($valor) && !is_numeric($valor)) {
            return null;
        }
        $txt = trim((string)$valor);
        if ($txt === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($txt, 0, 10));
        if (!$dt instanceof DateTimeImmutable) {
            throw new HttpException('La fecha del periodo no es válida.', 422);
        }

        return $dt->format('Y-m-d');
    }

    private function fechaExcel(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        $parte = substr($valor, 0, 10);
        $p = explode('-', $parte);
        if (count($p) !== 3) {
            return $valor;
        }

        return $p[2] . '/' . $p[1] . '/' . $p[0];
    }
}
