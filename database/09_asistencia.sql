-- Observación por convocado. motivo_ausencia y el ENUM CONVOCADO|ASISTIO|TARDE|AUSENTE ya existen.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/09_asistencia.sql

USE meridian_capacitaciones;

ALTER TABLE sesion_participantes
  ADD COLUMN IF NOT EXISTS observacion VARCHAR(500) NULL AFTER motivo_ausencia;
