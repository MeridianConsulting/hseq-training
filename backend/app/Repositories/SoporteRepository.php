<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class SoporteRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT s.*,
                    c.asignacion_id,
                    c.sesion_id,
                    c.resultado,
                    a.capacitacion_id,
                    a.persona_id_ext,
                    cap.certificado AS capacitacion_certificado
             FROM soportes_cumplimiento s
             INNER JOIN cumplimientos_capacitacion c ON c.cumplimiento_id = s.cumplimiento_id
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = c.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             WHERE s.soporte_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listarPorCumplimiento(int $cumplimientoId): array
    {
        return $this->db->fetchAll(
            'SELECT soporte_id, cumplimiento_id, tipo_soporte, nombre_archivo, mime_type,
                    tamano_bytes, cargado_por_usuario_id_ext, created_at
             FROM soportes_cumplimiento
             WHERE cumplimiento_id = ?
             ORDER BY soporte_id ASC',
            [$cumplimientoId]
        );
    }

    /**
     * @param list<int> $cumplimientoIds
     * @return list<array<string,mixed>>
     */
    public function listarPorCumplimientos(array $cumplimientoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $cumplimientoIds))));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));

        return $this->db->fetchAll(
            "SELECT soporte_id, cumplimiento_id, tipo_soporte, nombre_archivo, mime_type,
                    tamano_bytes, cargado_por_usuario_id_ext, created_at
             FROM soportes_cumplimiento
             WHERE cumplimiento_id IN ({$in})
             ORDER BY soporte_id ASC",
            $ids
        );
    }

    public function contarPorCumplimiento(int $cumplimientoId): int
    {
        $fila = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM soportes_cumplimiento WHERE cumplimiento_id = ?',
            [$cumplimientoId]
        );

        return (int)($fila['total'] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    public function rutasPorCumplimiento(int $cumplimientoId): array
    {
        return $this->db->fetchAll(
            'SELECT soporte_id, ruta_archivo FROM soportes_cumplimiento WHERE cumplimiento_id = ?',
            [$cumplimientoId]
        );
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos): int
    {
        return (int)$this->db->insert('soportes_cumplimiento', $datos);
    }

    public function eliminar(int $id): int
    {
        return $this->db->delete('soportes_cumplimiento', 'soporte_id = ?', [$id]);
    }

    public function eliminarPorCumplimiento(int $cumplimientoId): int
    {
        return $this->db->delete('soportes_cumplimiento', 'cumplimiento_id = ?', [$cumplimientoId]);
    }

    public function capacitacionRequiereCertificado(int $capacitacionId): bool
    {
        $fila = $this->db->fetch(
            'SELECT certificado FROM capacitaciones WHERE capacitacion_id = ? LIMIT 1',
            [$capacitacionId]
        );

        return $fila !== null && (int)$fila['certificado'] === 1;
    }
}
