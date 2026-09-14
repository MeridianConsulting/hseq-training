<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Recrea las vistas de estado/alertas si faltan o estan desactualizadas.
 * Se ejecuta al arrancar la API para que un git pull baste en otro PC.
 */
class VistasEsquema
{
    private static bool $hecho = false;

    public static function asegurar(): void
    {
        if (self::$hecho) {
            return;
        }
        self::$hecho = true;

        try {
            $db = Database::getInstance();
            if (self::listas($db)) {
                return;
            }
            self::recrear($db);
        } catch (Throwable $e) {
            Logger::error('No fue posible actualizar las vistas de asignaciones: ' . $e->getMessage());
        }
    }

    private static function listas(Database $db): bool
    {
        $fila = $db->fetch(
            "SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'vw_estado_asignaciones'
               AND COLUMN_NAME = 'estado_calculado'"
        );
        if ((int)($fila['total'] ?? 0) < 1) {
            return false;
        }

        try {
            $db->fetch('SELECT 1 AS ok FROM vw_alertas_vencimiento LIMIT 1');
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    private static function recrear(Database $db): void
    {
        $pdo = $db->getConnection();
        $pdo->exec(
            "CREATE OR REPLACE VIEW vw_estado_asignaciones AS
             SELECT
               a.asignacion_id,
               a.persona_id_ext,
               a.capacitacion_id,
               a.proyecto,
               a.fecha_limite_cumplimiento,
               c.cumplimiento_id,
               c.fecha_realizacion,
               c.fecha_vencimiento,
               CASE
                 WHEN c.cumplimiento_id IS NULL
                      AND a.fecha_limite_cumplimiento < CURDATE()
                   THEN 'PENDIENTE_VENCIDA'
                 WHEN c.cumplimiento_id IS NULL
                      AND a.fecha_limite_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                   THEN 'PENDIENTE_PROXIMA_A_VENCER'
                 WHEN c.cumplimiento_id IS NULL
                   THEN 'PENDIENTE'
                 WHEN c.resultado IS NULL OR c.resultado <> 'APROBADO'
                   THEN 'PENDIENTE'
                 WHEN cap.evaluacion = 1 AND (c.nota_evaluacion IS NULL OR c.nota_evaluacion < cap.nota_minima)
                   THEN 'PENDIENTE'
                 WHEN (cap.certificado = 1 OR cap.requiere_listado_asistencia = 1) AND NOT EXISTS (
                        SELECT 1 FROM soportes_cumplimiento so WHERE so.cumplimiento_id = c.cumplimiento_id
                      )
                   THEN 'PENDIENTE'
                 WHEN c.fecha_vencimiento IS NOT NULL AND c.fecha_vencimiento < CURDATE()
                   THEN 'VENCIDA'
                 WHEN c.fecha_vencimiento IS NOT NULL
                      AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                   THEN 'PROXIMA_A_VENCER'
                 ELSE 'COMPLETADA'
               END COLLATE utf8mb4_unicode_ci AS estado_calculado
             FROM asignaciones_capacitacion a
             LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
             INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id"
        );
        $pdo->exec(
            "CREATE OR REPLACE VIEW vw_alertas_vencimiento AS
             SELECT
               v.*,
               CASE
                 WHEN v.estado_calculado IN ('PENDIENTE_VENCIDA', 'PENDIENTE_PROXIMA_A_VENCER')
                   THEN 'LIMITE_CUMPLIMIENTO'
                 ELSE 'VIGENCIA_CUMPLIMIENTO'
               END COLLATE utf8mb4_unicode_ci AS tipo_alerta,
               CASE
                 WHEN v.estado_calculado IN ('PENDIENTE_VENCIDA', 'PENDIENTE_PROXIMA_A_VENCER')
                   THEN v.fecha_limite_cumplimiento
                 ELSE v.fecha_vencimiento
               END AS fecha_alerta
             FROM vw_estado_asignaciones v
             WHERE v.estado_calculado IN (
               'PENDIENTE_VENCIDA',
               'PENDIENTE_PROXIMA_A_VENCER',
               'VENCIDA',
               'PROXIMA_A_VENCER'
             )"
        );
    }
}
