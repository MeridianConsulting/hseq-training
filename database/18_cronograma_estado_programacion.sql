-- Estado operativo de la programación en el cronograma (RF-TC-010 / 014).
-- No elimina el detalle ni toca capacitación, matriz o plan anual.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/18_cronograma_estado_programacion.sql

USE meridian_capacitaciones;

ALTER TABLE plan_anual_detalle
  ADD COLUMN estado_programacion VARCHAR(20) NOT NULL DEFAULT 'PROGRAMADA' AFTER cantidad_programada;

UPDATE plan_anual_detalle
SET estado_programacion = 'PROGRAMADA'
WHERE estado_programacion IS NULL OR TRIM(estado_programacion) = '';
