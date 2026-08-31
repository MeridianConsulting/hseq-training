-- Puente entre detalle del plan anual y asignaciones RF-008.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/07_plan_detalle_asignaciones.sql

CREATE TABLE IF NOT EXISTS plan_detalle_asignaciones (
  plan_detalle_asignacion_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_detalle_id INT UNSIGNED NOT NULL,
  asignacion_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plan_det_asig (plan_detalle_id, asignacion_id),
  KEY ix_plan_det_asig_asignacion (asignacion_id),
  CONSTRAINT fk_pda_detalle FOREIGN KEY (plan_detalle_id) REFERENCES plan_anual_detalle(plan_detalle_id),
  CONSTRAINT fk_pda_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_capacitacion(asignacion_id)
) ENGINE=InnoDB;
