<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

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
 * TODO induccion/reinduccion: asignaciones.origen ya admite INDUCCION y REINDUCCION.
 * No inventar reglas de disparo hasta definirlas con HSEQ.
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

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,int> */
    public function resumenEstados(): array
    {
        $conteos = array_fill_keys(self::ESTADOS, 0);

        try {
            $filas = $this->db->fetchAll(
                'SELECT estado_calculado, COUNT(*) AS total
                 FROM vw_estado_asignaciones
                 GROUP BY estado_calculado'
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
                "SELECT asignacion_id, persona_id_ext, capacitacion_id, proyecto,
                        fecha_limite_cumplimiento, fecha_realizacion, fecha_vencimiento,
                        estado_calculado, tipo_alerta, fecha_alerta
                 FROM vw_alertas_vencimiento
                 ORDER BY fecha_alerta ASC
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
