-- COMPLETADA exige resultado APROBADO, evaluación si aplica, y al menos un soporte
-- si la capacitación pide certificado o listado (cualquier tipo de archivo cuenta).
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/17_estado_cumplimiento_requisitos.sql

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
    WHEN c.resultado IS NULL OR c.resultado <> 'APROBADO'
      THEN 'PENDIENTE'
    WHEN cap.evaluacion = 1 AND (c.nota_evaluacion IS NULL OR c.nota_evaluacion < cap.nota_minima)
      THEN 'PENDIENTE'
    WHEN (cap.certificado = 1 OR cap.requiere_listado_asistencia = 1) AND NOT EXISTS (
           SELECT 1 FROM soportes_cumplimiento so WHERE so.cumplimiento_id = c.cumplimiento_id
         )
      THEN 'PENDIENTE'
    WHEN c.fecha_vencimiento IS NOT NULL AND c.fecha_vencimiento < CURDATE()
      THEN 'VENCIDA'
    WHEN c.fecha_vencimiento IS NOT NULL
         AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      THEN 'PROXIMA_A_VENCER'
    ELSE 'COMPLETADA'
  END COLLATE utf8mb4_unicode_ci AS estado_calculado
FROM asignaciones_capacitacion a
LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
INNER JOIN capacitaciones cap ON cap.capacitacion_id = a.capacitacion_id;

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
