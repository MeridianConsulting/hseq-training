-- Propuesta de estructura para el Sistema de Gestion y Seguimiento del Programa de Capacitacion HSEQ
-- Basada en:
-- 1) 02_HSEQ_PRG_10_Capacitacion_entrenamiento_Frontera.xlsx
-- 2) Formato levantamiento de informacion - HSEQ.xlsx
-- 3) meridian_personal (24_08_2026_meridian_personal.sql)
--
-- Criterio de integracion:
-- - NO se crean tablas locales de trabajadores, usuarios, cargos ni proyectos.
-- - persona_id_ext, contrato_id_ext, cargo_id_ext y usuario_id_ext son referencias logicas a meridian_personal.
-- - El proyecto se conserva como VARCHAR porque meridian_personal.contratos.proyecto no tiene un proyecto_id normalizado.
-- - La auditoria puede reutilizar meridian_personal.auditoria.
-- - Alertas, indicadores, resumenes y reportes deben calcularse desde los datos operativos; no requieren tablas propias.

CREATE DATABASE IF NOT EXISTS meridian_hseq_capacitaciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meridian_hseq_capacitaciones;

-- =========================================================
-- CATALOGOS QUE SI SON PROPIOS DEL MODULO HSEQ
-- =========================================================

CREATE TABLE areas (
  area_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_areas_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE procesos (
  proceso_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_procesos_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE categorias_capacitacion (
  categoria_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categorias_cap_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE tipos_capacitacion (
  tipo_capacitacion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tipos_cap_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE modalidades (
  modalidad_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_modalidades_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE periodicidades (
  periodicidad_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  cantidad SMALLINT UNSIGNED NOT NULL,
  unidad ENUM('DIAS','MESES','ANIOS') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_periodicidades_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE proveedores_capacitadores (
  proveedor_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_proveedores_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE ubicaciones (
  ubicacion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ubicaciones_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE fuentes_normativas (
  fuente_normativa_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fuentes_normativas_nombre (nombre)
) ENGINE=InnoDB;

-- =========================================================
-- CATALOGO PRINCIPAL DE CAPACITACIONES
-- =========================================================

CREATE TABLE capacitaciones (
  capacitacion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  objetivo TEXT NOT NULL,
  descripcion_temario TEXT NULL,
  categoria_id INT UNSIGNED NULL,
  tipo_capacitacion_id INT UNSIGNED NULL,
  modalidad_default_id INT UNSIGNED NULL,
  duracion_estimada_horas DECIMAL(6,2) NOT NULL,
  criticidad VARCHAR(30) NOT NULL,
  es_tarea_critica TINYINT(1) NOT NULL DEFAULT 0,
  responsable VARCHAR(120) NULL,
  proveedor_default_id INT UNSIGNED NULL,
  periodicidad_default_id INT UNSIGNED NULL,
  requiere_evaluacion TINYINT(1) NOT NULL DEFAULT 0,
  nota_minima DECIMAL(5,2) NULL,
  requiere_certificado TINYINT(1) NOT NULL DEFAULT 0,
  requiere_listado_asistencia TINYINT(1) NOT NULL DEFAULT 0,
  fuente_normativa_id INT UNSIGNED NULL,
  estado ENUM('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  creado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_capacitaciones_codigo (codigo),
  CONSTRAINT fk_cap_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_capacitacion(categoria_id),
  CONSTRAINT fk_cap_tipo FOREIGN KEY (tipo_capacitacion_id) REFERENCES tipos_capacitacion(tipo_capacitacion_id),
  CONSTRAINT fk_cap_modalidad FOREIGN KEY (modalidad_default_id) REFERENCES modalidades(modalidad_id),
  CONSTRAINT fk_cap_proveedor FOREIGN KEY (proveedor_default_id) REFERENCES proveedores_capacitadores(proveedor_id),
  CONSTRAINT fk_cap_periodicidad FOREIGN KEY (periodicidad_default_id) REFERENCES periodicidades(periodicidad_id),
  CONSTRAINT fk_cap_fuente FOREIGN KEY (fuente_normativa_id) REFERENCES fuentes_normativas(fuente_normativa_id)
) ENGINE=InnoDB;

-- Equivale a la hoja "MATRIZ POR CARGO" y a RF-007.
-- cargo_id_ext se obtiene de meridian_personal.cargos.
-- proyecto conserva texto porque meridian_personal.contratos actualmente no tiene proyecto_id.
CREATE TABLE matriz_aplicabilidad (
  matriz_aplicabilidad_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  capacitacion_id INT UNSIGNED NOT NULL,
  cargo_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.cargos.cargo_id',
  area_id INT UNSIGNED NULL,
  proceso_id INT UNSIGNED NULL,
  ambito ENUM('ADMINISTRACION','PROYECTO') NULL,
  proyecto VARCHAR(120) NULL COMMENT 'Debe corresponder a meridian_personal.contratos.proyecto cuando aplique',
  periodicidad_id INT UNSIGNED NULL,
  obligatoria TINYINT(1) NOT NULL DEFAULT 1,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  creado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_matriz_cargo (cargo_id_ext),
  KEY ix_matriz_proyecto (proyecto),
  CONSTRAINT fk_matriz_cap FOREIGN KEY (capacitacion_id) REFERENCES capacitaciones(capacitacion_id),
  CONSTRAINT fk_matriz_area FOREIGN KEY (area_id) REFERENCES areas(area_id),
  CONSTRAINT fk_matriz_proceso FOREIGN KEY (proceso_id) REFERENCES procesos(proceso_id),
  CONSTRAINT fk_matriz_periodicidad FOREIGN KEY (periodicidad_id) REFERENCES periodicidades(periodicidad_id)
) ENGINE=InnoDB;

-- =========================================================
-- PLAN ANUAL Y CRONOGRAMA
-- =========================================================

CREATE TABLE planes_anuales (
  plan_anual_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anio SMALLINT UNSIGNED NOT NULL,
  estado ENUM('BORRADOR','EN_REVISION','APROBADO') NOT NULL DEFAULT 'BORRADOR',
  aprobado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  fecha_aprobacion DATETIME NULL,
  creado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plan_anio (anio)
) ENGINE=InnoDB;

CREATE TABLE plan_anual_detalle (
  plan_detalle_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_anual_id INT UNSIGNED NOT NULL,
  capacitacion_id INT UNSIGNED NOT NULL,
  mes_programado TINYINT UNSIGNED NOT NULL,
  cantidad_programada INT UNSIGNED NOT NULL DEFAULT 0,
  area_id INT UNSIGNED NULL,
  proceso_id INT UNSIGNED NULL,
  ambito ENUM('ADMINISTRACION','PROYECTO') NULL,
  proyecto VARCHAR(120) NULL,
  recursos_presupuestados TEXT NULL COMMENT 'Hoja CRONOGRAMA: RECURSOS PRESUPUESTADOS',
  recursos_utilizados TEXT NULL COMMENT 'Hoja CRONOGRAMA: RECURSOS UTILIZADOS',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_det_plan FOREIGN KEY (plan_anual_id) REFERENCES planes_anuales(plan_anual_id),
  CONSTRAINT fk_plan_det_cap FOREIGN KEY (capacitacion_id) REFERENCES capacitaciones(capacitacion_id),
  CONSTRAINT fk_plan_det_area FOREIGN KEY (area_id) REFERENCES areas(area_id),
  CONSTRAINT fk_plan_det_proceso FOREIGN KEY (proceso_id) REFERENCES procesos(proceso_id),
  CONSTRAINT chk_plan_mes CHECK (mes_programado BETWEEN 1 AND 12)
) ENGINE=InnoDB;

CREATE TABLE sesiones_capacitacion (
  sesion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_detalle_id INT UNSIGNED NULL,
  capacitacion_id INT UNSIGNED NOT NULL,
  fecha_hora DATETIME NOT NULL,
  modalidad_id INT UNSIGNED NOT NULL,
  ubicacion_id INT UNSIGNED NULL,
  enlace_virtual VARCHAR(500) NULL,
  proveedor_id INT UNSIGNED NULL,
  cupo_maximo INT UNSIGNED NULL,
  creado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sesion_plan_det FOREIGN KEY (plan_detalle_id) REFERENCES plan_anual_detalle(plan_detalle_id),
  CONSTRAINT fk_sesion_cap FOREIGN KEY (capacitacion_id) REFERENCES capacitaciones(capacitacion_id),
  CONSTRAINT fk_sesion_modalidad FOREIGN KEY (modalidad_id) REFERENCES modalidades(modalidad_id),
  CONSTRAINT fk_sesion_ubicacion FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(ubicacion_id),
  CONSTRAINT fk_sesion_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores_capacitadores(proveedor_id)
) ENGINE=InnoDB;

-- =========================================================
-- ASIGNACION, ASISTENCIA Y CUMPLIMIENTO POR PERSONA
-- =========================================================

CREATE TABLE asignaciones_capacitacion (
  asignacion_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  persona_id_ext INT UNSIGNED NOT NULL COMMENT 'meridian_personal.personas.persona_id',
  contrato_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.contratos.contrato_id',
  capacitacion_id INT UNSIGNED NOT NULL,
  matriz_aplicabilidad_id INT UNSIGNED NULL,
  fecha_asignacion DATE NOT NULL,
  origen ENUM('AUTOMATICA','MANUAL','INDUCCION','REINDUCCION') NOT NULL,
  cargo_id_ext INT UNSIGNED NULL COMMENT 'Snapshot: meridian_personal.cargos.cargo_id al momento de asignar',
  area_id INT UNSIGNED NULL,
  proceso_id INT UNSIGNED NULL,
  ambito ENUM('ADMINISTRACION','PROYECTO') NULL,
  proyecto VARCHAR(120) NULL COMMENT 'Snapshot del proyecto al momento de asignar',
  creada_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_asig_persona (persona_id_ext),
  KEY ix_asig_capacitacion (capacitacion_id),
  KEY ix_asig_proyecto (proyecto),
  CONSTRAINT fk_asig_cap FOREIGN KEY (capacitacion_id) REFERENCES capacitaciones(capacitacion_id),
  CONSTRAINT fk_asig_matriz FOREIGN KEY (matriz_aplicabilidad_id) REFERENCES matriz_aplicabilidad(matriz_aplicabilidad_id),
  CONSTRAINT fk_asig_area FOREIGN KEY (area_id) REFERENCES areas(area_id),
  CONSTRAINT fk_asig_proceso FOREIGN KEY (proceso_id) REFERENCES procesos(proceso_id)
) ENGINE=InnoDB;

CREATE TABLE sesion_participantes (
  sesion_participante_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesion_id INT UNSIGNED NOT NULL,
  asignacion_id BIGINT UNSIGNED NOT NULL,
  estado_asistencia ENUM('CONVOCADO','ASISTIO','TARDE','AUSENTE') NOT NULL DEFAULT 'CONVOCADO',
  motivo_ausencia VARCHAR(255) NULL,
  registrado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sesion_asignacion (sesion_id, asignacion_id),
  CONSTRAINT fk_part_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones_capacitacion(sesion_id),
  CONSTRAINT fk_part_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_capacitacion(asignacion_id)
) ENGINE=InnoDB;

CREATE TABLE cumplimientos_capacitacion (
  cumplimiento_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asignacion_id BIGINT UNSIGNED NOT NULL,
  sesion_id INT UNSIGNED NULL,
  fecha_realizacion DATE NOT NULL,
  resultado VARCHAR(60) NULL,
  horas_efectivas DECIMAL(6,2) NOT NULL,
  nota_evaluacion DECIMAL(5,2) NULL COMMENT 'La matriz Excel actual usa escala 0 a 5',
  fecha_vencimiento DATE NULL,
  registrado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cumplimiento_asignacion (asignacion_id),
  KEY ix_cumplimiento_vencimiento (fecha_vencimiento),
  CONSTRAINT fk_cump_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_capacitacion(asignacion_id),
  CONSTRAINT fk_cump_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones_capacitacion(sesion_id)
) ENGINE=InnoDB;

CREATE TABLE soportes_cumplimiento (
  soporte_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cumplimiento_id BIGINT UNSIGNED NOT NULL,
  tipo_soporte ENUM('CERTIFICADO','LISTADO_ASISTENCIA') NOT NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  ruta_archivo VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NULL,
  cargado_por_usuario_id_ext INT UNSIGNED NULL COMMENT 'meridian_personal.usuarios_sistema.usuario_id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_soporte_cumplimiento (cumplimiento_id),
  CONSTRAINT fk_soporte_cumplimiento FOREIGN KEY (cumplimiento_id) REFERENCES cumplimientos_capacitacion(cumplimiento_id)
) ENGINE=InnoDB;

-- =========================================================
-- VISTAS DERIVADAS: NO DUPLICAN ALERTAS NI ESTADOS
-- =========================================================

-- Estado vigente de cada asignacion segun cumplimiento y vencimiento.
CREATE OR REPLACE VIEW vw_estado_asignaciones AS
SELECT
  a.asignacion_id,
  a.persona_id_ext,
  a.capacitacion_id,
  a.proyecto,
  c.cumplimiento_id,
  c.fecha_realizacion,
  c.fecha_vencimiento,
  CASE
    WHEN c.cumplimiento_id IS NULL THEN 'PENDIENTE'
    WHEN c.fecha_vencimiento IS NOT NULL AND c.fecha_vencimiento < CURDATE() THEN 'VENCIDA'
    WHEN c.fecha_vencimiento IS NOT NULL
         AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
      THEN 'PROXIMA_A_VENCER'
    ELSE 'COMPLETADA'
  END AS estado_calculado
FROM asignaciones_capacitacion a
LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id;

CREATE OR REPLACE VIEW vw_alertas_vencimiento AS
SELECT *
FROM vw_estado_asignaciones
WHERE estado_calculado IN ('PROXIMA_A_VENCER','VENCIDA');

-- =========================================================
-- RELACIONES EXTERNAS ESPERADAS (NO SE DUPLICAN DATOS)
-- =========================================================
-- asignaciones_capacitacion.persona_id_ext   -> meridian_personal.personas.persona_id
-- asignaciones_capacitacion.contrato_id_ext  -> meridian_personal.contratos.contrato_id
-- asignaciones_capacitacion.cargo_id_ext     -> meridian_personal.cargos.cargo_id
-- matriz_aplicabilidad.cargo_id_ext           -> meridian_personal.cargos.cargo_id
-- *_usuario_id_ext                            -> meridian_personal.usuarios_sistema.usuario_id
-- proyecto                                    -> meridian_personal.contratos.proyecto (texto; pendiente normalizar)
-- auditoria de acciones HSEQ                  -> meridian_personal.auditoria
--
-- Para RF-005/RF-022, el sistema HSEQ debe consultar el estado del trabajador en meridian_personal,
-- no crear/inactivar trabajadores localmente.
