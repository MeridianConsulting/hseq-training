<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ReporteRepository
{
    public const COMPLETADAS = ['COMPLETADA', 'PROXIMA_A_VENCER'];
    public const PENDIENTES = ['PENDIENTE', 'PENDIENTE_PROXIMA_A_VENCER', 'PENDIENTE_VENCIDA'];
    public const VENCIDAS_VIGENCIA = ['VENCIDA'];
    public const VENCIDAS_REPORTE = ['VENCIDA', 'PENDIENTE_VENCIDA'];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(string $tipo, array $filtros, int $limite, int $offset): array
    {
        $limite = max(1, $limite);
        $offset = max(0, $offset);

        if ($this->esAgrupado($tipo)) {
            return $this->listarAgrupado($tipo, $filtros, $limite, $offset);
        }
        if ($tipo === 'horas') {
            return $this->listarHoras($filtros, $limite, $offset);
        }
        if ($tipo === 'asistencia') {
            return $this->listarAsistencia($filtros, $limite, $offset);
        }
        if ($tipo === 'evidencias_faltantes') {
            return $this->listarEvidencias($filtros, $limite, $offset);
        }

        [$where, $params] = $this->whereAsignaciones($tipo, $filtros);
        $sql = $this->selectAsignaciones() . " {$where}
            ORDER BY a.fecha_asignacion ASC, a.asignacion_id ASC
            LIMIT {$limite} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @param array<string,mixed> $filtros
     */
    public function contar(string $tipo, array $filtros): int
    {
        if ($this->esAgrupado($tipo)) {
            $fila = $this->db->fetch(
                'SELECT COUNT(*) AS total FROM (' . $this->sqlAgrupado($tipo, $filtros)[0] . ') g',
                $this->sqlAgrupado($tipo, $filtros)[1]
            );

            return (int)($fila['total'] ?? 0);
        }
        if ($tipo === 'horas') {
            [$where, $params] = $this->whereHoras($filtros);
            $fila = $this->db->fetch(
                "SELECT COUNT(*) AS total FROM cumplimientos_capacitacion c
                 INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                 {$where}",
                $params
            );

            return (int)($fila['total'] ?? 0);
        }
        if ($tipo === 'asistencia') {
            [$where, $params] = $this->whereAsistencia($filtros);
            $fila = $this->db->fetch(
                "SELECT COUNT(*) AS total
                 FROM sesion_participantes sp
                 INNER JOIN sesiones_capacitacion s ON s.sesion_id = sp.sesion_id
                 INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = sp.asignacion_id
                 {$where}",
                $params
            );

            return (int)($fila['total'] ?? 0);
        }
        if ($tipo === 'evidencias_faltantes') {
            [$where, $params] = $this->whereEvidencias($filtros);
            $fila = $this->db->fetch(
                "SELECT COUNT(*) AS total FROM cumplimientos_capacitacion c
                 INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                 INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                 {$where}",
                $params
            );

            return (int)($fila['total'] ?? 0);
        }

        [$where, $params] = $this->whereAsignaciones($tipo, $filtros);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM asignaciones_capacitacion a
             INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             LEFT JOIN tipos_capacitacion tip ON tip.tipo_capacitacion_id = cap.tipo_capacitacion_id
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function totales(string $tipo, array $filtros): array
    {
        if ($tipo === 'horas') {
            [$where, $params] = $this->whereHoras($filtros);
            $fila = $this->db->fetch(
                "SELECT COUNT(*) AS asignadas,
                        COALESCE(SUM(c.horas_efectivas), 0) AS horas
                 FROM cumplimientos_capacitacion c
                 INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                 {$where}",
                $params
            );

            return $this->empaquetarTotales(
                (int)($fila['asignadas'] ?? 0),
                0,
                0,
                0,
                0,
                (float)($fila['horas'] ?? 0)
            );
        }

        if ($tipo === 'asistencia') {
            [$where, $params] = $this->whereAsistencia($filtros);
            $fila = $this->db->fetch(
                "SELECT COUNT(*) AS asignadas,
                        SUM(CASE WHEN sp.estado_asistencia COLLATE utf8mb4_unicode_ci = 'ASISTIO' THEN 1 ELSE 0 END) AS asistieron,
                        SUM(CASE WHEN sp.estado_asistencia COLLATE utf8mb4_unicode_ci = 'TARDE' THEN 1 ELSE 0 END) AS tarde,
                        SUM(CASE WHEN sp.estado_asistencia COLLATE utf8mb4_unicode_ci = 'AUSENTE' THEN 1 ELSE 0 END) AS ausentes,
                        SUM(CASE WHEN sp.estado_asistencia COLLATE utf8mb4_unicode_ci = 'CONVOCADO' THEN 1 ELSE 0 END) AS convocados
                 FROM sesion_participantes sp
                 INNER JOIN sesiones_capacitacion s ON s.sesion_id = sp.sesion_id
                 INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = sp.asignacion_id
                 {$where}",
                $params
            );

            $totales = $this->empaquetarTotales((int)($fila['asignadas'] ?? 0), 0, 0, 0, 0, 0.0);
            $totales['asistieron'] = (int)($fila['asistieron'] ?? 0);
            $totales['tarde'] = (int)($fila['tarde'] ?? 0);
            $totales['ausentes'] = (int)($fila['ausentes'] ?? 0);
            $totales['convocados'] = (int)($fila['convocados'] ?? 0);

            return $totales;
        }

        if ($tipo === 'evidencias_faltantes') {
            $n = $this->contar($tipo, $filtros);

            return $this->empaquetarTotales($n, 0, 0, 0, 0, 0.0);
        }

        if ($this->esAgrupado($tipo)) {
            [$sql, $params] = $this->sqlAgrupado($tipo, $filtros);
            $fila = $this->db->fetch(
                "SELECT COALESCE(SUM(g.asignadas), 0) AS asignadas,
                        COALESCE(SUM(g.completadas), 0) AS completadas,
                        COALESCE(SUM(g.pendientes), 0) AS pendientes,
                        COALESCE(SUM(g.vencidas), 0) AS vencidas
                 FROM ({$sql}) g",
                $params
            );

            return $this->empaquetarTotales(
                (int)($fila['asignadas'] ?? 0),
                (int)($fila['completadas'] ?? 0),
                (int)($fila['pendientes'] ?? 0),
                (int)($fila['vencidas'] ?? 0),
                0,
                0.0
            );
        }

        [$where, $params] = $this->whereAsignaciones($tipo, $filtros);
        $inComp = $this->listaIn(self::COMPLETADAS);
        $inPend = $this->listaIn(self::PENDIENTES);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS asignadas,
                    SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci IN ({$inComp}) THEN 1 ELSE 0 END) AS completadas,
                    SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci IN ({$inPend}) THEN 1 ELSE 0 END) AS pendientes,
                    SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci = 'VENCIDA' THEN 1 ELSE 0 END) AS vencidas,
                    SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci IN ('PROXIMA_A_VENCER','PENDIENTE_PROXIMA_A_VENCER') THEN 1 ELSE 0 END) AS proximas
             FROM asignaciones_capacitacion a
             INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             LEFT JOIN tipos_capacitacion tip ON tip.tipo_capacitacion_id = cap.tipo_capacitacion_id
             {$where}",
            $params
        );

        return $this->empaquetarTotales(
            (int)($fila['asignadas'] ?? 0),
            (int)($fila['completadas'] ?? 0),
            (int)($fila['pendientes'] ?? 0),
            (int)($fila['vencidas'] ?? 0),
            (int)($fila['proximas'] ?? 0),
            0.0
        );
    }

    public function esAgrupado(string $tipo): bool
    {
        return in_array($tipo, ['cumplimiento_cargo', 'cumplimiento_proceso', 'cumplimiento_proyecto'], true);
    }

    /**
     * @return array{asignadas:int,completadas:int,pendientes:int,vencidas:int,proximas:int,porcentaje:?float,horas:float}
     */
    public function empaquetarTotales(
        int $asignadas,
        int $completadas,
        int $pendientes,
        int $vencidas,
        int $proximas,
        float $horas
    ): array {
        return [
            'asignadas' => $asignadas,
            'completadas' => $completadas,
            'pendientes' => $pendientes,
            'vencidas' => $vencidas,
            'proximas' => $proximas,
            'porcentaje' => $asignadas > 0 ? round($completadas / $asignadas * 100, 1) : null,
            'horas' => round($horas, 2),
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    private function listarAgrupado(string $tipo, array $filtros, int $limite, int $offset): array
    {
        [$sql, $params] = $this->sqlAgrupado($tipo, $filtros);

        return $this->db->fetchAll(
            "{$sql} LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function sqlAgrupado(string $tipo, array $filtros): array
    {
        [$where, $params] = $this->whereAsignaciones('cumplimiento_general', $filtros);
        $inComp = $this->listaIn(self::COMPLETADAS);
        $inPend = $this->listaIn(self::PENDIENTES);

        if ($tipo === 'cumplimiento_cargo') {
            $grupo = 'COALESCE(car.nombre_cargo, \'(Sin cargo)\')';
            $groupBy = 'a.cargo_id_ext, car.nombre_cargo';
            $joinCargo = $this->joinCargo();
        } elseif ($tipo === 'cumplimiento_proceso') {
            $grupo = 'COALESCE(proc.nombre, \'(Sin proceso)\')';
            $groupBy = 'a.proceso_id, proc.nombre';
            $joinCargo = 'LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id';
        } else {
            $grupo = 'COALESCE(NULLIF(TRIM(a.proyecto), \'\'), \'(Sin proyecto)\')';
            $groupBy = 'a.proyecto';
            $joinCargo = '';
        }

        $sql = "SELECT {$grupo} AS grupo,
                       COUNT(*) AS asignadas,
                       SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci IN ({$inComp}) THEN 1 ELSE 0 END) AS completadas,
                       SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci IN ({$inPend}) THEN 1 ELSE 0 END) AS pendientes,
                       SUM(CASE WHEN e.estado_calculado COLLATE utf8mb4_unicode_ci = 'VENCIDA' THEN 1 ELSE 0 END) AS vencidas
                FROM asignaciones_capacitacion a
                INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN tipos_capacitacion tip ON tip.tipo_capacitacion_id = cap.tipo_capacitacion_id
                {$joinCargo}
                {$where}
                GROUP BY {$groupBy}
                ORDER BY grupo ASC";

        return [$sql, $params];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    private function listarHoras(array $filtros, int $limite, int $offset): array
    {
        [$where, $params] = $this->whereHoras($filtros);

        return $this->db->fetchAll(
            $this->selectHoras() . " {$where}
             ORDER BY c.fecha_realizacion ASC, c.cumplimiento_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    private function listarAsistencia(array $filtros, int $limite, int $offset): array
    {
        [$where, $params] = $this->whereAsistencia($filtros);

        return $this->db->fetchAll(
            $this->selectAsistencia() . " {$where}
             ORDER BY s.fecha_hora ASC, sp.sesion_participante_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    private function listarEvidencias(array $filtros, int $limite, int $offset): array
    {
        [$where, $params] = $this->whereEvidencias($filtros);

        return $this->db->fetchAll(
            $this->selectEvidencias() . " {$where}
             ORDER BY c.fecha_realizacion ASC, c.cumplimiento_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    private function selectAsignaciones(): string
    {
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');
        $contratos = Database::personalTable('contratos');

        return "SELECT a.asignacion_id,
                       a.persona_id_ext,
                       a.origen,
                       a.fecha_asignacion,
                       a.fecha_limite_cumplimiento,
                       a.proyecto,
                       a.proceso_id,
                       a.cargo_id_ext,
                       e.estado_calculado,
                       e.cumplimiento_id,
                       e.fecha_realizacion,
                       e.fecha_vencimiento,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       cap.es_tarea_critica,
                       tip.nombre AS tipo_nombre,
                       proc.nombre AS proceso_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre,
                       car.nombre_cargo,
                       ct.fecha_inicio AS fecha_ingreso,
                       per_cap.nombre AS periodicidad_nombre,
                       c.horas_efectivas,
                       c.nota_evaluacion,
                       c.resultado AS cumplimiento_resultado
                FROM asignaciones_capacitacion a
                INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN tipos_capacitacion tip ON tip.tipo_capacitacion_id = cap.tipo_capacitacion_id
                LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
                LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
                LEFT JOIN periodicidades per_cap ON per_cap.periodicidad_id = cap.periodicidad_default_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
                LEFT JOIN {$cargos} car ON car.cargo_id = a.cargo_id_ext
                LEFT JOIN {$contratos} ct ON ct.contrato_id = a.contrato_id_ext";
    }

    private function selectHoras(): string
    {
        $personas = Database::personalTable('personas');

        return "SELECT c.cumplimiento_id,
                       c.asignacion_id,
                       c.fecha_realizacion,
                       c.horas_efectivas,
                       c.resultado AS cumplimiento_resultado,
                       a.persona_id_ext,
                       a.proyecto,
                       a.proceso_id,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       cap.duracion_estimada_horas,
                       proc.nombre AS proceso_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre
                FROM cumplimientos_capacitacion c
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext";
    }

    private function selectAsistencia(): string
    {
        $personas = Database::personalTable('personas');

        return "SELECT sp.sesion_participante_id,
                       sp.sesion_id,
                       sp.asignacion_id,
                       sp.estado_asistencia,
                       sp.motivo_ausencia,
                       s.fecha_hora,
                       md.nombre AS modalidad_nombre,
                       a.persona_id_ext,
                       a.proyecto,
                       a.proceso_id,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       proc.nombre AS proceso_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre
                FROM sesion_participantes sp
                INNER JOIN sesiones_capacitacion s ON s.sesion_id = sp.sesion_id
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = sp.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN modalidades md ON md.modalidad_id = s.modalidad_id
                LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext";
    }

    private function selectEvidencias(): string
    {
        $personas = Database::personalTable('personas');

        return "SELECT c.cumplimiento_id,
                       c.asignacion_id,
                       c.fecha_realizacion,
                       c.resultado AS cumplimiento_resultado,
                       a.persona_id_ext,
                       a.proyecto,
                       a.proceso_id,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       cap.certificado,
                       proc.nombre AS proceso_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre,
                       0 AS soportes_count
                FROM cumplimientos_capacitacion c
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext";
    }

    private function joinCargo(): string
    {
        $cargos = Database::personalTable('cargos');

        return "LEFT JOIN {$cargos} car ON car.cargo_id = a.cargo_id_ext";
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function whereAsignaciones(string $tipo, array $filtros): array
    {
        $condiciones = [];
        $params = [];
        $columnaFecha = $tipo === 'vencidas'
            ? 'COALESCE(e.fecha_vencimiento, e.fecha_limite_cumplimiento)'
            : 'a.fecha_asignacion';
        $this->aplicarComunes($condiciones, $params, $filtros, $columnaFecha, true);

        if ($tipo === 'vencidas') {
            $condiciones[] = 'e.estado_calculado COLLATE utf8mb4_unicode_ci IN (' . $this->listaIn(self::VENCIDAS_REPORTE) . ')';
        } elseif ($tipo === 'pendientes') {
            $condiciones[] = 'e.estado_calculado COLLATE utf8mb4_unicode_ci IN (' . $this->listaIn(self::PENDIENTES) . ')';
        } elseif ($tipo === 'tareas_criticas') {
            $condiciones[] = 'cap.es_tarea_critica = 1';
        } elseif ($tipo === 'inducciones') {
            $condiciones[] = "(a.origen COLLATE utf8mb4_unicode_ci = 'INDUCCION' OR UPPER(TRIM(tip.nombre)) COLLATE utf8mb4_unicode_ci = 'INDUCCION')";
        } elseif ($tipo === 'reinducciones') {
            $condiciones[] = "(a.origen COLLATE utf8mb4_unicode_ci = 'REINDUCCION' OR UPPER(TRIM(tip.nombre)) COLLATE utf8mb4_unicode_ci = 'REINDUCCION')";
        }

        $estado = isset($filtros['estado']) ? trim((string)$filtros['estado']) : '';
        if ($estado !== '' && in_array($tipo, ['cumplimiento_general', 'cumplimiento_trabajador', 'tareas_criticas', 'inducciones', 'reinducciones'], true)) {
            $condiciones[] = 'e.estado_calculado COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $estado;
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $params];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function whereHoras(array $filtros): array
    {
        $condiciones = [];
        $params = [];
        $this->aplicarComunes($condiciones, $params, $filtros, 'c.fecha_realizacion', false);

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $params];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function whereAsistencia(array $filtros): array
    {
        $condiciones = [];
        $params = [];
        $this->aplicarComunes($condiciones, $params, $filtros, 'DATE(s.fecha_hora)', false);

        $asistencia = isset($filtros['asistencia']) ? trim((string)$filtros['asistencia']) : '';
        if ($asistencia !== '' && in_array($asistencia, ['CONVOCADO', 'ASISTIO', 'TARDE', 'AUSENTE'], true)) {
            $condiciones[] = 'sp.estado_asistencia COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $asistencia;
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $params];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function whereEvidencias(array $filtros): array
    {
        $condiciones = [
            'cap.certificado = 1',
            'NOT EXISTS (SELECT 1 FROM soportes_cumplimiento so WHERE so.cumplimiento_id = c.cumplimiento_id)',
        ];
        $params = [];
        $this->aplicarComunes($condiciones, $params, $filtros, 'c.fecha_realizacion', false);

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $params];
    }

    /**
     * @param list<string> $condiciones
     * @param list<mixed> $params
     * @param array<string,mixed> $filtros
     */
    private function aplicarComunes(
        array &$condiciones,
        array &$params,
        array $filtros,
        string $columnaFecha,
        bool $conBuscar
    ): void {
        $desde = isset($filtros['desde']) ? trim((string)$filtros['desde']) : '';
        $hasta = isset($filtros['hasta']) ? trim((string)$filtros['hasta']) : '';
        if ($desde !== '') {
            $condiciones[] = "{$columnaFecha} >= ?";
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $condiciones[] = "{$columnaFecha} <= ?";
            $params[] = $hasta;
        }

        $procesoId = isset($filtros['proceso_id']) ? (int)$filtros['proceso_id'] : 0;
        if ($procesoId > 0) {
            $condiciones[] = 'a.proceso_id = ?';
            $params[] = $procesoId;
        }

        $proyecto = isset($filtros['proyecto']) ? trim((string)$filtros['proyecto']) : '';
        if ($proyecto !== '') {
            $condiciones[] = 'a.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $cargoId = isset($filtros['cargo_id_ext']) ? (int)$filtros['cargo_id_ext'] : 0;
        if ($cargoId > 0) {
            $condiciones[] = 'a.cargo_id_ext = ?';
            $params[] = $cargoId;
        }

        $personaId = isset($filtros['persona_id']) ? (int)$filtros['persona_id'] : 0;
        if ($personaId > 0) {
            $condiciones[] = 'a.persona_id_ext = ?';
            $params[] = $personaId;
        }

        if (!$conBuscar) {
            return;
        }

        $buscar = isset($filtros['buscar']) ? trim((string)$filtros['buscar']) : '';
        if ($buscar === '') {
            return;
        }

        $personas = Database::personalTable('personas');
        $like = '%' . $buscar . '%';
        $condiciones[] = "(EXISTS (
                SELECT 1 FROM {$personas} pb
                WHERE pb.persona_id = a.persona_id_ext
                  AND (pb.nombre_completo_nombres_primero LIKE ? OR pb.numero_documento LIKE ?)
            ) OR cap.codigo LIKE ? OR cap.nombre LIKE ?)";
        array_push($params, $like, $like, $like, $like);
    }

    /** @param list<string> $valores */
    private function listaIn(array $valores): string
    {
        $partes = [];
        foreach ($valores as $valor) {
            $partes[] = "'" . str_replace("'", '', $valor) . "'";
        }

        return implode(',', $partes);
    }
}
