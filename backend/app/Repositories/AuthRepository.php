<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

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
            'SELECT usuario_id, nombre_usuario, nombre_completo, correo, password_hash,
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
            'SELECT usuario_id, nombre_usuario, nombre_completo, correo,
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
}
