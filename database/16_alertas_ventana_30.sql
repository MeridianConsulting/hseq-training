-- Ventana de alertas: 30 días (RF-AL-002 / RF-AL-003).
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/16_alertas_ventana_30.sql

USE meridian_capacitaciones;

CREATE OR REPLACE VIEW vw_estado_asignaciones AS
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
    WHEN c.fecha_vencimiento IS NOT NULL AND c.fecha_vencimiento < CURDATE()
      THEN 'VENCIDA'
    WHEN c.fecha_vencimiento IS NOT NULL
         AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      THEN 'PROXIMA_A_VENCER'
    ELSE 'COMPLETADA'
  END COLLATE utf8mb4_unicode_ci AS estado_calculado
FROM asignaciones_capacitacion a
LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id;

CREATE OR REPLACE VIEW vw_alertas_vencimiento AS
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
);
