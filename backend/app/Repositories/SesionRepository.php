<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class SesionRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function detallePlan(int $planDetalleId): ?array
    {
        return $this->db->fetch(
            'SELECT d.plan_detalle_id,
                    d.plan_anual_id,
                    d.capacitacion_id,
                    d.mes_programado,
                    p.anio,
                    p.estado AS plan_estado,
                    c.codigo AS capacitacion_codigo,
                    c.nombre AS capacitacion_nombre,
                    c.modalidad_default_id,
                    c.proveedor_default_id
             FROM plan_anual_detalle d
             INNER JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
             INNER JOIN capacitaciones c ON c.capacitacion_id = d.capacitacion_id
             WHERE d.plan_detalle_id = ?
             LIMIT 1',
            [$planDetalleId]
        );
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            $this->selectSesion() . ' WHERE s.sesion_id = ? LIMIT 1',
            [$id]
        );
    }

    public function bloquearPorId(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT sesion_id, plan_detalle_id, capacitacion_id, cupo_maximo, estado
             FROM sesiones_capacitacion
             WHERE sesion_id = ?
             FOR UPDATE',
            [$id]
        );
    }

    /**
     * @param list<int> $detalleIds
     * @return list<array<string,mixed>>
     */
    public function listarPorDetalles(array $detalleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $detalleIds))));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        return $this->db->fetchAll(
            $this->selectSesion() . "
             WHERE s.plan_detalle_id IN ({$in})
             ORDER BY s.fecha_hora ASC, s.sesion_id ASC",
            $ids
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorDetalle(int $planDetalleId): array
    {
        return $this->listarPorDetalles([$planDetalleId]);
    }

    public function crear(array $datos): int
    {
        return (int)$this->db->insert('sesiones_capacitacion', $datos);
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update('sesiones_capacitacion', $datos, 'sesion_id = ?', [$id]);
    }

    public function contarParticipantes(int $sesionId): int
    {
        $fila = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM sesion_participantes WHERE sesion_id = ?',
            [$sesionId]
        );

        return (int)($fila['total'] ?? 0);
    }

    /** @return list<int> */
    public function idsParticipantes(int $sesionId): array
    {
        $filas = $this->db->fetchAll(
            'SELECT asignacion_id FROM sesion_participantes WHERE sesion_id = ?',
            [$sesionId]
        );

        return array_map(static fn (array $fila): int => (int)$fila['asignacion_id'], $filas);
    }

    /** @return list<array<string,mixed>> */
    public function participantes(int $sesionId): array
    {
        $personas = Database::personalTable('personas');

        return $this->db->fetchAll(
            "SELECT sp.sesion_participante_id,
                    sp.sesion_id,
                    sp.asignacion_id,
                    sp.estado_asistencia,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    per.estado AS persona_estado
             FROM sesion_participantes sp
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = sp.asignacion_id
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             WHERE sp.sesion_id = ?
             ORDER BY per.nombre_completo_nombres_primero ASC, sp.asignacion_id ASC",
            [$sesionId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function convocables(int $capacitacionId, int $planDetalleId, ?int $sesionId, ?string $buscar): array
    {
        $personas = Database::personalTable('personas');
        $params = [$planDetalleId, $capacitacionId];
        $filtroSesion = '';
        if ($sesionId !== null && $sesionId > 0) {
            $filtroSesion = 'AND NOT EXISTS (
                SELECT 1 FROM sesion_participantes sp
                WHERE sp.sesion_id = ? AND sp.asignacion_id = a.asignacion_id
            )';
            $params[] = $sesionId;
        }

        $filtroBuscar = '';
        if ($buscar !== null && $buscar !== '') {
            $filtroBuscar = 'AND (per.nombre_completo_nombres_primero LIKE ? OR per.numero_documento LIKE ?)';
            $like = '%' . $buscar . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return $this->db->fetchAll(
            "SELECT a.asignacion_id,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    a.origen,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    per.estado AS persona_estado,
                    CASE WHEN pda.plan_detalle_asignacion_id IS NULL THEN 0 ELSE 1 END AS en_plan
             FROM asignaciones_capacitacion a
             INNER JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             LEFT JOIN plan_detalle_asignaciones pda
                    ON pda.asignacion_id = a.asignacion_id
                   AND pda.plan_detalle_id = ?
             WHERE a.capacitacion_id = ?
               AND per.estado = 'Activo'
               {$filtroSesion}
               {$filtroBuscar}
             ORDER BY en_plan DESC, per.nombre_completo_nombres_primero ASC, a.asignacion_id ASC",
            $params
        );
    }

    public function buscarAsignacion(int $asignacionId): ?array
    {
        $personas = Database::personalTable('personas');

        return $this->db->fetch(
            "SELECT a.asignacion_id,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    per.estado AS persona_estado,
                    per.nombre_completo_nombres_primero AS persona_nombre
             FROM asignaciones_capacitacion a
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             WHERE a.asignacion_id = ?
             LIMIT 1",
            [$asignacionId]
        );
    }

    /**
     * @param list<int> $asignacionIds
     */
    public function insertarParticipantes(int $sesionId, array $asignacionIds, ?int $usuarioId): int
    {
        $insertados = 0;
        foreach ($asignacionIds as $asignacionId) {
            $this->db->insert('sesion_participantes', [
                'sesion_id' => $sesionId,
                'asignacion_id' => $asignacionId,
                'registrado_por_usuario_id_ext' => $usuarioId,
            ]);
            $insertados++;
        }

        return $insertados;
    }

    public function eliminarParticipante(int $sesionId, int $asignacionId): int
    {
        return $this->db->delete(
            'sesion_participantes',
            'sesion_id = ? AND asignacion_id = ?',
            [$sesionId, $asignacionId]
        );
    }

    public function catalogoActivo(string $tabla, string $pk, int $id): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla) || !preg_match('/^[A-Za-z0-9_]+$/', $pk)) {
            return null;
        }

        return $this->db->fetch(
            "SELECT {$pk}, nombre, activo FROM {$tabla} WHERE {$pk} = ? LIMIT 1",
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public function catalogoListar(string $tabla, string $pk): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla) || !preg_match('/^[A-Za-z0-9_]+$/', $pk)) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT {$pk}, nombre FROM {$tabla} WHERE activo = 1 ORDER BY nombre ASC"
        );
    }

    /**
     * @param callable():mixed $operacion
     */
    public function transaccion(callable $operacion): mixed
    {
        $this->db->beginTransaction();

        try {
            $resultado = $operacion();
            $this->db->commit();

            return $resultado;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function selectSesion(): string
    {
        return 'SELECT s.sesion_id,
                       s.plan_detalle_id,
                       s.capacitacion_id,
                       s.fecha_hora,
                       s.modalidad_id,
                       s.ubicacion_id,
                       s.enlace_virtual,
                       s.proveedor_id,
                       s.cupo_maximo,
                       s.creado_por_usuario_id_ext,
                       s.observaciones,
                       s.estado,
                       s.created_at,
                       s.updated_at,
                       c.codigo AS capacitacion_codigo,
                       c.nombre AS capacitacion_nombre,
                       p.plan_anual_id,
                       p.anio,
                       p.estado AS plan_estado,
                       mo.nombre AS modalidad_nombre,
                       ub.nombre AS ubicacion_nombre,
                       prv.nombre AS proveedor_nombre,
                       (SELECT COUNT(*) FROM sesion_participantes sp WHERE sp.sesion_id = s.sesion_id) AS convocados
                FROM sesiones_capacitacion s
                INNER JOIN capacitaciones c ON c.capacitacion_id = s.capacitacion_id
                LEFT JOIN plan_anual_detalle d ON d.plan_detalle_id = s.plan_detalle_id
                LEFT JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
                LEFT JOIN modalidades mo ON mo.modalidad_id = s.modalidad_id
                LEFT JOIN ubicaciones ub ON ub.ubicacion_id = s.ubicacion_id
                LEFT JOIN proveedores_capacitadores prv ON prv.proveedor_id = s.proveedor_id';
    }
}
