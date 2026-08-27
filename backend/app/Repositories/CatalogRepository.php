<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Acceso a datos generico para los catalogos. La tabla y la llave primaria llegan
 * siempre desde config/catalogs.php, nunca desde la peticion.
 */
class CatalogRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listar(array $def, bool $soloActivos = false, ?string $buscar = null): array
    {
        $condiciones = [];
        $params = [];

        if ($soloActivos && $def['soft_delete']) {
            $condiciones[] = 'activo = 1';
        }

        if ($buscar !== null && $buscar !== '') {
            $condiciones[] = 'nombre LIKE ?';
            $params[] = '%' . $buscar . '%';
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->db->fetchAll(
            "SELECT * FROM {$def['tabla']} {$where} ORDER BY nombre ASC",
            $params
        );
    }

    public function buscarPorId(array $def, int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$def['tabla']} WHERE {$def['pk']} = ?",
            [$id]
        );
    }

    public function crear(array $def, array $datos): int
    {
        return (int)$this->db->insert($def['tabla'], $datos);
    }

    public function actualizar(array $def, int $id, array $datos): int
    {
        return $this->db->update($def['tabla'], $datos, "{$def['pk']} = ?", [$id]);
    }

    public function inactivar(array $def, int $id): int
    {
        return $this->db->update($def['tabla'], ['activo' => 0], "{$def['pk']} = ?", [$id]);
    }

    public function eliminar(array $def, int $id): int
    {
        return $this->db->delete($def['tabla'], "{$def['pk']} = ?", [$id]);
    }

    public function nombreDuplicado(array $def, string $nombre, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) AS total FROM {$def['tabla']} WHERE nombre = ?";
        $params = [$nombre];

        if ($exceptoId !== null) {
            $sql .= " AND {$def['pk']} <> ?";
            $params[] = $exceptoId;
        }

        $fila = $this->db->fetch($sql, $params);

        return (int)($fila['total'] ?? 0) > 0;
    }
}
