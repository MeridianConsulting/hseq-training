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
        ?string $buscar,
        ?string $origen = null,
        ?int $procesoId = null,
        ?string $proyecto = null,
        ?string $fechaLimiteDesde = null,
        ?string $fechaLimiteHasta = null
    ): array {
        [$where, $params] = $this->filtros(
            $personaId,
            $capacitacionId,
            $estado,
            $alerta,
            $buscar,
            $origen,
            $procesoId,
            $proyecto,
            $fechaLimiteDesde,
            $fechaLimiteHasta
        );

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
        ?string $buscar,
        ?string $origen = null,
        ?int $procesoId = null,
        ?string $proyecto = null,
        ?string $fechaLimiteDesde = null,
        ?string $fechaLimiteHasta = null
    ): int {
        [$where, $params] = $this->filtros(
            $personaId,
            $capacitacionId,
            $estado,
            $alerta,
            $buscar,
            $origen,
            $procesoId,
            $proyecto,
            $fechaLimiteDesde,
            $fechaLimiteHasta
        );
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
             WHERE e.estado_calculado COLLATE utf8mb4_unicode_ci = 'PENDIENTE_PROXIMA_A_VENCER'
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

    public function buscarPorPersonaYCapacitacion(int $personaId, int $capacitacionId): ?array
    {
        return $this->db->fetch(
            'SELECT asignacion_id, persona_id_ext, capacitacion_id, origen, matriz_aplicabilidad_id
             FROM asignaciones_capacitacion
             WHERE persona_id_ext = ? AND capacitacion_id = ?
             ORDER BY asignacion_id DESC
             LIMIT 1',
            [$personaId, $capacitacionId]
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

    /**
     * Mapa "personaId:capacitacionId" de asignaciones pendientes (sin cumplimiento).
     *
     * @return array<string, true>
     */
    public function paresPendientes(?int $personaId = null): array
    {
        $sql = 'SELECT a.persona_id_ext, a.capacitacion_id
                FROM asignaciones_capacitacion a
                LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
                WHERE c.cumplimiento_id IS NULL';
        $params = [];
        if ($personaId !== null && $personaId > 0) {
            $sql .= ' AND a.persona_id_ext = ?';
            $params[] = $personaId;
        }

        $filas = $this->db->fetchAll($sql, $params);

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int)$fila['persona_id_ext'] . ':' . (int)$fila['capacitacion_id']] = true;
        }

        return $mapa;
    }

    /**
     * Mapa "personaId:capacitacionId" de cualquier asignación (pendiente o con cumplimiento).
     *
     * @return array<string, true>
     */
    public function paresExistentes(?int $personaId = null): array
    {
        $sql = 'SELECT a.persona_id_ext, a.capacitacion_id FROM asignaciones_capacitacion a';
        $params = [];
        if ($personaId !== null && $personaId > 0) {
            $sql .= ' WHERE a.persona_id_ext = ?';
            $params[] = $personaId;
        }

        $mapa = [];
        foreach ($this->db->fetchAll($sql, $params) as $fila) {
            $mapa[(int)$fila['persona_id_ext'] . ':' . (int)$fila['capacitacion_id']] = true;
        }

        return $mapa;
    }

    /**
     * Última fecha_vencimiento por persona+capacitación (cumplimiento más reciente).
     *
     * @return array<string, string|null>
     */
    public function ultimasFechasVencimiento(?int $personaId = null): array
    {
        $sql = 'SELECT a.persona_id_ext, a.capacitacion_id, c.fecha_vencimiento
                FROM cumplimientos_capacitacion c
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                INNER JOIN (
                    SELECT a2.persona_id_ext, a2.capacitacion_id, MAX(c2.cumplimiento_id) AS max_id
                    FROM cumplimientos_capacitacion c2
                    INNER JOIN asignaciones_capacitacion a2 ON a2.asignacion_id = c2.asignacion_id';
        $params = [];
        if ($personaId !== null && $personaId > 0) {
            $sql .= ' WHERE a2.persona_id_ext = ?';
            $params[] = $personaId;
        }
        $sql .= ' GROUP BY a2.persona_id_ext, a2.capacitacion_id
                ) u ON u.max_id = c.cumplimiento_id';

        $mapa = [];
        foreach ($this->db->fetchAll($sql, $params) as $fila) {
            $clave = (int)$fila['persona_id_ext'] . ':' . (int)$fila['capacitacion_id'];
            $fecha = $fila['fecha_vencimiento'] !== null && $fila['fecha_vencimiento'] !== ''
                ? (string)$fila['fecha_vencimiento']
                : null;
            $mapa[$clave] = $fecha;
        }

        return $mapa;
    }

    /**
     * Bloqueo nominado por trabajador para que dos sincronizaciones no dupliquen pendientes.
     *
     * @param callable():mixed $operacion
     */
    public function conLockPersona(int $personaId, callable $operacion): mixed
    {
        if ($personaId < 1) {
            return $operacion();
        }

        $nombre = 'hseq-asig-' . $personaId;
        $this->db->fetch('SELECT GET_LOCK(?, 10) AS tomado', [$nombre]);

        try {
            return $operacion();
        } finally {
            $this->db->fetch('SELECT RELEASE_LOCK(?) AS liberado', [$nombre]);
        }
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
                       e.fecha_realizacion,
                       e.fecha_vencimiento,
                       cc.resultado AS cumplimiento_resultado,
                       cc.horas_efectivas,
                       cc.sesion_id AS cumplimiento_sesion_id,
                       DATEDIFF(a.fecha_limite_cumplimiento, CURDATE()) AS dias_restantes,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       COALESCE(per_mat.nombre, per_cap.nombre) AS periodicidad_nombre,
                       mat.obligatoria AS obligatoria,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre
                FROM asignaciones_capacitacion a
                INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
                LEFT JOIN cumplimientos_capacitacion cc ON cc.cumplimiento_id = e.cumplimiento_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN matriz_aplicabilidad mat ON mat.matriz_aplicabilidad_id = a.matriz_aplicabilidad_id
                LEFT JOIN periodicidades per_mat ON per_mat.periodicidad_id = mat.periodicidad_id
                LEFT JOIN periodicidades per_cap ON per_cap.periodicidad_id = cap.periodicidad_default_id
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
        ?string $buscar,
        ?string $origen = null,
        ?int $procesoId = null,
        ?string $proyecto = null,
        ?string $fechaLimiteDesde = null,
        ?string $fechaLimiteHasta = null
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

        if ($origen !== null && $origen !== '') {
            $condiciones[] = 'a.origen = ?';
            $params[] = $origen;
        }

        if ($procesoId !== null && $procesoId > 0) {
            $condiciones[] = 'a.proceso_id = ?';
            $params[] = $procesoId;
        }

        if ($proyecto !== null && $proyecto !== '') {
            $condiciones[] = 'a.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        if ($fechaLimiteDesde !== null && $fechaLimiteDesde !== '') {
            $condiciones[] = 'a.fecha_limite_cumplimiento >= ?';
            $params[] = $fechaLimiteDesde;
        }

        if ($fechaLimiteHasta !== null && $fechaLimiteHasta !== '') {
            $condiciones[] = 'a.fecha_limite_cumplimiento <= ?';
            $params[] = $fechaLimiteHasta;
        }

        if ($alerta === 'proximas') {
            $condiciones[] = "e.estado_calculado COLLATE utf8mb4_unicode_ci = 'PENDIENTE_PROXIMA_A_VENCER'";
        } elseif ($alerta === 'vencidas') {
            $condiciones[] = "e.estado_calculado COLLATE utf8mb4_unicode_ci = 'PENDIENTE_VENCIDA'";
        } elseif ($estado !== null && $estado !== '') {
            $condiciones[] = 'e.estado_calculado COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
