<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Consulta del cronograma: una fila de plan_anual_detalle = una capacitación programada.
 * No une matriz ni sesiones (evitar duplicados).
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
    public function programadas(array $periodo, ?int $procesoId): array
    {
        $meses = array_values(array_unique(array_map('intval', $periodo['meses'])));
        if ($meses === []) {
            return [];
        }

        $inMeses = implode(',', array_fill(0, count($meses), '?'));
        $params = array_merge([$periodo['anio']], $meses);
        $filtroProceso = '';

        if ($procesoId !== null) {
            $filtroProceso = 'AND d.proceso_id = ?';
            $params[] = $procesoId;
        }

        return $this->db->fetchAll(
            "SELECT d.plan_detalle_id,
                    d.mes_programado,
                    c.capacitacion_id,
                    c.codigo,
                    c.nombre,
                    c.objetivo,
                    c.duracion_estimada_horas,
                    mo.nombre AS metodologia,
                    pr.proceso_id,
                    pr.nombre AS proceso_nombre
             FROM plan_anual_detalle d
             INNER JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
             INNER JOIN capacitaciones c ON c.capacitacion_id = d.capacitacion_id
             LEFT JOIN modalidades mo ON mo.modalidad_id = c.modalidad_default_id
             LEFT JOIN procesos pr ON pr.proceso_id = d.proceso_id
             WHERE p.anio = ?
               AND d.mes_programado IN ({$inMeses})
               {$filtroProceso}
             ORDER BY d.mes_programado ASC, c.nombre ASC",
            $params
        );
    }

    /** @return list<array{proceso_id:int,nombre:string}> */
    public function procesos(): array
    {
        $filas = $this->db->fetchAll(
            'SELECT proceso_id, nombre FROM procesos ORDER BY nombre ASC'
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
}
