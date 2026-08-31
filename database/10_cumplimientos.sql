-- Permisos de registro de cumplimiento. La tabla cumplimientos_capacitacion ya existe.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/10_cumplimientos.sql

USE meridian_capacitaciones;

INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
('cumplimientos.crear', 'Registrar cumplimiento de capacitacion'),
('cumplimientos.editar', 'Editar cumplimiento de capacitacion');

INSERT IGNORE INTO rol_permisos (role_id, permiso_id)
SELECT r.role_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre = 'Administrador HSEQ'
  AND p.codigo IN ('cumplimientos.crear', 'cumplimientos.editar');

ALTER TABLE cumplimientos_capacitacion
  ADD COLUMN IF NOT EXISTS observaciones VARCHAR(500) NULL AFTER nota_evaluacion;
