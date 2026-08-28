<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use Throwable;

class AuthRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarPorIdentificador(string $identificador): ?array
    {
        return $this->db->fetch(
            'SELECT usuario_id, nombre_usuario, correo, password_hash,
                    rol, estado, intentos_fallidos, bloqueado_hasta, ultimo_acceso
             FROM usuarios
             WHERE correo = ? OR nombre_usuario = ?
             LIMIT 1',
            [$identificador, $identificador]
        );
    }

    public function buscarPorId(int $usuarioId): ?array
    {
        return $this->db->fetch(
            'SELECT usuario_id, nombre_usuario, correo,
                    rol, estado, intentos_fallidos, bloqueado_hasta, ultimo_acceso
             FROM usuarios
             WHERE usuario_id = ?
             LIMIT 1',
            [$usuarioId]
        );
    }

    public function registrarIntentoFallido(int $usuarioId, int $intentos, ?string $bloqueadoHasta): void
    {
        $this->db->update(
            'usuarios',
            [
                'intentos_fallidos' => $intentos,
                'bloqueado_hasta' => $bloqueadoHasta,
            ],
            'usuario_id = ?',
            [$usuarioId]
        );
    }

    public function registrarAccesoExitoso(int $usuarioId): void
    {
        $this->db->update(
            'usuarios',
            [
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
                'ultimo_acceso' => date('Y-m-d H:i:s'),
            ],
            'usuario_id = ?',
            [$usuarioId]
        );
    }

    public function rolesHseq(int $usuarioId): array
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
                'nombre' => $fila['nombre'],
            ];
        }, $filas);
    }

    /**
     * En esta etapa el unico rol operativo es Administrador HSEQ: recibe todos
     * los codigos de config/permisos.php. Si existen tablas permisos/rol_permisos
     * se leen, pero su ausencia no debe romper el login.
     *
     * @return list<string>
     */
    public function permisos(int $usuarioId): array
    {
        $catalogo = config('permisos.codigos', []);
        $catalogo = is_array($catalogo) ? array_values(array_map('strval', $catalogo)) : [];

        if ($this->esAdministrador($usuarioId)) {
            return $catalogo;
        }

        return $this->permisosDesdeTablas($usuarioId);
    }

    public function esAdministrador(int $usuarioId): bool
    {
        $usuario = $this->buscarPorId($usuarioId);
        $rolColumna = strtolower(trim((string)($usuario['rol'] ?? '')));

        if (in_array($rolColumna, ['admin', 'administrador', 'administrador hseq'], true)) {
            return true;
        }

        foreach ($this->rolesHseq($usuarioId) as $rol) {
            $nombre = strtolower(trim((string)($rol['nombre'] ?? '')));
            if ($nombre === 'administrador hseq' || $nombre === 'admin') {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function permisosDesdeTablas(int $usuarioId): array
    {
        try {
            $filas = $this->db->fetchAll(
                'SELECT DISTINCT p.codigo
                 FROM user_roles ur
                 INNER JOIN rol_permisos rp ON rp.role_id = ur.role_id
                 INNER JOIN permisos p ON p.permiso_id = rp.permiso_id
                 WHERE ur.usuario_id = ?
                 ORDER BY p.codigo ASC',
                [$usuarioId]
            );

            return array_map(static fn (array $fila): string => (string)$fila['codigo'], $filas);
        } catch (Throwable $e) {
            return [];
        }
    }
}
