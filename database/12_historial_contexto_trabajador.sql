-- Historial laboral HSEQ: cargo y proyecto del trabajador por periodo.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/12_historial_contexto_trabajador.sql
-- No se toca meridian_personal. proceso_id no existe en el trabajador.

USE meridian_capacitaciones;

CREATE TABLE IF NOT EXISTS historial_contexto_trabajador (
  historial_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  persona_id_ext INT UNSIGNED NOT NULL COMMENT 'meridian_personal.personas.persona_id',
  cargo_id_ext INT UNSIGNED NULL COMMENT 'Snapshot de personas.cargo_id',
  proyecto VARCHAR(120) NULL COMMENT 'Snapshot de contratos.proyecto',
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NULL COMMENT 'NULL = periodo abierto (actual)',
  origen ENUM('ALTA','EDICION') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_hist_persona_vigencia (persona_id_ext, vigente_desde),
  KEY ix_hist_persona_abierto (persona_id_ext, vigente_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
