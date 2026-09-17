<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Agregados del dashboard. Programado = asignaciones con plazo en el periodo; ejecutado = cumplimientos APROBADO.
 * No une sesion_participantes (evitar duplicar personas).
 * Los KPIs no filtran por estado actual del trabajador (histórico ≠ población actual).
 */
class DashboardRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     */
    public function programado(array $periodo, string $recorte, array $alcance = []): int
    {
        $alcance = $this->normalizarAlcance($alcance);
        [$extraJoin, $extraWhere, $extraParams] = $this->recorteCumplimiento($recorte);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);
        [$inMeses, $paramsMeses] = $this->inMeses($periodo['meses']);

        $sql = "SELECT COUNT(*) AS total
                FROM asignaciones_capacitacion a
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                {$extraJoin}
                WHERE YEAR(a.fecha_limite_cumplimiento) = ?
                  AND MONTH(a.fecha_limite_cumplimiento) IN ({$inMeses})
                  {$filtroAlcance}
                  {$extraWhere}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['anio']], $paramsMeses, $paramsAlcance, $extraParams)
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * Cumplimientos APROBADO cuya fecha real supera la fecha hasta de la asignación.
     *
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     */
    public function ejecutadasFueraDeTiempo(array $periodo, array $alcance = []): int
    {
        $alcance = $this->normalizarAlcance($alcance);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);

        $sql = "SELECT COUNT(*) AS total
                FROM cumplimientos_capacitacion cump
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
                WHERE cump.fecha_realizacion BETWEEN ? AND ?
                  AND cump.resultado = 'APROBADO'
                  AND cump.fecha_realizacion > a.fecha_limite_cumplimiento
                  {$filtroAlcance}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['desde'], $periodo['hasta']], $paramsAlcance)
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     */
    public function ejecutado(array $periodo, string $recorte, array $alcance = []): int
    {
        $alcance = $this->normalizarAlcance($alcance);
        [$extraJoin, $extraWhere, $extraParams] = $this->recorteCumplimiento($recorte);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);

        $sql = "SELECT COUNT(*) AS total
                FROM cumplimientos_capacitacion cump
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                {$extraJoin}
                WHERE cump.fecha_realizacion BETWEEN ? AND ?
                  AND cump.resultado = 'APROBADO'
                  {$filtroAlcance}
                  {$extraWhere}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['desde'], $periodo['hasta']], $paramsAlcance, $extraParams)
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     * @return array{promedio:?float,evaluaciones:int}
     */
    public function eficacia(array $periodo, string $recorte, array $alcance = []): array
    {
        $alcance = $this->normalizarAlcance($alcance);
        [$extraJoin, $extraWhere, $extraParams] = $this->recorteCumplimiento($recorte);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);

        $sql = "SELECT AVG(cump.nota_evaluacion) AS promedio,
                       COUNT(cump.nota_evaluacion) AS evaluaciones
                FROM cumplimientos_capacitacion cump
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                {$extraJoin}
                WHERE cump.fecha_realizacion BETWEEN ? AND ?
                  AND cump.nota_evaluacion IS NOT NULL
                  {$filtroAlcance}
                  {$extraWhere}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['desde'], $periodo['hasta']], $paramsAlcance, $extraParams)
        );

        $evaluaciones = (int)($fila['evaluaciones'] ?? 0);

        return [
            'promedio' => $evaluaciones > 0 ? round((float)$fila['promedio'], 2) : null,
            'evaluaciones' => $evaluaciones,
        ];
    }

    /**
     * Cumplimientos que requieren soporte (capacitaciones.certificado = 1).
     *
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     * @return array{requieren:int,con_soporte:int,pendientes:int,porcentaje:?float}
     */
    public function soportes(array $periodo, array $alcance = []): array
    {
        $alcance = $this->normalizarAlcance($alcance);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);

        $sql = "SELECT COUNT(*) AS requieren,
                       COALESCE(SUM(CASE WHEN EXISTS (
                           SELECT 1 FROM soportes_cumplimiento s
                           WHERE s.cumplimiento_id = cump.cumplimiento_id
                       ) THEN 1 ELSE 0 END), 0) AS con_soporte
                FROM cumplimientos_capacitacion cump
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                WHERE cump.fecha_realizacion BETWEEN ? AND ?
                  AND cap.certificado = 1
                  {$filtroAlcance}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['desde'], $periodo['hasta']], $paramsAlcance)
        );

        $requieren = (int)($fila['requieren'] ?? 0);
        $conSoporte = (int)($fila['con_soporte'] ?? 0);
        $pendientes = max(0, $requieren - $conSoporte);

        return [
            'requieren' => $requieren,
            'con_soporte' => $conSoporte,
            'pendientes' => $pendientes,
            'porcentaje' => $requieren > 0 ? round($conSoporte / $requieren * 100, 1) : null,
        ];
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     * @return array{programadas:float,ejecutadas:float}
     */
    public function horas(array $periodo, string $recorte, array $alcance = []): array
    {
        $alcance = $this->normalizarAlcance($alcance);

        return [
            'programadas' => $this->horasProgramadas($periodo, $recorte, $alcance),
            'ejecutadas' => $this->horasEjecutadas($periodo, $recorte, $alcance),
        ];
    }

    /**
     * @param array{modo?:string,proceso_id?:?int,proyecto?:?string} $alcance
     * @return array{modo:string,proceso_id:?int,proyecto:?string}
     */
    private function normalizarAlcance(array $alcance): array
    {
        return [
            'modo' => (string)($alcance['modo'] ?? 'todos'),
            'proceso_id' => isset($alcance['proceso_id']) ? (int)$alcance['proceso_id'] : null,
            'proyecto' => isset($alcance['proyecto']) && is_string($alcance['proyecto']) && $alcance['proyecto'] !== ''
                ? $alcance['proyecto']
                : null,
        ];
    }

    /**
     * Procesos vigentes del catálogo HSEQ (filtro Panel y demás módulos).
     *
     * @return list<array{proceso_id:int,nombre:string}>
     */
    public function procesos(): array
    {
        $filas = $this->db->fetchAll(
            'SELECT proceso_id, nombre FROM procesos WHERE activo = 1 ORDER BY nombre ASC'
        );

        $salida = [];
        foreach ($filas as $fila) {
            $salida[] = [
                'proceso_id' => (int)$fila['proceso_id'],
                'nombre' => (string)$fila['nombre'],
            ];
        }

        return $salida;
    }

    /**
     * Proyectos vigentes del catálogo HSEQ.
     *
     * @return list<string>
     */
    public function proyectos(): array
    {
        $filas = $this->db->fetchAll(
            'SELECT nombre FROM proyectos WHERE activo = 1 ORDER BY nombre ASC'
        );

        $salida = [];
        foreach ($filas as $fila) {
            $nombre = trim((string)($fila['nombre'] ?? ''));
            if ($nombre !== '') {
                $salida[] = $nombre;
            }
        }

        return $salida;
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo:string,proceso_id:?int,proyecto:?string} $alcance
     */
    private function horasProgramadas(array $periodo, string $recorte, array $alcance): float
    {
        [$extraJoin, $extraWhere, $extraParams] = $this->recorteCumplimiento($recorte);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);
        [$inMeses, $paramsMeses] = $this->inMeses($periodo['meses']);

        $sql = "SELECT COALESCE(SUM(cap.duracion_estimada_horas), 0) AS total
                FROM asignaciones_capacitacion a
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                {$extraJoin}
                WHERE YEAR(a.fecha_limite_cumplimiento) = ?
                  AND MONTH(a.fecha_limite_cumplimiento) IN ({$inMeses})
                  {$filtroAlcance}
                  {$extraWhere}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['anio']], $paramsMeses, $paramsAlcance, $extraParams)
        );

        return round((float)($fila['total'] ?? 0), 2);
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @param array{modo:string,proceso_id:?int,proyecto:?string} $alcance
     */
    private function horasEjecutadas(array $periodo, string $recorte, array $alcance): float
    {
        [$extraJoin, $extraWhere, $extraParams] = $this->recorteCumplimiento($recorte);
        [$filtroAlcance, $paramsAlcance] = $this->filtroAlcance('a', $alcance);

        $sql = "SELECT COALESCE(SUM(cump.horas_efectivas), 0) AS total
                FROM cumplimientos_capacitacion cump
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                {$extraJoin}
                WHERE cump.fecha_realizacion BETWEEN ? AND ?
                  AND cump.resultado = 'APROBADO'
                  {$filtroAlcance}
                  {$extraWhere}";

        $fila = $this->db->fetch(
            $sql,
            array_merge([$periodo['desde'], $periodo['hasta']], $paramsAlcance, $extraParams)
        );

        return round((float)($fila['total'] ?? 0), 2);
    }

    /**
     * @param array{modo:string,proceso_id:?int,proyecto:?string} $alcance
     * @return array{0:string,1:list<mixed>}
     */
    private function filtroAlcance(string $alias, array $alcance): array
    {
        $modo = $alcance['modo'] ?? 'todos';

        if ($modo === 'proceso') {
            $procesoId = $alcance['proceso_id'] ?? null;
            if ($procesoId === null) {
                return ['AND (1 = 0)', []];
            }

            $where = "AND {$alias}.proceso_id = ? AND ({$alias}.ambito IN ('ADMINISTRACION', 'PROYECTO') OR {$alias}.ambito IS NULL)";
            $params = [(int)$procesoId];
            $proyecto = $alcance['proyecto'] ?? null;
            if (is_string($proyecto) && $proyecto !== '') {
                $where .= " AND {$alias}.proyecto = ?";
                $params[] = $proyecto;
            }

            return [$where, $params];
        }

        return [
            "AND ({$alias}.ambito IN ('ADMINISTRACION', 'PROYECTO') OR {$alias}.ambito IS NULL)",
            [],
        ];
    }

    /**
     * @return array{0:string,1:string,2:list<mixed>}
     */
    private function recorteCumplimiento(string $recorte): array
    {
        if ($recorte === 'critica') {
            return ['', 'AND cap.es_tarea_critica = 1', []];
        }

        if ($recorte === 'induccion') {
            return [
                'LEFT JOIN tipos_capacitacion t ON t.tipo_capacitacion_id = cap.tipo_capacitacion_id',
                'AND (a.origen IN (\'INDUCCION\', \'REINDUCCION\') OR ('
                    . 't.nombre IS NOT NULL AND ' . $this->sqlNombreInduccion('t.nombre')
                    . '))',
                [],
            ];
        }

        return ['', '', []];
    }

    private function sqlNombreInduccion(string $columna): string
    {
        return "(LOWER({$columna}) LIKE '%induc%' OR LOWER({$columna}) LIKE '%inducción%' OR LOWER({$columna}) LIKE '%reinducción%')";
    }

    /**
     * @param list<int> $meses
     * @return array{0:string,1:list<int>}
     */
    private function inMeses(array $meses): array
    {
        $meses = array_values(array_unique(array_map('intval', $meses)));
        if ($meses === []) {
            $meses = [0];
        }

        return [implode(',', array_fill(0, count($meses), '?')), $meses];
    }
}
