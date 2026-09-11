<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Consulta del cronograma: una fila de plan_anual_detalle = una capacitación programada.
 * No une matriz ni participantes (evitar duplicados). Las sesiones se cargan aparte.
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
        $params = array_merge([$periodo['anio']], $meses);
        $extras = '';

        if ($procesoId !== null) {
            $extras .= ' AND (
                d.proceso_id = ?
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
                d.proyecto COLLATE utf8mb4_unicode_ci = ?
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
            $this->selectBase() . "
             WHERE p.anio = ?
               AND p.estado = 'APROBADO'
               AND d.mes_programado IN ({$inMeses})
               {$extras}
             ORDER BY d.fecha_programada ASC, c.codigo ASC",
            $params
        );
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
