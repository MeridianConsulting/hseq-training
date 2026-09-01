<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class HistorialContextoRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function registrarAlta(int $personaId, ?int $cargoId, ?string $proyecto, string $vigenteDesde): void
    {
        $this->insertar($personaId, $cargoId, $proyecto, $vigenteDesde, null, 'ALTA');
    }

    public function registrarCambio(int $personaId, ?int $cargoId, ?string $proyecto): void
    {
        $hoy = date('Y-m-d');
        $abierto = $this->abierto($personaId);
        if ($abierto !== null) {
            $desde = (string)$abierto['vigente_desde'];
            $hasta = $hoy < $desde ? $desde : $hoy;
            $this->db->update(
                'historial_contexto_trabajador',
                ['vigente_hasta' => $hasta],
                'historial_id = ?',
                [(int)$abierto['historial_id']]
            );
        }

        $this->insertar($personaId, $cargoId, $proyecto, $hoy, null, 'EDICION');
    }

    /** @return list<array<string,mixed>> */
    public function listarPorPersona(int $personaId): array
    {
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT h.historial_id,
                    h.persona_id_ext,
                    h.cargo_id_ext,
                    car.nombre_cargo,
                    h.proyecto,
                    h.vigente_desde,
                    h.vigente_hasta,
                    h.origen
             FROM historial_contexto_trabajador h
             LEFT JOIN {$cargos} car ON car.cargo_id = h.cargo_id_ext
             WHERE h.persona_id_ext = ?
             ORDER BY h.vigente_desde DESC, h.historial_id DESC",
            [$personaId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function cargosDesdeAsignaciones(int $personaId): array
    {
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT a.cargo_id_ext,
                    car.nombre_cargo,
                    MIN(a.fecha_asignacion) AS primera_asignacion,
                    MAX(a.fecha_asignacion) AS ultima_asignacion
             FROM asignaciones_capacitacion a
             LEFT JOIN {$cargos} car ON car.cargo_id = a.cargo_id_ext
             WHERE a.persona_id_ext = ?
             GROUP BY a.cargo_id_ext, car.nombre_cargo
             ORDER BY primera_asignacion DESC, a.cargo_id_ext DESC",
            [$personaId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function proyectosDesdeAsignaciones(int $personaId): array
    {
        return $this->db->fetchAll(
            "SELECT a.proyecto,
                    MIN(a.fecha_asignacion) AS primera_asignacion,
                    MAX(a.fecha_asignacion) AS ultima_asignacion
             FROM asignaciones_capacitacion a
             WHERE a.persona_id_ext = ?
             GROUP BY a.proyecto
             ORDER BY primera_asignacion DESC",
            [$personaId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function procesosDesdeAsignaciones(int $personaId): array
    {
        return $this->db->fetchAll(
            "SELECT a.proceso_id,
                    proc.nombre AS proceso_nombre,
                    MIN(a.fecha_asignacion) AS primera_asignacion,
                    MAX(a.fecha_asignacion) AS ultima_asignacion
             FROM asignaciones_capacitacion a
             LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
             WHERE a.persona_id_ext = ?
             GROUP BY a.proceso_id, proc.nombre
             ORDER BY primera_asignacion DESC",
            [$personaId]
        );
    }

    /** @return array<string,mixed>|null */
    private function abierto(int $personaId): ?array
    {
        return $this->db->fetch(
            'SELECT historial_id, vigente_desde
             FROM historial_contexto_trabajador
             WHERE persona_id_ext = ? AND vigente_hasta IS NULL
             ORDER BY historial_id DESC
             LIMIT 1',
            [$personaId]
        );
    }

    private function insertar(
        int $personaId,
        ?int $cargoId,
        ?string $proyecto,
        string $vigenteDesde,
        ?string $vigenteHasta,
        string $origen
    ): void {
        $this->db->insert('historial_contexto_trabajador', [
            'persona_id_ext' => $personaId,
            'cargo_id_ext' => $cargoId !== null && $cargoId > 0 ? $cargoId : null,
            'proyecto' => $proyecto !== null && $proyecto !== '' ? $proyecto : null,
            'vigente_desde' => $vigenteDesde,
            'vigente_hasta' => $vigenteHasta,
            'origen' => $origen,
        ]);
    }
}
