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
