<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Solo lectura sobre meridian_personal. No copia filas a capacitaciones.
 */
class PersonalRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::personal();
    }

    public function listar(
        int $limite,
        int $offset,
        ?string $buscar,
        ?string $estado,
        ?int $cargoId
    ): array {
        [$where, $params] = $this->filtros($buscar, $estado, $cargoId);

        $sql = $this->selectPersona()
            . " {$where}
             ORDER BY p.primer_apellido ASC, p.primer_nombre ASC
             LIMIT {$limite} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    public function contar(?string $buscar, ?string $estado, ?int $cargoId): int
    {
        [$where, $params] = $this->filtros($buscar, $estado, $cargoId);
        $personas = Database::personalTable('personas');

        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM {$personas} p {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    public function buscarPorId(int $personaId): ?array
    {
        return $this->db->fetch(
            $this->selectPersona() . ' WHERE p.persona_id = ? LIMIT 1',
            [$personaId]
        );
    }

    public function cargos(): array
    {
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT cargo_id, nombre_cargo
             FROM {$cargos}
             ORDER BY nombre_cargo ASC"
        );
    }

    public function cargoExiste(int $cargoId): bool
    {
        $cargos = Database::personalTable('cargos');
        $fila = $this->db->fetch(
            "SELECT cargo_id FROM {$cargos} WHERE cargo_id = ? LIMIT 1",
            [$cargoId]
        );

        return $fila !== null;
    }

    /** @return array<int,string> */
    public function nombresCargosPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $cargos = Database::personalTable('cargos');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->db->fetchAll(
            "SELECT cargo_id, nombre_cargo FROM {$cargos} WHERE cargo_id IN ({$placeholders})",
            $ids
        );

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int)$fila['cargo_id']] = (string)$fila['nombre_cargo'];
        }

        return $mapa;
    }

    private function selectPersona(): string
    {
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');
        $contratos = Database::personalTable('contratos');

        return "SELECT
                    p.persona_id,
                    p.numero_documento,
                    p.nombre_completo_nombres_primero AS nombre_completo,
                    p.primer_nombre,
                    p.primer_apellido,
                    p.estado,
                    p.cargo_id,
                    c.nombre_cargo AS cargo,
                    p.correo_corporativo,
                    p.correo_personal,
                    p.celular,
                    ct.contrato_id,
                    ct.numero_contrato,
                    ct.proyecto,
                    ct.fecha_inicio AS contrato_fecha_inicio,
                    ct.fecha_terminacion AS contrato_fecha_terminacion
                FROM {$personas} p
                LEFT JOIN {$cargos} c ON c.cargo_id = p.cargo_id
                LEFT JOIN {$contratos} ct
                    ON ct.contrato_id = (
                        SELECT ct2.contrato_id
                        FROM {$contratos} ct2
                        WHERE ct2.persona_id = p.persona_id
                        ORDER BY (ct2.fecha_terminacion IS NULL) DESC, ct2.fecha_inicio DESC, ct2.contrato_id DESC
                        LIMIT 1
                    )";
    }

    /** @return array{0:string,1:list<mixed>} */
    private function filtros(?string $buscar, ?string $estado, ?int $cargoId): array
    {
        $condiciones = [];
        $params = [];

        if ($buscar !== null && $buscar !== '') {
            $condiciones[] = '(p.numero_documento LIKE ?
                OR p.nombre_completo_nombres_primero LIKE ?
                OR p.primer_apellido LIKE ?
                OR p.primer_nombre LIKE ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if ($estado !== null && $estado !== '') {
            $condiciones[] = 'p.estado = ?';
            $params[] = $estado;
        }

        if ($cargoId !== null && $cargoId > 0) {
            $condiciones[] = 'p.cargo_id = ?';
            $params[] = $cargoId;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return [$where, $params];
    }
}
