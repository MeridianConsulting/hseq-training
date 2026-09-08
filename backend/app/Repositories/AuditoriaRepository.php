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

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);

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

    /** @param array<string,mixed> $filtros */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM auditoria a
             LEFT JOIN usuarios u ON u.usuario_id = a.usuario_id_ext
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $entidad = trim((string)($filtros['entidad'] ?? ''));
        if ($entidad !== '') {
            $condiciones[] = 'a.entidad = ?';
            $params[] = $entidad;
        }

        $grupos = $filtros['modulo_grupos'] ?? [];
        if (is_array($grupos) && $grupos !== [] && $entidad === '') {
            $orModulo = [];
            foreach ($grupos as $grupo) {
                if (!is_array($grupo)) {
                    continue;
                }
                $ents = [];
                foreach ($grupo['entidades'] ?? [] as $e) {
                    if (is_string($e) && $e !== '') {
                        $ents[] = $e;
                    }
                }
                if ($ents === []) {
                    continue;
                }
                $ph = implode(', ', array_fill(0, count($ents), '?'));
                $parte = "a.entidad IN ({$ph})";
                $params = array_merge($params, $ents);
                $acciones = $grupo['acciones'] ?? null;
                if (is_array($acciones) && $acciones !== []) {
                    $clean = [];
                    foreach ($acciones as $ac) {
                        if (is_string($ac) && $ac !== '') {
                            $clean[] = $ac;
                        }
                    }
                    if ($clean !== []) {
                        $phA = implode(', ', array_fill(0, count($clean), '?'));
                        $parte = "({$parte} AND a.accion IN ({$phA}))";
                        $params = array_merge($params, $clean);
                    }
                }
                $orModulo[] = $parte;
            }
            if ($orModulo !== []) {
                $condiciones[] = '(' . implode(' OR ', $orModulo) . ')';
            }
        }

        $accion = trim((string)($filtros['accion'] ?? ''));
        if ($accion !== '') {
            $condiciones[] = 'a.accion = ?';
            $params[] = $accion;
        }

        $usuarioId = (int)($filtros['usuario_id'] ?? 0);
        if ($usuarioId > 0) {
            $condiciones[] = 'a.usuario_id_ext = ?';
            $params[] = $usuarioId;
        }

        $usuario = trim((string)($filtros['usuario'] ?? ''));
        if ($usuario !== '') {
            $condiciones[] = '(a.usuario_nombre LIKE ? OR u.nombre_usuario LIKE ?)';
            $like = '%' . $usuario . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $entidadId = (int)($filtros['entidad_id'] ?? 0);
        if ($entidadId > 0) {
            $condiciones[] = 'a.entidad_id = ?';
            $params[] = $entidadId;
        }

        $desde = trim((string)($filtros['desde'] ?? ''));
        if ($desde !== '') {
            $condiciones[] = 'a.created_at >= ?';
            $params[] = strlen($desde) === 10 ? $desde . ' 00:00:00' : $desde;
        }

        $hasta = trim((string)($filtros['hasta'] ?? ''));
        if ($hasta !== '') {
            $condiciones[] = 'a.created_at <= ?';
            $params[] = strlen($hasta) === 10 ? $hasta . ' 23:59:59' : $hasta;
        }

        $q = trim((string)($filtros['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $orQ = '(a.usuario_nombre LIKE ? OR u.nombre_usuario LIKE ? OR a.accion LIKE ? OR a.entidad LIKE ?';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            if (ctype_digit($q)) {
                $orQ .= ' OR a.entidad_id = ?';
                $params[] = (int)$q;
            }
            $orQ .= ')';
            $condiciones[] = $orQ;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
