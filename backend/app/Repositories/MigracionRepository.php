<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class MigracionRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function crear(array $datos): int
    {
        return (int)$this->db->insert('migraciones', $datos);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM migraciones WHERE migracion_id = ? LIMIT 1', [$id]);
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update('migraciones', $datos, 'migracion_id = ?', [$id]);
    }

    /**
     * @param callable():mixed $operacion
     */
    public function transaccion(callable $operacion): mixed
    {
        return $this->db->transaccion($operacion);
    }

    public function listarProcesos(): array
    {
        return $this->db->fetchAll('SELECT proceso_id, nombre FROM procesos');
    }

    public function listarModalidades(): array
    {
        return $this->db->fetchAll('SELECT modalidad_id, nombre FROM modalidades WHERE activo = 1');
    }
}
