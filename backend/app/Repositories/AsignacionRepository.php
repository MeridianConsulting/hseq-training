<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class AsignacionRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(
        int $limite,
        int $offset,
        ?int $personaId,
        ?int $capacitacionId,
        ?string $estado,
        ?string $alerta,
        ?string $buscar
    ): array {
        [$where, $params] = $this->filtros($personaId, $capacitacionId, $estado, $alerta, $buscar);

        return $this->db->fetchAll(
            $this->selectBase() . " {$where}
             ORDER BY a.fecha_limite_cumplimiento ASC, a.asignacion_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    public function contar(
        ?int $personaId,
        ?int $capacitacionId,
        ?string $estado,
        ?string $alerta,
        ?string $buscar
    ): int {
        [$where, $params] = $this->filtros($personaId, $capacitacionId, $estado, $alerta, $buscar);
        $personas = Database::personalTable('personas');
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM asignaciones_capacitacion a
             INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    public function proximasPendientes(): array
    {
        return $this->db->fetchAll(
            $this->selectBase() . "
             WHERE e.estado_calculado = 'PENDIENTE_PROXIMA_A_VENCER'
             ORDER BY a.fecha_limite_cumplimiento ASC, a.asignacion_id ASC"
        );
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE a.asignacion_id = ? LIMIT 1',
            [$id]
        );
    }

    public function pendienteDuplicada(int $personaId, int $capacitacionId, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT a.asignacion_id
                FROM asignaciones_capacitacion a
                LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
                WHERE a.persona_id_ext = ?
                  AND a.capacitacion_id = ?
                  AND c.cumplimiento_id IS NULL';
        $params = [$personaId, $capacitacionId];

        if ($exceptoId !== null) {
            $sql .= ' AND a.asignacion_id <> ?';
            $params[] = $exceptoId;
        }

        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params) !== null;
    }

    public function tieneCumplimiento(int $asignacionId): bool
    {
        $fila = $this->db->fetch(
            'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ? LIMIT 1',
            [$asignacionId]
        );

        return $fila !== null;
    }

    public function crear(array $datos): int
    {
        return (int)$this->db->insert('asignaciones_capacitacion', $datos);
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update('asignaciones_capacitacion', $datos, 'asignacion_id = ?', [$id]);
    }

    public function eliminar(int $id): int
    {
        return $this->db->delete('asignaciones_capacitacion', 'asignacion_id = ?', [$id]);
    }

    private function selectBase(): string
    {
        $personas = Database::personalTable('personas');

        return "SELECT a.asignacion_id,
                       a.persona_id_ext,
                       a.contrato_id_ext,
                       a.capacitacion_id,
                       a.matriz_aplicabilidad_id,
                       a.fecha_asignacion,
                       a.fecha_limite_cumplimiento,
                       a.origen,
                       a.cargo_id_ext,
                       a.area_id,
                       a.proceso_id,
                       a.ambito,
                       a.proyecto,
                       e.estado_calculado,
                       e.cumplimiento_id,
                       DATEDIFF(a.fecha_limite_cumplimiento, CURDATE()) AS dias_restantes,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre
                FROM asignaciones_capacitacion a
                INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext";
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(
        ?int $personaId,
        ?int $capacitacionId,
        ?string $estado,
        ?string $alerta,
        ?string $buscar
    ): array {
        $condiciones = [];
        $params = [];

        if ($personaId !== null && $personaId > 0) {
            $condiciones[] = 'a.persona_id_ext = ?';
            $params[] = $personaId;
        }

        if ($buscar !== null && $buscar !== '') {
            $condiciones[] = '(per.nombre_completo_nombres_primero LIKE ?
                OR per.numero_documento LIKE ?
                OR CAST(a.persona_id_ext AS CHAR) = ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $buscar);
        }

        if ($capacitacionId !== null && $capacitacionId > 0) {
            $condiciones[] = 'a.capacitacion_id = ?';
            $params[] = $capacitacionId;
        }

        if ($alerta === 'proximas') {
            $condiciones[] = "e.estado_calculado = 'PENDIENTE_PROXIMA_A_VENCER'";
        } elseif ($alerta === 'vencidas') {
            $condiciones[] = "e.estado_calculado = 'PENDIENTE_VENCIDA'";
        } elseif ($estado !== null && $estado !== '') {
            $condiciones[] = 'e.estado_calculado = ?';
            $params[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
