<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MatrizRepository;
use DateTimeImmutable;

/**
 * Punto unico de estados de cumplimiento / vencimiento.
 * No duplica datos: lee las vistas vw_estado_asignaciones y vw_alertas_vencimiento.
 *
 * Estados (no alterar el SQL de las vistas):
 * - PENDIENTE
 * - PENDIENTE_PROXIMA_A_VENCER
 * - PENDIENTE_VENCIDA
 * - COMPLETADA
 * - PROXIMA_A_VENCER
 * - VENCIDA
 *
 * fecha_limite_cumplimiento (asignacion pendiente) != fecha_vencimiento (vigencia del curso tomado).
 *
 * Inducción / reinducción: el disparo está en MotorAsignacionService.
 * Este servicio calcula fecha_vencimiento: periodicidad de matriz si existe;
 * si no, vigencia del catálogo de la capacitación.
 */
class VencimientoService
{
    public const ESTADOS = [
        'PENDIENTE',
        'PENDIENTE_PROXIMA_A_VENCER',
        'PENDIENTE_VENCIDA',
        'COMPLETADA',
        'PROXIMA_A_VENCER',
        'VENCIDA',
    ];

    public const ORIGEN_MATRIZ_SNAPSHOT = 'matriz_snapshot';
    public const ORIGEN_MATRIZ_ACTIVA = 'matriz_activa';
    public const ORIGEN_CAPACITACION = 'capacitacion';
    public const ORIGEN_NINGUNA = 'ninguna';

    private Database $db;
    private MatrizRepository $matriz;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->matriz = new MatrizRepository();
    }

    /**
     * Fecha de vencimiento = fecha de realización + periodicidad real.
     * cantidad <= 0 o unidad vacía / desconocida → null (capacitación de una sola vez).
     */
    public static function calcularFechaVencimiento(
        ?string $fechaRealizacion,
        ?int $cantidad,
        ?string $unidad
    ): ?string {
        $cantidad = (int)$cantidad;
        $unidad = strtoupper(trim((string)$unidad));
        if ($cantidad <= 0 || $unidad === '') {
            return null;
        }

        $mapa = ['DIAS' => 'days', 'MESES' => 'months', 'ANIOS' => 'years'];
        $mod = $mapa[$unidad] ?? null;
        if ($mod === null) {
            return null;
        }

        $baseTxt = is_string($fechaRealizacion) ? trim($fechaRealizacion) : '';
        if ($baseTxt === '') {
            return null;
        }

        $base = DateTimeImmutable::createFromFormat('Y-m-d', $baseTxt);
        if (!$base instanceof DateTimeImmutable || $base->format('Y-m-d') !== $baseTxt) {
            try {
                $base = new DateTimeImmutable($baseTxt);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $base->modify('+' . $cantidad . ' ' . $mod)->format('Y-m-d');
    }

    /**
     * Periodicidad del trabajador, no del nombre de la capacitación.
     * 1) snapshot de matriz en la asignación (aunque la regla se inactive después)
     * 2) regla activa cargo + proyecto + capacitación
     * 3) vigencia del catálogo de la capacitación (validez una vez tomada)
     * 4) ninguna → sin vencimiento
     *
     * @return array{cantidad:?int,unidad:?string,nombre:?string,origen:string,etiqueta:string}
     */
    public function resolverPeriodicidad(int $asignacionId): array
    {
        $asig = $this->contextoAsignacion($asignacionId);
        if ($asig === null) {
            return $this->sinPeriodicidad();
        }

        $matrizId = $asig['matriz_aplicabilidad_id'] !== null ? (int)$asig['matriz_aplicabilidad_id'] : 0;
        if ($matrizId > 0 && $asig['matriz_existe'] !== null) {
            $desdeMatriz = $this->empaquetar(
                $asig['mat_cantidad'] !== null ? (int)$asig['mat_cantidad'] : null,
                $asig['mat_unidad'] !== null ? (string)$asig['mat_unidad'] : null,
                $asig['mat_nombre'] !== null ? (string)$asig['mat_nombre'] : null,
                self::ORIGEN_MATRIZ_SNAPSHOT
            );
            if ($desdeMatriz['cantidad'] !== null && $desdeMatriz['cantidad'] > 0) {
                return $desdeMatriz;
            }

            $desdeCap = $this->desdeCapacitacion($asig, self::ORIGEN_MATRIZ_SNAPSHOT);
            if ($desdeCap['cantidad'] !== null && $desdeCap['cantidad'] > 0) {
                return $desdeCap;
            }

            return $this->sinPeriodicidad();
        }

        $activa = $this->periodicidadMatrizActiva($asig);
        if ($activa !== null) {
            return $activa;
        }

        return $this->desdeCapacitacion($asig, self::ORIGEN_CAPACITACION);
    }

    public function fechaVencimientoDeAsignacion(int $asignacionId, string $fechaRealizacion): ?string
    {
        $per = $this->resolverPeriodicidad($asignacionId);

        return self::calcularFechaVencimiento($fechaRealizacion, $per['cantidad'], $per['unidad']);
    }

    /**
     * Primera regla de matriz activa que aplica a cargo + proyecto + capacitación.
     */
    public function matrizIdAplicable(int $capacitacionId, ?int $cargoId, ?string $proyecto): ?int
    {
        if ($capacitacionId <= 0) {
            return null;
        }

        $cargo = $cargoId !== null && $cargoId > 0 ? $cargoId : 0;
        $proyectoPersona = is_string($proyecto) ? trim($proyecto) : '';

        foreach ($this->matriz->reglasActivasParaMotor() as $regla) {
            if ((int)($regla['capacitacion_id'] ?? 0) !== $capacitacionId) {
                continue;
            }
            if (!$this->coincide($regla, $cargo, $proyectoPersona)) {
                continue;
            }

            $id = (int)($regla['matriz_aplicabilidad_id'] ?? 0);

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     * Rellena fecha_vencimiento en cumplimientos aprobados que quedaron sin ella
     * (historial/migración antiguos que solo miraban vigencia del catálogo).
     * Idempotente: solo toca filas con fecha_vencimiento NULL.
     */
    public function recalcularVencimientosSinFecha(): int
    {
        $filas = $this->db->fetchAll(
            "SELECT c.cumplimiento_id, c.asignacion_id, c.fecha_realizacion
             FROM cumplimientos_capacitacion c
             WHERE c.fecha_vencimiento IS NULL
               AND c.fecha_realizacion IS NOT NULL
               AND c.resultado = 'APROBADO'"
        );

        $actualizados = 0;
        foreach ($filas as $fila) {
            $asigId = (int)($fila['asignacion_id'] ?? 0);
            $fecha = substr(trim((string)($fila['fecha_realizacion'] ?? '')), 0, 10);
            if ($asigId <= 0 || $fecha === '') {
                continue;
            }

            $vence = $this->fechaVencimientoDeAsignacion($asigId, $fecha);
            if ($vence === null) {
                continue;
            }

            $this->db->update(
                'cumplimientos_capacitacion',
                ['fecha_vencimiento' => $vence],
                'cumplimiento_id = ? AND fecha_vencimiento IS NULL',
                [(int)$fila['cumplimiento_id']]
            );
            $actualizados++;
        }

        return $actualizados;
    }

    private static bool $vencimientosAsegurados = false;

    /**
     * Vigencia de catálogo «1 año» (cantidad 1, unidad ANIOS).
     *
     * @return array{vigencia_id:int,cantidad:int,unidad:string}|null
     */
    public function vigenciaAnual(): ?array
    {
        $fila = $this->db->fetch(
            "SELECT vigencia_id, cantidad, unidad
             FROM vigencias
             WHERE activo = 1
               AND cantidad = 1
               AND UPPER(TRIM(unidad)) = 'ANIOS'
             ORDER BY vigencia_id ASC
             LIMIT 1"
        );
        if ($fila === null) {
            return null;
        }

        return [
            'vigencia_id' => (int)$fila['vigencia_id'],
            'cantidad' => (int)$fila['cantidad'],
            'unidad' => strtoupper(trim((string)$fila['unidad'])),
        ];
    }

    /** Periodicidad de catálogo «ANUAL» (cantidad 1, unidad ANIOS). */
    public function periodicidadAnualId(): ?int
    {
        $fila = $this->db->fetch(
            "SELECT periodicidad_id
             FROM periodicidades
             WHERE activo = 1
               AND cantidad = 1
               AND UPPER(TRIM(unidad)) = 'ANIOS'
             ORDER BY periodicidad_id ASC
             LIMIT 1"
        );
        if ($fila === null) {
            return null;
        }
        $id = (int)($fila['periodicidad_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Inducciones/reinducciones sin vigencia heredan 1 año (y periodicidad anual si falta).
     */
    public function asegurarVigenciaInducciones(): int
    {
        $vig = $this->vigenciaAnual();
        if ($vig === null) {
            return 0;
        }

        $perId = $this->periodicidadAnualId();
        // Solo filas sin vigencia; no pisa configuración manual.
        $sqlPer = $perId !== null
            ? ', periodicidad_default_id = COALESCE(periodicidad_default_id, ?)'
            : '';
        $params = [$vig['vigencia_id']];
        if ($perId !== null) {
            $params[] = $perId;
        }

        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare(
            "UPDATE capacitaciones
             SET vigencia_id = ?{$sqlPer}
             WHERE vigencia_id IS NULL
               AND (
                 LOWER(nombre) LIKE '%induc%'
                 OR LOWER(nombre) LIKE '%reinduc%'
               )"
        );
        $stmt->execute($params);

        return (int)$stmt->rowCount();
    }

    /** Una vez por proceso PHP: vigencia en inducciones + vencimientos de historial faltantes. */
    public static function asegurarVencimientosCumplimiento(): void
    {
        if (self::$vencimientosAsegurados) {
            return;
        }
        self::$vencimientosAsegurados = true;

        try {
            $svc = new self();
            $svc->asegurarVigenciaInducciones();
            $svc->recalcularVencimientosSinFecha();
        } catch (\Throwable $e) {
            // No bloquear la API si el recalculo falla.
        }
    }

    /** @return array<string,mixed>|null */
    private function contextoAsignacion(int $asignacionId): ?array
    {
        return $this->db->fetch(
            'SELECT a.asignacion_id,
                    a.persona_id_ext,
                    a.capacitacion_id,
                    a.matriz_aplicabilidad_id,
                    a.cargo_id_ext,
                    a.proyecto,
                    cap.vigencia_id,
                    COALESCE(vg.cantidad, pd.cantidad) AS cap_cantidad,
                    COALESCE(vg.unidad, pd.unidad) AS cap_unidad,
                    COALESCE(vg.nombre, pd.nombre) AS cap_nombre,
                    m.matriz_aplicabilidad_id AS matriz_existe,
                    COALESCE(pe.cantidad, pd.cantidad) AS mat_cantidad,
                    COALESCE(pe.unidad, pd.unidad) AS mat_unidad,
                    COALESCE(pe.nombre, pd.nombre) AS mat_nombre
             FROM asignaciones_capacitacion a
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id
             LEFT JOIN vigencias vg ON vg.vigencia_id = cap.vigencia_id
             LEFT JOIN periodicidades pd ON pd.periodicidad_id = cap.periodicidad_default_id
             LEFT JOIN matriz_aplicabilidad m ON m.matriz_aplicabilidad_id = a.matriz_aplicabilidad_id
             LEFT JOIN periodicidades pe ON pe.periodicidad_id = m.periodicidad_id
             WHERE a.asignacion_id = ?
             LIMIT 1',
            [$asignacionId]
        );
    }

    /**
     * @param array<string,mixed> $asig
     * @return array{cantidad:?int,unidad:?string,nombre:?string,origen:string,etiqueta:string}|null
     */
    private function periodicidadMatrizActiva(array $asig): ?array
    {
        $capacitacionId = (int)($asig['capacitacion_id'] ?? 0);
        $cargoId = (int)($asig['cargo_id_ext'] ?? 0);
        $proyecto = is_string($asig['proyecto'] ?? null) ? trim((string)$asig['proyecto']) : '';

        foreach ($this->matriz->reglasActivasParaMotor() as $regla) {
            if ((int)($regla['capacitacion_id'] ?? 0) !== $capacitacionId) {
                continue;
            }
            if (!$this->coincide($regla, $cargoId, $proyecto)) {
                continue;
            }

            $pack = $this->empaquetar(
                isset($regla['per_cantidad']) ? (int)$regla['per_cantidad'] : null,
                isset($regla['per_unidad']) ? (string)$regla['per_unidad'] : null,
                isset($regla['periodicidad_nombre']) ? (string)$regla['periodicidad_nombre'] : null,
                self::ORIGEN_MATRIZ_ACTIVA
            );
            if ($pack['cantidad'] !== null && $pack['cantidad'] > 0) {
                return $pack;
            }

            return $this->desdeCapacitacion($asig, self::ORIGEN_MATRIZ_ACTIVA);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $regla
     */
    private function coincide(array $regla, int $cargoId, string $proyectoPersona): bool
    {
        $cargoRegla = $regla['cargo_id_ext'] !== null ? (int)$regla['cargo_id_ext'] : null;
        if ($cargoRegla !== null && $cargoRegla !== $cargoId) {
            return false;
        }

        $proyectoRegla = is_string($regla['proyecto'] ?? null) ? trim((string)$regla['proyecto']) : '';
        if ($proyectoRegla !== '' && strcasecmp($proyectoRegla, $proyectoPersona) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $asig
     * @return array{cantidad:?int,unidad:?string,nombre:?string,origen:string,etiqueta:string}
     */
    private function desdeCapacitacion(array $asig, string $origen): array
    {
        $cantidad = $asig['cap_cantidad'] !== null ? (int)$asig['cap_cantidad'] : null;
        if ($cantidad === null || $cantidad <= 0) {
            return $this->sinPeriodicidad();
        }

        return $this->empaquetar(
            $cantidad,
            $asig['cap_unidad'] !== null ? (string)$asig['cap_unidad'] : null,
            $asig['cap_nombre'] !== null ? (string)$asig['cap_nombre'] : null,
            $origen === self::ORIGEN_CAPACITACION ? self::ORIGEN_CAPACITACION : $origen
        );
    }

    /**
     * @return array{cantidad:?int,unidad:?string,nombre:?string,origen:string,etiqueta:string}
     */
    private function empaquetar(?int $cantidad, ?string $unidad, ?string $nombre, string $origen): array
    {
        $unidad = $unidad !== null && trim($unidad) !== '' ? strtoupper(trim($unidad)) : null;
        $nombre = $nombre !== null && trim($nombre) !== '' ? trim($nombre) : null;

        return [
            'cantidad' => $cantidad !== null && $cantidad > 0 ? $cantidad : null,
            'unidad' => $unidad,
            'nombre' => humanizar_nombre_unidad($nombre),
            'origen' => $origen,
            'etiqueta' => $this->etiquetaPeriodicidad($cantidad, $unidad, $nombre),
        ];
    }

    /**
     * @return array{cantidad:?int,unidad:?string,nombre:?string,origen:string,etiqueta:string}
     */
    private function sinPeriodicidad(): array
    {
        return [
            'cantidad' => null,
            'unidad' => null,
            'nombre' => null,
            'origen' => self::ORIGEN_NINGUNA,
            'etiqueta' => 'Sin vencimiento',
        ];
    }

    private function etiquetaPeriodicidad(?int $cantidad, ?string $unidad, ?string $nombre): string
    {
        if ($nombre !== null && $nombre !== '') {
            return humanizar_nombre_unidad($nombre) ?? $nombre;
        }
        if ($cantidad === null || $cantidad <= 0 || $unidad === null || $unidad === '') {
            return 'Sin vencimiento';
        }

        return etiqueta_unidad($unidad, $cantidad) ?? 'Sin vencimiento';
    }

    /** @return array<string,int> */
    public function resumenEstados(): array
    {
        $conteos = array_fill_keys(self::ESTADOS, 0);

        try {
            $filas = $this->db->fetchAll(
                'SELECT e.estado_calculado, COUNT(*) AS total
                 FROM vw_estado_asignaciones e
                 INNER JOIN ' . Database::personalTable('personas') . " per ON per.persona_id = e.persona_id_ext AND per.estado = 'Activo'
                 GROUP BY e.estado_calculado"
            );
        } catch (\Throwable $e) {
            return $conteos;
        }

        foreach ($filas as $fila) {
            $estado = (string)$fila['estado_calculado'];
            if (array_key_exists($estado, $conteos)) {
                $conteos[$estado] = (int)$fila['total'];
            }
        }

        return $conteos;
    }

    public function alertas(int $limite = 20): array
    {
        $limite = min(100, max(1, $limite));

        try {
            return $this->db->fetchAll(
                "SELECT v.asignacion_id, v.persona_id_ext, v.capacitacion_id, v.proyecto,
                        v.fecha_limite_cumplimiento, v.fecha_realizacion, v.fecha_vencimiento,
                        v.estado_calculado, v.tipo_alerta, v.fecha_alerta
                 FROM vw_alertas_vencimiento v
                 INNER JOIN " . Database::personalTable('personas') . " per ON per.persona_id = v.persona_id_ext AND per.estado = 'Activo'
                 ORDER BY v.fecha_alerta ASC
                 LIMIT {$limite}"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function dashboard(): array
    {
        $estados = $this->resumenEstados();

        return [
            'estados' => $estados,
            'pendientes' => $estados['PENDIENTE']
                + $estados['PENDIENTE_PROXIMA_A_VENCER']
                + $estados['PENDIENTE_VENCIDA'],
            'alertas_activas' => $estados['PENDIENTE_PROXIMA_A_VENCER']
                + $estados['PENDIENTE_VENCIDA']
                + $estados['PROXIMA_A_VENCER']
                + $estados['VENCIDA'],
            'alertas' => $this->alertas(15),
        ];
    }

    public static function etiquetaDias(?int $dias): ?string
    {
        if ($dias === null) {
            return null;
        }

        if ($dias < 0) {
            return 'Vencida';
        }

        if ($dias === 0) {
            return 'Vence hoy';
        }

        if ($dias === 1) {
            return 'Falta 1 día';
        }

        return "Faltan {$dias} días";
    }
}
