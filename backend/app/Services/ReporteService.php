<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\ReporteRepository;
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
    ];

    private ReporteRepository $repo;
    private AlertaService $alertas;
    private AlertaRepository $alertaRepo;

    public function __construct()
    {
        $this->repo = new ReporteRepository();
        $this->alertas = new AlertaService();
        $this->alertaRepo = new AlertaRepository();
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
            'totales' => $this->repo->totales($tipo, $limpios),
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
            $totales = $this->repo->totales($tipo, $limpios);
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
        $hoja->setCellValue('B' . $fila, $totales['asignadas']);
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
            $hoja->setCellValue('A' . $fila, 'COMPLETADAS');
            $hoja->setCellValue('B' . $fila, $totales['completadas']);
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
        return $this->alertas->opciones();
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

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'proceso_id' => isset($filtros['proceso_id']) && (int)$filtros['proceso_id'] > 0
                ? (int)$filtros['proceso_id']
                : null,
            'proyecto' => isset($filtros['proyecto']) && trim((string)$filtros['proyecto']) !== ''
                ? trim((string)$filtros['proyecto'])
                : null,
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

        return [
            'Periodo' => $periodo,
            'Proceso' => $proceso,
            'Proyecto' => $proyecto,
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizarFila(string $tipo, array $fila): array
    {
        if ($this->repo->esAgrupado($tipo)) {
            $asignadas = (int)($fila['asignadas'] ?? 0);
            $completadas = (int)($fila['completadas'] ?? 0);

            return [
                'grupo' => (string)($fila['grupo'] ?? ''),
                'asignadas' => $asignadas,
                'completadas' => $completadas,
                'pendientes' => (int)($fila['pendientes'] ?? 0),
                'vencidas' => (int)($fila['vencidas'] ?? 0),
                'porcentaje' => $asignadas > 0 ? round($completadas / $asignadas * 100, 1) : null,
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

        return [
            'asignacion_id' => isset($fila['asignacion_id']) ? (int)$fila['asignacion_id'] : null,
            'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
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
        ];
    }

    /**
     * @return list<array{clave:string,etiqueta:string,tipo?:string}>
     */
    public function columnas(string $tipo): array
    {
        if ($this->repo->esAgrupado($tipo)) {
            $grupo = match ($tipo) {
                'cumplimiento_cargo' => 'Cargo',
                'cumplimiento_proceso' => 'Proceso',
                default => 'Proyecto',
            };

            return [
                ['clave' => 'grupo', 'etiqueta' => $grupo],
                ['clave' => 'asignadas', 'etiqueta' => 'Asignadas', 'tipo' => 'numero'],
                ['clave' => 'completadas', 'etiqueta' => 'Completadas', 'tipo' => 'numero'],
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

        return $cols;
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
