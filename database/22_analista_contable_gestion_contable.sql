-- Analista Contable pertenece a Gestión Contable (no a Gestión Estratégica).
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/22_analista_contable_gestion_contable.sql

USE meridian_capacitaciones;

UPDATE matriz_aplicabilidad m
INNER JOIN meridian_personal.cargos c ON c.cargo_id = m.cargo_id_ext
INNER JOIN procesos origen ON origen.proceso_id = m.proceso_id
INNER JOIN procesos destino ON destino.nombre = 'GESTION CONTABLE' AND destino.activo = 1
SET m.proceso_id = destino.proceso_id
WHERE c.nombre_cargo = 'ANALISTA CONTABLE'
  AND origen.nombre = 'GESTION ESTRATEGICA';
