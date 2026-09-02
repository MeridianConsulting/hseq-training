<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use InvalidArgumentException;
use PDOException;

/**
 * Acceso a datos generico para los catalogos. Tabla y PK llegan desde
 * config/catalogs.php, nunca desde la peticion.
 */
class CatalogRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listar(array $def, string $filtroEstado, ?string $buscar = null, ?int $limite = null, ?int $offset = null): array
    {
        [$where, $params] = $this->condiciones($def, $filtroEstado, $buscar);
        $tabla = $this->identificador((string)$def['tabla']);
        $sql = "SELECT * FROM {$tabla} {$where} ORDER BY nombre ASC";
        if ($limite !== null) {
            $limite = max(1, $limite);
            $offset = max(0, (int)$offset);
            $sql .= " LIMIT {$limite} OFFSET {$offset}";
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function contar(array $def, string $filtroEstado, ?string $buscar = null): int
    {
        [$where, $params] = $this->condiciones($def, $filtroEstado, $buscar);
        $tabla = $this->identificador((string)$def['tabla']);
        $fila = $this->db->fetch("SELECT COUNT(*) AS total FROM {$tabla} {$where}", $params);

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function condiciones(array $def, string $filtroEstado, ?string $buscar): array
    {
        $condiciones = [];
        $params = [];

        if (!empty($def['soft_delete'])) {
            if ($filtroEstado === 'activos') {
                $condiciones[] = 'activo = 1';
            } elseif ($filtroEstado === 'inactivos') {
                $condiciones[] = 'activo = 0';
            }
        }

        if ($buscar !== null && $buscar !== '') {
            $condiciones[] = 'nombre LIKE ?';
            $params[] = '%' . $buscar . '%';
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }

    public function buscarPorId(array $def, int $id): ?array
    {
        $tabla = $this->identificador((string)$def['tabla']);
        $pk = $this->identificador((string)$def['pk']);

        return $this->db->fetch(
            "SELECT * FROM {$tabla} WHERE {$pk} = ?",
            [$id]
        );
    }

    public function crear(array $def, array $datos): int
    {
        return (int)$this->db->insert((string)$def['tabla'], $datos);
    }

    public function actualizar(array $def, int $id, array $datos): int
    {
        $pk = $this->identificador((string)$def['pk']);

        return $this->db->update((string)$def['tabla'], $datos, "{$pk} = ?", [$id]);
    }

    public function inactivar(array $def, int $id): int
    {
        $pk = $this->identificador((string)$def['pk']);

        return $this->db->update((string)$def['tabla'], ['activo' => 0], "{$pk} = ?", [$id]);
    }

    public function reactivar(array $def, int $id): int
    {
        $pk = $this->identificador((string)$def['pk']);

        return $this->db->update((string)$def['tabla'], ['activo' => 1], "{$pk} = ?", [$id]);
    }

    public function nombreDuplicado(array $def, string $nombre, ?int $exceptoId = null): bool
    {
        $tabla = $this->identificador((string)$def['tabla']);
        $pk = $this->identificador((string)$def['pk']);
        $sql = "SELECT COUNT(*) AS total FROM {$tabla} WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))";
        $params = [$nombre];

        if ($exceptoId !== null) {
            $sql .= " AND {$pk} <> ?";
            $params[] = $exceptoId;
        }

        $fila = $this->db->fetch($sql, $params);

        return (int)($fila['total'] ?? 0) > 0;
    }

    /**
     * @return array{total:int, etiquetas:list<string>}
     */
    public function contarDependencias(array $def, int $id): array
    {
        $etiquetas = [];
        $total = 0;
        $deps = $def['dependencias'] ?? [];

        if (!is_array($deps)) {
            return ['total' => 0, 'etiquetas' => []];
        }

        foreach ($deps as $dep) {
            if (!is_array($dep)) {
                continue;
            }

            $tabla = (string)($dep['tabla'] ?? '');
            $columna = (string)($dep['columna'] ?? '');
            $etiqueta = (string)($dep['etiqueta'] ?? $tabla);

            if ($tabla === '' || $columna === '') {
                continue;
            }

            try {
                $t = $this->identificador($tabla);
                $c = $this->identificador($columna);
                $fila = $this->db->fetch(
                    "SELECT COUNT(*) AS total FROM {$t} WHERE {$c} = ?",
                    [$id]
                );
            } catch (PDOException $e) {
                continue;
            }

            $cantidad = (int)($fila['total'] ?? 0);
            if ($cantidad > 0) {
                $total += $cantidad;
                $etiquetas[] = $etiqueta;
            }
        }

        return ['total' => $total, 'etiquetas' => $etiquetas];
    }

    public function contarRolesAdminActivos(?int $exceptoId = null): int
    {
        $sql = "SELECT COUNT(*) AS total FROM roles
                WHERE activo = 1
                  AND LOWER(TRIM(nombre)) IN ('administrador hseq', 'admin')";
        $params = [];

        if ($exceptoId !== null && $exceptoId > 0) {
            $sql .= ' AND role_id <> ?';
            $params[] = $exceptoId;
        }

        $fila = $this->db->fetch($sql, $params);

        return (int)($fila['total'] ?? 0);
    }

    private function identificador(string $nombre): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $nombre)) {
            throw new InvalidArgumentException('Identificador de catalogo invalido.');
        }

        return $nombre;
    }
}
