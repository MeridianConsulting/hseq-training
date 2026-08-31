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
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string} $filtros
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
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string} $filtros
     */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM cumplimientos_capacitacion c
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
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
            'SELECT sesion_id, capacitacion_id, fecha_hora, estado
             FROM sesiones_capacitacion
             WHERE sesion_id = ?
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
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre
                FROM cumplimientos_capacitacion c
                INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
                INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
                ' . $this->joinPersonas();
    }

    private function joinPersonas(): string
    {
        $personas = Database::personalTable('personas');

        return "LEFT JOIN {$personas} per ON per.persona_id = a.persona_id_ext";
    }

    /**
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string} $filtros
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

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
