-- Fecha concreta de programación en el plan anual (RF-PA-008 / 017).
-- mes_programado se conserva y se deriva del mes de fecha_programada.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/17_plan_fecha_programada.sql

USE meridian_capacitaciones;

ALTER TABLE plan_anual_detalle
  ADD COLUMN fecha_programada DATE NULL AFTER mes_programado;

UPDATE plan_anual_detalle d
INNER JOIN planes_anuales p ON p.plan_anual_id = d.plan_anual_id
SET d.fecha_programada = STR_TO_DATE(
  CONCAT(p.anio, '-', LPAD(d.mes_programado, 2, '0'), '-01'),
  '%Y-%m-%d'
)
WHERE d.fecha_programada IS NULL;

ALTER TABLE plan_anual_detalle
  MODIFY fecha_programada DATE NOT NULL;
