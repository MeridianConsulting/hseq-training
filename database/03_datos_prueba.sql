-- Datos de prueba para meridian_capacitaciones (idempotente: se puede reejecutar).
-- No toca meridian_personal: persona_id_ext / cargo_id_ext / contrato_id_ext son referencias logicas.
--
-- Requisitos:
--   - Estructura y catalogos base ya cargados (areas, procesos, tipos, vigencias, etc.).
--   - Usuario admin (database/seed_usuario_inicial.php).
--   - Personas activas en meridian_personal (ids usados: 1, 2, 3, 8, 9, 11, 46, 106).
--
-- Uso:
--   mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/03_datos_prueba.sql
--
-- Cubre los estados de vw_estado_asignaciones (con CURDATE = 2026-08-28 aprox.):
--   PENDIENTE, PENDIENTE_PROXIMA_A_VENCER, PENDIENTE_VENCIDA,
--   COMPLETADA, PROXIMA_A_VENCER, VENCIDA.

USE meridian_capacitaciones;

-- =========================================================
-- CATALOGOS QUE AUN ESTAN VACIOS
-- =========================================================

INSERT IGNORE INTO proveedores_capacitadores (nombre, activo) VALUES
('SENA', 1),
('Cruz Roja Colombiana', 1),
('Consejo Colombiano de Seguridad', 1),
('Meridian Consulting - HSEQ interno', 1);

INSERT IGNORE INTO ubicaciones (nombre, descripcion, activo) VALUES
('Sede administrativa Bogota', 'Sala de capacitaciones piso 3', 1),
('Campo Frontera', 'Instalaciones del proyecto Frontera', 1),
('Aula virtual Teams', 'Sesiones sincronicas remotas', 1);

-- =========================================================
-- CAPACITACIONES
-- =========================================================

INSERT INTO capacitaciones (
  codigo, nombre, objetivo, descripcion_temario,
  categoria_id, tipo_capacitacion_id, duracion_estimada_horas,
  criticidad, es_tarea_critica, responsable,
  proveedor_default_id, periodicidad_default_id, vigencia_id, modalidad_default_id,
  evaluacion, nota_minima, certificado, requiere_listado_asistencia,
  fuente_normativa_id, estado, creado_por_usuario_id_ext
)
SELECT
  v.codigo, v.nombre, v.objetivo, v.temario,
  cat.categoria_id, tip.tipo_capacitacion_id, v.horas,
  v.criticidad, v.tarea_critica, 'Coordinador HSEQ',
  prv.proveedor_id, per.periodicidad_id, vig.vigencia_id, md.modalidad_id,
  v.evaluacion, v.nota_minima, v.certificado, v.asistencia,
  fnt.fuente_normativa_id, 'ACTIVA', usr.usuario_id
FROM (
  SELECT 'HSEQ-IND-001' AS codigo, 'Induccion SST y corporativa' AS nombre,
         'Presentar el SG-SST, reglamento interno y riesgos generales al ingreso.' AS objetivo,
         'Politica HSEQ, riesgos, EPP, reporte de incidentes, canales de emergencia.' AS temario,
         'HSE' AS categoria, 'INDUCCION' AS tipo, 4.00 AS horas, 'ALTA' AS criticidad, 0 AS tarea_critica,
         'Meridian Consulting - HSEQ interno' AS proveedor, 'UNICA VEZ' AS periodicidad, '1 ANIO' AS vigencia,
         'PRESENCIAL' AS modalidad, 1 AS evaluacion, 3.50 AS nota_minima, 1 AS certificado, 1 AS asistencia,
         'Decreto 1072 de 2015' AS fuente
  UNION ALL
  SELECT 'HSEQ-REI-001', 'Reinduccion anual SG-SST',
         'Actualizar al personal en cambios del sistema de gestion y lecciones aprendidas.',
         'Indicadores, cambios normativos, investigacion de accidentes, cultura de autocuidado.',
         'HSE', 'REINDUCCION', 3.00, 'MEDIA', 0,
         'Meridian Consulting - HSEQ interno', 'ANUAL', '1 ANIO',
         'MIXTA', 1, 3.50, 1, 1,
         'Resolucion 0312 de 2019'
  UNION ALL
  SELECT 'HSEQ-ALT-001', 'Trabajo seguro en alturas',
         'Capacitar en prevencion de caidas y uso de sistemas de proteccion contra caidas.',
         'Normativa, EPP, inspeccion de equipos, rescate basico, permiso de trabajo.',
         'HSE', 'TAREA CRITICA', 8.00, 'ALTA', 1,
         'Consejo Colombiano de Seguridad', 'ANUAL', '1 ANIO',
         'PRESENCIAL', 1, 4.00, 1, 1,
         'Resolucion 0312 de 2019'
  UNION ALL
  SELECT 'HSEQ-ESP-001', 'Espacios confinados',
         'Identificar peligros y controles para ingreso a espacios confinados.',
         'Atmosferas, medicion de gases, vigia, rescate, permiso de trabajo.',
         'HSE', 'TAREA CRITICA', 8.00, 'ALTA', 1,
         'Consejo Colombiano de Seguridad', 'ANUAL', '1 ANIO',
         'PRESENCIAL', 1, 4.00, 1, 1,
         'Decreto 1072 de 2015'
  UNION ALL
  SELECT 'HSEQ-PAX-001', 'Primeros auxilios',
         'Formar brigadistas en atencion inicial de lesionados.',
         'RCP, hemorragias, quemaduras, movilizacion, botiquin.',
         'HSE', 'OBLIGATORIA', 8.00, 'ALTA', 0,
         'Cruz Roja Colombiana', 'BIANUAL', '2 ANIOS',
         'PRESENCIAL', 1, 3.50, 1, 1,
         'Resolucion 0312 de 2019'
  UNION ALL
  SELECT 'HSEQ-EME-001', 'Brigada de emergencias',
         'Preparar la brigada para respuesta a emergencias en sede y campo.',
         'Plan de emergencias, puntos de encuentro, extintores, evacuacion.',
         'HSE', 'OBLIGATORIA', 6.00, 'ALTA', 0,
         'Cruz Roja Colombiana', 'ANUAL', '1 ANIO',
         'PRESENCIAL', 0, 0.00, 1, 1,
         'Decreto 1072 de 2015'
  UNION ALL
  SELECT 'HSEQ-ISO-001', 'Sensibilizacion ISO 45001',
         'Dar a conocer el alcance del sistema de gestion de SST.',
         'Contexto, partes interesadas, no conformidades, mejora continua.',
         'CALIDAD', 'OBLIGATORIA', 4.00, 'MEDIA', 0,
         'SENA', 'UNICA VEZ', '1 ANIO',
         'VIRTUAL', 1, 3.00, 0, 0,
         'ISO 45001'
  UNION ALL
  SELECT 'HSEQ-RUC-001', 'Requisitos RUC y clientes',
         'Alinear practicas HSEQ con el Registro Uniforme de contratistas.',
         'Estandares RUC, evidencias, auditorias de cliente, no conformidades.',
         'CUMPLIMIENTO', 'OBLIGATORIA', 4.00, 'ALTA', 0,
         'Meridian Consulting - HSEQ interno', 'ANUAL', '1 ANIO',
         'VIRTUAL', 1, 3.50, 1, 0,
         'Guia RUC'
) AS v
INNER JOIN categorias_capacitacion cat ON cat.nombre = v.categoria
INNER JOIN tipos_capacitacion tip ON tip.nombre = v.tipo
INNER JOIN proveedores_capacitadores prv ON prv.nombre = v.proveedor
INNER JOIN periodicidades per ON per.nombre = v.periodicidad
INNER JOIN vigencias vig ON vig.nombre = v.vigencia
INNER JOIN modalidades md ON md.nombre = v.modalidad
INNER JOIN fuentes_normativas fnt ON fnt.nombre = v.fuente
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM capacitaciones c WHERE c.codigo = v.codigo
);

-- Completar las filas de prueba ya existentes (HSEQ-TEST-*) si siguen sin catalogo.
UPDATE capacitaciones c
INNER JOIN categorias_capacitacion cat ON cat.nombre = 'HSE'
INNER JOIN tipos_capacitacion tip ON tip.nombre = 'INDUCCION'
SET c.categoria_id = cat.categoria_id,
    c.tipo_capacitacion_id = tip.tipo_capacitacion_id,
    c.objetivo = COALESCE(NULLIF(c.objetivo, ''), 'Curso de prueba de induccion HSEQ.')
WHERE c.codigo = 'HSEQ-TEST-01';

UPDATE capacitaciones c
INNER JOIN categorias_capacitacion cat ON cat.nombre = 'HSE'
INNER JOIN tipos_capacitacion tip ON tip.nombre = 'OBLIGATORIA'
SET c.categoria_id = cat.categoria_id,
    c.tipo_capacitacion_id = tip.tipo_capacitacion_id
WHERE c.codigo = 'HSEQ-TEST-02';

-- =========================================================
-- MATRIZ DE APLICABILIDAD
-- Cargos meridian_personal: 1 PRACTICANTE, 2 ASISTENTE ADM, 3 ANALISTA CONTABLE,
-- 8 PROFESIONAL GH, 9 PROFESIONAL SENIOR, 18 SOPORTE HSEQ, 21 COORDINADOR HSEQ.
-- =========================================================

INSERT INTO matriz_aplicabilidad (
  capacitacion_id, cargo_id_ext, area_id, proceso_id, ambito, proyecto,
  periodicidad_id, obligatoria, activa, creado_por_usuario_id_ext
)
SELECT c.capacitacion_id, v.cargo_id, ar.area_id, pr.proceso_id, v.ambito, v.proyecto,
       pe.periodicidad_id, 1, 1, usr.usuario_id
FROM (
  SELECT 'HSEQ-IND-001' AS codigo, 1 AS cargo_id, 'ADMINISTRACION' AS area, 'GESTION HUMANA' AS proceso,
         'ADMINISTRACION' AS ambito, NULL AS proyecto, 'UNICA VEZ' AS periodicidad
  UNION ALL
  SELECT 'HSEQ-IND-001', 2, 'ADMINISTRACION', 'GESTION HUMANA', 'ADMINISTRACION', NULL, 'UNICA VEZ'
  UNION ALL
  SELECT 'HSEQ-REI-001', 2, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-REI-001', 8, 'ADMINISTRACION', 'GESTION HUMANA', 'ADMINISTRACION', NULL, 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-ALT-001', 18, 'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA', 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-ALT-001', 21, 'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA', 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-ALT-001', 9, 'FRONTERA', 'GESTION DE PROYECTOS', 'PROYECTO', 'FRONTERA', 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-ESP-001', 18, 'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA', 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-PAX-001', 18, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'BIANUAL'
  UNION ALL
  SELECT 'HSEQ-PAX-001', 21, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'BIANUAL'
  UNION ALL
  SELECT 'HSEQ-ISO-001', 7, 'ADMINISTRACION', 'GESTION DE TECNOLOGIA', 'ADMINISTRACION', NULL, 'UNICA VEZ'
  UNION ALL
  SELECT 'HSEQ-RUC-001', 21, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'ANUAL'
  UNION ALL
  SELECT 'HSEQ-EME-001', 8, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'ANUAL'
) AS v
INNER JOIN capacitaciones c ON c.codigo = v.codigo
INNER JOIN areas ar ON ar.nombre = v.area
INNER JOIN procesos pr ON pr.nombre = v.proceso
INNER JOIN periodicidades pe ON pe.nombre = v.periodicidad
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1
  FROM matriz_aplicabilidad m
  WHERE m.capacitacion_id = c.capacitacion_id
    AND m.cargo_id_ext = v.cargo_id
    AND m.ambito <=> v.ambito
    AND m.proyecto <=> v.proyecto
);

-- =========================================================
-- PLAN ANUAL 2026
-- =========================================================

INSERT INTO planes_anuales (anio, estado, aprobado_por_usuario_id_ext, fecha_aprobacion, creado_por_usuario_id_ext)
SELECT 2026, 'APROBADO', usr.usuario_id, '2026-01-20 09:00:00', usr.usuario_id
FROM usuarios usr
WHERE usr.nombre_usuario = 'admin'
  AND NOT EXISTS (SELECT 1 FROM planes_anuales p WHERE p.anio = 2026);

INSERT INTO plan_anual_detalle (
  plan_anual_id, capacitacion_id, mes_programado, cantidad_programada,
  area_id, proceso_id, ambito, proyecto, recursos_presupuestados, recursos_utilizados
)
SELECT p.plan_anual_id, c.capacitacion_id, v.mes, v.cantidad,
       ar.area_id, pr.proceso_id, v.ambito, v.proyecto, v.presupuestado, v.utilizado
FROM (
  SELECT 'HSEQ-IND-001' AS codigo, 1 AS mes, 8 AS cantidad, 'ADMINISTRACION' AS area, 'GESTION HUMANA' AS proceso,
         'ADMINISTRACION' AS ambito, NULL AS proyecto, 'Tiempo interno HSEQ' AS presupuestado, '4 horas instructor interno' AS utilizado
  UNION ALL
  SELECT 'HSEQ-REI-001', 3, 25, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'Sala + refrigerio', 'Refrigerio ejecutado'
  UNION ALL
  SELECT 'HSEQ-ISO-001', 4, 12, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'Licencia aula virtual', NULL
  UNION ALL
  SELECT 'HSEQ-ALT-001', 8, 6, 'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA', 'Proveedor CCS + equipos', 'Factura CCS ago-2026'
  UNION ALL
  SELECT 'HSEQ-PAX-001', 8, 4, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'Cruz Roja', NULL
  UNION ALL
  SELECT 'HSEQ-EME-001', 9, 10, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'Simulacro sede', NULL
  UNION ALL
  SELECT 'HSEQ-RUC-001', 10, 8, 'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL, 'Tiempo interno', NULL
  UNION ALL
  SELECT 'HSEQ-ESP-001', 11, 4, 'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA', 'Proveedor CCS', NULL
) AS v
INNER JOIN planes_anuales p ON p.anio = 2026
INNER JOIN capacitaciones c ON c.codigo = v.codigo
INNER JOIN areas ar ON ar.nombre = v.area
INNER JOIN procesos pr ON pr.nombre = v.proceso
WHERE NOT EXISTS (
  SELECT 1 FROM plan_anual_detalle d
  WHERE d.plan_anual_id = p.plan_anual_id
    AND d.capacitacion_id = c.capacitacion_id
    AND d.mes_programado = v.mes
);

-- =========================================================
-- SESIONES
-- =========================================================

INSERT INTO sesiones_capacitacion (
  plan_detalle_id, capacitacion_id, fecha_hora, modalidad_id, ubicacion_id,
  enlace_virtual, proveedor_id, cupo_maximo, creado_por_usuario_id_ext,
  observaciones, estado
)
SELECT d.plan_detalle_id, c.capacitacion_id, v.fecha_hora, mo.modalidad_id, ub.ubicacion_id,
       v.enlace, prv.proveedor_id, v.cupo, usr.usuario_id, v.obs, v.estado
FROM (
  SELECT 'HSEQ-IND-001' AS codigo, 1 AS mes, '2026-01-22 08:00:00' AS fecha_hora, 'PRESENCIAL' AS modalidad,
         'Sede administrativa Bogota' AS ubicacion, NULL AS enlace, 'Meridian Consulting - HSEQ interno' AS proveedor,
         12 AS cupo, 'Induccion de ingreso primer trimestre' AS obs, 'EJECUTADA' AS estado
  UNION ALL
  SELECT 'HSEQ-REI-001', 3, '2026-03-12 09:00:00', 'MIXTA',
         'Sede administrativa Bogota', 'https://teams.microsoft.com/l/meetup-join/reinduccion-2026',
         'Meridian Consulting - HSEQ interno', 30, 'Reinduccion anual sede', 'EJECUTADA'
  UNION ALL
  SELECT 'HSEQ-ISO-001', 4, '2026-04-08 14:00:00', 'VIRTUAL',
         'Aula virtual Teams', 'https://teams.microsoft.com/l/meetup-join/iso45001',
         'SENA', 20, 'Sesion virtual SENA', 'EJECUTADA'
  UNION ALL
  SELECT 'HSEQ-ALT-001', 8, '2026-08-12 07:30:00', 'PRESENCIAL',
         'Campo Frontera', NULL, 'Consejo Colombiano de Seguridad', 8,
         'Curso de alturas en campo', 'EJECUTADA'
  UNION ALL
  SELECT 'HSEQ-PAX-001', 8, '2026-09-04 08:00:00', 'PRESENCIAL',
         'Sede administrativa Bogota', NULL, 'Cruz Roja Colombiana', 10,
         'Programada para brigadistas', 'PROGRAMADA'
  UNION ALL
  SELECT 'HSEQ-EME-001', 9, '2026-09-18 09:00:00', 'PRESENCIAL',
         'Sede administrativa Bogota', NULL, 'Cruz Roja Colombiana', 15,
         'Simulacro y formacion de brigada', 'PROGRAMADA'
) AS v
INNER JOIN capacitaciones c ON c.codigo = v.codigo
INNER JOIN planes_anuales p ON p.anio = 2026
INNER JOIN plan_anual_detalle d ON d.plan_anual_id = p.plan_anual_id
  AND d.capacitacion_id = c.capacitacion_id AND d.mes_programado = v.mes
INNER JOIN modalidades mo ON mo.nombre = v.modalidad
INNER JOIN ubicaciones ub ON ub.nombre = v.ubicacion
INNER JOIN proveedores_capacitadores prv ON prv.nombre = v.proveedor
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM sesiones_capacitacion s
  WHERE s.capacitacion_id = c.capacitacion_id AND s.fecha_hora = v.fecha_hora
);

-- =========================================================
-- ASIGNACIONES (personas reales de meridian_personal)
-- =========================================================

INSERT INTO asignaciones_capacitacion (
  persona_id_ext, contrato_id_ext, capacitacion_id, matriz_aplicabilidad_id,
  fecha_asignacion, fecha_limite_cumplimiento, origen, cargo_id_ext,
  area_id, proceso_id, ambito, proyecto, creada_por_usuario_id_ext
)
SELECT v.persona_id, v.contrato_id, c.capacitacion_id, m.matriz_aplicabilidad_id,
       v.fecha_asig, v.fecha_limite, v.origen, v.cargo_id,
       ar.area_id, pr.proceso_id, v.ambito, v.proyecto, usr.usuario_id
FROM (
  -- 1 MARIA AVELLANEDA / PRACTICANTE: pendiente (limite lejano)
  SELECT 1 AS persona_id, 1 AS contrato_id, 'HSEQ-IND-001' AS codigo, 1 AS cargo_id,
         '2026-08-03' AS fecha_asig, '2026-10-15' AS fecha_limite, 'INDUCCION' AS origen,
         'ADMINISTRACION' AS area, 'GESTION HUMANA' AS proceso, 'ADMINISTRACION' AS ambito, NULL AS proyecto
  UNION ALL
  -- 2 NATALIA FRANCO: pendiente proxima a vencer (limite dentro de 10 dias)
  SELECT 2, 2, 'HSEQ-REI-001', 2, '2026-07-01', '2026-09-04', 'AUTOMATICA',
         'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL
  UNION ALL
  -- 3 KAREN CARRANZA: pendiente vencida (usa el curso de prueba ya ligado al cargo 3)
  SELECT 3, 3, 'HSEQ-TEST-01', 3, '2026-06-01', '2026-08-10', 'AUTOMATICA',
         'ADMINISTRACION', 'GESTION ADMINISTRATIVA Y FINANCIERA', 'ADMINISTRACION', NULL
  UNION ALL
  -- 8 ANDRES CARDENAS: completada (vigencia lejana)
  SELECT 8, 8, 'HSEQ-ISO-001', 7, '2026-03-01', '2026-04-30', 'MANUAL',
         'ADMINISTRACION', 'GESTION DE TECNOLOGIA', 'ADMINISTRACION', NULL
  UNION ALL
  -- 9 SONIA FONSECA: cumplimiento proximo a vencer
  SELECT 9, 9, 'HSEQ-REI-001', 8, '2025-08-01', '2025-09-15', 'REINDUCCION',
         'ADMINISTRACION', 'GESTION HUMANA', 'ADMINISTRACION', NULL
  UNION ALL
  -- 11 FRANKLIN BOTERO: cumplimiento ya vencido (alturas)
  SELECT 11, 11, 'HSEQ-ALT-001', 9, '2025-06-01', '2025-07-15', 'AUTOMATICA',
         'FRONTERA', 'GESTION DE PROYECTOS', 'PROYECTO', 'FRONTERA'
  UNION ALL
  -- 46 DIANA JACOBO / SOPORTE HSEQ: completada en agosto (dashboard ejecutado)
  SELECT 46, 46, 'HSEQ-ALT-001', 18, '2026-07-15', '2026-08-20', 'AUTOMATICA',
         'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA'
  UNION ALL
  -- 46 DIANA: primeros auxilios pendiente (sesion programada)
  SELECT 46, 46, 'HSEQ-PAX-001', 18, '2026-08-01', '2026-09-10', 'MANUAL',
         'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL
  UNION ALL
  -- 106 LUIS GUEVARA / COORDINADOR HSEQ: pendiente RUC
  SELECT 106, 106, 'HSEQ-RUC-001', 21, '2026-08-20', '2026-11-30', 'MANUAL',
         'ADMINISTRACION', 'GESTION HSEQ', 'ADMINISTRACION', NULL
  UNION ALL
  -- 106 LUIS: alturas completada en agosto
  SELECT 106, 106, 'HSEQ-ALT-001', 21, '2026-07-15', '2026-08-20', 'AUTOMATICA',
         'FRONTERA', 'GESTION HSEQ', 'PROYECTO', 'FRONTERA'
) AS v
INNER JOIN capacitaciones c ON c.codigo = v.codigo
INNER JOIN areas ar ON ar.nombre = v.area
INNER JOIN procesos pr ON pr.nombre = v.proceso
LEFT JOIN matriz_aplicabilidad m
  ON m.capacitacion_id = c.capacitacion_id
 AND m.cargo_id_ext = v.cargo_id
 AND m.ambito <=> v.ambito
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM asignaciones_capacitacion a
  WHERE a.persona_id_ext = v.persona_id AND a.capacitacion_id = c.capacitacion_id
);

-- =========================================================
-- PARTICIPANTES DE SESION
-- =========================================================

INSERT INTO sesion_participantes (
  sesion_id, asignacion_id, estado_asistencia, motivo_ausencia, registrado_por_usuario_id_ext
)
SELECT s.sesion_id, a.asignacion_id, v.asistencia, v.motivo, usr.usuario_id
FROM (
  SELECT 'HSEQ-IND-001' AS codigo, '2026-01-22 08:00:00' AS fecha_hora, 1 AS persona_id,
         'CONVOCADO' AS asistencia, NULL AS motivo
  UNION ALL
  SELECT 'HSEQ-REI-001', '2026-03-12 09:00:00', 2, 'AUSENTE', 'Incapacidad medica'
  UNION ALL
  SELECT 'HSEQ-ISO-001', '2026-04-08 14:00:00', 8, 'ASISTIO', NULL
  UNION ALL
  SELECT 'HSEQ-ALT-001', '2026-08-12 07:30:00', 46, 'ASISTIO', NULL
  UNION ALL
  SELECT 'HSEQ-ALT-001', '2026-08-12 07:30:00', 106, 'TARDE', NULL
  UNION ALL
  SELECT 'HSEQ-PAX-001', '2026-09-04 08:00:00', 46, 'CONVOCADO', NULL
) AS v
INNER JOIN capacitaciones c ON c.codigo = v.codigo
INNER JOIN sesiones_capacitacion s ON s.capacitacion_id = c.capacitacion_id AND s.fecha_hora = v.fecha_hora
INNER JOIN asignaciones_capacitacion a ON a.persona_id_ext = v.persona_id AND a.capacitacion_id = c.capacitacion_id
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM sesion_participantes sp
  WHERE sp.sesion_id = s.sesion_id AND sp.asignacion_id = a.asignacion_id
);

-- =========================================================
-- CUMPLIMIENTOS
-- =========================================================

INSERT INTO cumplimientos_capacitacion (
  asignacion_id, sesion_id, fecha_realizacion, resultado, horas_efectivas,
  nota_evaluacion, observaciones, fecha_vencimiento, registrado_por_usuario_id_ext
)
SELECT a.asignacion_id, s.sesion_id, v.fecha_real, v.resultado, v.horas,
       v.nota, v.obs, v.vence, usr.usuario_id
FROM (
  -- Completada, vigencia 2027
  SELECT 8 AS persona_id, 'HSEQ-ISO-001' AS codigo, '2026-04-08 14:00:00' AS fecha_sesion,
         '2026-04-08' AS fecha_real, 'APROBADO' AS resultado, 4.00 AS horas, 4.20 AS nota,
         'Evaluacion virtual SENA' AS obs, '2027-04-08' AS vence
  UNION ALL
  -- Proxima a vencer (vigencia ~ 2026-09-05)
  SELECT 9, 'HSEQ-REI-001', NULL,
         '2025-09-05', 'APROBADO', 3.00, 3.80,
         'Reinduccion 2025', '2026-09-05'
  UNION ALL
  -- Ya vencida
  SELECT 11, 'HSEQ-ALT-001', NULL,
         '2025-07-10', 'APROBADO', 8.00, 4.50,
         'Certificado 2025 vencido', '2026-07-10'
  UNION ALL
  -- Completada en agosto 2026 (dashboard)
  SELECT 46, 'HSEQ-ALT-001', '2026-08-12 07:30:00',
         '2026-08-12', 'APROBADO', 8.00, 4.70,
         'Curso CCS en campo Frontera', '2027-08-12'
  UNION ALL
  SELECT 106, 'HSEQ-ALT-001', '2026-08-12 07:30:00',
         '2026-08-12', 'APROBADO', 8.00, 4.10,
         'Asistio con llegada tarde', '2027-08-12'
) AS v
INNER JOIN capacitaciones c ON c.codigo = v.codigo
INNER JOIN asignaciones_capacitacion a ON a.persona_id_ext = v.persona_id AND a.capacitacion_id = c.capacitacion_id
LEFT JOIN sesiones_capacitacion s ON s.capacitacion_id = c.capacitacion_id AND s.fecha_hora = v.fecha_sesion
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM cumplimientos_capacitacion x WHERE x.asignacion_id = a.asignacion_id
);

-- =========================================================
-- SOPORTES (metadatos; los archivos no tienen que existir en disco)
-- =========================================================

INSERT INTO soportes_cumplimiento (
  cumplimiento_id, tipo_soporte, nombre_archivo, ruta_archivo, mime_type, tamano_bytes, cargado_por_usuario_id_ext
)
SELECT cump.cumplimiento_id, v.tipo, v.archivo, v.ruta, v.mime, v.bytes, usr.usuario_id
FROM (
  SELECT 46 AS persona_id, 'HSEQ-ALT-001' AS codigo, 'CERTIFICADO' AS tipo,
         'certificado_alturas_diana_jacob.pdf' AS archivo,
         '/uploads/soportes/2026/certificado_alturas_diana_jacob.pdf' AS ruta,
         'application/pdf' AS mime, 245760 AS bytes
  UNION ALL
  SELECT 46, 'HSEQ-ALT-001', 'LISTADO_ASISTENCIA',
         'listado_alturas_20260812.pdf',
         '/uploads/soportes/2026/listado_alturas_20260812.pdf',
         'application/pdf', 102400
  UNION ALL
  SELECT 8, 'HSEQ-ISO-001', 'CERTIFICADO',
         'constancia_iso45001_cardenas.pdf',
         '/uploads/soportes/2026/constancia_iso45001_cardenas.pdf',
         'application/pdf', 81920
) AS v
INNER JOIN capacitaciones cap ON cap.codigo = v.codigo
INNER JOIN asignaciones_capacitacion a ON a.persona_id_ext = v.persona_id AND a.capacitacion_id = cap.capacitacion_id
INNER JOIN cumplimientos_capacitacion cump ON cump.asignacion_id = a.asignacion_id
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM soportes_cumplimiento s
  WHERE s.cumplimiento_id = cump.cumplimiento_id AND s.tipo_soporte = v.tipo
);

-- =========================================================
-- AUDITORIA DE EJEMPLO
-- =========================================================

INSERT INTO auditoria (usuario_id_ext, usuario_nombre, accion, entidad, entidad_id, ip_origen, valor_anterior, valor_nuevo)
SELECT usr.usuario_id, usr.nombre_usuario, v.accion, v.entidad, v.entidad_id, '127.0.0.1', v.antes, v.despues
FROM (
  SELECT 'login_exitoso' AS accion, 'usuarios' AS entidad, 1 AS entidad_id,
         NULL AS antes, '{"usuario":"admin"}' AS despues
  UNION ALL
  SELECT 'crear', 'capacitaciones', NULL, NULL, '{"codigo":"HSEQ-ALT-001"}'
  UNION ALL
  SELECT 'crear', 'asignaciones_capacitacion', NULL, NULL, '{"persona_id_ext":46,"codigo":"HSEQ-ALT-001"}'
) AS v
LEFT JOIN usuarios usr ON usr.nombre_usuario = 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM auditoria a
  WHERE a.accion = v.accion AND a.entidad = v.entidad AND a.valor_nuevo <=> v.despues
);

-- Completar entidad_id de capacitacion cuando ya exista el curso.
UPDATE auditoria a
INNER JOIN capacitaciones c ON c.codigo = 'HSEQ-ALT-001'
SET a.entidad_id = c.capacitacion_id
WHERE a.accion = 'crear' AND a.entidad = 'capacitaciones' AND a.entidad_id IS NULL;
