-- Alinear meridian_capacitaciones.auditoria con el codigo (valor_anterior / valor_nuevo).
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/13_auditoria_columnas.sql

USE meridian_capacitaciones;

SET @existe_nombre := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria'
      AND COLUMN_NAME = 'usuario_nombre'
);
SET @sql_nombre := IF(
    @existe_nombre = 0,
    'ALTER TABLE auditoria ADD COLUMN usuario_nombre VARCHAR(120) NULL AFTER usuario_id_ext',
    'SELECT 1'
);
PREPARE stmt_nombre FROM @sql_nombre;
EXECUTE stmt_nombre;
DEALLOCATE PREPARE stmt_nombre;

SET @existe_ant := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria'
      AND COLUMN_NAME = 'valor_anterior'
);
SET @sql_ant := IF(
    @existe_ant = 0,
    'ALTER TABLE auditoria ADD COLUMN valor_anterior LONGTEXT NULL AFTER entidad_id',
    'SELECT 1'
);
PREPARE stmt_ant FROM @sql_ant;
EXECUTE stmt_ant;
DEALLOCATE PREPARE stmt_ant;

SET @existe_nuevo := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria'
      AND COLUMN_NAME = 'valor_nuevo'
);
SET @sql_nuevo := IF(
    @existe_nuevo = 0,
    'ALTER TABLE auditoria ADD COLUMN valor_nuevo LONGTEXT NULL AFTER valor_anterior',
    'SELECT 1'
);
PREPARE stmt_nuevo FROM @sql_nuevo;
EXECUTE stmt_nuevo;
DEALLOCATE PREPARE stmt_nuevo;

SET @existe_ix := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria'
      AND INDEX_NAME = 'ix_auditoria_accion_fecha'
);
SET @sql_ix := IF(
    @existe_ix = 0,
    'ALTER TABLE auditoria ADD KEY ix_auditoria_accion_fecha (accion, created_at)',
    'SELECT 1'
);
PREPARE stmt_ix FROM @sql_ix;
EXECUTE stmt_ix;
DEALLOCATE PREPARE stmt_ix;
