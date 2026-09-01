-- Permisos HSEQ (aditivo, sin DROP).
-- No ejecutar hasta autorizacion explicita. El login admin no depende de estas tablas:
-- el codigo otorga todos los permisos al rol Administrador HSEQ.
--
-- Uso (cuando se autorice): mysql -u root meridian_capacitaciones < database/02_permisos.sql

USE meridian_capacitaciones;

CREATE TABLE IF NOT EXISTS permisos (
  permiso_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL,
  descripcion VARCHAR(180) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_permisos_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rol_permisos (
  rol_permiso_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rol_permiso (role_id, permiso_id),
  CONSTRAINT fk_rol_permisos_rol FOREIGN KEY (role_id) REFERENCES roles(role_id),
  CONSTRAINT fk_rol_permisos_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(permiso_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
('dashboard.ver', 'Ver panel de control'),
('reportes.ver', 'Ver reportes'),
('alertas.ver', 'Ver alertas de capacitaciones próximas a vencer'),
('capacitaciones.ver', 'Ver capacitaciones'),
('capacitaciones.crear', 'Crear capacitaciones'),
('capacitaciones.editar', 'Editar capacitaciones'),
('capacitaciones.eliminar', 'Eliminar o inactivar capacitaciones'),
('matriz.ver', 'Ver matriz de aplicabilidad'),
('matriz.crear', 'Crear filas de matriz'),
('matriz.editar', 'Editar filas de matriz'),
('matriz.eliminar', 'Eliminar filas de matriz'),
('planes.ver', 'Ver plan anual'),
('planes.crear', 'Crear plan anual'),
('planes.editar', 'Editar plan anual y enviar a revisión'),
('planes.aprobar', 'Aprobar plan anual'),
('sesiones.ver', 'Ver sesiones'),
('sesiones.crear', 'Crear sesiones de capacitacion'),
('sesiones.editar', 'Editar sesiones y gestionar convocados'),
('personal.ver', 'Consultar personal corporativo'),
('personal.crear', 'Registrar personal corporativo'),
('personal.editar', 'Editar personal corporativo'),
('personal.importar', 'Carga masiva de personal corporativo'),
('asignaciones.ver', 'Ver asignaciones'),
('asignaciones.crear', 'Asignar capacitaciones a personal'),
('asignaciones.editar', 'Editar fecha limite de asignaciones'),
('asignaciones.eliminar', 'Eliminar asignaciones sin cumplimiento'),
('cumplimientos.ver', 'Ver cumplimientos'),
('catalogos.ver', 'Ver catalogos'),
('catalogos.gestionar', 'Crear, editar y eliminar catalogos'),
('auditoria.ver', 'Ver auditoria del modulo');

INSERT IGNORE INTO roles (nombre) VALUES ('Administrador HSEQ');

INSERT IGNORE INTO rol_permisos (role_id, permiso_id)
SELECT r.role_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre = 'Administrador HSEQ';
