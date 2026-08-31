-- Columna activo en catalogos HSEQ que aun no la tenian.
-- Aditivo, sin DROP. Las filas existentes quedan activas (DEFAULT 1).
-- Uso: mysql -u root meridian_capacitaciones < database/05_catalogos_columna_activo.sql

USE meridian_capacitaciones;

ALTER TABLE areas
  ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER nombre;

ALTER TABLE procesos
  ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER nombre;

ALTER TABLE roles
  ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER nombre;
