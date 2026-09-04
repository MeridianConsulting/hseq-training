<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Alertas alineadas con vw_alertas_vencimiento.
 * Incluye pendientes y vigencias próximas/vencidas (ventana 30 días en la vista).
 */
class AlertaRepository
{
    private Database $db;

    /** @var list<string> */
    private const PROCESOS_PANEL = [
        'GESTION ESTRATEGICA',
        'GESTION ADMINISTRATIVA Y FINANCIERA',
        'GESTION HSEQ',
        'GESTION DE PROYECTOS',
    ];

    /** @var list<string> */
    private const PROYECTOS_PANEL = [
        'FRONTERA',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite, int $offset, array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);
        $personas = Database::personalTable('personas');
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT v.asignacion_id,
                    v.persona_id_ext,
                    v.capacitacion_id,
                    a.proceso_id,
                    a.cargo_id_ext,
                    v.proyecto,
                    v.fecha_limite_cumplimiento,
                    v.fecha_realizacion,
                    v.fecha_vencimiento,
                    v.estado_calculado,
                    v.tipo_alerta,
                    v.fecha_alerta,
                    v.cumplimiento_id,
                    DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) AS dias_restantes,
                    cap.codigo AS capacitacion_codigo,
                    cap.nombre AS capacitacion_nombre,
                    cap.certificado AS capacitacion_certificado,
                    cump.nota_evaluacion,
                    cump.resultado AS cumplimiento_resultado,
                    (SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.cumplimiento_id = v.cumplimiento_id) AS soportes_count,
                    proc.nombre AS proceso_nombre,
                    per.numero_documento,
                    per.nombre_completo_nombres_primero AS persona_nombre,
                    car.nombre_cargo
             FROM vw_alertas_vencimiento v
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = v.asignacion_id
             INNER JOIN {$personas} per ON per.persona_id = v.persona_id_ext AND per.estado = 'Activo'
             LEFT JOIN cumplimientos_capacitacion cump ON cump.cumplimiento_id = v.cumplimiento_id
             LEFT JOIN capacitaciones cap ON cap.capacitacion_id = v.capacitacion_id
             LEFT JOIN procesos proc ON proc.proceso_id = a.proceso_id
             LEFT JOIN {$cargos} car ON car.cargo_id = a.cargo_id_ext
             {$where}
             ORDER BY
               CASE
                 WHEN DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) < 0 THEN 1
                 WHEN DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) = 0 THEN 2
                 WHEN DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) BETWEEN 1 AND 7 THEN 3
                 WHEN DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) BETWEEN 8 AND 15 THEN 4
                 ELSE 5
               END ASC,
               COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento) ASC,
               v.asignacion_id ASC
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );
    }

    /**
     * @param array<string,mixed> $filtros
     */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->filtros($filtros);
        $fila = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM vw_alertas_vencimiento v
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = v.asignacion_id
             INNER JOIN " . Database::personalTable('personas') . " per ON per.persona_id = v.persona_id_ext AND per.estado = 'Activo'
             {$where}",
            $params
        );

        return (int)($fila['total'] ?? 0);
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{vencidas:int,proximas_30:int}
     */
    public function resumen(array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);
        $personas = Database::personalTable('personas');

        $fila = $this->db->fetch(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) < 0 THEN 1
                    ELSE 0
                END), 0) AS vencidas,
                COALESCE(SUM(CASE
                    WHEN DATEDIFF(COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento), CURDATE()) BETWEEN 0 AND 30 THEN 1
                    ELSE 0
                END), 0) AS proximas_30
             FROM vw_alertas_vencimiento v
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = v.asignacion_id
             INNER JOIN {$personas} per ON per.persona_id = v.persona_id_ext AND per.estado = 'Activo'
             {$where}",
            $params
        );

        return [
            'vencidas' => (int)($fila['vencidas'] ?? 0),
            'proximas_30' => (int)($fila['proximas_30'] ?? 0),
        ];
    }

    /** @return list<array{proceso_id:int,nombre:string}> */
    public function procesosActivos(): array
    {
        $filas = $this->db->fetchAll(
            'SELECT proceso_id, nombre FROM procesos WHERE activo = 1 ORDER BY nombre ASC'
        );

        $porNombre = [];
        foreach ($filas as $fila) {
            $clave = $this->normalizarNombre((string)$fila['nombre']);
            if (in_array($clave, self::PROCESOS_PANEL, true)) {
                $porNombre[$clave] = [
                    'proceso_id' => (int)$fila['proceso_id'],
                    'nombre' => (string)$fila['nombre'],
                ];
            }
        }

        $salida = [];
        foreach (self::PROCESOS_PANEL as $clave) {
            if (isset($porNombre[$clave])) {
                $salida[] = $porNombre[$clave];
            }
        }

        return $salida;
    }

    public function procesoEsGestionProyectos(?int $procesoId): bool
    {
        if ($procesoId === null || $procesoId < 1) {
            return false;
        }

        foreach ($this->procesosActivos() as $proceso) {
            if ($proceso['proceso_id'] !== $procesoId) {
                continue;
            }

            return str_contains($this->normalizarNombre($proceso['nombre']), 'GESTION DE PROYECTOS');
        }

        return false;
    }

    /**
     * @return list<array{cargo_id:int|string,nombre_cargo:string}>
     */
    public function cargos(): array
    {
        $cargos = Database::personalTable('cargos');

        return $this->db->fetchAll(
            "SELECT cargo_id, nombre_cargo
             FROM {$cargos}
             ORDER BY nombre_cargo ASC"
        );
    }

    /** @return list<string> */
    public function proyectos(): array
    {
        $candidatos = [];
        foreach (
            [
                "SELECT DISTINCT proyecto FROM matriz_aplicabilidad
                 WHERE proyecto IS NOT NULL AND TRIM(proyecto) <> ''",
                "SELECT DISTINCT proyecto FROM asignaciones_capacitacion
                 WHERE proyecto IS NOT NULL AND TRIM(proyecto) <> ''",
            ] as $sql
        ) {
            foreach ($this->db->fetchAll($sql) as $fila) {
                $nombre = trim((string)($fila['proyecto'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $clave = $this->normalizarNombre($nombre);
                if (in_array($clave, self::PROYECTOS_PANEL, true)) {
                    $candidatos[$clave] = $nombre;
                }
            }
        }

        $salida = [];
        foreach (self::PROYECTOS_PANEL as $clave) {
            if (isset($candidatos[$clave])) {
                $salida[] = $candidatos[$clave];
            }
        }

        return $salida;
    }

    /**
     * @return list<array{capacitacion_id:int,codigo:string,nombre:string}>
     */
    public function capacitacionesEnAlertas(): array
    {
        $personas = Database::personalTable('personas');
        $filas = $this->db->fetchAll(
            "SELECT DISTINCT cap.capacitacion_id, cap.codigo, cap.nombre
             FROM vw_alertas_vencimiento v
             INNER JOIN asignaciones_capacitacion a ON a.asignacion_id = v.asignacion_id
             INNER JOIN {$personas} per ON per.persona_id = v.persona_id_ext AND per.estado = 'Activo'
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = v.capacitacion_id
             ORDER BY cap.nombre ASC"
        );

        $salida = [];
        foreach ($filas as $fila) {
            $salida[] = [
                'capacitacion_id' => (int)$fila['capacitacion_id'],
                'codigo' => (string)$fila['codigo'],
                'nombre' => (string)$fila['nombre'],
            ];
        }

        return $salida;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:list<mixed>}
     */
    private function filtros(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $procesoId = $filtros['proceso_id'] ?? null;
        if ($procesoId !== null && $procesoId > 0) {
            $condiciones[] = 'a.proceso_id = ?';
            $params[] = $procesoId;
        }

        $proyecto = $filtros['proyecto'] ?? null;
        if (is_string($proyecto) && $proyecto !== '') {
            $condiciones[] = 'v.proyecto COLLATE utf8mb4_unicode_ci = ?';
            $params[] = $proyecto;
        }

        $cargoId = $filtros['cargo_id_ext'] ?? null;
        if ($cargoId !== null && $cargoId > 0) {
            $condiciones[] = 'a.cargo_id_ext = ?';
            $params[] = $cargoId;
        }

        $estado = strtolower(trim((string)($filtros['estado_alerta'] ?? 'todas')));
        if ($estado === 'vencidas') {
            $condiciones[] = "v.estado_calculado IN ('VENCIDA', 'PENDIENTE_VENCIDA')";
        } elseif ($estado === 'proximas') {
            $condiciones[] = "v.estado_calculado IN ('PROXIMA_A_VENCER', 'PENDIENTE_PROXIMA_A_VENCER')";
        }

        $q = trim((string)($filtros['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $condiciones[] = '(per.nombre_completo_nombres_primero LIKE ? OR per.numero_documento LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $capId = $filtros['capacitacion_id'] ?? null;
        if ($capId !== null && $capId > 0) {
            $condiciones[] = 'v.capacitacion_id = ?';
            $params[] = $capId;
        }

        $desde = $filtros['vencimiento_desde'] ?? null;
        if (is_string($desde) && $desde !== '') {
            $condiciones[] = 'COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento) >= ?';
            $params[] = $desde;
        }

        $hasta = $filtros['vencimiento_hasta'] ?? null;
        if (is_string($hasta) && $hasta !== '') {
            $condiciones[] = 'COALESCE(v.fecha_vencimiento, v.fecha_limite_cumplimiento) <= ?';
            $params[] = $hasta;
        }

        $where = $condiciones === [] ? '' : ('WHERE ' . implode(' AND ', $condiciones));

        return [$where, $params];
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        $nombre = strtr($nombre, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return preg_replace('/\s+/', ' ', $nombre) ?? $nombre;
    }
}
