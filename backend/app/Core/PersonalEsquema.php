<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Asegura columnas de meridian_personal que el código HSEQ necesita.
 * Idempotente: al arrancar la API basta un pull en otro PC.
 */
class PersonalEsquema
{
    private static bool $hecho = false;

    public static function asegurar(): void
    {
        if (self::$hecho) {
            return;
        }
        self::$hecho = true;

        try {
            self::asegurarFechaInactivacion();
        } catch (Throwable $e) {
            Logger::error('No fue posible actualizar el esquema de personal: ' . $e->getMessage());
        }
    }

    private static function asegurarFechaInactivacion(): void
    {
        $db = Database::personal();
        $schema = Database::personalName();
        $fila = $db->fetch(
            "SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'personas'
               AND COLUMN_NAME = 'fecha_inactivacion'",
            [$schema]
        );
        if ((int)($fila['total'] ?? 0) > 0) {
            return;
        }

        $tabla = Database::personalTable('personas');
        $db->getConnection()->exec(
            "ALTER TABLE {$tabla}
             ADD COLUMN fecha_inactivacion DATETIME NULL DEFAULT NULL AFTER estado"
        );
    }
}
