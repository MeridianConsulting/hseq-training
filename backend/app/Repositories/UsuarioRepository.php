<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class UsuarioRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, ?string $buscar, ?string $estado): array
    {
        [$where, $params] = $this->filtros($buscar, $estado);
        $sql = "SELECT u.usuario_id, u.nombre_usuario, u.correo, u.rol, u.estado,
                       u.ultimo_acceso, u.created_at, u.updated_at
                FROM usuarios u
                {$where}
                ORDER BY u.nombre_usuario ASC
                LIMIT {$limite} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    public function contar(?string $buscar, ?string $estado): int
    {
        [$where, $params] = $this->filtros($buscar, $estado);
        $fila = $this->db->fetch("SELECT COUNT(*) AS total FROM usuarios u {$where}", $params);

        return (int)($fila['total'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public function buscarPorId(int $usuarioId): ?array
    {
        return $this->db->fetch(
            'SELECT usuario_id, nombre_usuario, correo, rol, estado,
                    intentos_fallidos, bloqueado_hasta, ultimo_acceso, created_at, updated_at
             FROM usuarios
             WHERE usuario_id = ?
             LIMIT 1',
            [$usuarioId]
        );
    }

    public function existeNombreOCorreo(string $nombreUsuario, string $correo, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT usuario_id FROM usuarios
                WHERE (nombre_usuario = ? OR correo = ?)';
        $params = [$nombreUsuario, $correo];
        if ($exceptoId !== null && $exceptoId > 0) {
            $sql .= ' AND usuario_id <> ?';
            $params[] = $exceptoId;
        }
        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params) !== null;
    }

    /**
     * @param array{nombre_usuario:string,correo:string,password_hash:string,rol:string,estado:string} $datos
     */
    public function crear(array $datos): int
    {
        return $this->db->insert('usuarios', $datos);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function actualizar(int $usuarioId, array $datos): void
    {
        if ($datos === []) {
            return;
        }
        $this->db->update('usuarios', $datos, 'usuario_id = ?', [$usuarioId]);
    }

    public function asegurarRolAdministradorHseq(): int
    {
        $this->db->query("INSERT IGNORE INTO roles (nombre) VALUES ('Administrador HSEQ')");
        $fila = $this->db->fetch(
            "SELECT role_id FROM roles WHERE nombre = 'Administrador HSEQ' LIMIT 1"
        );

        return (int)($fila['role_id'] ?? 0);
    }

    public function sincronizarRolPrincipal(int $usuarioId, int $roleId): void
    {
        if ($roleId < 1) {
            return;
        }
        $this->db->query('DELETE FROM user_roles WHERE usuario_id = ?', [$usuarioId]);
        $this->db->insert('user_roles', [
            'usuario_id' => $usuarioId,
            'role_id' => $roleId,
        ]);
    }

    /** @return list<array{role_id:int,nombre:string}> */
    public function rolesDeUsuario(int $usuarioId): array
    {
        $filas = $this->db->fetchAll(
            'SELECT r.role_id, r.nombre
             FROM user_roles ur
             INNER JOIN roles r ON r.role_id = ur.role_id
             WHERE ur.usuario_id = ?
             ORDER BY r.nombre ASC',
            [$usuarioId]
        );

        return array_map(static function (array $fila): array {
            return [
                'role_id' => (int)$fila['role_id'],
                'nombre' => (string)$fila['nombre'],
            ];
        }, $filas);
    }

    public function contarAdministradoresActivos(?int $exceptoId = null): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM usuarios u
                WHERE u.estado = 'Activo'
                  AND (
                    LOWER(TRIM(u.rol)) IN ('admin', 'administrador', 'administrador hseq')
                    OR EXISTS (
                      SELECT 1 FROM user_roles ur
                      INNER JOIN roles r ON r.role_id = ur.role_id
                      WHERE ur.usuario_id = u.usuario_id
                        AND LOWER(TRIM(r.nombre)) IN ('administrador hseq', 'admin')
                    )
                  )";
        $params = [];
        if ($exceptoId !== null && $exceptoId > 0) {
            $sql .= ' AND u.usuario_id <> ?';
            $params[] = $exceptoId;
        }
        $fila = $this->db->fetch($sql, $params);

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(?string $buscar, ?string $estado): array
    {
        $condiciones = [];
        $params = [];
        if ($buscar !== null && $buscar !== '') {
            $condiciones[] = '(u.nombre_usuario LIKE ? OR u.correo LIKE ?)';
            $like = '%' . $buscar . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($estado !== null && $estado !== '' && in_array($estado, ['Activo', 'Inactivo'], true)) {
            $condiciones[] = 'u.estado = ?';
            $params[] = $estado;
        }
        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $params];
    }
}
