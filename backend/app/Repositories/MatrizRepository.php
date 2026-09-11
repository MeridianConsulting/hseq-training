<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDOException;
use Throwable;

class MatrizRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{capacitacion_id?:?int, cargo_id_ext?:?int, proceso_id?:?int, proyecto?:?string, activa?:?int} $filtros
     */
    public function listar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);

        return $this->db->fetchAll(
            $this->selectBase() . " {$where} ORDER BY cap.codigo ASC, m.matriz_aplicabilidad_id DESC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array{capacitacion_id?:?int, cargo_id_ext?:?int, proceso_id?:?int, proyecto?:?string, activa?:?int} $filtros
     */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM matriz_aplicabilidad m {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE m.matriz_aplicabilidad_id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Reglas activas con capacitación ACTIVA. NULL en cargo/proceso/proyecto = comodín.
     *
     * @return list<array<string,mixed>>
     */
    public function aplicables(?int $cargoId, ?int $procesoId, ?string $proyecto): array
    {
        $sql = $this->selectBase() . '
                WHERE m.activa = 1
                  AND cap.estado = \'ACTIVA\'';
        $params = [];

        if ($cargoId !== null && $cargoId > 0) {
            $sql .= ' AND (m.cargo_id_ext IS NULL OR m.cargo_id_ext = ?)';
            $params[] = $cargoId;
        }

        if ($procesoId !== null && $procesoId > 0) {
            $sql .= ' AND (m.proceso_id IS NULL OR m.proceso_id = ?)';
            $params[] = $procesoId;
        }

        if ($proyecto !== null && $proyecto !== '') {
            $sql .= ' AND (m.proyecto IS NULL OR TRIM(m.proyecto) = ?)';
            $params[] = $proyecto;
        }

        $sql .= ' ORDER BY cap.codigo ASC, m.matriz_aplicabilidad_id ASC';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Todas las reglas activas (capacitación ACTIVA) con periodicidad efectiva para el motor.
     *
     * @return list<array<string,mixed>>
     */
    public function reglasActivasParaMotor(): array
    {
        return $this->db->fetchAll(
            'SELECT m.*,
                    cap.codigo AS capacitacion_codigo,
                    cap.nombre AS capacitacion_nombre,
                    cap.periodicidad_default_id,
                    ar.nombre AS area_nombre,
                    pr.nombre AS proceso_nombre,
                    COALESCE(pe.cantidad, pd.cantidad) AS per_cantidad,
                    COALESCE(pe.unidad, pd.unidad) AS per_unidad,
                    COALESCE(pe.nombre, pd.nombre) AS periodicidad_nombre
             FROM matriz_aplicabilidad m
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = m.capacitacion_id
             LEFT JOIN areas ar ON ar.area_id = m.area_id
             LEFT JOIN procesos pr ON pr.proceso_id = m.proceso_id
             LEFT JOIN periodicidades pe ON pe.periodicidad_id = m.periodicidad_id
             LEFT JOIN periodicidades pd ON pd.periodicidad_id = cap.periodicidad_default_id
             WHERE m.activa = 1
               AND cap.estado = \'ACTIVA\'
             ORDER BY m.matriz_aplicabilidad_id ASC'
        );
    }

    /**
     * Filas del contexto (activas e inactivas) para la grilla.
     *
     * @return list<array<string,mixed>>
     */
    public function listarContexto(int $procesoId, ?string $proyecto): array
    {
        $sql = $this->selectBase() . ' WHERE m.proceso_id = ? AND m.area_id IS NULL AND m.cargo_id_ext IS NOT NULL';
        $params = [$procesoId];

        if ($proyecto === null || $proyecto === '') {
            $sql .= " AND (m.proyecto IS NULL OR TRIM(m.proyecto) = '')";
        } else {
            $sql .= ' AND m.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $sql .= ' ORDER BY cap.codigo ASC, m.cargo_id_ext ASC';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cargos con marca activa para una capacitación en un proceso/proyecto.
     *
     * @return list<array<string,mixed>>
     */
    public function cargosActivosDeCapacitacion(int $capacitacionId, int $procesoId, ?string $proyecto): array
    {
        $sql = $this->selectBase() . '
                WHERE m.activa = 1
                  AND cap.estado = \'ACTIVA\'
                  AND m.capacitacion_id = ?
                  AND m.proceso_id = ?
                  AND m.cargo_id_ext IS NOT NULL';
        $params = [$capacitacionId, $procesoId];

        if ($proyecto === null || $proyecto === '') {
            $sql .= " AND (m.proyecto IS NULL OR TRIM(m.proyecto) = '')";
        } else {
            $sql .= ' AND m.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $sql .= ' ORDER BY m.cargo_id_ext ASC';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cargos con al menos una marca activa en el proceso (capacitaciones ACTIVA).
     *
     * @return list<int>
     */
    public function cargoIdsActivosPorProceso(int $procesoId): array
    {
        if ($procesoId < 1) {
            return [];
        }

        $filas = $this->db->fetchAll(
            'SELECT DISTINCT m.cargo_id_ext
             FROM matriz_aplicabilidad m
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = m.capacitacion_id
             WHERE m.activa = 1
               AND cap.estado = \'ACTIVA\'
               AND m.proceso_id = ?
               AND m.cargo_id_ext IS NOT NULL',
            [$procesoId]
        );

        $ids = [];
        foreach ($filas as $fila) {
            $id = (int)($fila['cargo_id_ext'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Procesos de matriz para los cargos indicados.
     *
     * @param list<int> $cargoIds
     * @return list<array{cargo_id:int,proyecto:?string,proceso_id:int,proceso_nombre:string}>
     */
    public function procesosDeCargos(array $cargoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $cargoIds))));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->db->fetchAll(
            "SELECT DISTINCT m.cargo_id_ext,
                    m.proyecto,
                    m.proceso_id,
                    pr.nombre AS proceso_nombre
             FROM matriz_aplicabilidad m
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = m.capacitacion_id
             INNER JOIN procesos pr ON pr.proceso_id = m.proceso_id
             WHERE m.activa = 1
               AND cap.estado = 'ACTIVA'
               AND m.cargo_id_ext IN ({$in})
               AND m.proceso_id IS NOT NULL
             ORDER BY pr.nombre ASC",
            $ids
        );

        $salida = [];
        foreach ($filas as $fila) {
            $procesoId = (int)($fila['proceso_id'] ?? 0);
            $cargoId = (int)($fila['cargo_id_ext'] ?? 0);
            if ($procesoId < 1 || $cargoId < 1) {
                continue;
            }
            $proyecto = $fila['proyecto'] !== null && trim((string)$fila['proyecto']) !== ''
                ? (string)$fila['proyecto']
                : null;
            $salida[] = [
                'cargo_id' => $cargoId,
                'proyecto' => $proyecto,
                'proceso_id' => $procesoId,
                'proceso_nombre' => (string)($fila['proceso_nombre'] ?? ''),
            ];
        }

        return $salida;
    }

    /**
     * Proceso de matriz para un cargo (oficina o proyecto del trabajador).
     */
    public function procesoIdParaCargo(int $cargoId, ?string $proyecto = null): ?int
    {
        if ($cargoId < 1) {
            return null;
        }

        $proyectoNorm = $proyecto !== null ? trim($proyecto) : '';
        foreach ($this->procesosDeCargos([$cargoId]) as $fila) {
            if ((int)$fila['cargo_id'] !== $cargoId) {
                continue;
            }
            $filaProyecto = $fila['proyecto'];
            if ($filaProyecto !== null && $proyectoNorm === '') {
                continue;
            }
            if ($filaProyecto !== null && strcasecmp($filaProyecto, $proyectoNorm) !== 0) {
                continue;
            }

            $id = (int)$fila['proceso_id'];
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    public function buscarPorClave(array $datos): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE m.capacitacion_id = ?
                  AND (m.cargo_id_ext <=> ?)
                  AND (m.area_id <=> ?)
                  AND (m.proceso_id <=> ?)
                  AND (m.ambito <=> ?)
                  AND (m.proyecto <=> ?)
             LIMIT 1',
            [
                $datos['capacitacion_id'],
                $datos['cargo_id_ext'] ?? null,
                $datos['area_id'] ?? null,
                $datos['proceso_id'] ?? null,
                $datos['ambito'] ?? null,
                $datos['proyecto'] ?? null,
            ]
        );
    }

    public function duplicado(array $datos, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad
                WHERE capacitacion_id = ?
                  AND (cargo_id_ext <=> ?)
                  AND (area_id <=> ?)
                  AND (proceso_id <=> ?)
                  AND (ambito <=> ?)
                  AND (proyecto <=> ?)';
        $params = [
            $datos['capacitacion_id'],
            $datos['cargo_id_ext'] ?? null,
            $datos['area_id'] ?? null,
            $datos['proceso_id'] ?? null,
            $datos['ambito'] ?? null,
            $datos['proyecto'] ?? null,
        ];

        if ($exceptoId !== null) {
            $sql .= ' AND matriz_aplicabilidad_id <> ?';
            $params[] = $exceptoId;
        }

        return $this->db->fetch($sql, $params) !== null;
    }

    public function crear(array $datos): int
    {
        return (int)$this->db->insert('matriz_aplicabilidad', $datos);
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update('matriz_aplicabilidad', $datos, 'matriz_aplicabilidad_id = ?', [$id]);
    }

    public function inactivar(int $id): int
    {
        return $this->db->update('matriz_aplicabilidad', ['activa' => 0], 'matriz_aplicabilidad_id = ?', [$id]);
    }

    public function activar(int $id): int
    {
        return $this->db->update('matriz_aplicabilidad', ['activa' => 1], 'matriz_aplicabilidad_id = ?', [$id]);
    }

    /**
     * @param callable():mixed $operacion
     */
    public function transaccion(callable $operacion): mixed
    {
        $this->db->beginTransaction();

        try {
            $resultado = $operacion();
            $this->db->commit();

            return $resultado;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function selectBase(): string
    {
        return 'SELECT m.*,
                    cap.codigo AS capacitacion_codigo,
                    cap.nombre AS capacitacion_nombre,
                    ar.nombre AS area_nombre,
                    pr.nombre AS proceso_nombre,
                    pe.nombre AS periodicidad_nombre
                FROM matriz_aplicabilidad m
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = m.capacitacion_id
                LEFT JOIN areas ar ON ar.area_id = m.area_id
                LEFT JOIN procesos pr ON pr.proceso_id = m.proceso_id
                LEFT JOIN periodicidades pe ON pe.periodicidad_id = m.periodicidad_id';
    }

    /**
     * @param array{capacitacion_id?:?int, cargo_id_ext?:?int, proceso_id?:?int, proyecto?:?string, activa?:?int} $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $capacitacionId = $filtros['capacitacion_id'] ?? null;
        if ($capacitacionId !== null && $capacitacionId > 0) {
            $condiciones[] = 'm.capacitacion_id = ?';
            $params[] = $capacitacionId;
        }

        $cargoId = $filtros['cargo_id_ext'] ?? null;
        if ($cargoId !== null && $cargoId > 0) {
            $condiciones[] = 'm.cargo_id_ext = ?';
            $params[] = $cargoId;
        }

        $procesoId = $filtros['proceso_id'] ?? null;
        if ($procesoId !== null && $procesoId > 0) {
            $condiciones[] = 'm.proceso_id = ?';
            $params[] = $procesoId;
        }

        $proyecto = $filtros['proyecto'] ?? null;
        if (is_string($proyecto) && $proyecto !== '') {
            $condiciones[] = 'm.proyecto LIKE ?';
            $params[] = '%' . $proyecto . '%';
        }

        if (array_key_exists('activa', $filtros) && $filtros['activa'] !== null) {
            $condiciones[] = 'm.activa = ?';
            $params[] = (int)$filtros['activa'];
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
