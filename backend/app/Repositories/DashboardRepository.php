<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Agregados del dashboard. Programado = plan anual APROBADO; ejecutado = cumplimientos.
 * No une sesion_participantes (evitar duplicar personas).
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
     */
    public function programado(array $periodo, string $recorte): int
    {
        [$extraJoin, $extraWhere, $extraParams] = $this->recortePlan($recorte);
        [$inMeses, $paramsMeses] = $this->inMeses($periodo['meses']);

        $sql = "SELECT COALESCE(SUM(d.cantidad_programada), 0) AS total
                FROM plan_anual_detalle d
                INNER JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = d.capacitacion_id
                {$extraJoin}
                WHERE p.anio = ?
                  AND p.estado = 'APROBADO'
                  AND d.mes_programado IN ({$inMeses})
                  AND (d.ambito IN ('ADMINISTRACION', 'PROYECTO') OR d.ambito IS NULL)
                  {$extraWhere}";

        $fila = $this->db->fetch($sql, array_merge([$periodo['anio']], $paramsMeses, $extraParams));

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     */
    public function ejecutado(array $periodo, string $recorte): int
    {
        [$extraJoin, $extraWhere, $extraParams] = $this->recorteCumplimiento($recorte);

        $sql = "SELECT COUNT(*) AS total
                FROM cumplimientos_capacitacion cump
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                {$this->joinPersonaActiva()}
                {$extraJoin}
                WHERE cump.fecha_realizacion BETWEEN ? AND ?
                  AND (a.ambito IN ('ADMINISTRACION', 'PROYECTO') OR a.ambito IS NULL)
                  {$extraWhere}";

        $fila = $this->db->fetch($sql, array_merge([$periodo['desde'], $periodo['hasta']], $extraParams));

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @return list<array{capacitacion_id:int,codigo:string,nombre:string,promedio:float,evaluaciones:int}>
     */
    public function eficaciaPorTema(array $periodo): array
    {
        $filas = $this->db->fetchAll(
            "SELECT cap.capacitacion_id, cap.codigo, cap.nombre,
                    AVG(cump.nota_evaluacion) AS promedio,
                    COUNT(cump.nota_evaluacion) AS evaluaciones
             FROM cumplimientos_capacitacion cump
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             {$this->joinPersonaActiva()}
             WHERE cump.fecha_realizacion BETWEEN ? AND ?
               AND cump.nota_evaluacion IS NOT NULL
               AND (a.ambito IN ('ADMINISTRACION', 'PROYECTO') OR a.ambito IS NULL)
             GROUP BY cap.capacitacion_id, cap.codigo, cap.nombre
             ORDER BY cap.nombre ASC",
            [$periodo['desde'], $periodo['hasta']]
        );

        $salida = [];
        foreach ($filas as $fila) {
            $salida[] = [
                'capacitacion_id' => (int)$fila['capacitacion_id'],
                'codigo' => (string)$fila['codigo'],
                'nombre' => (string)$fila['nombre'],
                'promedio' => round((float)$fila['promedio'], 2),
                'evaluaciones' => (int)$fila['evaluaciones'],
            ];
        }

        return $salida;
    }

    /**
     * @param array{anio:int,meses:list<int>,desde:string,hasta:string} $periodo
     * @return list<array{capacitacion_id:int,codigo:string,nombre:string,horas:float}>
     */
    public function horasPorTema(array $periodo): array
    {
        $filas = $this->db->fetchAll(
            "SELECT cap.capacitacion_id, cap.codigo, cap.nombre,
                    COALESCE(SUM(cump.horas_efectivas), 0) AS horas
             FROM cumplimientos_capacitacion cump
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = cump.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             {$this->joinPersonaActiva()}
             WHERE cump.fecha_realizacion BETWEEN ? AND ?
               AND (a.ambito IN ('ADMINISTRACION', 'PROYECTO') OR a.ambito IS NULL)
             GROUP BY cap.capacitacion_id, cap.codigo, cap.nombre
             ORDER BY horas DESC, cap.nombre ASC",
            [$periodo['desde'], $periodo['hasta']]
        );

        $salida = [];
        foreach ($filas as $fila) {
            $salida[] = [
                'capacitacion_id' => (int)$fila['capacitacion_id'],
                'codigo' => (string)$fila['codigo'],
                'nombre' => (string)$fila['nombre'],
                'horas' => round((float)$fila['horas'], 2),
            ];
        }

        return $salida;
    }

    /**
     * @return array{0:string,1:string,2:list<mixed>}
     */
    private function recortePlan(string $recorte): array
    {
        if ($recorte === 'critica') {
            return ['', 'AND cap.es_tarea_critica = 1', []];
        }

        if ($recorte === 'induccion') {
            return [
                'LEFT JOIN tipos_capacitacion t ON t.tipo_capacitacion_id = cap.tipo_capacitacion_id',
                'AND t.nombre IS NOT NULL AND (' . $this->sqlNombreInduccion('t.nombre') . ')',
                [],
            ];
        }

        return ['', '', []];
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

    private function joinPersonaActiva(): string
    {
        $personas = Database::personalTable('personas');

        return "INNER JOIN {$personas} per_vig ON per_vig.persona_id = a.persona_id_ext AND per_vig.estado = 'Activo'";
    }
}
