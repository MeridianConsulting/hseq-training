<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class AlertaRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{proceso_id?:?int, proyecto?:?string, cargo_id_ext?:?int} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT c.cumplimiento_id,
                    c.asignacion_id,
                    c.fecha_realizacion,
                    c.fecha_vencimiento,
                    DATEDIFF(c.fecha_vencimiento, CURDATE()) AS dias_restantes,
                    a.persona_id_ext,
                    a.proceso_id,
                    a.cargo_id_ext,
                    a.proyecto,
                    a.capacitacion_id,
                    cap.codigo AS capacitacion_codigo,
                    cap.nombre AS capacitacion_nombre,
                    proc.nombre AS proceso_nombre,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    car.nombre_cargo
             FROM cumplimientos_capacitacion c
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             LEFT JOIN {$cargos} car ON car.cargo_id = a.cargo_id_ext
             {$where}
             ORDER BY c.fecha_vencimiento ASC, c.cumplimiento_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array{proceso_id?:?int, proyecto?:?string, cargo_id_ext?:?int} $filtros
     */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM cumplimientos_capacitacion c
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @return list<array{proceso_id:int|string,nombre:string}>
     */
    public function procesosActivos(): array
    {
        return $this->db->fetchAll(
            'SELECT proceso_id, nombre
             FROM procesos
             WHERE activo = 1
             ORDER BY nombre ASC'
        );
    }

    /**
     * @return list<array{cargo_id:int|string,nombre_cargo:string}>
     */
    public function cargos(): array
    {
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT cargo_id, nombre_cargo
             FROM {$cargos}
             ORDER BY nombre_cargo ASC"
        );
    }

    /** @return list<string> */
    public function proyectos(): array
    {
        $filas = $this->db->fetchAll(
            "SELECT DISTINCT proyecto
             FROM asignaciones_capacitacion
             WHERE proyecto IS NOT NULL AND TRIM(proyecto) <> ''
             ORDER BY proyecto ASC"
        );

        $nombres = [];
        foreach ($filas as $fila) {
            $nombre = trim((string)($fila['proyecto'] ?? ''));
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return $nombres;
    }

    /**
     * @param array{proceso_id?:?int, proyecto?:?string, cargo_id_ext?:?int} $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(array $filtros): array
    {
        $condiciones = [
            "c.resultado COLLATE utf8mb4_unicode_ci = 'APROBADO'",
            'c.fecha_vencimiento > CURDATE()',
            'c.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)',
        ];
        $params = [];

        $procesoId = $filtros['proceso_id'] ?? null;
        if ($procesoId !== null && $procesoId > 0) {
            $condiciones[] = 'a.proceso_id = ?';
            $params[] = $procesoId;
        }

        $proyecto = $filtros['proyecto'] ?? null;
        if (is_string($proyecto) && $proyecto !== '') {
            $condiciones[] = 'a.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $cargoId = $filtros['cargo_id_ext'] ?? null;
        if ($cargoId !== null && $cargoId > 0) {
            $condiciones[] = 'a.cargo_id_ext = ?';
            $params[] = $cargoId;
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $params];
    }
}
