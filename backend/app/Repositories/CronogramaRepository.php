<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Cronograma operativo: fuente = asignaciones (plazo) y, si existe, vínculo al plan aprobado.
 * No exige fecha en plan_anual_detalle.
 */
class CronogramaRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{anio:int,meses:list<int>} $periodo
     * @return list<array<string,mixed>>
     */
    public function programadas(
        array $periodo,
        ?int $procesoId,
        ?string $proyecto = null,
        ?string $buscar = null
    ): array {
        $meses = array_values(array_unique(array_map('intval', $periodo['meses'])));
        if ($meses === []) {
            return [];
        }

        $inMeses = implode(',', array_fill(0, count($meses), '?'));
        $params = [$periodo['anio'], $periodo['anio']];
        foreach ($meses as $mes) {
            $params[] = $mes;
        }
        $params[] = $periodo['anio'];
        $extras = '';

        if ($procesoId !== null) {
            $extras .= ' AND (
                g.proceso_id = ?
                OR EXISTS (
                    SELECT 1 FROM plan_detalle_alcances a
                    WHERE a.plan_detalle_id = d.plan_detalle_id AND a.proceso_id = ?
                )
            )';
            $params[] = $procesoId;
            $params[] = $procesoId;
        }

        if ($proyecto !== null && $proyecto !== '') {
            $extras .= ' AND (
                g.proyecto COLLATE utf8mb4_unicode_ci = ?
                OR EXISTS (
                    SELECT 1 FROM plan_detalle_alcances a
                    WHERE a.plan_detalle_id = d.plan_detalle_id
                      AND a.proyecto COLLATE utf8mb4_unicode_ci = ?
                )
            )';
            $params[] = $proyecto;
            $params[] = $proyecto;
        }

        if ($buscar !== null && $buscar !== '') {
            $extras .= ' AND (c.codigo LIKE ? OR c.nombre LIKE ? OR c.objetivo LIKE ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $like);
        }

        return $this->db->fetchAll(
            "SELECT d.plan_detalle_id,
                    d.plan_anual_id,
                    COALESCE(d.mes_programado, g.mes_programado) AS mes_programado,
                    g.fecha_desde,
                    g.fecha_hasta,
                    COALESCE(d.fecha_programada, g.fecha_hasta) AS fecha_programada,
                    g.cantidad_programada,
                    g.ejecutadas_fuera_de_tiempo,
                    g.pendientes_fuera_plazo,
                    COALESCE(d.estado_programacion, 'PROGRAMADA') AS estado_programacion,
                    COALESCE(d.ambito, g.ambito) AS ambito,
                    COALESCE(d.proyecto, g.proyecto) AS proyecto,
                    ? AS anio,
                    COALESCE(p.estado, 'APROBADO') AS plan_estado,
                    c.capacitacion_id,
                    c.codigo,
                    c.nombre,
                    c.objetivo,
                    c.duracion_estimada_horas,
                    c.modalidad_default_id,
                    c.proveedor_default_id,
                    c.evaluacion,
                    c.certificado,
                    c.vigencia_id,
                    vig.nombre AS vigencia_nombre,
                    vig.cantidad AS vigencia_cantidad,
                    vig.unidad AS vigencia_unidad,
                    mo.nombre AS metodologia,
                    COALESCE(d.proceso_id, g.proceso_id, pr.proceso_id) AS proceso_id,
                    pr.nombre AS proceso_nombre
             FROM (
                SELECT a.capacitacion_id,
                       MONTH(a.fecha_limite_cumplimiento) AS mes_programado,
                       MIN(a.fecha_asignacion) AS fecha_desde,
                       MAX(a.fecha_limite_cumplimiento) AS fecha_hasta,
                       MAX(a.fecha_limite_cumplimiento) AS fecha_programada,
                       COUNT(*) AS cantidad_programada,
                       SUM(
                         CASE
                           WHEN cump.fecha_realizacion IS NOT NULL
                            AND DATE(cump.fecha_realizacion) > a.fecha_limite_cumplimiento
                           THEN 1 ELSE 0
                         END
                       ) AS ejecutadas_fuera_de_tiempo,
                       SUM(
                         CASE
                           WHEN cump.cumplimiento_id IS NULL
                            AND a.fecha_limite_cumplimiento < CURDATE()
                           THEN 1 ELSE 0
                         END
                       ) AS pendientes_fuera_plazo,
                       MIN(a.proceso_id) AS proceso_id,
                       MIN(a.ambito) AS ambito,
                       MIN(a.proyecto) AS proyecto
                FROM asignaciones_capacitacion a
                LEFT JOIN cumplimientos_capacitacion cump ON cump.asignacion_id = a.asignacion_id
                WHERE YEAR(a.fecha_limite_cumplimiento) = ?
                  AND MONTH(a.fecha_limite_cumplimiento) IN ({$inMeses})
                GROUP BY a.capacitacion_id, MONTH(a.fecha_limite_cumplimiento)
             ) g
             INNER JOIN capacitaciones c ON c.capacitacion_id = g.capacitacion_id
             LEFT JOIN planes_anuales p
               ON p.anio = ?
              AND p.estado = 'APROBADO'
             LEFT JOIN plan_anual_detalle d
               ON d.plan_anual_id = p.plan_anual_id
              AND d.capacitacion_id = g.capacitacion_id
             LEFT JOIN vigencias vig ON vig.vigencia_id = c.vigencia_id
             LEFT JOIN modalidades mo ON mo.modalidad_id = c.modalidad_default_id
             LEFT JOIN procesos pr ON pr.proceso_id = COALESCE(d.proceso_id, g.proceso_id)
             WHERE 1 = 1
               {$extras}
             ORDER BY COALESCE(d.fecha_programada, g.fecha_hasta) ASC, c.codigo ASC",
            $params
        );
    }

    /**
     * Un grupo operativo: capacitación + año + mes de Fecha Hasta.
     *
     * @return array<string,mixed>|null
     */
    public function buscarGrupo(int $capacitacionId, int $anio, int $mes): ?array
    {
        if ($capacitacionId < 1 || $anio < 2000 || $mes < 1 || $mes > 12) {
            return null;
        }

        $filas = $this->programadas(
            ['anio' => $anio, 'meses' => [$mes]],
            null,
            null,
            null
        );
        foreach ($filas as $fila) {
            if ((int)$fila['capacitacion_id'] === $capacitacionId && (int)$fila['mes_programado'] === $mes) {
                $fila['anio'] = $anio;
                return $fila;
            }
        }

        return null;
    }

    /**
     * @return array{fecha_desde:?string,fecha_hasta:?string,cantidad:int}
     */
    public function periodoGrupo(int $capacitacionId, int $anio, int $mes): array
    {
        $fila = $this->db->fetch(
            "SELECT MIN(a.fecha_asignacion) AS fecha_desde,
                    MAX(a.fecha_limite_cumplimiento) AS fecha_hasta,
                    COUNT(*) AS cantidad
             FROM asignaciones_capacitacion a
             WHERE a.capacitacion_id = ?
               AND YEAR(a.fecha_limite_cumplimiento) = ?
               AND MONTH(a.fecha_limite_cumplimiento) = ?",
            [$capacitacionId, $anio, $mes]
        );

        return [
            'fecha_desde' => isset($fila['fecha_desde']) && $fila['fecha_desde'] !== null
                ? substr((string)$fila['fecha_desde'], 0, 10)
                : null,
            'fecha_hasta' => isset($fila['fecha_hasta']) && $fila['fecha_hasta'] !== null
                ? substr((string)$fila['fecha_hasta'], 0, 10)
                : null,
            'cantidad' => (int)($fila['cantidad'] ?? 0),
        ];
    }

    public function buscarProgramacion(int $detalleId): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE d.plan_detalle_id = ? LIMIT 1',
            [$detalleId]
        );
    }

    /**
     * @param list<int> $cargoIds
     * @return list<array<string,mixed>>
     */
    public function trabajadoresProgramados(int $capacitacionId, array $cargoIds): array
    {
        $ids = [];
        foreach ($cargoIds as $id) {
            $n = (int)$id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');
        $params = [$capacitacionId];
        $orden = 'per.nombre_completo_nombres_primero ASC, a.asignacion_id ASC';
        if ($ids !== []) {
            $lista = array_values($ids);
            $in = implode(',', array_fill(0, count($lista), '?'));
            $orden = "(CASE WHEN a.cargo_id_ext IN ({$in})
                    OR (a.cargo_id_ext IS NULL AND per.cargo_id IN ({$in}))
                 THEN 1 ELSE 0 END) DESC, {$orden}";
            $params = array_merge($params, $lista, $lista);
        }

        return $this->db->fetchAll(
            "SELECT a.asignacion_id,
                    a.persona_id_ext,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    COALESCE(cg.nombre_cargo, cgp.nombre_cargo) AS nombre_cargo,
                    e.estado_calculado
             FROM asignaciones_capacitacion a
             INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
             INNER JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             LEFT JOIN {$cargos} cg ON cg.cargo_id = a.cargo_id_ext
             LEFT JOIN {$cargos} cgp ON cgp.cargo_id = per.cargo_id
             WHERE a.capacitacion_id = ?
               AND per.estado = 'Activo'
             ORDER BY {$orden}",
            $params
        );
    }

    /**
     * Trabajadores con plazo en un mes/año concretos (fuente operativa del cronograma).
     *
     * @param list<int> $cargoIds
     * @return list<array<string,mixed>>
     */
    public function trabajadoresPorPlazo(int $capacitacionId, int $anio, int $mes, array $cargoIds = []): array
    {
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');
        $params = [$capacitacionId, $anio, $mes];

        return $this->db->fetchAll(
            "SELECT a.asignacion_id,
                    a.persona_id_ext,
                    a.fecha_asignacion,
                    a.fecha_limite_cumplimiento,
                    a.proyecto,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    COALESCE(cg.nombre_cargo, cgp.nombre_cargo) AS nombre_cargo,
                    e.estado_calculado,
                    cump.fecha_realizacion,
                    CASE
                      WHEN cump.fecha_realizacion IS NOT NULL
                       AND DATE(cump.fecha_realizacion) > a.fecha_limite_cumplimiento
                      THEN 1 ELSE 0
                    END AS ejecutada_fuera_de_tiempo
             FROM asignaciones_capacitacion a
             INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
             INNER JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             LEFT JOIN cumplimientos_capacitacion cump ON cump.asignacion_id = a.asignacion_id
             LEFT JOIN {$cargos} cg ON cg.cargo_id = a.cargo_id_ext
             LEFT JOIN {$cargos} cgp ON cgp.cargo_id = per.cargo_id
             WHERE a.capacitacion_id = ?
               AND YEAR(a.fecha_limite_cumplimiento) = ?
               AND MONTH(a.fecha_limite_cumplimiento) = ?
               AND per.estado = 'Activo'
             ORDER BY per.nombre_completo_nombres_primero ASC, a.asignacion_id ASC",
            $params
        );
    }

    private function selectBase(): string
    {
        return 'SELECT d.plan_detalle_id,
                    d.plan_anual_id,
                    d.mes_programado,
                    d.fecha_programada,
                    d.cantidad_programada,
                    d.estado_programacion,
                    d.ambito,
                    d.proyecto,
                    p.anio,
                    p.estado AS plan_estado,
                    c.capacitacion_id,
                    c.codigo,
                    c.nombre,
                    c.objetivo,
                    c.duracion_estimada_horas,
                    c.modalidad_default_id,
                    c.proveedor_default_id,
                    c.evaluacion,
                    c.certificado,
                    c.vigencia_id,
                    vig.nombre AS vigencia_nombre,
                    vig.cantidad AS vigencia_cantidad,
                    vig.unidad AS vigencia_unidad,
                    mo.nombre AS metodologia,
                    pr.proceso_id,
                    pr.nombre AS proceso_nombre
             FROM plan_anual_detalle d
             INNER JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
             INNER JOIN capacitaciones c ON c.capacitacion_id = d.capacitacion_id
             LEFT JOIN vigencias vig ON vig.vigencia_id = c.vigencia_id
             LEFT JOIN modalidades mo ON mo.modalidad_id = c.modalidad_default_id
             LEFT JOIN procesos pr ON pr.proceso_id = d.proceso_id';
    }
}
