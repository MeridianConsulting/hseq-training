-- Usuarios de acceso propios del modulo HSEQ (meridian_capacitaciones).
-- No usa meridian_personal.usuarios_sistema.

USE meridian_capacitaciones;

CREATE TABLE IF NOT EXISTS usuarios (
  usuario_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_usuario VARCHAR(50) NOT NULL,
  correo VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol VARCHAR(50) NOT NULL DEFAULT 'usuario',
  estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  intentos_fallidos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta TIMESTAMP NULL DEFAULT NULL,
  ultimo_acceso TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_nombre_usuario (nombre_usuario),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY ix_usuarios_estado (estado)
) ENGINE=InnoDB;

-- user_roles pasa a referenciar usuarios locales.
ALTER TABLE user_roles
  DROP INDEX uq_user_roles_user_role,
  DROP INDEX ix_user_roles_usuario;

ALTER TABLE user_roles
  CHANGE usuario_id_ext usuario_id INT UNSIGNED NOT NULL;

ALTER TABLE user_roles
  ADD UNIQUE KEY uq_user_roles_user_role (usuario_id, role_id),
  ADD KEY ix_user_roles_usuario (usuario_id),
  ADD CONSTRAINT fk_user_roles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id);
