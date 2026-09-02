-- Índices para filtros frecuentes de listados y reportes.
-- Aditivo. Uso: mysql -u root meridian_capacitaciones < database/15_indices_filtros.sql
-- Nota: fecha_realizacion ya tiene ix_cumplimiento_realizacion en algunos entornos.

USE meridian_capacitaciones;

-- proceso_id (reportes, alertas, asignaciones)
SET @existe := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'asignaciones_capacitacion' AND index_name = 'ix_asig_proceso'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE asignaciones_capacitacion ADD KEY ix_asig_proceso (proceso_id)',
  'SELECT ''ix_asig_proceso ya existe'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'asignaciones_capacitacion' AND index_name = 'ix_asig_cargo'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE asignaciones_capacitacion ADD KEY ix_asig_cargo (cargo_id_ext)',
  'SELECT ''ix_asig_cargo ya existe'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'asignaciones_capacitacion' AND index_name = 'ix_asig_fecha_asignacion'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE asignaciones_capacitacion ADD KEY ix_asig_fecha_asignacion (fecha_asignacion)',
  'SELECT ''ix_asig_fecha_asignacion ya existe'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'cumplimientos_capacitacion' AND index_name = 'ix_cumplimiento_venc_resultado'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cumplimientos_capacitacion ADD KEY ix_cumplimiento_venc_resultado (fecha_vencimiento, resultado)',
  'SELECT ''ix_cumplimiento_venc_resultado ya existe'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
