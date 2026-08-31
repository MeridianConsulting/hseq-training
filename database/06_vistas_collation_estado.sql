-- Alinea el CASE de estado_calculado con utf8mb4_unicode_ci (PDO SET NAMES).
-- Evita Error 1267 al filtrar asignaciones por estado.
-- Uso: mysql -u root meridian_capacitaciones < database/06_vistas_collation_estado.sql

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
         AND a.fecha_limite_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
      THEN 'PENDIENTE_PROXIMA_A_VENCER'
    WHEN c.cumplimiento_id IS NULL
      THEN 'PENDIENTE'
    WHEN c.fecha_vencimiento IS NOT NULL AND c.fecha_vencimiento < CURDATE()
      THEN 'VENCIDA'
    WHEN c.fecha_vencimiento IS NOT NULL
         AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
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
