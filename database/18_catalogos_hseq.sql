-- Catálogos HSEQ: proyectos como fuente maestra + semilla de procesos/modalidades/tipos.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/18_catalogos_hseq.sql
-- No borra historial. Gestión de Tecnología se inactiva si existe.

USE meridian_capacitaciones;

CREATE TABLE IF NOT EXISTS proyectos (
  proyecto_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_proyectos_nombre (nombre)
) ENGINE=InnoDB;

INSERT INTO procesos (nombre, activo)
SELECT 'GESTION CONTABLE', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM procesos WHERE LOWER(TRIM(nombre)) IN (LOWER('GESTION CONTABLE'), LOWER('Gestión Contable'))
);

INSERT INTO procesos (nombre, activo)
SELECT 'LICITACIONES', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM procesos WHERE LOWER(TRIM(nombre)) = LOWER('LICITACIONES')
);

UPDATE procesos
SET nombre = 'GESTION CONTABLE'
WHERE nombre <> 'GESTION CONTABLE'
  AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        UPPER(TRIM(nombre)), 'Á','A'), 'É','E'), 'Í','I'), 'Ó','O'), 'Ú','U')
      = 'GESTION CONTABLE';

UPDATE procesos
SET nombre = 'LICITACIONES'
WHERE LOWER(TRIM(nombre)) = 'licitaciones'
  AND nombre <> 'LICITACIONES';

-- PetroServicios es proyecto de obra, no proceso HSEQ.
UPDATE procesos
SET activo = 0
WHERE activo = 1
  AND LOWER(TRIM(nombre)) = 'petroservicios';

UPDATE procesos
SET activo = 0
WHERE activo = 1
  AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        UPPER(TRIM(nombre)), 'Á','A'), 'É','E'), 'Í','I'), 'Ó','O'), 'Ú','U')
      LIKE '%GESTION DE TECNOLOGIA%';

INSERT INTO proyectos (nombre, activo)
SELECT 'FRONTERA', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM proyectos WHERE LOWER(TRIM(nombre)) = LOWER('FRONTERA')
);

INSERT INTO proyectos (nombre, activo)
SELECT 'PETROSERVICIOS', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM proyectos WHERE LOWER(TRIM(nombre)) = LOWER('PETROSERVICIOS')
);

UPDATE proyectos
SET nombre = 'PETROSERVICIOS'
WHERE LOWER(TRIM(nombre)) = 'petroservicios'
  AND nombre <> BINARY 'PETROSERVICIOS';

INSERT INTO proyectos (nombre, activo)
SELECT 'CW', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM proyectos WHERE LOWER(TRIM(nombre)) = LOWER('CW')
);

INSERT INTO modalidades (nombre, activo)
SELECT 'PRESENCIAL', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM modalidades WHERE LOWER(TRIM(nombre)) = LOWER('PRESENCIAL')
);

INSERT INTO modalidades (nombre, activo)
SELECT 'VIRTUAL', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM modalidades WHERE LOWER(TRIM(nombre)) = LOWER('VIRTUAL')
);

INSERT INTO modalidades (nombre, activo)
SELECT 'MIXTA', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM modalidades WHERE LOWER(TRIM(nombre)) = LOWER('MIXTA')
);

INSERT INTO tipos_capacitacion (nombre, descripcion, activo)
SELECT 'INDUCCION', 'Inducción al cargo / ingreso', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER('INDUCCION')
);

INSERT INTO tipos_capacitacion (nombre, descripcion, activo)
SELECT 'REINDUCCION', 'Reinducción periódica', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER('REINDUCCION')
);

INSERT INTO tipos_capacitacion (nombre, descripcion, activo)
SELECT 'OBLIGATORIA', 'Capacitación obligatoria del programa HSEQ', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER('OBLIGATORIA')
);

INSERT INTO tipos_capacitacion (nombre, descripcion, activo)
SELECT 'TECNICA', 'Capacitación técnica', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER('TECNICA')
);

INSERT INTO tipos_capacitacion (nombre, descripcion, activo)
SELECT 'BIENESTAR', 'Capacitación de bienestar', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER('BIENESTAR')
);

INSERT INTO tipos_capacitacion (nombre, descripcion, activo)
SELECT 'CAPACITACION GENERAL', 'Capacitación general del programa HSEQ', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER('CAPACITACION GENERAL')
);

-- Tarea crítica es el Sí/No de la capacitación, no un tipo.
UPDATE tipos_capacitacion
SET activo = 0
WHERE activo = 1
  AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        UPPER(TRIM(nombre)), 'Á','A'), 'É','E'), 'Í','I'), 'Ó','O'), 'Ú','U')
      = 'TAREA CRITICA';
