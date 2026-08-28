<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class MatrizRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listar(int $limite, int $offset, ?int $capacitacionId, ?int $cargoId): array
    {
        [$where, $params] = $this->filtros($capacitacionId, $cargoId);

        return $this->db->fetchAll(
            $this->selectBase() . " {$where} ORDER BY cap.codigo ASC, m.matriz_aplicabilidad_id DESC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    public function contar(?int $capacitacionId, ?int $cargoId): int
    {
        [$where, $params] = $this->filtros($capacitacionId, $cargoId);
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

    public function eliminar(int $id): int
    {
        return $this->db->delete('matriz_aplicabilidad', 'matriz_aplicabilidad_id = ?', [$id]);
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

    /** @return array{0:string,1:list<mixed>} */
    private function filtros(?int $capacitacionId, ?int $cargoId): array
    {
        $condiciones = [];
        $params = [];

        if ($capacitacionId !== null && $capacitacionId > 0) {
            $condiciones[] = 'm.capacitacion_id = ?';
            $params[] = $capacitacionId;
        }

        if ($cargoId !== null && $cargoId > 0) {
            $condiciones[] = 'm.cargo_id_ext = ?';
            $params[] = $cargoId;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
