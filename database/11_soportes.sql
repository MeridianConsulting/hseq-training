-- Soportes de cumplimiento: tipos adicionales y tamano.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/11_soportes.sql

USE meridian_capacitaciones;

ALTER TABLE soportes_cumplimiento
  MODIFY COLUMN tipo_soporte ENUM('CERTIFICADO','LISTADO_ASISTENCIA','OTRO') NOT NULL;

SET @existe_tamano := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'soportes_cumplimiento'
    AND COLUMN_NAME = 'tamano_bytes'
);
SET @sql_tamano := IF(
  @existe_tamano = 0,
  'ALTER TABLE soportes_cumplimiento ADD COLUMN tamano_bytes INT UNSIGNED NULL AFTER mime_type',
  'SELECT 1'
);
PREPARE stmt_tamano FROM @sql_tamano;
EXECUTE stmt_tamano;
DEALLOCATE PREPARE stmt_tamano;
