<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Asegura columnas opcionales de plan_anual_detalle (planeación sin fecha obligatoria).
 * Idempotente: al arrancar la API basta un pull en otro PC.
 */
class PlanEsquema
{
    private static bool $hecho = false;

    public static function asegurar(): void
    {
        if (self::$hecho) {
            return;
        }
        self::$hecho = true;

        try {
            self::asegurarFechasNullable();
        } catch (Throwable $e) {
            Logger::error('No fue posible actualizar el esquema del plan anual: ' . $e->getMessage());
        }
    }

    private static function asegurarFechasNullable(): void
    {
        $db = Database::getInstance();
        $filas = $db->fetchAll(
            "SELECT COLUMN_NAME, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'plan_anual_detalle'
               AND COLUMN_NAME IN ('fecha_programada', 'mes_programado')"
        );
        if ($filas === []) {
            return;
        }

        $necesitaFecha = false;
        $necesitaMes = false;
        foreach ($filas as $fila) {
            $nullable = strtoupper((string)($fila['IS_NULLABLE'] ?? '')) === 'YES';
            if ((string)$fila['COLUMN_NAME'] === 'fecha_programada' && !$nullable) {
                $necesitaFecha = true;
            }
            if ((string)$fila['COLUMN_NAME'] === 'mes_programado' && !$nullable) {
                $necesitaMes = true;
            }
        }

        if (!$necesitaFecha && !$necesitaMes) {
            return;
        }

        $pdo = $db->getConnection();
        if ($necesitaFecha) {
            $pdo->exec(
                'ALTER TABLE plan_anual_detalle
                 MODIFY COLUMN fecha_programada DATE NULL'
            );
        }
        if ($necesitaMes) {
            $pdo->exec(
                'ALTER TABLE plan_anual_detalle
                 MODIFY COLUMN mes_programado TINYINT UNSIGNED NULL'
            );
        }
    }
}
