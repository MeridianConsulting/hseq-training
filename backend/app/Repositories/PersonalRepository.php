<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDOException;

/**
 * Maestro de trabajadores en meridian_personal (personas + contratos + cargos).
 */
class PersonalRepository
{
    private const SQLSTATE_INTEGRIDAD = '23000';

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

    public function existeDocumento(string $numeroDocumento, ?int $exceptoPersonaId = null): bool
    {
        $personas = Database::personalTable('personas');
        $sql = "SELECT persona_id FROM {$personas} WHERE numero_documento = ?";
        $params = [$numeroDocumento];

        if ($exceptoPersonaId !== null && $exceptoPersonaId > 0) {
            $sql .= ' AND persona_id <> ?';
            $params[] = $exceptoPersonaId;
        }

        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params) !== null;
    }

    public function buscarIdPorDocumento(string $numeroDocumento): ?int
    {
        $personas = Database::personalTable('personas');
        $fila = $this->db->fetch(
            "SELECT persona_id FROM {$personas} WHERE numero_documento = ? LIMIT 1",
            [$numeroDocumento]
        );

        return $fila === null ? null : (int)$fila['persona_id'];
    }

    /**
     * @param list<string> $numeros
     * @return array<string, true>
     */
    public function documentosExistentes(array $numeros): array
    {
        $numeros = array_values(array_unique(array_filter($numeros, static fn ($n) => $n !== '')));

        if ($numeros === []) {
            return [];
        }

        $personas = Database::personalTable('personas');
        $encontrados = [];

        foreach (array_chunk($numeros, 500) as $lote) {
            $placeholders = implode(',', array_fill(0, count($lote), '?'));
            $filas = $this->db->fetchAll(
                "SELECT numero_documento FROM {$personas} WHERE numero_documento IN ({$placeholders})",
                $lote
            );

            foreach ($filas as $fila) {
                $encontrados[(string)$fila['numero_documento']] = true;
            }
        }

        return $encontrados;
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
    /**
     * Trabajadores activos con cargo, para el motor RF-008.
     *
     * @return list<array<string,mixed>>
     */
    public function listarActivosParaMotor(): array
    {
        return $this->db->fetchAll(
            $this->selectPersona() . " WHERE p.estado = 'Activo' AND p.cargo_id IS NOT NULL"
        );
    }

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

    /**
     * @return array{por_nombre: array<string,int>, por_id: array<int,string>}
     */
    public function mapaCargos(): array
    {
        $porNombre = [];
        $porId = [];

        foreach ($this->cargos() as $fila) {
            $id = (int)$fila['cargo_id'];
            $nombre = (string)$fila['nombre_cargo'];
            $porId[$id] = $nombre;
            $porNombre[$this->claveCargo($nombre)] = $id;
        }

        return [
            'por_nombre' => $porNombre,
            'por_id' => $porId,
        ];
    }

    public function tiposDocumento(): array
    {
        $tabla = Database::personalTable('tipos_documento');

        return $this->db->fetchAll(
            "SELECT tipo_documento_id, descripcion, abreviatura
             FROM {$tabla}
             ORDER BY tipo_documento_id ASC"
        );
    }

    public function tipoDocumentoExiste(int $tipoDocumentoId): bool
    {
        $tabla = Database::personalTable('tipos_documento');
        $fila = $this->db->fetch(
            "SELECT tipo_documento_id FROM {$tabla} WHERE tipo_documento_id = ? LIMIT 1",
            [$tipoDocumentoId]
        );

        return $fila !== null;
    }

    /**
     * @param array{
     *   numero_documento:string,
     *   tipo_documento_id:int,
     *   primer_nombre:string,
     *   segundo_nombre:?string,
     *   primer_apellido:string,
     *   segundo_apellido:?string,
     *   fecha_nacimiento_texto:string,
     *   correo_corporativo:?string,
     *   cargo_id:int,
     *   estado:string
     * } $datos
     */
    public function insertarPersona(array $datos): int
    {
        $tabla = Database::personalTable('personas');

        return (int)$this->db->insert($tabla, [
            'numero_documento' => $datos['numero_documento'],
            'tipo_documento_id' => $datos['tipo_documento_id'],
            'primer_nombre' => $datos['primer_nombre'],
            'segundo_nombre' => $datos['segundo_nombre'],
            'primer_apellido' => $datos['primer_apellido'],
            'segundo_apellido' => $datos['segundo_apellido'],
            'fecha_nacimiento_texto' => $datos['fecha_nacimiento_texto'],
            'correo_corporativo' => $datos['correo_corporativo'],
            'cargo_id' => $datos['cargo_id'],
            'estado' => $datos['estado'],
        ]);
    }

    /**
     * @param array{
     *   persona_id:int,
     *   fecha_inicio:string,
     *   proyecto:?string
     * } $datos
     */
    public function insertarContrato(array $datos): int
    {
        $tabla = Database::personalTable('contratos');

        return (int)$this->db->insert($tabla, [
            'persona_id' => $datos['persona_id'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'proyecto' => $datos['proyecto'],
        ]);
    }

    public function insertarCargo(string $nombre): int
    {
        $tabla = Database::personalTable('cargos');

        return (int)$this->db->insert($tabla, [
            'nombre_cargo' => $nombre,
        ]);
    }

    /**
     * @param array{correo_corporativo:?string, cargo_id:int} $datos
     */
    public function actualizarPersona(int $personaId, array $datos): void
    {
        $tabla = Database::personalTable('personas');

        $this->db->update(
            $tabla,
            [
                'correo_corporativo' => $datos['correo_corporativo'],
                'cargo_id' => $datos['cargo_id'],
            ],
            'persona_id = ?',
            [$personaId]
        );
    }

    public function actualizarEstado(int $personaId, string $estado): void
    {
        $tabla = Database::personalTable('personas');

        $this->db->update(
            $tabla,
            ['estado' => $estado],
            'persona_id = ?',
            [$personaId]
        );
    }

    /** @return array{activos:int, inactivos:int} */
    public function contarPorEstado(): array
    {
        $tabla = Database::personalTable('personas');
        $filas = $this->db->fetchAll(
            "SELECT estado, COUNT(*) AS total FROM {$tabla} GROUP BY estado"
        );
        $activos = 0;
        $inactivos = 0;
        foreach ($filas as $fila) {
            $n = (int)($fila['total'] ?? 0);
            if (($fila['estado'] ?? '') === 'Activo') {
                $activos = $n;
            } elseif (($fila['estado'] ?? '') === 'Inactivo') {
                $inactivos = $n;
            }
        }

        return ['activos' => $activos, 'inactivos' => $inactivos];
    }

    /**
     * @param array{fecha_inicio?:string, proyecto?:?string} $datos
     */
    public function actualizarContrato(int $contratoId, array $datos): void
    {
        $campos = array_intersect_key($datos, array_flip(['fecha_inicio', 'proyecto']));
        if ($campos === []) {
            return;
        }

        $tabla = Database::personalTable('contratos');

        $this->db->update(
            $tabla,
            $campos,
            'contrato_id = ?',
            [$contratoId]
        );
    }

    /**
     * @param callable():int $operacion
     */
    public function transaccion(callable $operacion): int
    {
        return (int)$this->db->transaccion($operacion);
    }

    public static function esConflictoUnico(PDOException $e): bool
    {
        $sqlState = (string)($e->errorInfo[0] ?? $e->getCode());

        return $sqlState === self::SQLSTATE_INTEGRIDAD;
    }

    public function claveCargo(string $nombre): string
    {
        $texto = trim($nombre);
        if ($texto === '') {
            return '';
        }
        $texto = str_replace(['.', '°', 'º', '?', '/', '_', '-', ',', ';', ':'], ' ', $texto);
        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower($texto, 'UTF-8');
        } else {
            $texto = strtolower($texto);
        }
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function selectPersona(): string
    {
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');
        $contratos = Database::personalTable('contratos');

        return "SELECT
                    p.persona_id,
                    p.numero_documento,
                    p.tipo_documento_id,
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
