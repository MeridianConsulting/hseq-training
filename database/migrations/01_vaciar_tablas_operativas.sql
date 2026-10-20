-- =====================================================================
-- Base de datos: meridian_capacitaciones
-- 06 · Vaciar las tablas operativas (movimientos), conservando la
--      información general (catálogos, capacitaciones, matriz, usuarios).
--
-- ⚠  ANTES DE EJECUTAR: saque un respaldo (phpMyAdmin > Exportar).
--    TRUNCATE no se puede deshacer con ROLLBACK y reinicia los consecutivos
--    (AUTO_INCREMENT) de cada tabla vaciada.
--
-- SE VACÍAN (14 tablas):
--   asignaciones_capacitacion ....... asignaciones de cursos a personas
--   cumplimientos_capacitacion ...... registros de cursos realizados
--   soportes_cumplimiento ........... soportes cargados (los archivos físicos
--                                     en el servidor NO se borran)
--   sesiones_capacitacion ........... sesiones programadas
--   sesion_participantes ............ asistentes a sesiones
--   planes_anuales .................. planes anuales (2026, 2027 en borrador)
--   plan_anual_detalle / plan_detalle_alcances / plan_detalle_asignaciones
--   persona_contexto_hseq ........... proceso/proyecto asignado a cada persona
--   historial_contexto_trabajador ... historial de cargo/proyecto por persona
--   migraciones ..................... historial de cargues de Excel
--   auditoria ....................... bitácora de acciones
--   usuario_accesos ................. intentos fallidos / bloqueos de login
--
-- SE CONSERVAN (información general):
--   capacitaciones, matriz_aplicabilidad, categorias_capacitacion,
--   tipos_capacitacion, modalidades, periodicidades, vigencias,
--   fuentes_normativas, proveedores_capacitadores, proyectos, procesos,
--   areas, ubicaciones, usuarios, roles, permisos, rol_permisos,
--   user_roles, usuario_roles, schema_migrations
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Cumplimientos y sesiones
TRUNCATE TABLE `soportes_cumplimiento`;
TRUNCATE TABLE `sesion_participantes`;
TRUNCATE TABLE `cumplimientos_capacitacion`;
TRUNCATE TABLE `sesiones_capacitacion`;

-- Plan anual
TRUNCATE TABLE `plan_detalle_asignaciones`;
TRUNCATE TABLE `plan_detalle_alcances`;
TRUNCATE TABLE `plan_anual_detalle`;
TRUNCATE TABLE `planes_anuales`;

-- Asignaciones y contexto de trabajadores
TRUNCATE TABLE `asignaciones_capacitacion`;
TRUNCATE TABLE `persona_contexto_hseq`;
TRUNCATE TABLE `historial_contexto_trabajador`;

-- Bitácoras
TRUNCATE TABLE `migraciones`;
TRUNCATE TABLE `auditoria`;
TRUNCATE TABLE `usuario_accesos`;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: todas deben quedar en 0
SELECT 'asignaciones_capacitacion' AS tabla, COUNT(*) AS filas FROM `asignaciones_capacitacion`
UNION ALL SELECT 'cumplimientos_capacitacion', COUNT(*) FROM `cumplimientos_capacitacion`
UNION ALL SELECT 'soportes_cumplimiento', COUNT(*) FROM `soportes_cumplimiento`
UNION ALL SELECT 'sesiones_capacitacion', COUNT(*) FROM `sesiones_capacitacion`
UNION ALL SELECT 'sesion_participantes', COUNT(*) FROM `sesion_participantes`
UNION ALL SELECT 'planes_anuales', COUNT(*) FROM `planes_anuales`
UNION ALL SELECT 'plan_anual_detalle', COUNT(*) FROM `plan_anual_detalle`
UNION ALL SELECT 'plan_detalle_alcances', COUNT(*) FROM `plan_detalle_alcances`
UNION ALL SELECT 'plan_detalle_asignaciones', COUNT(*) FROM `plan_detalle_asignaciones`
UNION ALL SELECT 'persona_contexto_hseq', COUNT(*) FROM `persona_contexto_hseq`
UNION ALL SELECT 'historial_contexto_trabajador', COUNT(*) FROM `historial_contexto_trabajador`
UNION ALL SELECT 'migraciones', COUNT(*) FROM `migraciones`
UNION ALL SELECT 'auditoria', COUNT(*) FROM `auditoria`
UNION ALL SELECT 'usuario_accesos', COUNT(*) FROM `usuario_accesos`;
