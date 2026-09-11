<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Cargo y proceso efectivos de una asignación.
 * Si el snapshot está vacío (inducción/manual), se usa el cargo actual
 * de la persona y el proceso de la matriz para ese cargo.
 */
final class ContextoLaboralSql
{
    public static function cargoId(string $asignacion = 'a', string $persona = 'per'): string
    {
        return "COALESCE({$asignacion}.cargo_id_ext, {$persona}.cargo_id)";
    }

    public static function procesoId(string $asignacion = 'a', string $persona = 'per'): string
    {
        $cargo = self::cargoId($asignacion, $persona);

        return "COALESCE({$asignacion}.proceso_id, (
            SELECT m.proceso_id
            FROM matriz_aplicabilidad m
            INNER JOIN capacitaciones capm ON capm.capacitacion_id = m.capacitacion_id
            WHERE m.activa = 1
              AND capm.estado = 'ACTIVA'
              AND m.proceso_id IS NOT NULL
              AND m.cargo_id_ext = {$cargo}
            ORDER BY
              CASE WHEN m.capacitacion_id = {$asignacion}.capacitacion_id THEN 0 ELSE 1 END,
              m.proceso_id
            LIMIT 1
        ))";
    }
}
