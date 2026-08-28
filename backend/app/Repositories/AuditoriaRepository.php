<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class AuditoriaRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function registrar(
        ?int $usuarioId,
        ?string $usuarioNombre,
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        ?string $valorAnterior,
        ?string $valorNuevo,
        ?string $ip
    ): int {
        return (int)$this->db->insert('auditoria', [
            'usuario_id_ext' => $usuarioId,
            'usuario_nombre' => $usuarioNombre ?? '',
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => $valorNuevo,
            'ip_origen' => $ip,
        ]);
    }

    public function listar(int $limite, int $offset, ?string $entidad, ?string $accion): array
    {
        [$where, $params] = $this->filtros($entidad, $accion);

        return $this->db->fetchAll(
            "SELECT a.auditoria_id, a.usuario_id_ext, a.usuario_nombre, a.accion, a.entidad, a.entidad_id,
                    a.valor_anterior, a.valor_nuevo, a.ip_origen, a.created_at,
                    u.nombre_usuario
             FROM auditoria a
             LEFT JOIN usuarios u ON u.usuario_id = a.usuario_id_ext
             {$where}
             ORDER BY a.auditoria_id DESC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    public function contar(?string $entidad, ?string $accion): int
    {
        [$where, $params] = $this->filtros($entidad, $accion);
        $fila = $this->db->fetch("SELECT COUNT(*) AS total FROM auditoria a {$where}", $params);

        return (int)($fila['total'] ?? 0);
    }

    /** @return array{0:string,1:list<mixed>} */
    private function filtros(?string $entidad, ?string $accion): array
    {
        $condiciones = [];
        $params = [];

        if ($entidad !== null && $entidad !== '') {
            $condiciones[] = 'a.entidad = ?';
            $params[] = $entidad;
        }

        if ($accion !== null && $accion !== '') {
            $condiciones[] = 'a.accion = ?';
            $params[] = $accion;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
