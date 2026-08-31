<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class PlanAnualRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, ?int $anio, ?string $estado): array
    {
        [$where, $params] = $this->filtros($anio, $estado);

        return $this->db->fetchAll(
            $this->selectPlan() . " {$where}
             ORDER BY p.anio DESC, p.plan_anual_id DESC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    public function contar(?int $anio, ?string $estado): int
    {
        [$where, $params] = $this->filtros($anio, $estado);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM planes_anuales p {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            $this->selectPlan() . ' WHERE p.plan_anual_id = ? LIMIT 1',
            [$id]
        );
    }

    public function buscarPorAnio(int $anio): ?array
    {
        return $this->db->fetch(
            $this->selectPlan() . ' WHERE p.anio = ? LIMIT 1',
            [$anio]
        );
    }

    public function crear(array $datos): int
    {
        return (int)$this->db->insert('planes_anuales', $datos);
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update('planes_anuales', $datos, 'plan_anual_id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function detalles(int $planId): array
    {
        return $this->db->fetchAll(
            'SELECT d.plan_detalle_id,
                    d.plan_anual_id,
                    d.capacitacion_id,
                    d.mes_programado,
                    d.cantidad_programada,
                    d.area_id,
                    d.proceso_id,
                    d.ambito,
                    d.proyecto,
                    c.codigo AS capacitacion_codigo,
                    c.nombre AS capacitacion_nombre,
                    pr.nombre AS proceso_nombre
             FROM plan_anual_detalle d
             INNER JOIN capacitaciones c ON c.capacitacion_id = d.capacitacion_id
             LEFT JOIN procesos pr ON pr.proceso_id = d.proceso_id
             WHERE d.plan_anual_id = ?
             ORDER BY d.mes_programado ASC, c.nombre ASC',
            [$planId]
        );
    }

    public function buscarDetalle(int $planId, int $capacitacionId, int $mes): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM plan_anual_detalle
             WHERE plan_anual_id = ? AND capacitacion_id = ? AND mes_programado = ?
             LIMIT 1',
            [$planId, $capacitacionId, $mes]
        );
    }

    public function crearDetalle(array $datos): int
    {
        return (int)$this->db->insert('plan_anual_detalle', $datos);
    }

    public function actualizarCantidad(int $detalleId, int $cantidad): int
    {
        return $this->db->update(
            'plan_anual_detalle',
            ['cantidad_programada' => $cantidad],
            'plan_detalle_id = ?',
            [$detalleId]
        );
    }

    public function eliminarDetalle(int $detalleId): int
    {
        return $this->db->delete('plan_anual_detalle', 'plan_detalle_id = ?', [$detalleId]);
    }

    public function detalleTieneSesiones(int $detalleId): bool
    {
        $fila = $this->db->fetch(
            'SELECT sesion_id FROM sesiones_capacitacion WHERE plan_detalle_id = ? LIMIT 1',
            [$detalleId]
        );

        return $fila !== null;
    }

    public function contarEnlacesDetalle(int $detalleId): int
    {
        $fila = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM plan_detalle_asignaciones WHERE plan_detalle_id = ?',
            [$detalleId]
        );

        return (int)($fila['total'] ?? 0);
    }

    public function contarDetalles(int $planId): int
    {
        $fila = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM plan_anual_detalle WHERE plan_anual_id = ?',
            [$planId]
        );

        return (int)($fila['total'] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    public function asignacionesDePlan(int $planId): array
    {
        $personas = Database::personalTable('personas');

        return $this->db->fetchAll(
            "SELECT pda.plan_detalle_id,
                    pda.asignacion_id,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    a.origen,
                    a.proceso_id,
                    a.ambito,
                    a.proyecto,
                    a.area_id,
                    cap.codigo AS capacitacion_codigo,
                    cap.nombre AS capacitacion_nombre,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre
             FROM plan_detalle_asignaciones pda
             INNER JOIN plan_anual_detalle d ON d.plan_detalle_id = pda.plan_detalle_id
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = pda.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             WHERE d.plan_anual_id = ?
             ORDER BY pda.asignacion_id ASC",
            [$planId]
        );
    }

    public function enlacePorAsignacion(int $planId, int $asignacionId): ?array
    {
        return $this->db->fetch(
            'SELECT pda.plan_detalle_asignacion_id, pda.plan_detalle_id, pda.asignacion_id, d.mes_programado
             FROM plan_detalle_asignaciones pda
             INNER JOIN plan_anual_detalle d ON d.plan_detalle_id = pda.plan_detalle_id
             WHERE d.plan_anual_id = ? AND pda.asignacion_id = ?
             LIMIT 1',
            [$planId, $asignacionId]
        );
    }

    public function enlazar(int $detalleId, int $asignacionId): int
    {
        return (int)$this->db->insert('plan_detalle_asignaciones', [
            'plan_detalle_id' => $detalleId,
            'asignacion_id' => $asignacionId,
        ]);
    }

    public function desenlazar(int $detalleId, int $asignacionId): int
    {
        return $this->db->delete(
            'plan_detalle_asignaciones',
            'plan_detalle_id = ? AND asignacion_id = ?',
            [$detalleId, $asignacionId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function asignacionesDisponibles(int $planId, ?string $buscar, int $limite): array
    {
        $personas = Database::personalTable('personas');
        $sql = "SELECT a.asignacion_id,
                       a.persona_id_ext,
                       a.capacitacion_id,
                       a.origen,
                       a.area_id,
                       a.proceso_id,
                       a.ambito,
                       a.proyecto,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre
                FROM asignaciones_capacitacion a
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM plan_detalle_asignaciones pda
                    INNER JOIN plan_anual_detalle d ON d.plan_detalle_id = pda.plan_detalle_id
                    WHERE pda.asignacion_id = a.asignacion_id
                      AND d.plan_anual_id = ?
                )";
        $params = [$planId];

        if ($buscar !== null && $buscar !== '') {
            $sql .= ' AND (per.nombre_completo_nombres_primero LIKE ?
                OR per.numero_documento LIKE ?
                OR cap.codigo LIKE ?
                OR cap.nombre LIKE ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY cap.codigo ASC, per.nombre_completo_nombres_primero ASC LIMIT ' . $limite;

        return $this->db->fetchAll($sql, $params);
    }

    public function buscarAsignacion(int $asignacionId): ?array
    {
        return $this->db->fetch(
            'SELECT asignacion_id, persona_id_ext, capacitacion_id, origen,
                    area_id, proceso_id, ambito, proyecto
             FROM asignaciones_capacitacion
             WHERE asignacion_id = ?
             LIMIT 1',
            [$asignacionId]
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
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function selectPlan(): string
    {
        return 'SELECT p.plan_anual_id,
                       p.anio,
                       p.estado,
                       p.aprobado_por_usuario_id_ext,
                       p.fecha_aprobacion,
                       p.creado_por_usuario_id_ext,
                       p.created_at,
                       p.updated_at,
                       COALESCE((
                           SELECT SUM(d.cantidad_programada)
                           FROM plan_anual_detalle d
                           WHERE d.plan_anual_id = p.plan_anual_id
                       ), 0) AS total_programadas
                FROM planes_anuales p';
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(?int $anio, ?string $estado): array
    {
        $condiciones = [];
        $params = [];

        if ($anio !== null && $anio > 0) {
            $condiciones[] = 'p.anio = ?';
            $params[] = $anio;
        }

        if ($estado !== null && $estado !== '') {
            $condiciones[] = 'p.estado = ?';
            $params[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
