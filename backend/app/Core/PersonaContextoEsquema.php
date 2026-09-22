<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Asegura la tabla persona_contexto_hseq (proceso/proyecto por trabajador).
 * Idempotente: al arrancar la API basta un pull en otro PC.
 */
class PersonaContextoEsquema
{
    private static bool $hecho = false;

    public static function asegurar(): void
    {
        if (self::$hecho) {
            return;
        }
        self::$hecho = true;

        try {
            self::asegurarTabla();
        } catch (Throwable $e) {
            Logger::error('No fue posible asegurar persona_contexto_hseq: ' . $e->getMessage());
        }
    }

    private static function asegurarTabla(): void
    {
        $db = Database::getInstance();
        $fila = $db->fetch(
            "SELECT COUNT(*) AS total
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'persona_contexto_hseq'"
        );
        if ((int)($fila['total'] ?? 0) > 0) {
            return;
        }

        $db->getConnection()->exec(
            "CREATE TABLE persona_contexto_hseq (
              persona_contexto_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              persona_id_ext INT UNSIGNED NOT NULL COMMENT 'meridian_personal.personas.persona_id',
              numero_documento VARCHAR(30) NOT NULL COMMENT 'Documento normalizado para enlace con meridian_personal',
              proceso_id INT UNSIGNED NOT NULL,
              proyecto VARCHAR(120) NULL COMMENT 'Requerido solo si el proceso es de proyectos',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_persona_contexto_persona (persona_id_ext),
              UNIQUE KEY uq_persona_contexto_documento (numero_documento),
              KEY ix_persona_contexto_proceso (proceso_id),
              KEY ix_persona_contexto_proyecto (proyecto),
              CONSTRAINT fk_persona_contexto_proceso FOREIGN KEY (proceso_id) REFERENCES procesos(proceso_id)
            ) ENGINE=InnoDB"
        );
    }
}
