-- Alcance del plan anual: varios cargos/procesos por actividad (p. ej. inducción).
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/24_plan_detalle_alcances.sql

USE meridian_capacitaciones;

CREATE TABLE IF NOT EXISTS plan_detalle_alcances (
  plan_detalle_alcance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_detalle_id INT UNSIGNED NOT NULL,
  proceso_id INT UNSIGNED NOT NULL,
  cargo_id_ext INT UNSIGNED NOT NULL,
  proyecto VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pda_alcance (plan_detalle_id, proceso_id, cargo_id_ext, proyecto),
  KEY ix_pda_alc_proceso (proceso_id),
  CONSTRAINT fk_pda_alc_detalle FOREIGN KEY (plan_detalle_id)
    REFERENCES plan_anual_detalle(plan_detalle_id) ON DELETE CASCADE,
  CONSTRAINT fk_pda_alc_proceso FOREIGN KEY (proceso_id) REFERENCES procesos(proceso_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO plan_detalle_alcances (plan_detalle_id, proceso_id, cargo_id_ext, proyecto)
SELECT d.plan_detalle_id,
       m.proceso_id,
       m.cargo_id_ext,
       IFNULL(NULLIF(TRIM(d.proyecto), ''), '')
FROM plan_anual_detalle d
INNER JOIN matriz_aplicabilidad m
        ON m.capacitacion_id = d.capacitacion_id
       AND m.proceso_id = d.proceso_id
       AND m.activa = 1
       AND m.cargo_id_ext IS NOT NULL
INNER JOIN capacitaciones cap ON cap.capacitacion_id = m.capacitacion_id AND cap.estado = 'ACTIVA'
WHERE d.proceso_id IS NOT NULL
  AND IFNULL(NULLIF(TRIM(m.proyecto), ''), '') COLLATE utf8mb4_unicode_ci
      = IFNULL(NULLIF(TRIM(d.proyecto), ''), '') COLLATE utf8mb4_unicode_ci;
