-- Carga inicial desde la matriz Excel HSEQ (RF-021).
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/14_migracion.sql

USE meridian_capacitaciones;

CREATE TABLE IF NOT EXISTS migraciones (
  migracion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id_ext INT UNSIGNED NULL,
  usuario_nombre VARCHAR(120) NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  ruta_archivo VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NULL,
  tamano_bytes INT UNSIGNED NULL,
  anio_programa SMALLINT UNSIGNED NOT NULL,
  estado ENUM('VALIDADA','CONFIRMADA','CANCELADA','FALLIDA') NOT NULL DEFAULT 'VALIDADA',
  resumen_json LONGTEXT NULL,
  inconsistencias_json LONGTEXT NULL,
  conteos_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmada_at TIMESTAMP NULL,
  KEY ix_migraciones_estado_fecha (estado, created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
('migracion.ejecutar', 'Carga inicial desde la matriz Excel HSEQ');

INSERT IGNORE INTO rol_permisos (role_id, permiso_id)
SELECT r.role_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre = 'Administrador HSEQ'
  AND p.codigo = 'migracion.ejecutar';
