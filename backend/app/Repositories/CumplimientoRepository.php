<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDOException;
use Throwable;

class CumplimientoRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string, evidencia_faltante?:?int} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);

        return $this->db->fetchAll(
            $this->selectBase() . " {$where}
             ORDER BY c.fecha_realizacion DESC, c.cumplimiento_id DESC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string, evidencia_faltante?:?int} $filtros
     */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM cumplimientos_capacitacion c
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             ' . $this->joinPersonas() . "
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE c.cumplimiento_id = ? LIMIT 1',
            [$id]
        );
    }

    public function buscarPorAsignacion(int $asignacionId): ?array
    {
        return $this->db->fetch(
            $this->selectBase() . ' WHERE c.asignacion_id = ? LIMIT 1',
            [$asignacionId]
        );
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos): int
    {
        return (int)$this->db->insert('cumplimientos_capacitacion', $datos);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function actualizar(int $id, array $datos): int
    {
        return $this->db->update(
            'cumplimientos_capacitacion',
            $datos,
            'cumplimiento_id = ?',
            [$id]
        );
    }

    public function participanteEnSesion(int $sesionId, int $asignacionId): ?array
    {
        $personas = Database::personalTable('personas');

        return $this->db->fetch(
            "SELECT sp.sesion_participante_id,
                    sp.sesion_id,
                    sp.asignacion_id,
                    sp.estado_asistencia,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre
             FROM sesion_participantes sp
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = sp.asignacion_id
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             WHERE sp.sesion_id = ? AND sp.asignacion_id = ?
             LIMIT 1",
            [$sesionId, $asignacionId]
        );
    }

    /** @return array<string,mixed>|null */
    public function sesionPorId(int $sesionId): ?array
    {
        return $this->db->fetch(
            'SELECT s.sesion_id, s.capacitacion_id, s.fecha_hora, s.estado, s.plan_detalle_id,
                    cap.certificado AS capacitacion_certificado,
                    cap.evaluacion AS capacitacion_evaluacion,
                    cap.nota_minima AS capacitacion_nota_minima,
                    d.estado_programacion
             FROM sesiones_capacitacion s
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = s.capacitacion_id
             LEFT JOIN plan_anual_detalle d ON d.plan_detalle_id = s.plan_detalle_id
             WHERE s.sesion_id = ?
             LIMIT 1',
            [$sesionId]
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
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function selectBase(): string
    {
        return 'SELECT c.*,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    a.proyecto,
                    cap.codigo AS capacitacion_codigo,
                    cap.nombre AS capacitacion_nombre,
                    cap.certificado AS capacitacion_certificado,
                    cap.evaluacion AS capacitacion_evaluacion,
                    cap.nota_minima AS capacitacion_nota_minima,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    e.estado_calculado
                FROM cumplimientos_capacitacion c
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN vw_estado_asignaciones e ON e.asignacion_id = c.asignacion_id
                ' . $this->joinPersonas();
    }

    private function joinPersonas(): string
    {
        $personas = Database::personalTable('personas');

        return "LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext";
    }

    /**
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string, evidencia_faltante?:?int} $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $personaId = $filtros['persona_id'] ?? null;
        if ($personaId !== null && $personaId > 0) {
            $condiciones[] = 'a.persona_id_ext = ?';
            $params[] = $personaId;
        }

        $sesionId = $filtros['sesion_id'] ?? null;
        if ($sesionId !== null && $sesionId > 0) {
            $condiciones[] = 'c.sesion_id = ?';
            $params[] = $sesionId;
        }

        $buscar = $filtros['buscar'] ?? null;
        if (is_string($buscar) && $buscar !== '') {
            $condiciones[] = '(per.nombre_completo_nombres_primero LIKE ?
                OR per.numero_documento LIKE ?
                OR cap.codigo LIKE ?
                OR cap.nombre LIKE ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if (!empty($filtros['evidencia_faltante'])) {
            $condiciones[] = 'cap.certificado = 1';
            $condiciones[] = 'NOT EXISTS (
                SELECT 1 FROM soportes_cumplimiento so
                WHERE so.cumplimiento_id = c.cumplimiento_id
            )';
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }

    /**
     * Obligaciones (asignaciones) con estado de cumplimiento calculado.
     *
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function consultar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtrosConsulta($filtros);

        return $this->db->fetchAll(
            $this->selectConsulta() . " {$where}
             ORDER BY a.fecha_limite_cumplimiento ASC, a.asignacion_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array<string,mixed> $filtros
     */
    public function contarConsulta(array $filtros): int
    {
        [$where, $params] = $this->filtrosConsulta($filtros);
        $personas = Database::personalTable('personas');
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM asignaciones_capacitacion a
             INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    public function consultarPorAsignacion(int $asignacionId): ?array
    {
        return $this->db->fetch(
            $this->selectConsulta() . ' WHERE a.asignacion_id = ? LIMIT 1',
            [$asignacionId]
        );
    }

    public function ultimaAsistencia(int $asignacionId): ?array
    {
        return $this->db->fetch(
            'SELECT s.sesion_id,
                    DATE(s.fecha_hora) AS fecha_sesion,
                    s.fecha_hora,
                    s.estado AS sesion_estado,
                    sp.estado_asistencia
             FROM sesion_participantes sp
             INNER JOIN sesiones_capacitacion s ON s.sesion_id = sp.sesion_id
             WHERE sp.asignacion_id = ?
             ORDER BY s.fecha_hora DESC, sp.sesion_participante_id DESC
             LIMIT 1',
            [$asignacionId]
        );
    }

    public function programacionAsignacion(int $asignacionId): ?array
    {
        return $this->db->fetch(
            'SELECT p.anio, d.mes_programado, d.plan_detalle_id
             FROM plan_detalle_asignaciones pda
             INNER JOIN plan_anual_detalle d ON d.plan_detalle_id = pda.plan_detalle_id
             INNER JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
             WHERE pda.asignacion_id = ?
             ORDER BY p.anio ASC, d.mes_programado ASC
             LIMIT 1',
            [$asignacionId]
        );
    }

    public function reglaMatriz(int $matrizId): ?array
    {
        if ($matrizId < 1) {
            return null;
        }

        return $this->db->fetch(
            'SELECT m.matriz_aplicabilidad_id,
                    m.activa,
                    m.obligatoria,
                    m.proyecto,
                    pr.nombre AS proceso_nombre
             FROM matriz_aplicabilidad m
             LEFT JOIN procesos pr ON pr.proceso_id = m.proceso_id
             WHERE m.matriz_aplicabilidad_id = ?
             LIMIT 1',
            [$matrizId]
        );
    }

    public function reglaMatrizPorContexto(int $capacitacionId, ?int $cargoId, ?string $proyecto): ?array
    {
        if ($capacitacionId < 1 || $cargoId === null || $cargoId < 1) {
            return null;
        }

        $sql = 'SELECT m.matriz_aplicabilidad_id,
                       m.activa,
                       m.obligatoria,
                       m.proyecto,
                       pr.nombre AS proceso_nombre
                FROM matriz_aplicabilidad m
                LEFT JOIN procesos pr ON pr.proceso_id = m.proceso_id
                WHERE m.capacitacion_id = ?
                  AND m.activa = 1
                  AND m.cargo_id_ext = ?';
        $params = [$capacitacionId, $cargoId];
        if ($proyecto !== null && $proyecto !== '') {
            $sql .= ' AND (m.proyecto IS NULL OR TRIM(m.proyecto) COLLATE utf8mb4_unicode_ci = ?)';
            $params[] = $proyecto;
        }
        $sql .= ' ORDER BY m.matriz_aplicabilidad_id DESC LIMIT 1';

        return $this->db->fetch($sql, $params);
    }

    private function selectConsulta(): string
    {
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');

        return "SELECT a.asignacion_id,
                       a.persona_id_ext,
                       a.capacitacion_id,
                       a.fecha_asignacion,
                       a.fecha_limite_cumplimiento,
                       a.origen,
                       a.cargo_id_ext,
                       a.proceso_id,
                       a.proyecto,
                       a.matriz_aplicabilidad_id,
                       a.ambito,
                       e.estado_calculado,
                       e.cumplimiento_id,
                       e.fecha_realizacion,
                       e.fecha_vencimiento,
                       cc.resultado,
                       cc.nota_evaluacion,
                       cc.horas_efectivas,
                       cc.sesion_id,
                       cc.observaciones,
                       cap.codigo AS capacitacion_codigo,
                       cap.nombre AS capacitacion_nombre,
                       cap.evaluacion AS capacitacion_evaluacion,
                       cap.nota_minima AS capacitacion_nota_minima,
                       cap.certificado AS capacitacion_certificado,
                       cap.requiere_listado_asistencia,
                       cap.es_tarea_critica,
                       cap.tipo_capacitacion_id,
                       tc.nombre AS tipo_nombre,
                       vg.nombre AS vigencia_nombre,
                       pr.nombre AS proceso_nombre,
                       per.numero_documento,
                       per.nombre_completo_nombres_primero AS persona_nombre,
                       per.estado AS estado_laboral,
                       per.cargo_id AS cargo_id_actual,
                       cg.nombre_cargo AS cargo
                FROM asignaciones_capacitacion a
                INNER JOIN vw_estado_asignaciones e ON e.asignacion_id = a.asignacion_id
                LEFT JOIN cumplimientos_capacitacion cc ON cc.asignacion_id = a.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                LEFT JOIN tipos_capacitacion tc ON tc.tipo_capacitacion_id = cap.tipo_capacitacion_id
                LEFT JOIN vigencias vg ON vg.vigencia_id = cap.vigencia_id
                LEFT JOIN procesos pr ON pr.proceso_id = a.proceso_id
                LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext
                LEFT JOIN {$cargos} cg ON cg.cargo_id = a.cargo_id_ext";
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtrosConsulta(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $personaId = $filtros['persona_id'] ?? null;
        if ($personaId !== null && (int)$personaId > 0) {
            $condiciones[] = 'a.persona_id_ext = ?';
            $params[] = (int)$personaId;
        }

        $buscar = $filtros['buscar'] ?? null;
        if (is_string($buscar) && $buscar !== '') {
            $condiciones[] = '(per.nombre_completo_nombres_primero LIKE ?
                OR per.numero_documento LIKE ?
                OR CAST(a.persona_id_ext AS CHAR) = ?
                OR cap.codigo LIKE ?
                OR cap.nombre LIKE ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $buscar, $like, $like);
        }

        $cargoId = $filtros['cargo_id'] ?? null;
        if ($cargoId !== null && (int)$cargoId > 0) {
            $condiciones[] = 'a.cargo_id_ext = ?';
            $params[] = (int)$cargoId;
        }

        $procesoId = $filtros['proceso_id'] ?? null;
        if ($procesoId !== null && (int)$procesoId > 0) {
            $condiciones[] = 'a.proceso_id = ?';
            $params[] = (int)$procesoId;
        }

        $proyecto = $filtros['proyecto'] ?? null;
        if (is_string($proyecto) && $proyecto !== '') {
            $condiciones[] = 'a.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $capacitacionId = $filtros['capacitacion_id'] ?? null;
        if ($capacitacionId !== null && (int)$capacitacionId > 0) {
            $condiciones[] = 'a.capacitacion_id = ?';
            $params[] = (int)$capacitacionId;
        }

        $tipoId = $filtros['tipo_capacitacion_id'] ?? null;
        if ($tipoId !== null && (int)$tipoId > 0) {
            $condiciones[] = 'cap.tipo_capacitacion_id = ?';
            $params[] = (int)$tipoId;
        }

        if (!empty($filtros['es_tarea_critica'])) {
            $condiciones[] = 'cap.es_tarea_critica = 1';
        }

        $estado = $filtros['estado'] ?? null;
        if (is_string($estado) && $estado !== '') {
            $condiciones[] = 'e.estado_calculado COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $estado;
        }

        $estadoLaboral = $filtros['estado_laboral'] ?? null;
        if (is_string($estadoLaboral) && $estadoLaboral !== '') {
            $condiciones[] = 'per.estado = ?';
            $params[] = $estadoLaboral;
        }

        $desde = $filtros['fecha_realizacion_desde'] ?? null;
        if (is_string($desde) && $desde !== '') {
            $condiciones[] = 'e.fecha_realizacion >= ?';
            $params[] = $desde;
        }

        $hasta = $filtros['fecha_realizacion_hasta'] ?? null;
        if (is_string($hasta) && $hasta !== '') {
            $condiciones[] = 'e.fecha_realizacion <= ?';
            $params[] = $hasta;
        }

        $venceDesde = $filtros['fecha_vencimiento_desde'] ?? null;
        if (is_string($venceDesde) && $venceDesde !== '') {
            $condiciones[] = 'e.fecha_vencimiento >= ?';
            $params[] = $venceDesde;
        }

        $venceHasta = $filtros['fecha_vencimiento_hasta'] ?? null;
        if (is_string($venceHasta) && $venceHasta !== '') {
            $condiciones[] = 'e.fecha_vencimiento <= ?';
            $params[] = $venceHasta;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
