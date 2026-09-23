<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class PersonaContextoRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,mixed>|null */
    public function buscarPorPersonaId(int $personaId): ?array
    {
        return $this->db->fetch(
            'SELECT c.persona_contexto_id, c.persona_id_ext, c.numero_documento,
                    c.proceso_id, pr.nombre AS proceso_nombre, c.proyecto
             FROM persona_contexto_hseq c
             INNER JOIN procesos pr ON pr.proceso_id = c.proceso_id
             WHERE c.persona_id_ext = ?
             LIMIT 1',
            [$personaId]
        );
    }

    /**
     * @param list<int> $personaIds
     * @return array<int, array{proceso_id:int,proceso_nombre:string,proyecto:?string,numero_documento:string}>
     */
    public function mapaPorPersonaIds(array $personaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $personaIds))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->db->fetchAll(
            "SELECT c.persona_id_ext, c.numero_documento, c.proceso_id,
                    pr.nombre AS proceso_nombre, c.proyecto
             FROM persona_contexto_hseq c
             INNER JOIN procesos pr ON pr.proceso_id = c.proceso_id
             WHERE c.persona_id_ext IN ({$placeholders})",
            $ids
        );

        $mapa = [];
        foreach ($filas as $fila) {
            $personaId = (int)$fila['persona_id_ext'];
            $mapa[$personaId] = [
                'proceso_id' => (int)$fila['proceso_id'],
                'proceso_nombre' => (string)$fila['proceso_nombre'],
                'proyecto' => $fila['proyecto'] !== null && $fila['proyecto'] !== ''
                    ? (string)$fila['proyecto']
                    : null,
                'numero_documento' => (string)$fila['numero_documento'],
            ];
        }

        return $mapa;
    }

    /** @return list<int> */
    public function personaIdsPorProceso(int $procesoId): array
    {
        if ($procesoId < 1) {
            return [];
        }

        $filas = $this->db->fetchAll(
            'SELECT persona_id_ext FROM persona_contexto_hseq WHERE proceso_id = ?',
            [$procesoId]
        );

        return array_map(static fn (array $f): int => (int)$f['persona_id_ext'], $filas);
    }

    /** @return list<int> */
    public function personaIdsPorProcesoYProyecto(int $procesoId, string $proyecto): array
    {
        if ($procesoId < 1 || $proyecto === '') {
            return [];
        }

        $filas = $this->db->fetchAll(
            'SELECT persona_id_ext
             FROM persona_contexto_hseq
             WHERE proceso_id = ?
               AND proyecto COLLATE utf8mb4_unicode_ci = ?',
            [$procesoId, $proyecto]
        );

        return array_map(static fn (array $f): int => (int)$f['persona_id_ext'], $filas);
    }

    /**
     * Cargos distintos de trabajadores Activos con este proceso (y proyecto si aplica).
     *
     * @return list<array{cargo_id:int,nombre_cargo:string}>
     */
    public function cargosPorProceso(int $procesoId, ?string $proyecto = null): array
    {
        if ($procesoId < 1) {
            return [];
        }

        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');

        $sql = "SELECT DISTINCT p.cargo_id, c.nombre_cargo
                FROM persona_contexto_hseq ctx
                INNER JOIN {$personas} p ON p.persona_id = ctx.persona_id_ext
                INNER JOIN {$cargos} c ON c.cargo_id = p.cargo_id
                WHERE ctx.proceso_id = ?
                  AND p.estado = 'Activo'
                  AND p.cargo_id IS NOT NULL";
        $params = [$procesoId];

        if ($proyecto !== null && $proyecto !== '') {
            $sql .= ' AND ctx.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $sql .= ' ORDER BY c.nombre_cargo ASC';

        $filas = $this->db->fetchAll($sql, $params);
        $salida = [];
        foreach ($filas as $fila) {
            $id = (int)($fila['cargo_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $salida[] = [
                'cargo_id' => $id,
                'nombre_cargo' => (string)($fila['nombre_cargo'] ?? ''),
            ];
        }

        return $salida;
    }

    public function guardar(
        int $personaId,
        string $numeroDocumento,
        int $procesoId,
        ?string $proyecto
    ): void {
        $existente = $this->db->fetch(
            'SELECT persona_contexto_id FROM persona_contexto_hseq WHERE persona_id_ext = ? LIMIT 1',
            [$personaId]
        );

        $datos = [
            'numero_documento' => $numeroDocumento,
            'proceso_id' => $procesoId,
            'proyecto' => $proyecto !== null && $proyecto !== '' ? $proyecto : null,
        ];

        if ($existente === null) {
            $this->db->insert('persona_contexto_hseq', $datos + [
                'persona_id_ext' => $personaId,
            ]);

            return;
        }

        $this->db->update(
            'persona_contexto_hseq',
            $datos,
            'persona_contexto_id = ?',
            [(int)$existente['persona_contexto_id']]
        );
    }
}
