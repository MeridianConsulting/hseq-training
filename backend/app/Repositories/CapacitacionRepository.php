<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class CapacitacionRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);

        return $this->db->fetchAll(
            $this->selectBase() . " {$where} ORDER BY c.codigo ASC LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /** @param array<string,mixed> $filtros */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM capacitaciones c {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE c.capacitacion_id = ? LIMIT 1',
            [$id]
        );
    }

    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE c.codigo = ? LIMIT 1',
            [$codigo]
        );
    }

    public function codigoDuplicado(string $codigo, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT capacitacion_id FROM capacitaciones WHERE codigo = ?';
        $params = [$codigo];

        if ($exceptoId !== null) {
            $sql .= ' AND capacitacion_id <> ?';
            $params[] = $exceptoId;
        }

        return $this->db->fetch($sql, $params) !== null;
    }

    public function catalogoExiste(string $tabla, string $pk, int $id): bool
    {
        return $this->db->fetch("SELECT {$pk} FROM {$tabla} WHERE {$pk} = ? LIMIT 1", [$id]) !== null;
    }

    public function catalogoActivo(string $tabla, string $pk, int $id): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla) || !preg_match('/^[A-Za-z0-9_]+$/', $pk)) {
            return false;
        }

        $fila = $this->db->fetch(
            "SELECT activo FROM {$tabla} WHERE {$pk} = ? LIMIT 1",
            [$id]
        );

        return $fila !== null && (int)($fila['activo'] ?? 0) === 1;
    }

    public function crear(array $datos): int
    {
        return (int)$this->db->insert('capacitaciones', $datos);
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update('capacitaciones', $datos, 'capacitacion_id = ?', [$id]);
    }

    public function eliminar(int $id): int
    {
        return $this->db->delete('capacitaciones', 'capacitacion_id = ?', [$id]);
    }

    public function inactivar(int $id): int
    {
        return $this->db->update(
            'capacitaciones',
            ['estado' => 'INACTIVA'],
            'capacitacion_id = ?',
            [$id]
        );
    }

    /**
     * @param callable():mixed $operacion
     */
    public function transaccion(callable $operacion): mixed
    {
        return $this->db->transaccion($operacion);
    }

    /**
     * Cursos ACTIVA cuyo tipo normalizado es INDUCCION o REINDUCCION.
     *
     * @return list<array{
     *   capacitacion_id:int,
     *   codigo:string,
     *   nombre:string,
     *   origen:string,
     *   per_cantidad:?int,
     *   per_unidad:?string
     * }>
     */
    public function activasInduccionReinduccion(): array
    {
        $filas = $this->db->fetchAll(
            $this->selectBase() . " WHERE c.estado = 'ACTIVA' AND tip.nombre IS NOT NULL"
        );

        $salida = [];
        foreach ($filas as $fila) {
            $origen = $this->origenEspecial($fila['tipo_nombre'] ?? null);
            if ($origen === null) {
                continue;
            }

            $salida[] = [
                'capacitacion_id' => (int)$fila['capacitacion_id'],
                'codigo' => (string)$fila['codigo'],
                'nombre' => (string)$fila['nombre'],
                'origen' => $origen,
                'per_cantidad' => $fila['periodicidad_cantidad'] !== null ? (int)$fila['periodicidad_cantidad'] : null,
                'per_unidad' => $fila['periodicidad_unidad'] !== null ? (string)$fila['periodicidad_unidad'] : null,
            ];
        }

        return $salida;
    }

    public static function normalizarTipoNombre(?string $nombre): string
    {
        $texto = strtoupper(trim((string)$nombre));
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U',
        ]);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    private function origenEspecial(mixed $nombre): ?string
    {
        $tipo = self::normalizarTipoNombre(is_string($nombre) ? $nombre : null);
        if ($tipo === 'INDUCCION' || $tipo === 'REINDUCCION') {
            return $tipo;
        }

        return null;
    }

    private function selectBase(): string
    {
        return 'SELECT c.*,
                    cat.nombre AS categoria_nombre,
                    tip.nombre AS tipo_nombre,
                    per.nombre AS periodicidad_nombre,
                    per.cantidad AS periodicidad_cantidad,
                    per.unidad AS periodicidad_unidad,
                    vig.nombre AS vigencia_nombre,
                    vig.cantidad AS vigencia_cantidad,
                    vig.unidad AS vigencia_unidad,
                    md.nombre AS modalidad_nombre,
                    prv.nombre AS proveedor_nombre,
                    fte.nombre AS fuente_normativa_nombre
                FROM capacitaciones c
                LEFT JOIN categorias_capacitacion cat ON cat.categoria_id = c.categoria_id
                LEFT JOIN tipos_capacitacion tip ON tip.tipo_capacitacion_id = c.tipo_capacitacion_id
                LEFT JOIN periodicidades per ON per.periodicidad_id = c.periodicidad_default_id
                LEFT JOIN vigencias vig ON vig.vigencia_id = c.vigencia_id
                LEFT JOIN modalidades md ON md.modalidad_id = c.modalidad_default_id
                LEFT JOIN proveedores_capacitadores prv ON prv.proveedor_id = c.proveedor_default_id
                LEFT JOIN fuentes_normativas fte ON fte.fuente_normativa_id = c.fuente_normativa_id';
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $buscar = isset($filtros['buscar']) ? trim((string)$filtros['buscar']) : '';
        if ($buscar !== '') {
            $condiciones[] = '(c.codigo LIKE ? OR c.nombre LIKE ?)';
            $like = '%' . $buscar . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $estado = isset($filtros['estado']) ? trim((string)$filtros['estado']) : '';
        if ($estado !== '') {
            $condiciones[] = 'c.estado = ?';
            $params[] = $estado;
        }

        $tipoId = isset($filtros['tipo_capacitacion_id']) ? (int)$filtros['tipo_capacitacion_id'] : 0;
        if ($tipoId > 0) {
            $condiciones[] = 'c.tipo_capacitacion_id = ?';
            $params[] = $tipoId;
        }

        $modalidadId = isset($filtros['modalidad_default_id']) ? (int)$filtros['modalidad_default_id'] : 0;
        if ($modalidadId > 0) {
            $condiciones[] = 'c.modalidad_default_id = ?';
            $params[] = $modalidadId;
        }

        if (array_key_exists('es_tarea_critica', $filtros) && $filtros['es_tarea_critica'] !== null && $filtros['es_tarea_critica'] !== '') {
            $condiciones[] = 'c.es_tarea_critica = ?';
            $params[] = (int)$filtros['es_tarea_critica'] === 1 ? 1 : 0;
        }

        if (array_key_exists('evaluacion', $filtros) && $filtros['evaluacion'] !== null && $filtros['evaluacion'] !== '') {
            $condiciones[] = 'c.evaluacion = ?';
            $params[] = (int)$filtros['evaluacion'] === 1 ? 1 : 0;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
