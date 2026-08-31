-- Permisos de sesiones. Las tablas sesiones_capacitacion y sesion_participantes
-- ya existen. No duplicar columnas: estado (PROGRAMADA|EJECUTADA|CANCELADA),
-- observaciones e ix_sesion_fecha ya estan en la base en uso.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/08_sesiones.sql

USE meridian_capacitaciones;

INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
('sesiones.crear', 'Crear sesiones de capacitacion'),
('sesiones.editar', 'Editar sesiones y gestionar convocados');

INSERT IGNORE INTO rol_permisos (role_id, permiso_id)
SELECT r.role_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre = 'Administrador HSEQ'
  AND p.codigo IN ('sesiones.crear', 'sesiones.editar');
