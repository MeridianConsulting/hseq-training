-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 27-08-2026 a las 08:52:06
-- Versión del servidor: 10.11.18-MariaDB-cll-lve
-- Versión de PHP: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `meridian_personal`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `auditoria_id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `accion` varchar(60) NOT NULL,
  `entidad` varchar(60) DEFAULT NULL,
  `entidad_id` int(10) UNSIGNED DEFAULT NULL,
  `detalle_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle_json`)),
  `ip_origen` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`auditoria_id`, `usuario_id`, `accion`, `entidad`, `entidad_id`, `detalle_json`, `ip_origen`, `created_at`) VALUES
(1, 1, 'login_exitoso', 'usuarios_sistema', 1, NULL, '186.80.229.102', '2026-08-18 13:27:06'),
(2, 1, 'login_exitoso', 'usuarios_sistema', 1, NULL, '186.80.229.102', '2026-08-18 14:32:36'),
(3, 1, 'crear', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-18 14:34:28'),
(4, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-18 15:20:41'),
(5, 2, 'logout', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-18 15:20:45'),
(6, NULL, 'login_fallido', 'usuarios_sistema', 1, NULL, '186.80.229.102', '2026-08-20 13:07:20'),
(7, NULL, 'login_fallido', 'usuarios_sistema', 1, NULL, '186.80.229.102', '2026-08-20 13:07:56'),
(8, NULL, 'login_fallido', 'usuarios_sistema', 1, NULL, '186.80.229.102', '2026-08-25 14:16:54'),
(9, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-25 14:18:00'),
(10, 2, 'importar', 'novasoft', NULL, '{\"personas_creadas\":154,\"personas_actualizadas\":0,\"fallidas\":0}', '186.80.229.102', '2026-08-25 14:19:10'),
(11, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-25 14:21:51'),
(12, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '179.13.160.231', '2026-08-25 15:47:54'),
(13, 2, 'exportar', 'excel', NULL, '{\"q\":\"\",\"estado\":\"Activo\",\"cargo_id\":null}', '179.13.160.231', '2026-08-25 15:48:45'),
(14, NULL, 'login_fallido', 'usuarios_sistema', 1, NULL, '186.80.229.102', '2026-08-26 14:32:23'),
(15, NULL, 'login_fallido', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-26 14:32:39'),
(16, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-26 14:32:47'),
(17, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-26 14:35:23'),
(18, 2, 'importar', 'novasoft', NULL, '{\"personas_creadas\":2,\"personas_actualizadas\":151,\"fallidas\":0}', '186.80.229.102', '2026-08-26 14:55:28'),
(19, 2, 'exportar', 'excel', NULL, '{\"q\":\"\",\"estado\":\"Activo\",\"cargo_id\":null}', '186.80.229.102', '2026-08-26 14:56:49'),
(20, 2, 'actualizar', 'cargos', 3, NULL, '186.80.229.102', '2026-08-26 15:08:50'),
(21, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-26 15:16:02'),
(22, 2, 'cambiar_estado', 'personas', 97, '{\"estado\":\"Inactivo\"}', '186.80.229.102', '2026-08-26 15:26:54'),
(23, 2, 'generar', 'backup', NULL, NULL, '186.80.229.102', '2026-08-26 15:29:39'),
(24, 2, 'login_exitoso', 'usuarios_sistema', 2, NULL, '186.80.229.102', '2026-08-26 20:04:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bancos`
--

CREATE TABLE `bancos` (
  `banco_id` smallint(5) UNSIGNED NOT NULL,
  `codigo_novasoft` varchar(2) DEFAULT NULL,
  `nombre` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `bancos`
--

INSERT INTO `bancos` (`banco_id`, `codigo_novasoft`, `nombre`) VALUES
(1, NULL, 'BANCO DAVIVIENDA S.A.'),
(2, NULL, 'BANCOLOMBIA S.A.'),
(3, NULL, 'SCOTIABANK COLPATRIA'),
(4, NULL, 'BANCO NU'),
(5, NULL, 'BBVA COLOMBIA'),
(6, NULL, 'BANCO DE OCCIDENTE'),
(7, NULL, 'BANCO DE BOGOTÁ'),
(8, NULL, 'RAPPIPAY'),
(9, NULL, 'BANCO CAJA SOCIAL - BCSC S.A.'),
(10, NULL, 'LULO BANK S.A.'),
(11, NULL, 'NEQUI'),
(12, NULL, 'ITAÚ CORPBANCA COLOMBIA S.A.'),
(13, NULL, 'BANCOOMEVA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bonificaciones`
--

CREATE TABLE `bonificaciones` (
  `bonificacion_id` int(10) UNSIGNED NOT NULL,
  `contrato_id` int(10) UNSIGNED NOT NULL,
  `tipo_bonificacion` varchar(60) NOT NULL,
  `valor` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

CREATE TABLE `cargos` (
  `cargo_id` int(10) UNSIGNED NOT NULL,
  `codigo_cargo_novasoft` varchar(8) DEFAULT NULL,
  `nombre_cargo` varchar(120) NOT NULL,
  `descripcion_cargo` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`cargo_id`, `codigo_cargo_novasoft`, `nombre_cargo`, `descripcion_cargo`, `created_at`, `updated_at`) VALUES
(1, NULL, 'PRACTICANTE', NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(2, NULL, 'ASISTENTE ADMINISTRATIVO', NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(3, NULL, 'ANALISTA CONTABLE', NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(4, NULL, 'ASISTENTE LICITACIONES', NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(5, NULL, 'PROFESIONAL JUNIOR', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(6, NULL, 'PROFESIONAL BASICO', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(7, NULL, 'PROFESIONAL IT', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(8, NULL, 'PROFESIONAL GH', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(9, NULL, 'PROFESIONAL SENIOR', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(10, NULL, 'APRENDIZ SENA', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(11, NULL, 'TECNOLOGO DE DESARROLLO Y AUTOMATIZACION', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(12, NULL, 'ASISTENTE CONTABLE', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(13, NULL, 'PROFESIONAL ADMINISTRATIVA', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(14, NULL, 'AUXILIAR CONTABLE', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(15, NULL, 'ING COMPANY  D1', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(16, NULL, 'COORDINADOR  GH', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(17, NULL, 'SOPORTE OPERATIVO  D3', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(18, NULL, 'SOPORTE HSEQ', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(19, NULL, 'PROFESIONAL DE PROYECTOS', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(20, NULL, 'ASISTENTE COMPANY  D2', NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(21, NULL, 'COORDINADOR HSEQ', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(22, NULL, 'PROFESIONAL ESPECIALISTA', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(23, NULL, 'COORDINADOR CONTABLE', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(24, NULL, 'GERENTE ADMON Y  FINANCIERO', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(25, NULL, 'SERVICIOS GENERALES', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(26, NULL, 'SUBGERENTE', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(27, NULL, 'GERENTE GENERAL', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(28, NULL, 'ANALISTA NOMINA Y CONTRATACION', NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ciudades`
--

CREATE TABLE `ciudades` (
  `ciudad_id` int(10) UNSIGNED NOT NULL,
  `pais_id` smallint(5) UNSIGNED NOT NULL,
  `codigo_departamento` varchar(3) DEFAULT NULL,
  `codigo_novasoft` varchar(5) NOT NULL,
  `nombre` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `ciudades`
--

INSERT INTO `ciudades` (`ciudad_id`, `pais_id`, `codigo_departamento`, `codigo_novasoft`, `nombre`) VALUES
(1, 1, NULL, '', 'EL ENCANTO'),
(2, 1, NULL, '', 'BOGOTA'),
(3, 1, NULL, '', 'IBAGUE'),
(4, 1, NULL, '', 'CHIA'),
(5, 1, NULL, '', 'VILLAVICENCIO'),
(6, 1, NULL, '', 'NEIVA'),
(7, 1, NULL, '', 'FLORIDABLANCA'),
(8, 1, NULL, '', 'PIEDECUESTA'),
(9, 1, NULL, '', 'CUCUTA'),
(10, 1, NULL, '', 'LA CALERA'),
(11, 1, NULL, '', 'BUCARAMANGA'),
(12, 1, NULL, '', 'EL CERRITO'),
(13, 1, NULL, '', 'FUNZA'),
(14, 1, NULL, '', 'MEDELLIN'),
(15, 1, NULL, '', 'SANTA MARTA'),
(16, 1, NULL, '', 'PEREIRA'),
(17, 1, NULL, '', 'SOPO'),
(18, 1, NULL, '', 'YOPAL');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_nomina_novasoft`
--

CREATE TABLE `configuracion_nomina_novasoft` (
  `config_id` int(10) UNSIGNED NOT NULL,
  `contrato_id` int(10) UNSIGNED NOT NULL,
  `forma_pago_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `banco_id` smallint(5) UNSIGNED DEFAULT NULL,
  `numero_cuenta_banco` varchar(20) DEFAULT NULL,
  `tipo_cuenta_banco` enum('Ahorros','Corriente') DEFAULT NULL,
  `codigo_pago_electronico` varchar(20) DEFAULT NULL,
  `periodicidad_liquidacion_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `regimen_cesantias` enum('Anterior','Ley 50') DEFAULT NULL,
  `clase_salario` enum('Normal','Integral') DEFAULT NULL,
  `modo_liquidacion_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `metodo_retencion_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `porcentaje_retencion` decimal(5,2) DEFAULT NULL,
  `salario_variable` tinyint(1) DEFAULT NULL,
  `pago_por_dias` tinyint(1) DEFAULT NULL,
  `sabado_habil` tinyint(1) DEFAULT NULL,
  `pagar_dia_31_vacaciones` tinyint(1) DEFAULT NULL,
  `dias_vacaciones_anio` tinyint(3) UNSIGNED DEFAULT NULL,
  `promedio_salud_anio_anterior` decimal(14,2) DEFAULT NULL,
  `horas_mes` decimal(6,2) DEFAULT NULL,
  `valor_hora` decimal(12,2) DEFAULT NULL,
  `compania` varchar(10) DEFAULT NULL,
  `sucursal` varchar(10) DEFAULT NULL,
  `centro_costo` varchar(15) DEFAULT NULL,
  `centro_trabajo` varchar(15) DEFAULT NULL,
  `ciudad_labor_id` int(10) UNSIGNED DEFAULT NULL,
  `cuenta_gasto` varchar(20) DEFAULT NULL,
  `clasificador_1` varchar(15) DEFAULT NULL,
  `clasificador_2` varchar(15) DEFAULT NULL,
  `clasificador_3` varchar(15) DEFAULT NULL,
  `clasificador_4` varchar(15) DEFAULT NULL,
  `clasificador_5` varchar(15) DEFAULT NULL,
  `clasificador_6` varchar(15) DEFAULT NULL,
  `clasificador_7` varchar(15) DEFAULT NULL,
  `sucursal_seguridad_social` varchar(10) DEFAULT NULL,
  `convenio` varchar(30) DEFAULT NULL,
  `porcentaje_riesgo_arl` decimal(5,2) DEFAULT NULL,
  `actividad_economica_arl` varchar(10) DEFAULT NULL,
  `deducible_salud` decimal(14,2) DEFAULT NULL,
  `deducible_dependientes` decimal(14,2) DEFAULT NULL,
  `aporte_voluntario` decimal(14,2) DEFAULT NULL,
  `tipo_cotizante_id` char(2) DEFAULT NULL,
  `subtipo_cotizante` varchar(20) DEFAULT NULL,
  `es_contratista` tinyint(1) DEFAULT NULL,
  `es_lider_equipo` tinyint(1) DEFAULT NULL,
  `tipo_trabajo` varchar(30) DEFAULT NULL,
  `aplica_sector_publico` tinyint(1) DEFAULT NULL,
  `indicador_primer_empleo` tinyint(1) DEFAULT NULL,
  `indicador_ley1393` tinyint(1) DEFAULT NULL,
  `indicador_ley1450` tinyint(1) DEFAULT NULL,
  `atributos_novasoft_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`atributos_novasoft_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `configuracion_nomina_novasoft`
--

INSERT INTO `configuracion_nomina_novasoft` (`config_id`, `contrato_id`, `forma_pago_id`, `banco_id`, `numero_cuenta_banco`, `tipo_cuenta_banco`, `codigo_pago_electronico`, `periodicidad_liquidacion_id`, `regimen_cesantias`, `clase_salario`, `modo_liquidacion_id`, `metodo_retencion_id`, `porcentaje_retencion`, `salario_variable`, `pago_por_dias`, `sabado_habil`, `pagar_dia_31_vacaciones`, `dias_vacaciones_anio`, `promedio_salud_anio_anterior`, `horas_mes`, `valor_hora`, `compania`, `sucursal`, `centro_costo`, `centro_trabajo`, `ciudad_labor_id`, `cuenta_gasto`, `clasificador_1`, `clasificador_2`, `clasificador_3`, `clasificador_4`, `clasificador_5`, `clasificador_6`, `clasificador_7`, `sucursal_seguridad_social`, `convenio`, `porcentaje_riesgo_arl`, `actividad_economica_arl`, `deducible_salud`, `deducible_dependientes`, `aporte_voluntario`, `tipo_cotizante_id`, `subtipo_cotizante`, `es_contratista`, `es_lider_equipo`, `tipo_trabajo`, `aplica_sector_publico`, `indicador_primer_empleo`, `indicador_ley1393`, `indicador_ley1450`, `atributos_novasoft_json`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, '488474554711', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(2, 2, NULL, 2, '63422256646', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0102 - STAFF ADMINISTR 2\",\"SubCecosContable\":\"2 - Subcentro Contable 2\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(3, 3, NULL, 2, '91238514709', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 12976.19, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(4, 4, NULL, 2, '09426893484', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 11261.90, '001', '001', '004', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(5, 5, NULL, 3, '712009644', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(6, 6, NULL, 4, '62810996', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0102 - STAFF ADMINISTR 2\",\"SubCecosContable\":\"2 - Subcentro Contable 2\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(7, 7, NULL, 4, '50154243', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 32704.68, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90560321 - PETROSERVICIOS\",\"SubCecosContable\":\"321 - ODS 90560321\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(8, 8, NULL, 5, '0187001710', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 10961.90, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(9, 9, NULL, 2, '91232781071', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 10480.95, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(10, 10, NULL, 2, '00950901691', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(11, 11, NULL, 3, '1006326788', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(12, 12, NULL, 1, '0550488412147958', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(13, 13, NULL, 1, '0550488452468496', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 1, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 6253.23, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(14, 14, NULL, 4, '57614214', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(15, 15, NULL, 6, '256155656', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 11010.48, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(16, 16, NULL, 4, '95828827', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0102 - STAFF ADMINISTR 2\",\"SubCecosContable\":\"2 - Subcentro Contable 2\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(17, 17, NULL, 1, '488418257587', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90612258 - PETROSERVICIOS\",\"SubCecosContable\":\"258 - ODS 90612258\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(18, 18, NULL, 1, '6301272057', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90732957 - PETROSERVICIOS\",\"SubCecosContable\":\"957 - 957\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(19, 19, NULL, 1, '008400341650', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90560321 - PETROSERVICIOS\",\"SubCecosContable\":\"321 - ODS 90560321\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(20, 20, NULL, 1, '488406515970', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 10480.95, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(21, 21, NULL, 2, '70452348894', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90590325 - PETROSERVICIOS\",\"SubCecosContable\":\"325 - ODS 90590325\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(22, 22, NULL, 1, '005000378777', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(23, 23, NULL, 2, '04234517380', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90806951 - PETROSERVICIOS\",\"SubCecosContable\":\"951 - 96-951\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(24, 24, NULL, 5, '418205811', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(25, 25, NULL, 1, '488426651862', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90600752 - PETROSERVICIOS\",\"SubCecosContable\":\"752 - ODS 90600752\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(26, 26, NULL, 2, '04000000148', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90806951 - PETROSERVICIOS\",\"SubCecosContable\":\"951 - 96-951\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(27, 27, NULL, 2, '24545814087', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(28, 28, NULL, 2, '01363585490', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(29, 29, NULL, 1, '462900102866', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(30, 30, NULL, 2, '33748265517', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0102 - STAFF ADMINISTR 2\",\"SubCecosContable\":\"2 - Subcentro Contable 2\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(31, 31, NULL, 7, '474374295', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 43090.48, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93010 - NO\",\"SubCecosContable\":\"10 - Subcentro Contable 10\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(32, 32, NULL, 1, '0570488473661574', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(33, 33, NULL, 8, '143982322', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 19333.33, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(34, 34, NULL, 2, '61200017471', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(35, 35, NULL, 1, '0550006900753309', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 15519.05, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93012 - 9312 - FRONTERA D3\",\"SubCecosContable\":\"012 - D3 12\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(36, 36, NULL, 7, '047011838', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0102 - STAFF ADMINISTR 2\",\"SubCecosContable\":\"2 - Subcentro Contable 2\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(37, 37, NULL, 3, '7912978350', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90594501 - PETROSERVICIOS\",\"SubCecosContable\":\"501 - ODS 90594501\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(38, 38, NULL, 9, '24067622601', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 32704.68, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(39, 39, NULL, 2, '17475438035', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', NULL, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(40, 40, NULL, 10, '572967061171', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 11010.48, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(41, 41, NULL, 1, '0570488471454899', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', NULL, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.62, '001', '001', '096', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"96012 - ADMNISTRACION PETRO\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(42, 42, NULL, 1, '0570488470498731', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 1, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 6253.23, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(43, 43, NULL, 1, '005000190537', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(44, 44, NULL, 9, '24057244491', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(45, 45, NULL, 1, '176300002019', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 32704.68, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(46, 46, NULL, 5, '0136010015', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 9344.00, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(47, 47, NULL, 11, '3136279527', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 1, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 6253.23, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(48, 48, NULL, 2, '57461879227', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90590325 - PETROSERVICIOS\",\"SubCecosContable\":\"325 - ODS 90590325\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(49, 49, NULL, 2, '30400028334', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 14776.19, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"96012 - ADMNISTRACION PETRO\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(50, 50, NULL, 2, '65007616117', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(51, 51, NULL, 9, '24147345831', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', NULL, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.62, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(52, 52, NULL, 2, '01772357768', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(53, 53, NULL, 2, '18054527825', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(54, 54, NULL, 2, '67861125251', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(55, 55, NULL, 2, '21300059934', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 1, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 6253.23, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(56, 56, NULL, 7, '275099091', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 22990.48, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93021 - ADMIN FRONTERA\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(57, 57, NULL, 2, '25811411232', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(58, 58, NULL, 1, '0550006100993937', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(59, 59, NULL, 9, '24131076945', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', NULL, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(60, 60, NULL, 9, '24154612003', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 1, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 6253.23, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(61, 61, NULL, 2, '09000017041', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599358 - PETROSERVICIOS\",\"SubCecosContable\":\"958 - ODS 90599358\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(62, 62, NULL, 2, '17868710276', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90632454 - PETROSERVICIOS\",\"SubCecosContable\":\"454 - ODS90632454\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(63, 63, NULL, 1, '076000779744', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(64, 64, NULL, 2, '45497502024', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(65, 65, NULL, 2, '23682792329', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(66, 66, NULL, 5, '0483001450', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 43090.48, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93010 - NO\",\"SubCecosContable\":\"16 - D1 DIA\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-26 14:55:27'),
(67, 67, NULL, 5, '981634371', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(68, 68, NULL, 2, '45557445688', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(69, 69, NULL, 1, '0550488400817729', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(70, 70, NULL, 2, '06879869224', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(71, 71, NULL, 2, '07604255451', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(72, 72, NULL, 5, '0396000200014714', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(73, 73, NULL, 5, '232342840', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 32704.67, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(74, 74, NULL, 2, '00994630954', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90806951 - PETROSERVICIOS\",\"SubCecosContable\":\"951 - 96-951\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(75, 75, NULL, 2, '79974959424', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(76, 76, NULL, 2, '32270424341', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(77, 77, NULL, 2, '79658432171', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(78, 78, NULL, 2, '30600048640', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90806951 - PETROSERVICIOS\",\"SubCecosContable\":\"951 - 96-951\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(79, 79, NULL, 2, '24963721958', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-26 14:55:27'),
(80, 80, NULL, 5, '897219234', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(81, 81, NULL, 5, '333928869', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(82, 82, NULL, 7, '253066138', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(83, 83, NULL, 1, '007070364141', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(84, 84, NULL, 2, '72637924762', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599358 - PETROSERVICIOS\",\"SubCecosContable\":\"958 - ODS 90599358\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(85, 85, NULL, 2, '09035644935', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(86, 86, NULL, 2, '81446274571', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(87, 87, NULL, 2, '85868288553', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(88, 88, NULL, 7, '283054880', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(89, 89, NULL, 9, '24040574909', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(90, 90, NULL, 2, '09048984875', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90764204 - PETROSERVICIOS\",\"SubCecosContable\":\"404 - 404\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(91, 91, NULL, 2, '04483038254', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577770 - PETROSERVICIOS\",\"SubCecosContable\":\"777 - ODS 90577770\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(92, 92, NULL, 2, '60231784821', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(93, 93, NULL, 7, '157557059', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(94, 94, NULL, 9, '24103120847', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(95, 95, NULL, 10, '987429385270', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 43090.48, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93010 - NO\",\"SubCecosContable\":\"10 - Subcentro Contable 10\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(96, 96, NULL, 1, '0550488445206011', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599358 - PETROSERVICIOS\",\"SubCecosContable\":\"958 - ODS 90599358\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(97, 97, NULL, 12, '401107428', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 21761.90, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93011 - D2\",\"SubCecosContable\":\"11 - Subcentro Contable 11\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(98, 98, NULL, 5, '0232001144', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599924 - PETROSERVICIOS\",\"SubCecosContable\":\"924 - ODS 90599924\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(99, 99, NULL, 2, '09022014134', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 21761.90, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93011 - D2\",\"SubCecosContable\":\"11 - Subcentro Contable 11\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(100, 100, NULL, 2, '32269220855', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(101, 101, NULL, 5, '0135321925', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10');
INSERT INTO `configuracion_nomina_novasoft` (`config_id`, `contrato_id`, `forma_pago_id`, `banco_id`, `numero_cuenta_banco`, `tipo_cuenta_banco`, `codigo_pago_electronico`, `periodicidad_liquidacion_id`, `regimen_cesantias`, `clase_salario`, `modo_liquidacion_id`, `metodo_retencion_id`, `porcentaje_retencion`, `salario_variable`, `pago_por_dias`, `sabado_habil`, `pagar_dia_31_vacaciones`, `dias_vacaciones_anio`, `promedio_salud_anio_anterior`, `horas_mes`, `valor_hora`, `compania`, `sucursal`, `centro_costo`, `centro_trabajo`, `ciudad_labor_id`, `cuenta_gasto`, `clasificador_1`, `clasificador_2`, `clasificador_3`, `clasificador_4`, `clasificador_5`, `clasificador_6`, `clasificador_7`, `sucursal_seguridad_social`, `convenio`, `porcentaje_riesgo_arl`, `actividad_economica_arl`, `deducible_salud`, `deducible_dependientes`, `aporte_voluntario`, `tipo_cotizante_id`, `subtipo_cotizante`, `es_contratista`, `es_lider_equipo`, `tipo_trabajo`, `aplica_sector_publico`, `indicador_primer_empleo`, `indicador_ley1393`, `indicador_ley1450`, `atributos_novasoft_json`, `created_at`, `updated_at`) VALUES
(102, 102, NULL, 1, '0570047970010121', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(103, 103, NULL, 1, '0550146200084128', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(104, 104, NULL, 2, '29028954172', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(105, 105, NULL, 13, '051300474901', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90600752 - PETROSERVICIOS\",\"SubCecosContable\":\"752 - ODS 90600752\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(106, 106, NULL, 2, '15400000653', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 22119.05, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(107, 107, NULL, 2, '03099394942', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(108, 108, NULL, 1, '488415223103', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90612258 - PETROSERVICIOS\",\"SubCecosContable\":\"258 - ODS 90612258\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(109, 109, NULL, 2, '91254857951', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 14776.19, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93021 - ADMIN FRONTERA\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(110, 110, NULL, 5, '0333002156', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(111, 111, NULL, 2, '30451488984', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(112, 112, NULL, 2, '78878973835', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 32704.68, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(113, 113, NULL, 2, '36087691637', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90600752 - PETROSERVICIOS\",\"SubCecosContable\":\"752 - ODS 90600752\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(114, 114, NULL, 1, '116170080299', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', 'Cotizante no obligad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90732957 - PETROSERVICIOS\",\"SubCecosContable\":\"957 - 957\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(115, 115, NULL, 2, '29142425368', 'Ahorros', NULL, 2, 'Ley 50', 'Integral', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 113425.15, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90917418 - PETROSERVICIOS\",\"SubCecosContable\":\"418 - PETROSERVICIOS\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(116, 116, NULL, 2, '72691494722', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90917418 - PETROSERVICIOS\",\"SubCecosContable\":\"418 - PETROSERVICIOS\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(117, 117, NULL, 2, '29143798702', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(118, 118, NULL, 5, '628007569', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', 'Dependiente pensiona', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0102 - STAFF ADMINISTR 2\",\"SubCecosContable\":\"2 - Subcentro Contable 2\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(119, 119, NULL, 2, '20201158768', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90599358 - PETROSERVICIOS\",\"SubCecosContable\":\"958 - ODS 90599358\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(120, 120, NULL, 2, '69753677089', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(121, 121, NULL, 6, '215821042', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90600752 - PETROSERVICIOS\",\"SubCecosContable\":\"752 - ODS 90600752\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(122, 122, NULL, 3, '4192010744', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(123, 123, NULL, 1, '036600151710', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(124, 124, NULL, 6, '241883511', 'Ahorros', NULL, 2, 'Ley 50', 'Integral', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 113425.15, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90590325 - PETROSERVICIOS\",\"SubCecosContable\":\"0325 - ODS90590325\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(125, 125, NULL, 5, '765022025', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.41, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598546 - PETROSERVICIOS\",\"SubCecosContable\":\"846 - ODS90598546\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(126, 126, NULL, 3, '1008825153', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90732957 - PETROSERVICIOS\",\"SubCecosContable\":\"957 - 957\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(127, 127, NULL, 7, '799214937', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', 'Dependiente pensiona', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(128, 128, NULL, 2, '19161449876', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 30152.38, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(129, 129, NULL, 5, '880021159', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 34426.86, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(130, 130, NULL, 2, '01372415122', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(131, 131, NULL, 2, '19109966404', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(132, 132, NULL, 2, '60188065484', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(133, 133, NULL, 1, '0570008970179647', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"1661981 - PETROSERVICIOS\",\"SubCecosContable\":\"981 - ODS 1661981\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(134, 134, NULL, 5, '135993673', 'Ahorros', NULL, 2, 'Ley 50', 'Integral', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 113425.15, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90590325 - PETROSERVICIOS\",\"SubCecosContable\":\"0325 - ODS90590325\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(135, 135, NULL, 2, '20165829351', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90590325 - PETROSERVICIOS\",\"SubCecosContable\":\"325 - ODS 90590325\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(136, 136, NULL, 1, '488409892285', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90783729 - PETROSERVICIOS\",\"SubCecosContable\":\"729 - 729\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(137, 137, NULL, 3, '5442526011', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90577837 - PETROSERVICIOS\",\"SubCecosContable\":\"837 - ODS 90577837\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(138, 138, NULL, 2, '17213522132', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90917418 - PETROSERVICIOS\",\"SubCecosContable\":\"418 - PETROSERVICIOS\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(139, 139, NULL, 5, '396122574', 'Ahorros', NULL, 2, 'Ley 50', 'Integral', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 113425.15, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90560321 - PETROSERVICIOS\",\"SubCecosContable\":\"321 - ODS 90560321\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(140, 140, NULL, 4, '46606496', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90958813 - PETROSERVICIOS\",\"SubCecosContable\":\"813 - PETROSERVICIOS\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(141, 141, NULL, 4, '86176522', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 43090.48, '001', '001', '093', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"93010 - NO\",\"SubCecosContable\":\"10 - Subcentro Contable 10\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(142, 142, NULL, 2, '07064323592', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90560321 - PETROSERVICIOS\",\"SubCecosContable\":\"321 - ODS 90560321\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-26 14:55:28'),
(143, 143, NULL, 2, '17648719566', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(144, 144, NULL, 5, '781021720', 'Ahorros', NULL, 2, 'Ley 50', 'Integral', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 108389.36, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(145, 145, NULL, 1, '550008900117873', 'Ahorros', NULL, 2, 'Ley 50', 'Integral', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 108389.36, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(146, 146, NULL, 5, '0980009229', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90642057 - PETROSERVICIOS\",\"SubCecosContable\":\"57 - 90642057\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(147, 147, NULL, 2, '65874295641', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 14013.33, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(148, 148, NULL, 2, '07626895756', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(149, 149, NULL, 2, '00983341985', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(150, 150, NULL, 3, '1008167946', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90782963 - PETROSERVICIOS\",\"SubCecosContable\":\"963 - 963\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(151, 151, NULL, 1, '488440895479', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90642057 - PETROSERVICIOS\",\"SubCecosContable\":\"57 - 90642057\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(152, 152, NULL, 5, '396012419', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90598918 - PETROSERVICIOS\",\"SubCecosContable\":\"918 - ODS 90598918\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(153, 153, NULL, 2, '07836842141', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 49055.76, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90600752 - PETROSERVICIOS\",\"SubCecosContable\":\"752 - ODS 90600752\",\"DeducibleDependientesAplica\":\"SI\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(154, 154, NULL, 2, '04860659358', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 57282.40, '001', '001', '096', NULL, NULL, '72', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"90764204 - PETROSERVICIOS\",\"SubCecosContable\":\"404 - 404\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(167, 155, NULL, 1, '0550488452468496', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', NULL, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.64, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-26 14:55:27', '2026-08-26 14:55:27'),
(228, 156, NULL, 1, '0570489870025165', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 9047.62, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-26 14:55:27', '2026-08-26 14:55:27'),
(258, 157, NULL, 7, '299095174', 'Ahorros', NULL, 2, 'Ley 50', 'Normal', 0, 1, 0.00, 0, 0, NULL, NULL, NULL, NULL, 210.00, 8337.62, '001', '001', '001', NULL, NULL, '51', NULL, NULL, '0', '0', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"SubCentroCostos\":\"0101 - ADMINISTRACION 1\",\"SubCecosContable\":\"1 - ADMON\",\"DeducibleDependientesAplica\":\"NO\"}', '2026-08-26 14:55:27', '2026-08-26 14:55:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contratos`
--

CREATE TABLE `contratos` (
  `contrato_id` int(10) UNSIGNED NOT NULL,
  `persona_id` int(10) UNSIGNED NOT NULL,
  `contrato_anterior_id` int(10) UNSIGNED DEFAULT NULL,
  `numero_contrato` varchar(30) DEFAULT NULL,
  `tipo_contrato_id` smallint(5) UNSIGNED DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_terminacion` date DEFAULT NULL,
  `fecha_firma` date DEFAULT NULL,
  `fecha_fin_periodo_prueba` date DEFAULT NULL,
  `periodo_prueba_dias` smallint(5) UNSIGNED DEFAULT NULL,
  `motivo_terminacion` varchar(200) DEFAULT NULL,
  `ciudad_firma_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha_liquidacion_nomina` date DEFAULT NULL,
  `ods` varchar(50) DEFAULT NULL,
  `objeto_ods` varchar(200) DEFAULT NULL,
  `plazo_ods` varchar(50) DEFAULT NULL,
  `proyecto` varchar(120) DEFAULT NULL,
  `categoria` varchar(60) DEFAULT NULL,
  `gerencia_campo` varchar(60) DEFAULT NULL,
  `orden_trabajo` varchar(60) DEFAULT NULL,
  `salario_basico` decimal(14,2) DEFAULT NULL,
  `incluir_en_nomina` tinyint(1) NOT NULL DEFAULT 1,
  `deducible_vivienda` decimal(14,2) DEFAULT NULL,
  `deducible_medicina_prepagada` decimal(14,2) DEFAULT NULL,
  `deducible_beneficiarios` decimal(14,2) DEFAULT NULL,
  `afc` decimal(14,2) DEFAULT NULL,
  `avp` decimal(14,2) DEFAULT NULL,
  `avp_po` decimal(14,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `contratos`
--

INSERT INTO `contratos` (`contrato_id`, `persona_id`, `contrato_anterior_id`, `numero_contrato`, `tipo_contrato_id`, `fecha_inicio`, `fecha_terminacion`, `fecha_firma`, `fecha_fin_periodo_prueba`, `periodo_prueba_dias`, `motivo_terminacion`, `ciudad_firma_id`, `fecha_liquidacion_nomina`, `ods`, `objeto_ods`, `plazo_ods`, `proyecto`, `categoria`, `gerencia_campo`, `orden_trabajo`, `salario_basico`, `incluir_en_nomina`, `deducible_vivienda`, `deducible_medicina_prepagada`, `deducible_beneficiarios`, `afc`, `avp`, `avp_po`, `observaciones`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, 8, '2026-08-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(2, 2, NULL, NULL, 1, '2024-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(3, 3, NULL, NULL, 1, '2024-10-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2725000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(4, 4, NULL, NULL, 5, '2025-11-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2365000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(5, 5, NULL, NULL, 10, '2026-01-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 1192787.00, 589190.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(6, 6, NULL, NULL, 1, '2024-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(7, 7, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6867982.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(8, 8, NULL, NULL, 1, '2023-12-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2302000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(9, 9, NULL, NULL, 5, '2024-05-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2201000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(10, 10, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 192167.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(11, 11, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 1629186.00, 406169.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(12, 12, NULL, NULL, 10, '2026-06-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(13, 13, NULL, NULL, 9, '2025-05-20', '2026-08-18', NULL, NULL, NULL, 'Retiro reportado por Novasoft.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1313179.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(14, 14, NULL, NULL, 2, '2025-07-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(15, 15, NULL, NULL, 5, '2025-11-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2312200.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(16, 16, NULL, NULL, 1, '2024-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(17, 17, NULL, NULL, 10, '2026-01-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(18, 18, NULL, NULL, 10, '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(19, 19, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(20, 20, NULL, NULL, 1, '2023-05-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2201000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(21, 21, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(22, 22, NULL, NULL, 1, '2020-12-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(23, 23, NULL, NULL, 10, '2026-08-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(24, 24, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 338411.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(25, 25, NULL, NULL, 10, '2026-04-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(26, 26, NULL, NULL, 10, '2026-05-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(27, 27, NULL, NULL, 1, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(28, 28, NULL, NULL, 5, '2025-07-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(29, 29, NULL, NULL, 10, '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(30, 30, NULL, NULL, 1, '2024-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(31, 31, NULL, NULL, 10, '2026-01-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9049000.00, 1, 333444.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(32, 32, NULL, NULL, 5, '2026-07-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(33, 33, NULL, NULL, 1, '2023-06-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4060000.00, 1, 0.00, 35000.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(34, 34, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(35, 35, NULL, NULL, 10, '2026-04-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3259000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(36, 36, NULL, NULL, 1, '2024-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(37, 37, NULL, NULL, 10, '2026-01-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(38, 38, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6867982.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(39, 39, NULL, NULL, 8, '2026-04-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(40, 40, NULL, NULL, 5, '2025-12-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2312200.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(41, 41, NULL, NULL, 8, '2026-04-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750900.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(42, 42, NULL, NULL, 9, '2026-02-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1313179.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(43, 43, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(44, 44, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(45, 45, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6867982.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(46, 46, NULL, NULL, 1, '2023-08-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1962240.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(47, 47, NULL, NULL, 9, '2026-06-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1313179.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(48, 48, NULL, NULL, 10, '2026-01-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(49, 49, NULL, NULL, 2, '2024-08-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3103000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(50, 50, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(51, 51, NULL, NULL, 8, '2026-04-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750900.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(52, 52, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 318417.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(53, 53, NULL, NULL, 10, '2026-01-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 91143.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(54, 54, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(55, 55, NULL, NULL, 9, '2026-08-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1313179.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-26 14:55:27'),
(56, 56, NULL, NULL, 1, '2019-11-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4828000.00, 1, 0.00, 185191.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(57, 57, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(58, 58, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(59, 59, NULL, NULL, 8, '2026-07-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(60, 60, NULL, NULL, 9, '2026-05-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1313179.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(61, 61, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 630727.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(62, 62, NULL, NULL, 10, '2026-01-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(63, 63, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(64, 64, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(65, 65, NULL, NULL, 10, '2026-01-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(66, 66, NULL, NULL, 10, '2026-05-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9049000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(67, 67, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(68, 68, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 648018.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(69, 69, NULL, NULL, 10, '2026-06-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(70, 70, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(71, 71, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(72, 72, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 7583301.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(73, 73, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6867981.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(74, 74, NULL, NULL, 10, '2026-05-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(75, 75, NULL, NULL, 10, '2024-10-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(76, 76, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(77, 77, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(78, 78, NULL, NULL, 10, '2026-05-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(79, 79, NULL, NULL, 10, '2026-08-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(80, 80, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(81, 81, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(82, 82, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 303708.00, 62746.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(83, 83, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(84, 84, NULL, NULL, 10, '2026-01-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 372173.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(85, 85, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(86, 86, NULL, NULL, 10, '2026-01-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(87, 87, NULL, NULL, 1, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(88, 88, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(89, 89, NULL, NULL, 10, '2026-01-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(90, 90, NULL, NULL, 10, '2026-04-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(91, 91, NULL, NULL, 10, '2026-01-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 441175.00, 133697.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(92, 92, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(93, 93, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(94, 94, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(95, 95, NULL, NULL, 10, '2026-03-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9049000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(96, 96, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 957074.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(97, 97, NULL, NULL, 10, '2026-03-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4570000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(98, 98, NULL, NULL, 10, '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(99, 99, NULL, NULL, 10, '2026-04-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4570000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(100, 100, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(101, 101, NULL, NULL, 10, '2024-09-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 2321553.00, 736994.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(102, 102, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(103, 103, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(104, 104, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 1033583.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(105, 105, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 477967.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(106, 106, NULL, NULL, 1, '2022-11-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4645000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(107, 107, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(108, 108, NULL, NULL, 10, '2026-01-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 1194818.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(109, 109, NULL, NULL, 5, '2025-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3103000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(110, 110, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(111, 111, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(112, 112, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6867982.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(113, 113, NULL, NULL, 1, '2026-04-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(114, 114, NULL, NULL, 10, '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(115, 115, NULL, NULL, 10, '2026-07-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23819282.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(116, 116, NULL, NULL, 10, '2026-07-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(117, 117, NULL, NULL, 10, '2025-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 1079055.00, 234478.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(118, 118, NULL, NULL, 1, '2024-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(119, 119, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 1456943.00, 1601440.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(120, 120, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 2484279.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(121, 121, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(122, 122, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 512965.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(123, 123, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 816014.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(124, 124, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23819282.00, 1, 0.00, 1195202.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(125, 125, NULL, NULL, 10, '2026-01-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029307.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(126, 126, NULL, NULL, 10, '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(127, 127, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(128, 128, NULL, NULL, 1, '2018-01-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6332000.00, 1, 0.00, 209690.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(129, 129, NULL, NULL, 1, '2009-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 7229640.00, 1, 1217782.00, 388596.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(130, 130, NULL, NULL, 1, '2011-06-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(131, 131, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(132, 132, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(133, 133, NULL, NULL, 10, '2026-01-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(134, 134, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23819282.00, 1, 0.00, 809231.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(135, 135, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(136, 136, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(137, 137, NULL, NULL, 10, '2026-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 1507679.00, 565656.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(138, 138, NULL, NULL, 10, '2026-07-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 165346.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(139, 139, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23819282.00, 1, 2350361.00, 665000.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(140, 140, NULL, NULL, 10, '2026-08-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(141, 141, NULL, NULL, 10, '2026-04-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9049000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(142, 142, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(143, 143, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(144, 144, NULL, NULL, 1, '2009-08-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22761765.00, 1, 0.00, 1826046.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(145, 145, NULL, NULL, 1, '2009-08-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22761765.00, 1, 0.00, 770000.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(146, 146, NULL, NULL, 10, '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(147, 147, NULL, NULL, 1, '2025-01-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2942800.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(148, 148, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(149, 149, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(150, 150, NULL, NULL, 10, '2026-04-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(151, 151, NULL, NULL, 10, '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 2242085.00, 600697.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(152, 152, NULL, NULL, 10, '2026-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(153, 153, NULL, NULL, 10, '2026-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10301710.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(154, 154, NULL, NULL, 10, '2026-08-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12029304.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(155, 13, NULL, NULL, 8, '2026-08-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750905.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-26 14:55:27', '2026-08-26 14:55:27'),
(156, 155, NULL, NULL, 2, '2026-07-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1900000.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-26 14:55:27', '2026-08-26 14:55:27'),
(157, 156, NULL, NULL, 2, '2026-08-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1750900.00, 1, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-08-26 14:55:27', '2026-08-26 14:55:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_civiles`
--

CREATE TABLE `estados_civiles` (
  `estado_civil_id` tinyint(3) UNSIGNED NOT NULL,
  `descripcion` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `estados_civiles`
--

INSERT INTO `estados_civiles` (`estado_civil_id`, `descripcion`) VALUES
(0, 'Desconocido'),
(1, 'Soltero'),
(2, 'Casado'),
(3, 'Viudo'),
(4, 'Separado'),
(5, 'Unión Libre'),
(6, 'Religioso'),
(7, 'Otro');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fondos`
--

CREATE TABLE `fondos` (
  `fondo_id` smallint(5) UNSIGNED NOT NULL,
  `tipo` enum('AFP','EPS','ARL','CCF','CESANTIAS','OTRO') NOT NULL,
  `codigo_novasoft` varchar(4) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `fondos`
--

INSERT INTO `fondos` (`fondo_id`, `tipo`, `codigo_novasoft`, `nombre`) VALUES
(1, 'EPS', NULL, 'COMPENSAR ENTIDAD PROMOTORA DE SALUD'),
(2, 'AFP', NULL, 'PORVENIR'),
(3, 'ARL', NULL, 'COLMENA RIESGOS PROFESIONALES'),
(4, 'CCF', NULL, 'CCF COMPENSAR'),
(5, 'CESANTIAS', NULL, 'PORVENIR'),
(6, 'EPS', NULL, 'E.P.S SANITAS'),
(7, 'CESANTIAS', NULL, 'PROTECCION'),
(8, 'EPS', NULL, 'FAMISANAR'),
(9, 'CESANTIAS', NULL, 'COLFONDOS'),
(10, 'AFP', NULL, 'PROTECCIÓN'),
(11, 'AFP', NULL, 'COLFONDOS'),
(12, 'CESANTIAS', NULL, 'FONDO NACIONAL DEL AHORRO - FNA'),
(13, 'EPS', NULL, 'ALIANSALUD EPS'),
(14, 'AFP', NULL, 'ADMINISTRADORA COLOMBIANA DE PENSIONES COLPENSIONES'),
(15, 'EPS', NULL, 'EPS SURA'),
(16, 'AFP', NULL, 'OLD MUTUAL FONDO DE PENSIONES OBLIGATORIAS'),
(17, 'CESANTIAS', NULL, 'SKANDIA'),
(18, 'EPS', NULL, 'SALUD TOTAL S.A.'),
(19, 'EPS', NULL, 'FONDO DE SOLIDARIDAD Y GARANTÍA FOSYGA'),
(20, 'EPS', NULL, 'NUEVA EPS'),
(21, 'CCF', NULL, 'COFREM META'),
(22, 'EPS', NULL, 'Capital Salud'),
(23, 'CCF', NULL, 'COMFACASANARE'),
(24, 'EPS', NULL, 'UNISALUD EPS'),
(25, 'EPS', NULL, 'SALUD MIA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formas_pago`
--

CREATE TABLE `formas_pago` (
  `forma_pago_id` tinyint(3) UNSIGNED NOT NULL,
  `descripcion` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `formas_pago`
--

INSERT INTO `formas_pago` (`forma_pago_id`, `descripcion`) VALUES
(1, 'Consignación cta. ahorros'),
(2, 'Consignación cta. corriente'),
(3, 'Pago con cheque'),
(4, 'Pago en efectivo'),
(5, 'Otra forma de pago');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_ausentismos`
--

CREATE TABLE `historial_ausentismos` (
  `historial_id` bigint(20) UNSIGNED NOT NULL,
  `persona_id` int(10) UNSIGNED NOT NULL,
  `tipo_ausencia` varchar(5) DEFAULT NULL,
  `fecha_inicio_ausencia` date NOT NULL,
  `fecha_inicio_nomina` date DEFAULT NULL,
  `dias_ausencia` smallint(5) UNSIGNED DEFAULT NULL,
  `codigo_enfermedad` varchar(10) DEFAULT NULL,
  `causa` varchar(100) DEFAULT NULL,
  `numero_incapacidad_admon` varchar(30) DEFAULT NULL,
  `interrumpe` tinyint(1) DEFAULT NULL,
  `hospitalaria` tinyint(1) DEFAULT NULL,
  `patron_asume` tinyint(1) DEFAULT NULL,
  `interrumpio` tinyint(1) DEFAULT NULL,
  `prorroga` tinyint(1) DEFAULT NULL,
  `numero_incapacidad_prorroga` varchar(30) DEFAULT NULL,
  `usuario_novasoft` varchar(30) DEFAULT NULL,
  `indicador_legalizada` tinyint(1) DEFAULT NULL,
  `incluir_nomina` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_nomina`
--

CREATE TABLE `historial_nomina` (
  `historial_id` bigint(20) UNSIGNED NOT NULL,
  `persona_id` int(10) UNSIGNED NOT NULL,
  `codigo_concepto` varchar(10) DEFAULT NULL,
  `tipo_liquidacion` char(2) DEFAULT NULL,
  `fecha_corte` date NOT NULL,
  `valor_liquidado` decimal(14,2) DEFAULT NULL,
  `cantidad_liquidada` decimal(10,2) DEFAULT NULL,
  `naturaleza_liquidacion` tinyint(4) DEFAULT NULL,
  `modo_liquidacion_codigo` tinyint(4) DEFAULT NULL,
  `aplica_concepto` tinyint(4) DEFAULT NULL,
  `secuencia_concepto` smallint(6) DEFAULT NULL,
  `codigo_contrato` varchar(20) DEFAULT NULL,
  `fecha_liquidacion` date DEFAULT NULL,
  `usuario_novasoft` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_seg_social`
--

CREATE TABLE `historial_seg_social` (
  `historial_id` bigint(20) UNSIGNED NOT NULL,
  `persona_id` int(10) UNSIGNED NOT NULL,
  `tipo_liquidacion` char(2) DEFAULT NULL,
  `tipo_registro` char(1) DEFAULT NULL,
  `fecha_corte` date NOT NULL,
  `dias_salud` smallint(5) UNSIGNED DEFAULT NULL,
  `dias_pension` smallint(5) UNSIGNED DEFAULT NULL,
  `dias_riesgos` smallint(5) UNSIGNED DEFAULT NULL,
  `ibc_salud` decimal(14,2) DEFAULT NULL,
  `ibc_pension` decimal(14,2) DEFAULT NULL,
  `ibc_riesgos` decimal(14,2) DEFAULT NULL,
  `codigo_contrato` varchar(20) DEFAULT NULL,
  `usuario_novasoft` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_vacaciones`
--

CREATE TABLE `historial_vacaciones` (
  `historial_id` bigint(20) UNSIGNED NOT NULL,
  `persona_id` int(10) UNSIGNED NOT NULL,
  `fecha_inicio` date NOT NULL,
  `dias_a_disfrutar` smallint(5) UNSIGNED DEFAULT NULL,
  `dias_a_pagar` smallint(5) UNSIGNED DEFAULT NULL,
  `fecha_corte` date DEFAULT NULL,
  `pagar` tinyint(1) DEFAULT NULL,
  `tipo_liquidacion` char(2) DEFAULT NULL,
  `pagar_hasta_inicio` tinyint(1) DEFAULT NULL,
  `usuario_novasoft` varchar(30) DEFAULT NULL,
  `valor_vacaciones` decimal(14,2) DEFAULT NULL,
  `valor_dinero` decimal(14,2) DEFAULT NULL,
  `fecha_ibc` date DEFAULT NULL,
  `fecha_corte_pago` date DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_retencion`
--

CREATE TABLE `metodos_retencion` (
  `metodo_retencion_id` tinyint(3) UNSIGNED NOT NULL,
  `descripcion` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `metodos_retencion`
--

INSERT INTO `metodos_retencion` (`metodo_retencion_id`, `descripcion`) VALUES
(1, 'Método 1'),
(2, 'Método 2');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modos_liquidacion`
--

CREATE TABLE `modos_liquidacion` (
  `modo_liquidacion_id` tinyint(3) UNSIGNED NOT NULL,
  `descripcion` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `modos_liquidacion`
--

INSERT INTO `modos_liquidacion` (`modo_liquidacion_id`, `descripcion`) VALUES
(0, 'Normal'),
(1, 'Aprendiz'),
(2, 'Sin transporte'),
(3, 'Asumido SS'),
(4, 'Especial'),
(5, 'Liquidación x hora'),
(6, 'Flexibilización'),
(10, 'Valor hora x clasificación'),
(11, 'Valor hora x puntos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nacionalidades`
--

CREATE TABLE `nacionalidades` (
  `nacionalidad_id` tinyint(3) UNSIGNED NOT NULL,
  `descripcion` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `nacionalidades`
--

INSERT INTO `nacionalidades` (`nacionalidad_id`, `descripcion`) VALUES
(1, 'Colombiano'),
(2, 'Extranjero'),
(3, 'Doble');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paises`
--

CREATE TABLE `paises` (
  `pais_id` smallint(5) UNSIGNED NOT NULL,
  `codigo_novasoft` varchar(3) DEFAULT NULL,
  `nombre` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `paises`
--

INSERT INTO `paises` (`pais_id`, `codigo_novasoft`, `nombre`) VALUES
(1, NULL, 'Colombia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodicidades_liquidacion`
--

CREATE TABLE `periodicidades_liquidacion` (
  `periodicidad_id` tinyint(3) UNSIGNED NOT NULL,
  `descripcion` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `periodicidades_liquidacion`
--

INSERT INTO `periodicidades_liquidacion` (`periodicidad_id`, `descripcion`) VALUES
(0, 'No aplica'),
(1, 'Quincenal'),
(2, 'Mensual'),
(3, 'Semanal'),
(4, 'Catorcenal'),
(5, 'Grupo 2');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas`
--

CREATE TABLE `personas` (
  `persona_id` int(10) UNSIGNED NOT NULL,
  `numero_documento` varchar(15) NOT NULL,
  `tipo_documento_id` smallint(5) UNSIGNED NOT NULL,
  `cod_emp_novasoft` varchar(12) DEFAULT NULL,
  `codigo_alterno_novasoft` varchar(15) DEFAULT NULL,
  `pais_exp_documento_id` smallint(5) UNSIGNED DEFAULT NULL,
  `ciudad_exp_documento_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha_expedicion_documento` date DEFAULT NULL,
  `numero_pasaporte` varchar(20) DEFAULT NULL,
  `pais_emisor_pasaporte_id` smallint(5) UNSIGNED DEFAULT NULL,
  `fecha_expedicion_pasaporte` date DEFAULT NULL,
  `numero_libreta_militar` varchar(12) DEFAULT NULL,
  `clase_libreta_militar` varchar(2) DEFAULT NULL,
  `distrito_militar` varchar(2) DEFAULT NULL,
  `primer_apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `primer_nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `nombre_completo_apellidos_primero` varchar(210) GENERATED ALWAYS AS (trim(concat_ws(' ',`primer_apellido`,`segundo_apellido`,`primer_nombre`,`segundo_nombre`))) VIRTUAL,
  `nombre_completo_nombres_primero` varchar(210) GENERATED ALWAYS AS (trim(concat_ws(' ',`primer_nombre`,`segundo_nombre`,`primer_apellido`,`segundo_apellido`))) VIRTUAL,
  `fecha_nacimiento_texto` char(8) NOT NULL,
  `fecha_nacimiento_date` date GENERATED ALWAYS AS (str_to_date(`fecha_nacimiento_texto`,'%d%m%Y')) VIRTUAL,
  `genero` enum('F','M') DEFAULT NULL,
  `estado_civil_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `nacionalidad_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `grupo_sanguineo` varchar(3) DEFAULT NULL,
  `factor_rh` char(1) DEFAULT NULL,
  `direccion_residencia` varchar(150) DEFAULT NULL,
  `barrio` varchar(80) DEFAULT NULL,
  `codigo_barrio` varchar(10) DEFAULT NULL,
  `localidad` varchar(80) DEFAULT NULL,
  `ciudad_residencia_id` int(10) UNSIGNED DEFAULT NULL,
  `ciudad_nacimiento_id` int(10) UNSIGNED DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `telefono_fijo` varchar(20) DEFAULT NULL,
  `correo_personal` varchar(100) DEFAULT NULL,
  `correo_corporativo` varchar(100) DEFAULT NULL,
  `cargo_id` int(10) UNSIGNED DEFAULT NULL,
  `eps_id` smallint(5) UNSIGNED DEFAULT NULL,
  `afp_id` smallint(5) UNSIGNED DEFAULT NULL,
  `fondo_cesantias_id` smallint(5) UNSIGNED DEFAULT NULL,
  `ccf_id` smallint(5) UNSIGNED DEFAULT NULL,
  `arl_id` smallint(5) UNSIGNED DEFAULT NULL,
  `nivel_riesgo_arl` varchar(10) DEFAULT NULL,
  `estatura_cm` smallint(5) UNSIGNED DEFAULT NULL,
  `peso_kg` decimal(5,2) DEFAULT NULL,
  `numero_hijos` smallint(5) UNSIGNED DEFAULT NULL,
  `numero_personas_a_cargo` smallint(5) UNSIGNED DEFAULT NULL,
  `tiene_dependientes` tinyint(1) DEFAULT NULL,
  `pensionado` tinyint(1) DEFAULT NULL,
  `pensionado_por_empresa` tinyint(1) DEFAULT NULL,
  `indicador_transicion_pensional` tinyint(1) DEFAULT NULL,
  `indicador_extranjero_pension` tinyint(1) DEFAULT NULL,
  `reside_extranjero` tinyint(1) DEFAULT NULL,
  `profesion` varchar(100) DEFAULT NULL,
  `poliza_vida` varchar(60) DEFAULT NULL,
  `postulacion_spe` varchar(30) DEFAULT NULL,
  `certificado_residencia_vigencia` date DEFAULT NULL,
  `observaciones_alivios` text DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `personas`
--

INSERT INTO `personas` (`persona_id`, `numero_documento`, `tipo_documento_id`, `cod_emp_novasoft`, `codigo_alterno_novasoft`, `pais_exp_documento_id`, `ciudad_exp_documento_id`, `fecha_expedicion_documento`, `numero_pasaporte`, `pais_emisor_pasaporte_id`, `fecha_expedicion_pasaporte`, `numero_libreta_militar`, `clase_libreta_militar`, `distrito_militar`, `primer_apellido`, `segundo_apellido`, `primer_nombre`, `segundo_nombre`, `fecha_nacimiento_texto`, `genero`, `estado_civil_id`, `nacionalidad_id`, `grupo_sanguineo`, `factor_rh`, `direccion_residencia`, `barrio`, `codigo_barrio`, `localidad`, `ciudad_residencia_id`, `ciudad_nacimiento_id`, `celular`, `telefono_fijo`, `correo_personal`, `correo_corporativo`, `cargo_id`, `eps_id`, `afp_id`, `fondo_cesantias_id`, `ccf_id`, `arl_id`, `nivel_riesgo_arl`, `estatura_cm`, `peso_kg`, `numero_hijos`, `numero_personas_a_cargo`, `tiene_dependientes`, `pensionado`, `pensionado_por_empresa`, `indicador_transicion_pensional`, `indicador_extranjero_pension`, `reside_extranjero`, `profesion`, `poliza_vida`, `postulacion_spe`, `certificado_residencia_vigencia`, `observaciones_alivios`, `estado`, `created_at`, `updated_at`) VALUES
(1, '1000003164', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVELLANEDA', 'RODRIGUEZ', 'MARIA', 'FERNANDA', '23052002', 'F', 1, NULL, NULL, NULL, 'Calle 54D Sur - 78F 21', NULL, NULL, NULL, 1, NULL, '3142965412', '3142965412', 'maria.avellaneda1014@gmail.com', NULL, 1, 1, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(2, '1000185449', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FRANCO', 'REINA', 'NATALIA', 'XIMENA', '29042001', 'F', 1, NULL, NULL, NULL, 'AV BYC  129  01', NULL, NULL, NULL, 2, NULL, '33769555697', '7469090', 'nataliaxfranco@gmail.com', NULL, 2, 6, 2, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(3, '1000931984', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CARRANZA', 'RODRIGUEZ', 'KAREN', 'JULIETH', '21052002', 'F', 1, NULL, NULL, NULL, 'TV 18 Q BIS D  61B SUR', NULL, NULL, NULL, 2, NULL, '3053852350', '7469090', 'krodri8888@gmail.com', NULL, 3, 8, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(4, '1000987240', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GAMEZ', 'GOMEZ', 'LAURA', 'KARIINA', '11112000', 'F', 1, NULL, NULL, NULL, 'cra 14T # 74 B 59 sur', NULL, NULL, NULL, 2, NULL, '3133328547', '3133328547', 'lkarinag2026@icloud.com', NULL, 4, 8, 10, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:08', '2026-08-25 14:19:08'),
(5, '1003934174', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CUESTA', 'ASPRILLA', 'JHON', 'ABELARDO', '26051992', 'M', 1, NULL, NULL, NULL, 'CL 48  13  70', NULL, NULL, NULL, 2, NULL, '3218467737', '7469090', 'jhcuestaa@gmail.com', NULL, 5, 6, 11, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(6, '1007407868', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URREGO', 'SANDOVAL', 'NICOLAS', NULL, '19092000', 'M', 1, NULL, NULL, NULL, 'CL  166  9  45', NULL, NULL, NULL, 2, NULL, '3185179135', '7469090', 'nicolasurregos@gmail.com', NULL, 2, 13, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(7, '1007555164', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'POVEDA', 'SALAZAR', 'SERGIO', 'FERNANDO', '11082000', 'M', 1, NULL, NULL, NULL, 'CR 32  19B  10', NULL, NULL, NULL, 2, NULL, '3015198066', '7469090', 'sf.poveda@uniandes.edu.co', NULL, 6, 6, 11, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(8, '1007627524', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CARDENAS', 'REYES', 'ANDRES', 'CAMILO', '31101999', 'M', 1, NULL, NULL, NULL, 'CR  116A  15C  70', NULL, NULL, NULL, 2, NULL, '3138458839', '7469090', 'camilocardena73@gmail.com', NULL, 7, 1, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(9, '1007647736', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FONSECA', 'LOPEZ', 'SONIA', 'STEPHANIA', '25032000', 'F', 1, NULL, NULL, NULL, 'CL 55 SUR 69A 19', NULL, NULL, NULL, 2, NULL, '3115960601', '3115960601', 'stephanyfon25@gmail.com', NULL, 8, 8, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(10, '1010056001', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'YACELGA', 'CHITAN', 'EMELI', 'YOHANA', '02101989', 'F', 1, NULL, NULL, NULL, 'CL 5A SUR  40A 127', NULL, NULL, NULL, 2, NULL, '3223594580', '7469090', 'emeli.yacelga@gmail.com', NULL, 5, 15, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(11, '1010167959', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BOTERO', 'RIVERA', 'FRANKLIN', 'ALEJANDRO', '11091986', 'M', 1, NULL, NULL, NULL, 'CL 144  11 07', NULL, NULL, NULL, 2, NULL, '3046364482', '7469090', 'alejandro.botero1120@gmail.com', NULL, 9, 6, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(12, '1010173817', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SIERRA', 'AMARIS', 'MARIA', 'FERNANDA', '27081987', 'F', 1, NULL, NULL, NULL, 'Carrera 8 # 167D - 62 Apto 1201', NULL, NULL, NULL, 2, NULL, '3143161153', '3143161153', 'mafe.siam@gmail.com', NULL, 5, 6, 16, 17, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(13, '1010222610', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALAVA', 'CHAVEZ', 'JESSICA', 'ALEXANDRA', '20061995', 'F', 1, NULL, NULL, NULL, 'CL 143 A 113 C 50', NULL, NULL, NULL, 2, NULL, '3132757050', '7469090', 'jessica.a.alava@hotmail.com', NULL, 10, 18, 14, 5, NULL, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-26 14:55:27'),
(14, '1011202252', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LOPEZ', 'CIFUENTES', 'JOSE', 'MATEO', '25112006', 'M', 1, NULL, NULL, NULL, 'CR 71d  56f  22 SUR', NULL, NULL, NULL, 2, NULL, '3208023808', '7469090', 'josemateolopezcifuentes@gmail.com', NULL, 11, 1, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(15, '1012395152', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MORALES', 'SEGURA', 'JULIAN', 'ANDRES', '16111992', 'M', 1, NULL, NULL, NULL, 'DG 73 H SUR # 78 B 09', NULL, NULL, NULL, 2, NULL, '3164215232', '3164215232', 'JULIAN.ANDRESM1992@GMAIL.COM', NULL, 12, 8, 10, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(16, '1013261036', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FRANCO', 'REINA', 'CHRISTIAN', 'CAMILO', '27012005', 'M', 1, NULL, NULL, NULL, 'AV BYC  129  01', NULL, NULL, NULL, 2, NULL, '3505393712', '7469090', 'christianfranco688@gmail.com', NULL, 2, 19, 2, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(17, '1013629348', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CHIRTIAN', 'CAMILO', 'RIVERA', 'SANCHEZ', '05121991', 'M', 1, NULL, NULL, NULL, 'Cra 69d # 1 - 60 Sur, Torre 1 Apto 202', NULL, NULL, NULL, 1, NULL, '3115546422', '3115546422', 'chris911205@gmail.com', NULL, 9, 6, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(18, '1013633604', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BAUTISTA', 'VELANDIA', 'GUSTAVO', 'ANDRES', '07071992', 'M', 1, NULL, NULL, NULL, 'CL 19 SUR 27 18', NULL, NULL, NULL, 2, NULL, '3134217276', '3057081527', 'andresbautistavelandia@gmail.com', NULL, 9, 1, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(19, '1013634120', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MAYORGA', 'LOPEZ', 'EDWIN', 'FABIAN', '07081992', 'M', 1, NULL, NULL, NULL, 'CR 24  31c  27 SUR', NULL, NULL, NULL, 2, NULL, '3114985755', '7469090', 'edwin.f.mayorga@gmail.com', NULL, 5, 1, 14, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(20, '1014180459', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FLOREZ', 'PRADO', 'SANDRA', 'MILENA', '26101986', 'F', 1, NULL, NULL, NULL, 'CL 1d  5B  25 Este', NULL, NULL, NULL, 2, NULL, '3125478393', '7469090', 'samiflop@hotmail.com', NULL, 13, 6, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(21, '1014216060', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FORERO', 'PENA', 'CARLOS', 'ALEJANDRO', '15111990', 'M', 1, NULL, NULL, NULL, 'CL 160 57 70', NULL, NULL, NULL, 2, NULL, '3175768857', '7469090', 'forero_c05@hotmail.com', NULL, 9, 8, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(22, '1014251428', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONTRERAS', 'TORRES', 'AURA', 'ALEJANDRA', '13121993', 'F', 1, NULL, NULL, NULL, 'CR 111  86 A 68', NULL, NULL, NULL, 2, NULL, '3103404348', '7469090', 'alejandracontrerast.13@gmail.com', NULL, 2, 1, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(23, '1014262113', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PRADA', 'FONSECA', 'MARIA', 'ANGELICA', '23121994', 'F', 1, NULL, NULL, NULL, 'CL 87 103 C 50', NULL, NULL, NULL, 2, NULL, '3144498741', '7469090', 'pradaangelica@hotmail.com', NULL, 5, 15, 2, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(24, '1016037506', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GOMEZ', 'CABRERA', 'PAOLA', 'ANDREA', '03071991', 'F', 1, NULL, NULL, NULL, 'CR 27  49  29', NULL, NULL, NULL, 2, NULL, '3168735316', '7469090', 'paolago40@gmail.com', NULL, 5, 8, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(25, '1016077074', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GOMEZ', 'ARDILA', 'MIGUEL', 'ANGEL', '04061995', 'M', 1, NULL, NULL, NULL, 'Carrera 81b #19b-90 Torre A Apto 401', NULL, NULL, NULL, 2, NULL, '3196906840', '3196906840', 'miguelmag1500@gmail.com', NULL, 5, 6, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(26, '1016597', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CEDEÑO', 'ORFILA', 'JEAN', 'PABLO', '10021981', 'M', 1, NULL, NULL, NULL, 'Carrera 74 # 152b -70 Conjunto Residencial Natura Living, Torre 3 Apto 1504, Colina Campestre, Suba', NULL, NULL, NULL, 2, NULL, '3015428536', '3015428536', 'jeanpabloc@gmail.com', NULL, 9, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(27, '1017211010', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GIRALDO', 'MUÑOZ', 'MARIA', 'ALEJANDRA', '12061993', 'F', 1, NULL, NULL, NULL, 'Calle 106a # 43b - 163 2do piso - Andalucía', NULL, NULL, NULL, 1, NULL, '3017742634', '3017742634', 'maragiraldomun@gmail.com', NULL, 9, 6, 14, 17, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(28, '1018516821', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MELO', 'RODRIGUEZ', 'LUISA', 'MARIA', '22092005', 'F', 1, NULL, NULL, NULL, 'CL 71 P SUR 27 K 22', NULL, NULL, NULL, 2, NULL, '3046045261', '7469090', 'luisamrdz22@gmail.com', NULL, 14, 18, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(29, '1019011177', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BELLO', 'AREVALO', 'LEIDY', 'JOHANNA', '18111986', 'F', 1, NULL, NULL, NULL, 'CR 62  165A  69', NULL, NULL, NULL, 2, NULL, '3142420913', '7469090', 'jovare_x86@hotmail.com', NULL, 5, 20, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(30, '1019087239', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CASTRO', 'FRANCO', 'JUAN', 'DAVID', '03101993', 'M', 1, NULL, NULL, NULL, 'CR 48  150 A 40', NULL, NULL, NULL, 2, NULL, '3158111370', '7469090', 'juandafranco3@gmail.com', NULL, 2, 6, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(31, '1019133012', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'POMARE', 'ORTEGA', 'DARIO', 'ALEXANDER', '30081997', 'M', 1, NULL, NULL, NULL, 'Cra. 13B Sur #100-59 Torre 6, Apto 721', NULL, NULL, NULL, 3, NULL, '3128894731', '3128894731', 'juniorpomareo@hotmail.com', NULL, 15, 18, 14, 12, 21, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(32, '1019136324', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MACIAS', 'CARDENAS', 'SANDRA', 'MILENA', '16121997', 'F', 1, NULL, NULL, NULL, 'Carrera 12B #11-50 SUR', NULL, NULL, NULL, 2, NULL, '3112318372', '3112318372', 'macias_sandramilena@hotmail.com', NULL, 2, 15, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(33, '1020733194', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GOMEZ', 'REYES', 'ELOY', 'GABRIEL', '07061988', 'M', 1, NULL, NULL, NULL, 'CL 185  17 66', NULL, NULL, NULL, 2, NULL, '3011282498', '1', 'gabrielgomezreyes.07@gmail.com', NULL, 16, 1, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(34, '1020792684', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALARCON', 'TORRES', 'JORGE', 'FELIPE', '16051994', 'M', 1, NULL, NULL, NULL, 'Calle 146 # 7b - 50', NULL, NULL, NULL, 2, NULL, '3022867803', '3022867803', 'jorge.alarcon7473@gmail.com', NULL, 5, 22, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(35, '1020837503', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CANO', 'CHACUA', 'JUAN', 'GUILLERMO', '10121998', 'M', 1, NULL, NULL, NULL, 'Diagonal 182 No. 20-71', NULL, NULL, NULL, 2, NULL, '3192406873', '3192406873', 'jgcano1012@gmail.com', NULL, 17, 15, 11, 9, 23, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(36, '1022344726', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALFONSO', 'AVENDANO', 'VIVIANA', 'DEL PILAR', '18021988', 'F', 1, NULL, NULL, NULL, 'CR 50 123 A 09', NULL, NULL, NULL, 2, NULL, '3053993712', '7469090', 'viviana.alfonsoa@gmail.com', NULL, 2, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(37, '1022380991', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CASTILLO', 'BAYONA', 'DIEGO', 'FERNANDO', '05011993', 'M', 1, NULL, NULL, NULL, 'CR 99B  139  77', NULL, NULL, NULL, 2, NULL, '3192191632', '7469090', 'diegocastillok8@gmail.com', NULL, 9, 1, 10, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(38, '1023961699', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVENDANO', 'VASQUEZ', 'NICOLAS', NULL, '12071997', 'M', 1, NULL, NULL, NULL, 'CL 25 SUR  4 A 49', NULL, NULL, NULL, 2, NULL, '3166181606', '7469090', 'nav2052@hotmail.com', NULL, 6, 13, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(39, '1024478397', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SALCEDO', 'ROMERO', 'KAROL', 'DANIELA', '25112005', 'F', 1, NULL, NULL, NULL, 'CR 44 N 76  40', NULL, NULL, NULL, 2, NULL, '3059257440', '7469090', 'karoldanielasr12@gmail.com', NULL, 10, 18, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(40, '1024491663', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AGUIRRE', 'ROMERO', 'LADY', 'JOHANNA', '22051989', 'F', 1, NULL, NULL, NULL, 'CRA 67 57V 09 TORRE 5 APTO1637', NULL, NULL, NULL, 2, NULL, '3507923927', '3507923927', 'arjohanna@gmail.com', NULL, 12, 15, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(41, '1025143691', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'QUIÑONES', 'RIVERA', 'MAILY', 'VALERIA', '26122007', 'F', 1, NULL, NULL, NULL, 'Carrera 2 e # 22 a 63 sur', NULL, NULL, NULL, 2, NULL, '3123204438', '3142612400', 'mailyvaleriaq@gmail.com', NULL, 10, 8, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(42, '1025537140', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BUCURU', 'VALBUENA', 'NIKOL', 'ALEJANDRA', '05122007', 'F', 1, NULL, NULL, NULL, 'CALLE 84 SUR NUMERO 96 - 20', NULL, NULL, NULL, 2, NULL, '3203645960', '3203645960', 'lorellardila8@gmail.com', NULL, 10, 15, NULL, 5, NULL, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(43, '1026255124', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MOJICA', 'ARCINIEGAS', 'MARIA', 'ALEJANDRA', '18011987', 'F', 1, NULL, NULL, NULL, 'CL 145A 12A09', NULL, NULL, NULL, 2, NULL, '3166215115', '7469090', 'alejandra_mojica123@hotmail.com', NULL, 5, 20, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(44, '1026267749', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ARISTIZABAL', 'MARULANDA', 'JUAN', 'DAVID', '22011990', 'M', 1, NULL, NULL, NULL, 'CL 6 A  88 D  60', NULL, NULL, NULL, 2, NULL, '3156168706', '7469090', 'juancheing@hotmail.com', NULL, 5, 6, 14, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(45, '1026292916', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SANTANA', 'OTALORA', 'CAMILO', 'ANDRES', '31101995', 'M', 1, NULL, NULL, NULL, 'AC 147  7 F 12', NULL, NULL, NULL, 2, NULL, '3108526871', '7469090', 'camilo.santana@hotmail.com', NULL, 6, 6, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(46, '1031145571', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JACOBO', 'MANCERA', 'DIANA', 'MARCELA', '24031993', 'F', 1, NULL, NULL, NULL, 'CR 63 B 62 C 74 SUR', NULL, NULL, NULL, 2, NULL, '3014799855', '7469090', 'd.marce930324@gmail.com', NULL, 18, 18, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(47, '1031813496', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MONTIEL', 'CHICA', 'MICHELLE', NULL, '06032008', 'F', 1, NULL, NULL, NULL, 'Carrera 94 #130C- 17', NULL, NULL, NULL, 2, NULL, '3136279527', '3136279527', 'montielchicam@gmail.com', NULL, 10, 8, NULL, 9, NULL, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(48, '1032414423', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'HERNANDEZ', 'RIVEROS', 'LAURA', 'MARIA', '07051988', 'F', 1, NULL, NULL, NULL, 'CR 8  167 D 62', NULL, NULL, NULL, 2, NULL, '3235904772', '7469090', 'lhernandez@utexas.edu', NULL, 9, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(49, '1032446831', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALARCON', 'RONDON', 'ELIANA', 'IVETH', '25111991', 'F', 1, NULL, NULL, NULL, 'CL 181 C 13 91', NULL, NULL, NULL, 2, NULL, '3178534038', '7469090', 'nana.alarcon08@hotmail.com', NULL, 19, 6, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(50, '1032467291', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PARDO', 'CARRANZA', 'CHRISTIAN', 'MAURICIO', '16111994', 'M', 1, NULL, NULL, NULL, 'CL 147  7 G 94', NULL, NULL, NULL, 2, NULL, '3166969988', '7469090', 'christianmauriciopardo@gmail.com', NULL, 9, 20, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(51, '1033703338', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MURILLO', 'CRISTIAN', 'ANDRES', NULL, '22102006', 'M', 1, NULL, NULL, NULL, 'CR 52A ESTE 48 90', NULL, NULL, NULL, 2, NULL, '3133104012', '7469090', 'andresmurillo163@gmail.com', NULL, 10, 18, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(52, '1039448281', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CARO', 'VELEZ', 'CRISTINA', NULL, '17061988', 'F', 1, NULL, NULL, NULL, 'CR 46  75 SUR  150', NULL, NULL, NULL, 2, NULL, '3187120087', '7469090', 'Criscarovelez@gmail.com', NULL, 9, 15, 11, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(53, '1045706790', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GARCIA', 'RUEDA', 'JOSE', 'CARLOS', '27061992', 'M', 1, NULL, NULL, NULL, 'CL 126  7C  66', NULL, NULL, NULL, 2, NULL, '3114156922', '7469090', 'josecarlosgarcia92@gmail.com', NULL, 5, 15, 14, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(54, '1047451443', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'OLMOS', 'CARVAL', 'CARLOS', 'RAFAEL', '29091992', 'M', 1, NULL, NULL, NULL, 'Barrio Alameda La Victoria, Manzana  Lote 3', NULL, NULL, NULL, 2, NULL, '3012392187', '7469090', 'carlosolmosc@outlook.com', NULL, 5, 6, 14, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(55, '1052841082', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CACERES', 'ESTUPIÑAN', 'ANA', 'MARIA', '24082008', 'F', 1, NULL, NULL, NULL, 'Calle 40A #90A 04 sur', NULL, NULL, NULL, 2, NULL, '3209766170', '3209766170', 'anacaceres1082@gmail.com', NULL, 10, 20, NULL, 9, NULL, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(56, '1053788938', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GIRALDO', 'CORREA', 'GUSTAVO', 'ADOLFO', '12091988', 'M', 5, NULL, NULL, NULL, 'CR 101 70 55', NULL, NULL, NULL, 2, NULL, '3102244072', '5314801', 'ggiraldocorrea@gmail.com', NULL, 19, 20, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(57, '1056709240', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MOZO', 'MORENO', 'IVAN', 'DARIO', '17021993', 'M', 1, NULL, NULL, NULL, 'CR 15  8  23', NULL, NULL, NULL, 2, NULL, '3174236296', '7469090', 'mozoivan@gmail.com', NULL, 9, 6, 11, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(58, '1065599609', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RODRIGUEZ', 'CAMELO', 'CESAR', 'ELIECER', '31101988', 'M', 1, NULL, NULL, NULL, 'CLL 15  10  64', NULL, NULL, NULL, 2, NULL, '3005462735', '7469090', 'cesarstam84@hotmail.com', NULL, 5, 18, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(59, '1069714921', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ACOSTA', 'REINA', 'NICOLAS', 'ALEJANDRO', '09052004', 'M', 1, NULL, NULL, NULL, 'Carrera 81G # 42B 45 Sur', NULL, NULL, NULL, 2, NULL, '3016934633', '3016934633', 'nicoalejo0855@gmail.com', NULL, 10, 22, 2, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(60, '1070390716', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GALVIS', 'RESTREPO', 'MARIANA', NULL, '27012009', 'F', 1, NULL, NULL, NULL, 'Calle 84 Sur 96-85', NULL, NULL, NULL, 2, NULL, '3123442903', '3123442903', 'marianagalvisrestrepo199@gmail.com', NULL, 10, 19, NULL, 5, NULL, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(61, '1072699593', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ORTIZ', 'MURCIA', 'RUBEN', 'DARIO', '27041993', 'M', 1, NULL, NULL, NULL, 'CR 5  17 89', NULL, NULL, NULL, 4, NULL, '3144574949', '7469090', 'ruben_ortiz07@hotmail.com', NULL, 9, 1, 14, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(62, '1075212439', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MAHECHA', 'LAVERDE', 'MARIANN', 'LISSETTE', '06041986', 'F', 1, NULL, NULL, NULL, 'CLL 65  4A  50', NULL, NULL, NULL, 2, NULL, '3164987258', '7469090', 'malimagen@hotmail.com', NULL, 9, 6, 16, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(63, '1075215815', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RIVERA', 'MANRIQUE', 'MELINA', 'ANDREA', '25101986', 'F', 1, NULL, NULL, NULL, 'Cra. 35 # 21 - 06 Apto 102 Torre 1 Conjunto Torres de San Marcos, Barrio Buganviles', NULL, NULL, NULL, 1, NULL, '3134024281|', '3134024281', 'melinarivera1025@gmail.com', NULL, 9, 6, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(64, '1075239408', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CORDOBA', 'RIVAS', 'LENIN', NULL, '06091989', 'M', 2, NULL, NULL, NULL, 'Manzana A, Casa 7 - Gramalote', NULL, NULL, NULL, 5, NULL, '3214568903', '3214568903', 'lening06@gmail.com', NULL, 9, 20, 10, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(65, '1075242729', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RODRIGUEZ', 'FLOREZ', 'XIMENA', 'ALEJANDRA', '09031990', 'F', 1, NULL, NULL, NULL, 'Calle 55 # 77b - 23 Santa Cecilia - Anillo 13 Apto 501', NULL, NULL, NULL, 2, NULL, '3002492506', '3002492506', 'ximalejr@gmail.com', NULL, 9, 24, 10, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(66, '1075262171', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SALAZAR', 'ZULETA', 'EDWIN', 'ALBERTO', '30011992', 'M', 1, NULL, NULL, NULL, 'Calle 15 No. 39 - 36, Barrio Vergel', NULL, NULL, NULL, 6, NULL, '3137372206', '3137372206', 'edwinsalazarz.17@hotmail.com', NULL, 15, 8, 11, 9, 21, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(67, '1075263195', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'COQUECO', 'VARGAS', 'JESUS', 'ERNESTO', '08031992', 'M', 1, NULL, NULL, NULL, 'DG 6 SUR  39  144', NULL, NULL, NULL, 5, NULL, '3209113396', '7469090', 'jesusccvargas@gmail.com', NULL, 9, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(68, '1075284985', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LLANOS', 'GALLO', 'SEBASTIAN', NULL, '09121994', 'M', 2, NULL, NULL, NULL, 'CLL 71  1C  52', NULL, NULL, NULL, 6, NULL, '3203210974', '7469090', 'sllanosg@unal.edu.co', NULL, 5, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(69, '1075286613', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VARGAS', 'QUINTERO', 'JULLY', 'ALEXANDRA', '01011995', 'F', 2, NULL, NULL, NULL, 'CR 2 26 SUR 02', NULL, NULL, NULL, 6, NULL, '3223424156', '7469090', 'vargasjully2@gmail.com', NULL, 9, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(70, '1075292422', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MORALES', 'MORA', 'OLMER', 'ANDRES', '07101995', 'M', 1, NULL, NULL, NULL, 'CLL 25  68 C 50', NULL, NULL, NULL, 2, NULL, '3232847716', '7469090', 'andresmoralesmora95@gmail.com', NULL, 5, 6, 10, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(71, '1075293846', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MOLINA', 'LANDINEZ', 'DANIELA', NULL, '19121995', 'F', 1, NULL, NULL, NULL, 'CR 7  53 48', NULL, NULL, NULL, 2, NULL, '3123109391', '7469090', 'Danimolina.l19@gmail.com', NULL, 5, 15, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(72, '1081820719', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GUERRERO', 'ARRIETA', 'JAVIER', 'ENRIQUE', '01121994', 'M', 1, NULL, NULL, NULL, 'Carrera 17 #154-93', NULL, NULL, NULL, 7, NULL, '3004958242', '3004958242', 'javierguerrero.1994@gmail.com', NULL, 9, 6, 10, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(73, '1082981742', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MENDOZA', 'RAMIREZ', 'CHRISTIAN', 'DAVID', '07031994', 'M', 1, NULL, NULL, NULL, 'CLL 3AN 8 145', NULL, NULL, NULL, 8, NULL, '3006036245', '7469090', 'chrisdavidmendoza@gmail.com', NULL, 6, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(74, '1087047704', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RODRIGUEZ', 'ARTURO', 'YUBER', NULL, '12011991', 'M', 1, NULL, NULL, NULL, 'CLL 65 80A 107', NULL, NULL, NULL, 2, NULL, '3128357827', '7469090', 'yuberrodriguezarturo@gmail.com', NULL, 5, 15, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(75, '1095786398', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TRASLAVINA', 'PRADA', 'AURA', 'MARIA', '13121985', 'F', 2, NULL, NULL, NULL, 'CLL 175  15 20', NULL, NULL, NULL, 2, NULL, '3132028099', '7469090', 'aurita1385@gmail.com', NULL, 5, 19, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(76, '1095826986', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BAUTISTA', 'RICO', 'LIZETH', 'DAYANA', '07121995', 'F', 1, NULL, NULL, NULL, 'CLL 146  222 56', NULL, NULL, NULL, 2, NULL, '3138678621', '7469090', 'lbautistarico@gmail.com', NULL, 5, 6, 10, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(77, '1095918218', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'DE', 'LA', 'HOZ', 'GAMBOA WILMAR ANDRES', '13071989', 'M', 1, NULL, NULL, NULL, 'TV 56  105  8', NULL, NULL, NULL, 2, NULL, '3184960345', '7469090', 'w.andresgeo@gmail.com', NULL, 9, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(78, '1096219044', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ARCINIEGAS', 'HERNANDEZ', 'LAURA', 'MILENA', '25111992', 'F', 1, NULL, NULL, NULL, 'Calle 4 # 2-74 casa 9 etapa 2', NULL, NULL, NULL, 9, NULL, '3124689929', '3124689929', 'lauramilearciniegas25@gmail.com', NULL, 5, 20, 14, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(79, '1098605467', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GOMEZ', 'GUALDRON', 'MAX', 'BRADLEY', '22101985', 'M', 1, NULL, NULL, NULL, 'Carrera 2b # 12 - 19, Torre 3 Apto 601', NULL, NULL, NULL, 10, NULL, '3152120885', '3152120885', 'maxmbg@gmail.com', NULL, 5, 19, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(80, '1098663190', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MATEUS', 'TARAZONA', 'YESSICA', 'DEL CARMEN', '18101988', 'F', 1, NULL, NULL, NULL, 'CRA 47 10 144', NULL, NULL, NULL, 2, NULL, '3228437251', '7469090', 'yessicamateus@gmail.com', NULL, 9, 6, 16, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(81, '1098681773', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CARREÑO', 'HERNANDEZ', 'JHON', 'HARVEY', '31011990', 'M', 1, NULL, NULL, NULL, 'CR 15  1 N  40', NULL, NULL, NULL, 2, NULL, '3114976619', '7469090', 'jhocar_1990@hotmail.com', NULL, 5, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(82, '1098683077', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MONSALVE', 'PARRA', 'LUIS', 'CARLOS', '24021990', 'M', 1, NULL, NULL, NULL, 'CR 15  89  51', NULL, NULL, NULL, 2, NULL, '3187441574', '7469090', 'luisc.monsalve@gmail.com', NULL, 5, 25, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(83, '1098692205', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CAMACHO', 'JEREZ', 'BRIGGITE', 'SUSEC', '06081990', 'F', 1, NULL, NULL, NULL, 'CR 8W  62  48', NULL, NULL, NULL, 2, NULL, '3186506670', '7469090', 'briggite.camachoj@gmail.com', NULL, 5, 6, 16, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(84, '1098697791', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JOYA', 'RINCON', 'MARIA', 'ALEJANDRA', '21091990', 'F', 1, NULL, NULL, NULL, 'CR 18  158  72', NULL, NULL, NULL, 11, NULL, '3158471823', '7469090', 'alejandra.joya@gmail.com', NULL, 9, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(85, '1098706838', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'HERNANDEZ', 'PINTO', 'JULIAN', 'ANDRES', '28061991', 'M', 1, NULL, NULL, NULL, 'CR 31  40 59', NULL, NULL, NULL, 2, NULL, '3174478283', '7469090', 'julianh.9128@gmail.com', NULL, 5, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(86, '1098709932', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AMAYA', 'HERNANDEZ', 'ANDRES', 'FABIAN', '28081991', 'M', 1, NULL, NULL, NULL, 'Calle 14  26   28 Edificio U14 / 26 Apto 605', NULL, NULL, NULL, 11, NULL, '3008669991', '3008669991', 'afamayah@gmail.com', NULL, 5, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(87, '1098719174', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JIMENEZ', 'BARANDICA', 'OSCAR', 'IVAN', '06021992', 'M', 1, NULL, NULL, NULL, 'Calle 4B sur # 8-20', NULL, NULL, NULL, 12, NULL, '3157059227', '3157059227', 'oscaringelectronica@gmail.com', NULL, 5, 6, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(88, '1098725794', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NASSAR', 'DIAZ', 'JOSE', 'GABRIEL', '21081992', 'M', 1, NULL, NULL, NULL, 'CLL 153B  22 43', NULL, NULL, NULL, 2, NULL, '3166233088', '7469090', 'josse.nazzar@gmail.com', NULL, 5, 20, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(89, '1098726424', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROBLES', 'ALBARRACIN', 'EMMANUEL', NULL, '17011992', 'M', 1, NULL, NULL, NULL, 'CR 23  32  38', NULL, NULL, NULL, 2, NULL, '3163835735', '7469090', 'emmanuel_robles17@hotmail.com', NULL, 5, 20, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(90, '1098733967', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SUAREZ', 'SUAREZ', 'OSCAR', 'FABIAN', '15021993', 'M', 1, NULL, NULL, NULL, 'CLL 32  25  50', NULL, NULL, NULL, 2, NULL, '3161567895', '7469090', 'oscarsuarez93@hotmail.com', NULL, 9, 15, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(91, '1098745210', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VELANDIA', 'JAIMES', 'ESTEFANY', 'LIZETH', '16091993', 'F', 2, NULL, NULL, NULL, 'CR 4W  17 1', NULL, NULL, NULL, 8, NULL, '3154045354', '7469090', 'gestefanyvelandia@gmail.com', NULL, 5, 6, 14, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(92, '1098755426', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MARTINEZ', 'VERTEL', 'JAIME', 'JOSE', '03061994', 'M', 2, NULL, NULL, NULL, 'CR 6  5121', NULL, NULL, NULL, 2, NULL, '3102376098', '7469090', 'jaimemartinez12345@gmail.com', NULL, 5, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(93, '1098758681', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GUALTEROS', 'QUIROGA', 'MILTON', 'JULIAN', '31051994', 'M', 1, NULL, NULL, NULL, 'CR 27A  42 51', NULL, NULL, NULL, 11, NULL, '3002755299', '7469090', 'juliangualteros31@hotmail.com', NULL, 9, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(94, '1098761186', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LONDOÑO', 'CAMACHO', 'ALEXANDRA', 'KATHERINE', '10081994', 'F', 1, NULL, NULL, NULL, 'Cra 6 # 19 - 85 Reserva Loma Torre 7 Apto 553', NULL, NULL, NULL, 8, NULL, '3162343563', '3162343563', 'alexandraklc@hotmail.com', NULL, 5, 6, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(95, '1098774228', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GONZALEZ', 'SANCHEZ', 'ANGELICA', 'MARIA', '30081995', 'F', 1, NULL, NULL, NULL, 'Carrera 9 No. 60-02, Int. 6 Apto 203', NULL, NULL, NULL, 11, NULL, '3014187370', '3014187370', 'ing.gonzalez.angelica@gmail.com', NULL, 15, 18, 16, 17, 23, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(96, '1098782789', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVILA', 'PARRA', 'JUAN', 'SEBASTIAN', '06041996', 'M', 1, NULL, NULL, NULL, 'CLL 15  15  46', NULL, NULL, NULL, 2, NULL, '3105854019', '7469090', 'juansebastian964@gmail.com', NULL, 5, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(97, '1098801262', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FLETCHER', 'PEÑA', 'SILVIA', 'JULIANA', '21091997', 'F', 1, NULL, NULL, NULL, 'Cra 29 45 26 , apto 301.', NULL, NULL, NULL, 11, NULL, '3162875008', '3162875008', 'Sjulianafletcher@gmail.com', NULL, 20, 20, 2, 5, 23, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Inactivo', '2026-08-25 14:19:09', '2026-08-26 15:26:54'),
(98, '1098802405', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CHINOMES', 'GUALDRON', 'LUIS', 'ALBERTO', '19121997', 'M', 1, NULL, NULL, NULL, 'Calle 90 # 24 - 135 Diamante 2', NULL, NULL, NULL, 11, NULL, '3246306301', '3246306301', 'luis.chinomes@gmail.com', NULL, 5, 15, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:09', '2026-08-25 14:19:09'),
(99, '1098822990', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ORDUZ', 'RIOS', 'SANTIAGO', NULL, '29091999', 'M', 1, NULL, NULL, NULL, 'Calle 14A No. 32B - 68', NULL, NULL, NULL, 13, NULL, '3142163398', '3142163398', 'santiagorduz@hotmail.com', NULL, 20, 15, 10, 7, 23, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(100, '1100950373', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LOPEZ', 'ROJAS', 'LADY', 'MILENA', '17011987', 'F', 1, NULL, NULL, NULL, 'CLL 165 B  13 C 55', NULL, NULL, NULL, 2, NULL, '3112450500', '7469090', 'lopezladymilena@gmail.com', NULL, 5, 19, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(101, '1100954344', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MEDINA', 'SANDOVAL', 'CAMILA', 'FERNANDA', '23021989', 'F', 1, NULL, NULL, NULL, 'CR 8 A 153 51', NULL, NULL, NULL, 2, NULL, '3155257550', '7469090', 'mcamilafernanda@gmail.com', NULL, 9, 6, 16, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(102, '1100961505', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ZAFRA', 'URREA', 'GEISSON', 'RENE', '19061992', 'M', 1, NULL, NULL, NULL, 'CLL 4  139', NULL, NULL, NULL, 2, NULL, '3163677407', '7469090', 'jason920619@hotmail.com', NULL, 5, 18, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(103, '1101692935', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PINTO', 'HERNANDEZ', 'DIEGO', 'FERNANDO', '07071994', 'M', 1, NULL, NULL, NULL, 'CR 8  10  64', NULL, NULL, NULL, 2, NULL, '3143794371', '7469090', 'diegopintoh.uis@hotmail.com', NULL, 5, 20, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(104, '1101693549', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GARNICA', 'GOMEZ', 'CESAR', 'EDUARDO', '18121994', 'M', 1, NULL, NULL, NULL, 'CLL 19  27  34', NULL, NULL, NULL, 2, NULL, '3173374883', '7469090', 'cesar.garnica94@gmail.com', NULL, 5, 25, 16, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(105, '1115069820', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VELEZ', 'BARRERA', 'GABRIEL', 'EDUARDO', '06011989', 'M', 2, NULL, NULL, NULL, 'AV CR 68  67 F 64', NULL, NULL, NULL, 2, NULL, '3007087857', '7469090', 'gabrielvelezb6@gmail.com', NULL, 5, 20, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(106, '1119211830', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GUEVARA', 'MARLES', 'LUIS', 'MIGUEL', '13011989', 'M', 1, NULL, NULL, NULL, 'CLL 6 SUR  36 16', NULL, NULL, NULL, 5, NULL, '3225570275', '7469090', 'luismarles23@gmail.com', NULL, 21, 6, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(107, '1121941649', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LOPEZ', 'ROJAS', 'ALEJANDRO', 'DUVAN', '19011997', 'M', 1, NULL, NULL, NULL, 'CR 9 BIS A  49F  62 SUR', NULL, NULL, NULL, 2, NULL, '3214472738', '7469090', 'alejandro_.lopez@hotmail.com', NULL, 5, 8, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(108, '1128452509', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ISAZA', 'TORO', 'CINDY', 'NATALIA', '03091990', 'F', 1, NULL, NULL, NULL, 'CLL 35 A 85 C 93', NULL, NULL, NULL, 14, NULL, '3053271677', '7469090', 'natiisaza@hotmail.com', NULL, 9, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(109, '1136888916', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JOYA', 'SAAVEDRA', 'DANIEL', 'ANDRES', '30031998', 'M', 1, NULL, NULL, NULL, 'CLL 161  8 f 13', NULL, NULL, NULL, 2, NULL, '3108612386', '7469090', 'danieljoyas30@gmail.com', NULL, 19, 1, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(110, '1140847297', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GARCIA', 'CORONADO', 'DAVID', 'ALEJANDRO', '19121991', 'M', 1, NULL, NULL, NULL, 'CLL 100  13  44', NULL, NULL, NULL, 2, NULL, '3005751696', '7469090', 'alejandro_7269@hotmail.com', NULL, 9, 6, 14, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(111, '1143327261', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MARTINEZ', 'LEONES', 'GIOVANNI', NULL, '25011988', 'M', 1, NULL, NULL, NULL, 'CLL 159  56  75', NULL, NULL, NULL, 2, NULL, '3016620595', '7469090', 'gio.martinez.leones@hotmail.com', NULL, 5, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(112, '1152210959', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URZOLA', 'EBRATT', 'CARLOS', 'JOSE', '05111995', 'M', 2, NULL, NULL, NULL, 'CLL 34B  80  26', NULL, NULL, NULL, 14, NULL, '3182840175', '7469090', 'carlosjose.ue@gmail.com', NULL, 6, 15, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10');
INSERT INTO `personas` (`persona_id`, `numero_documento`, `tipo_documento_id`, `cod_emp_novasoft`, `codigo_alterno_novasoft`, `pais_exp_documento_id`, `ciudad_exp_documento_id`, `fecha_expedicion_documento`, `numero_pasaporte`, `pais_emisor_pasaporte_id`, `fecha_expedicion_pasaporte`, `numero_libreta_militar`, `clase_libreta_militar`, `distrito_militar`, `primer_apellido`, `segundo_apellido`, `primer_nombre`, `segundo_nombre`, `fecha_nacimiento_texto`, `genero`, `estado_civil_id`, `nacionalidad_id`, `grupo_sanguineo`, `factor_rh`, `direccion_residencia`, `barrio`, `codigo_barrio`, `localidad`, `ciudad_residencia_id`, `ciudad_nacimiento_id`, `celular`, `telefono_fijo`, `correo_personal`, `correo_corporativo`, `cargo_id`, `eps_id`, `afp_id`, `fondo_cesantias_id`, `ccf_id`, `arl_id`, `nivel_riesgo_arl`, `estatura_cm`, `peso_kg`, `numero_hijos`, `numero_personas_a_cargo`, `tiene_dependientes`, `pensionado`, `pensionado_por_empresa`, `indicador_transicion_pensional`, `indicador_extranjero_pension`, `reside_extranjero`, `profesion`, `poliza_vida`, `postulacion_spe`, `certificado_residencia_vigencia`, `observaciones_alivios`, `estado`, `created_at`, `updated_at`) VALUES
(113, '1152688360', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VARGAS', 'CLAVIJO', 'JOHANNA', NULL, '02111992', 'F', 1, NULL, NULL, NULL, 'Carrera 15 aa # 36 - 38', NULL, NULL, NULL, 14, NULL, '3015386107', '3015386107', 'johavarcl@gmail.com', NULL, 5, 15, 10, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(114, '1160627', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PEREZ', 'DANIEL', 'CLAUDIO', NULL, '28051961', 'M', 1, NULL, NULL, NULL, 'Calle 26-1-48- Ap 1803. Edificio Baltia.', NULL, NULL, NULL, 15, NULL, '3004281249', '7469090', 'daniel.pereze@hotmail.com', NULL, 9, 6, NULL, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 1, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(115, '13720871', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MORENO', 'CASTELLANOS', 'MARIO', 'AUGUSTO', '21101978', 'M', 1, NULL, NULL, NULL, 'AV CR 24  61 D 85', NULL, NULL, NULL, 2, NULL, '3208889081', '7469090', 'geomario@hotmail.com', NULL, 22, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(116, '13740129', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FIGUEROA', 'VEGA', 'JULIO', 'CESAR', '18011980', 'M', 1, NULL, NULL, NULL, 'CL 143  43  26', NULL, NULL, NULL, 2, NULL, '3022586566', '7469090', 'Juliofigue@hotmail.com', NULL, 9, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(117, '13959717', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GALEANO', 'BARRERA', 'DIEGO', 'FERNANDO', '08051985', 'M', 1, NULL, NULL, NULL, 'CL 98 68 b', NULL, NULL, NULL, 2, NULL, '3212060755', '7469090', 'diego581@yahoo.com', NULL, 9, 6, 16, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(118, '20312319', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LAITON', 'DE', 'REINA', 'BLANCA ATILIA', '22061941', 'F', 1, NULL, NULL, NULL, 'CR 73  75A 29', NULL, NULL, NULL, 2, NULL, '3212055243', '7469090', 'monica_reina@outlook.com', NULL, 2, 19, NULL, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 1, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(119, '24332450', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MARTINEZ', 'URIBE', 'LILIANA', NULL, '10011982', 'F', 1, NULL, NULL, NULL, 'Calle 153 #23 - 41 Mz 6 Casa 25', NULL, NULL, NULL, 16, NULL, '3102553497', '3102553497', 'limauribe@hotmail.com', NULL, 9, 6, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(120, '30405867', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CACERES', 'SALINAS', 'DIANA', 'MARCELA', '27071980', 'F', 2, NULL, NULL, NULL, 'CR 12b  14037', NULL, NULL, NULL, 2, NULL, '3203003436', '7469090', 'caceres.diana@gmail.com', NULL, 5, 15, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(121, '37546080', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MURILLO', 'LOPEZ', 'MARIA', 'HIMELDA', '12101977', 'F', 2, NULL, NULL, NULL, 'Calle 106A No. 54-20, Apto 301', NULL, NULL, NULL, 2, NULL, '3108586179', '3108586179', 'pauegip_12@hotmail.com', NULL, 9, 15, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(122, '40936668', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'COTES', 'LEON', 'ESPERANZA', 'DE JESUS', '23111980', 'F', 1, NULL, NULL, NULL, 'CR 57 68 124', NULL, NULL, NULL, 2, NULL, '3183728370', '7469090', 'esperanza.cotes@gmail.com', NULL, 9, 15, 10, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(123, '43578774', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ARBELAEZ', 'LONDONO', 'ALEJANDRA', NULL, '21121973', 'F', 1, NULL, NULL, NULL, 'CR 44  22', NULL, NULL, NULL, 14, NULL, '3001163730', '6042027776', 'alarbalaez@yahoo.com', NULL, 9, 6, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(124, '43728382', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MESA', 'CARDENAS', 'ALEXANDRA', 'ISABEL', '22041970', 'F', 1, NULL, NULL, NULL, 'CR 67  175  65', NULL, NULL, NULL, 2, NULL, '3102046026', '7469090', 'alexandramesa85@gmail.com', NULL, 22, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(125, '478731', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MARCANO', 'DE', 'VILLARROEL', 'ZENAIDA DEL VALLE', '20041975', 'F', 1, NULL, NULL, NULL, 'CL 146 19 54', NULL, NULL, NULL, 2, NULL, '3057684591', '7469090', 'marcanozdelvalle@gmail.com', NULL, 9, 6, 16, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(126, '506169', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CAROPRESE', 'GARCIA', 'CLAUDIA', NULL, '19091974', 'F', 1, NULL, NULL, NULL, 'CL 147  7B  37', NULL, NULL, NULL, 2, NULL, '3184178528', '7469090', 'caropresec@gmail.com', NULL, 9, 6, 16, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(127, '51781946', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VIDAL', 'GONZALEZ', 'GLORIA', 'FERNANDA', '29071965', 'F', 1, NULL, NULL, NULL, 'KM 9 VIA LA CALERA', NULL, NULL, NULL, 2, NULL, '3157805737', '7469090', 'gloriavidal25@hotmail.com', NULL, 9, 13, NULL, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 1, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(128, '52005033', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MAYORGA', 'GOMEZ', 'ZANDRA', 'PATRICIA', '22091970', 'F', 1, NULL, NULL, NULL, 'CR 145 B 143 A 20', NULL, NULL, NULL, 2, NULL, '3112123257', '7469090', 'zandramayorga@yahoo.es', NULL, 23, 8, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(129, '52030991', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MORENO', 'MORENO', 'NORA', 'GISELL', '28031972', 'F', 1, NULL, NULL, NULL, 'CR 79 A  12 B 52', NULL, NULL, NULL, 2, NULL, '3134844336', '7469090', 'nogimo1@hotmail.com', NULL, 24, 18, 14, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(130, '52147279', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MUNOZ', 'CASTILLO', 'RUTH', NULL, '16061970', 'F', 1, NULL, NULL, NULL, 'TV 13B SUR 55 76', NULL, NULL, NULL, 2, NULL, '3203904580', '7469090', 'rmunoz@meridian.com.co', NULL, 25, 8, 11, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(131, '52423689', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'REYES', 'AVILA', 'NUBIA', 'SOLANLLY', '20121977', 'F', 1, NULL, NULL, NULL, 'CR 74  66 B 33', NULL, NULL, NULL, 2, NULL, '3145078088', '7469090', '50123yes@gmail.com', NULL, 9, 1, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(132, '52455261', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CASTELLANOS', 'BARRETO', 'ANA', 'MARIA', '29011979', 'F', 1, NULL, NULL, NULL, 'CR 56  152 42', NULL, NULL, NULL, 2, NULL, '3219970758', '7469090', 'amariacast@yahoo.com', NULL, 9, 6, 14, 12, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(133, '52556069', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GOMEZ', 'GALARZA', 'YOHANEY', 'LUCIA', '22031972', 'F', 2, NULL, NULL, NULL, 'Km 4 via La Calera- Sopó. Conjunto cerrado Casa de Campo. Casa 72.', NULL, NULL, NULL, 17, NULL, '3153934777', '3153934777', 'yoha_gomez@hotmail.com', NULL, 9, 13, 14, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(134, '52844528', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NINO', 'OROZCO', 'EDNA', 'MILED', '31081981', 'F', 2, NULL, NULL, NULL, 'CR 8 65 17', NULL, NULL, NULL, 2, NULL, '3112978636', '0', 'miledn7@yahoo.com.mx', NULL, 22, 6, 10, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(135, '52967140', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOLANO', 'SUA', 'DIANA', 'PAOLA', '01011983', 'F', 1, NULL, NULL, NULL, 'CR 17  173 52', NULL, NULL, NULL, 2, NULL, '3015808137', '7469090', 'dianapsolano.2017@gmail.com', NULL, 9, 13, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(136, '53103915', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MARTINEZ', 'VERA', 'MONICA', 'DEL PILAR', '29121985', 'F', 1, NULL, NULL, NULL, 'CL 12 71B 40', NULL, NULL, NULL, 2, NULL, '3028018043', '7469090', 'monamartinez1228@gmail.com', NULL, 9, 8, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(137, '63527981', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SERRANO', 'LASTRE', 'INA', 'YADITH', '26091981', 'F', 1, NULL, NULL, NULL, 'CR 9 A  134 B 41', NULL, NULL, NULL, 2, NULL, '3134023172', '7469090', 'inaserrano@gmail.com', NULL, 9, 6, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(138, '63536247', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ORTEGON', 'BARRERA', 'JULLY', 'MARCELA', '20121982', 'F', 1, NULL, NULL, NULL, 'CL 167d 8  58', NULL, NULL, NULL, 2, NULL, '3167194344', '7469090', 'marcelageo1@hotmail.com', NULL, 9, 6, 10, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(139, '63540751', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'DUENES', 'GARCES', 'ADRIANA', 'PATRICIA', '16071983', 'F', 1, NULL, NULL, NULL, 'CL 37 42 29', NULL, NULL, NULL, 2, NULL, '3168691669', '7469090', 'adriana_geologia@hotmail.com', NULL, 22, 15, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(140, '71787712', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'QUIROGA', 'CRUZ', 'HUGO', NULL, '22061977', 'M', 1, NULL, NULL, NULL, 'Carrera 19a 118-55, Apto 407', NULL, NULL, NULL, 2, NULL, '3187401374', '3187401374', 'hugo_quiroga@yahoo.com', NULL, 9, 6, 16, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(141, '74856617', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RODRIGUEZ', 'NIETO', 'JUAN', 'LEONARDO', '18081979', 'M', 1, NULL, NULL, NULL, 'AV marginal de la selva No. 7-212, Cojunto Rosales de piedemonte. Apto 215. Barrio las Palmeras', NULL, NULL, NULL, 18, NULL, '3134352646', '3134352646', 'juan.rodriguezuis@gmail.com', NULL, 15, 6, 14, 9, 23, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(142, '75101511', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAIBA', 'ALZATE', 'JORGE', 'EDUARDO', '03071984', 'M', 1, NULL, NULL, NULL, 'CL 35  52 SUR  82', NULL, NULL, NULL, 2, NULL, '3155056633', '7469090', 'jepalzate@gmail.com', NULL, 9, 15, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(143, '7729979', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FRANCO', 'GRAJALES', 'LEONARDO', NULL, '26111984', 'M', 1, NULL, NULL, NULL, 'CL 53  4A71', NULL, NULL, NULL, 2, NULL, '3012641268', '7469090', 'leonardofrancograjales@outlook.com', NULL, 9, 6, 16, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(144, '79490148', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URREGO', 'AVENDANO', 'CESAR', 'AUGUSTO', '26041969', 'M', 1, NULL, NULL, NULL, 'CR 50 123 A 09', NULL, NULL, NULL, 2, NULL, '3102541498', '7469090', 'geocau01@gmail.com', NULL, 26, 13, 14, NULL, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(145, '79613401', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FRANCO', 'CASTELLANOS', 'WILLIAM', 'AUGUSTO', '03111971', 'M', 1, NULL, NULL, NULL, 'CL 121 40 32', NULL, NULL, NULL, 2, NULL, '3138174046', '7469090', 'william.francoc@hotmail.com', NULL, 27, 19, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(146, '79686130', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GAVIRIA', 'GARCIA', 'RICARDO', NULL, '10111974', 'M', 2, NULL, NULL, NULL, 'CL 119A N 70G  15', NULL, NULL, NULL, 2, NULL, '3243242116', '7469090', 'ricardo.gaviria@outlook.com', NULL, 9, 13, 16, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(147, '79954907', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VASQUEZ', 'ZARATE', 'RONALD', NULL, '14121979', 'M', 1, NULL, NULL, NULL, 'CR 5 26 120', NULL, NULL, NULL, 13, NULL, '3195969245', '3195969245', 'ronaldvaz79@hotmail.com', NULL, 28, 6, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(148, '80243783', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MARTINEZ', 'BRAVO', 'DIEGO', 'MAURICIO', '22071982', 'M', 1, NULL, NULL, NULL, 'CR 17  173  52', NULL, NULL, NULL, 2, NULL, '3103012637', '7469090', 'dimartinezbravo@gmail.com', NULL, 9, 6, 11, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(149, '80883010', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SANTAMARIA', 'TORRES', 'OVEIMAR', NULL, '12061985', 'M', 1, NULL, NULL, NULL, 'CR 86D  6D 10', NULL, NULL, NULL, 2, NULL, '3017227315', '7469090', 'oveimar.santamaria@gmail.com', NULL, 9, 15, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(150, '83042295', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CABRERA', 'CASTRO', 'WILLIAM', NULL, '03051983', 'M', 1, NULL, NULL, NULL, 'CR 19  151 60', NULL, NULL, NULL, 2, NULL, '3147301063', '7469090', 'williamcabreracastro@hotmail.com', NULL, 9, 15, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(151, '91514446', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'OREJARENA', 'ESCOBAR', 'GERMAN', 'DARIO', '19121982', 'M', 2, NULL, NULL, NULL, 'CL 213  114 10', NULL, NULL, NULL, 2, NULL, '3164954753', '7469090', 'german18224@yahoo.com', NULL, 9, 13, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(152, '91520047', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ARENAS', 'NAVARRO', 'JESUS', 'DAVID', '10061983', 'M', 0, NULL, NULL, NULL, 'CL 7  4 50', NULL, NULL, NULL, 2, NULL, '3183544282', '7469090', 'ajbdavidgeo@gmail.com', NULL, 5, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(153, '91524899', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ANAYA', 'MANCIPE', 'JOSE', 'ANDRES', '04111983', 'M', 1, NULL, NULL, NULL, 'DG 14  56  37', NULL, NULL, NULL, 11, NULL, '3158608522', '7469090', 'anayamancipe_04@hotmail.com', NULL, 5, 6, 11, 7, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(154, '91532360', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'MAURICIO', 'ANDRES', 'VASQUEZ', 'PINTO', '23081984', 'M', 1, NULL, NULL, NULL, 'Autopista Floridablanca 144 - 114 Conjunto Villa Firenze, Torre 4 Apto 302', NULL, NULL, NULL, 1, NULL, '3003044721', '3003044721', 'vasquez.mao@gmail.com', NULL, 9, 6, 14, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-25 14:19:10', '2026-08-25 14:19:10'),
(155, '1089379887', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VELASQUEZ', 'ARIAS', 'JUAN', 'ESTEBAN', '19122004', 'M', 1, NULL, NULL, NULL, 'CRA 111C 88 05', NULL, NULL, NULL, 2, NULL, '3025991748', '3025991748', 'juanesv2011@gmail.com', NULL, 2, 18, 2, 5, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-26 14:55:27', '2026-08-26 14:55:27'),
(156, '1105465424', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GONZALEZ', 'OROZCO', 'ALISON', 'VANESA', '08062007', 'F', 1, NULL, NULL, NULL, 'TRANSVERSAL 113C #64D-30 C31', NULL, NULL, NULL, 2, NULL, '3125504217', '3125504217', 'gonzalezorozcoalisonvanesa@gmail.com', NULL, 11, 18, 2, 9, 4, 3, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Activo', '2026-08-26 14:55:27', '2026-08-26 14:55:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `migration` varchar(180) NOT NULL,
  `applied_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `schema_migrations`
--

INSERT INTO `schema_migrations` (`migration`, `applied_at`) VALUES
('001_usuarios_sistema.sql', '2026-08-18 13:26:52'),
('002_auditoria.sql', '2026-08-18 13:26:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_contrato`
--

CREATE TABLE `tipos_contrato` (
  `tipo_contrato_id` smallint(5) UNSIGNED NOT NULL,
  `descripcion` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `tipos_contrato`
--

INSERT INTO `tipos_contrato` (`tipo_contrato_id`, `descripcion`) VALUES
(1, 'Término indefinido'),
(2, 'Término fijo'),
(3, 'Término indefinido sin transporte'),
(5, 'Término fijo < 1 año'),
(6, 'Honorarios'),
(8, 'Aprendiz Sena — Etapa Productiva'),
(9, 'Aprendiz Sena — Etapa Lectiva'),
(10, 'Termino Obra O Labor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_cotizante`
--

CREATE TABLE `tipos_cotizante` (
  `tipo_cotizante_id` char(2) NOT NULL,
  `descripcion` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `tipos_cotizante`
--

INSERT INTO `tipos_cotizante` (`tipo_cotizante_id`, `descripcion`) VALUES
('01', 'Dependiente'),
('12', 'Aprendiz etapa lectiva'),
('19', 'Aprendiz etapa productiva');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_documento`
--

CREATE TABLE `tipos_documento` (
  `tipo_documento_id` smallint(5) UNSIGNED NOT NULL,
  `codigo_novasoft` char(2) NOT NULL,
  `descripcion` varchar(60) NOT NULL,
  `abreviatura` varchar(5) NOT NULL,
  `abreviatura_puntuada` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `tipos_documento`
--

INSERT INTO `tipos_documento` (`tipo_documento_id`, `codigo_novasoft`, `descripcion`, `abreviatura`, `abreviatura_puntuada`) VALUES
(1, '01', 'Cédula de Ciudadanía', 'CC', 'C.C.'),
(2, '02', 'Cédula de Extranjería', 'CE', 'C.E.'),
(3, '03', 'Tarjeta de Identidad', 'TI', 'T.I.'),
(4, '04', 'Número Único de Identificación Personal', 'UN', 'N.U.I.P.'),
(5, '05', 'Registro Civil', 'RC', 'R.C.'),
(6, '06', 'Pasaporte', 'PA', 'PA.'),
(7, '07', 'Permiso Especial de Trabajo', 'PE', 'P.E.'),
(10, '10', 'Número de Identificación Tributaria', 'NI', 'N.I.T.'),
(21, '21', 'Tarjeta de Extranjería', 'TE', 'T.E.'),
(22, '22', 'Tipo documento Extranjero', 'DE', 'D.E.'),
(23, '23', 'Documento definido información exógena', 'IE', 'I.E.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_sistema`
--

CREATE TABLE `usuarios_sistema` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin') NOT NULL DEFAULT 'admin',
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `intentos_fallidos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta` timestamp NULL DEFAULT NULL,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios_sistema`
--

INSERT INTO `usuarios_sistema` (`usuario_id`, `nombre_usuario`, `nombre_completo`, `correo`, `password_hash`, `rol`, `estado`, `intentos_fallidos`, `bloqueado_hasta`, `ultimo_acceso`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrador del sistema', 'admin@meridianconsulting.com', '$argon2id$v=19$m=65536,t=4,p=1$QWhTU254WTQ3TU9BdnE0OQ$+N2vJueg3Ludeina76gnqCDQwzAri3qsTw8AUzhWVVE', 'admin', 'Activo', 4, NULL, '2026-08-18 14:32:36', '2026-08-18 13:26:52', '2026-08-26 14:32:23'),
(2, 'Gestion', 'Gestion Humana', 'profesionalgh@meridian.com.co', '$argon2id$v=19$m=65536,t=4,p=1$eGQwQmtHdGprdDN0Qml5eQ$IoxZqC57q8LIfKrLRwTxj+0d0AEUTsh7b6DgyafASgE', 'admin', 'Activo', 0, NULL, '2026-08-26 20:04:57', '2026-08-18 14:34:28', '2026-08-26 20:04:57');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_contrato_vigente`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_contrato_vigente` (
`persona_id` int(10) unsigned
,`nombre_completo` varchar(210)
,`contrato_id` int(10) unsigned
,`numero_contrato` varchar(30)
,`tipo_contrato` varchar(60)
,`fecha_inicio` date
,`salario_basico` decimal(14,2)
,`proyecto` varchar(120)
,`ods` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_personas_activas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_personas_activas` (
`persona_id` int(10) unsigned
,`tipo_documento` varchar(10)
,`numero_documento` varchar(15)
,`nombre_completo` varchar(210)
,`fecha_nacimiento` date
,`cargo` varchar(120)
,`ciudad_residencia` varchar(80)
,`celular` varchar(20)
,`correo_personal` varchar(100)
,`correo_corporativo` varchar(100)
,`estado` enum('Activo','Inactivo')
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`auditoria_id`),
  ADD KEY `idx_auditoria_usuario_fecha` (`usuario_id`,`created_at`),
  ADD KEY `idx_auditoria_entidad` (`entidad`,`entidad_id`);

--
-- Indices de la tabla `bancos`
--
ALTER TABLE `bancos`
  ADD PRIMARY KEY (`banco_id`),
  ADD UNIQUE KEY `codigo_novasoft` (`codigo_novasoft`);

--
-- Indices de la tabla `bonificaciones`
--
ALTER TABLE `bonificaciones`
  ADD PRIMARY KEY (`bonificacion_id`),
  ADD KEY `idx_bonificaciones_contrato` (`contrato_id`,`tipo_bonificacion`);

--
-- Indices de la tabla `cargos`
--
ALTER TABLE `cargos`
  ADD PRIMARY KEY (`cargo_id`),
  ADD UNIQUE KEY `codigo_cargo_novasoft` (`codigo_cargo_novasoft`);

--
-- Indices de la tabla `ciudades`
--
ALTER TABLE `ciudades`
  ADD PRIMARY KEY (`ciudad_id`),
  ADD KEY `pais_id` (`pais_id`);

--
-- Indices de la tabla `configuracion_nomina_novasoft`
--
ALTER TABLE `configuracion_nomina_novasoft`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `contrato_id` (`contrato_id`),
  ADD KEY `banco_id` (`banco_id`),
  ADD KEY `ciudad_labor_id` (`ciudad_labor_id`),
  ADD KEY `forma_pago_id` (`forma_pago_id`),
  ADD KEY `periodicidad_liquidacion_id` (`periodicidad_liquidacion_id`),
  ADD KEY `modo_liquidacion_id` (`modo_liquidacion_id`),
  ADD KEY `metodo_retencion_id` (`metodo_retencion_id`),
  ADD KEY `tipo_cotizante_id` (`tipo_cotizante_id`);

--
-- Indices de la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`contrato_id`),
  ADD KEY `contrato_anterior_id` (`contrato_anterior_id`),
  ADD KEY `tipo_contrato_id` (`tipo_contrato_id`),
  ADD KEY `ciudad_firma_id` (`ciudad_firma_id`),
  ADD KEY `idx_contratos_persona_vigencia` (`persona_id`,`fecha_terminacion`);

--
-- Indices de la tabla `estados_civiles`
--
ALTER TABLE `estados_civiles`
  ADD PRIMARY KEY (`estado_civil_id`);

--
-- Indices de la tabla `fondos`
--
ALTER TABLE `fondos`
  ADD PRIMARY KEY (`fondo_id`),
  ADD UNIQUE KEY `uq_fondo` (`tipo`,`nombre`);

--
-- Indices de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  ADD PRIMARY KEY (`forma_pago_id`);

--
-- Indices de la tabla `historial_ausentismos`
--
ALTER TABLE `historial_ausentismos`
  ADD PRIMARY KEY (`historial_id`),
  ADD KEY `idx_hist_ausentismos_persona_fecha` (`persona_id`,`fecha_inicio_ausencia`);

--
-- Indices de la tabla `historial_nomina`
--
ALTER TABLE `historial_nomina`
  ADD PRIMARY KEY (`historial_id`),
  ADD KEY `idx_hist_nomina_persona_fecha` (`persona_id`,`fecha_corte`);

--
-- Indices de la tabla `historial_seg_social`
--
ALTER TABLE `historial_seg_social`
  ADD PRIMARY KEY (`historial_id`),
  ADD KEY `idx_hist_seg_social_persona_fecha` (`persona_id`,`fecha_corte`);

--
-- Indices de la tabla `historial_vacaciones`
--
ALTER TABLE `historial_vacaciones`
  ADD PRIMARY KEY (`historial_id`),
  ADD KEY `idx_hist_vacaciones_persona_fecha` (`persona_id`,`fecha_inicio`);

--
-- Indices de la tabla `metodos_retencion`
--
ALTER TABLE `metodos_retencion`
  ADD PRIMARY KEY (`metodo_retencion_id`);

--
-- Indices de la tabla `modos_liquidacion`
--
ALTER TABLE `modos_liquidacion`
  ADD PRIMARY KEY (`modo_liquidacion_id`);

--
-- Indices de la tabla `nacionalidades`
--
ALTER TABLE `nacionalidades`
  ADD PRIMARY KEY (`nacionalidad_id`);

--
-- Indices de la tabla `paises`
--
ALTER TABLE `paises`
  ADD PRIMARY KEY (`pais_id`),
  ADD UNIQUE KEY `codigo_novasoft` (`codigo_novasoft`);

--
-- Indices de la tabla `periodicidades_liquidacion`
--
ALTER TABLE `periodicidades_liquidacion`
  ADD PRIMARY KEY (`periodicidad_id`);

--
-- Indices de la tabla `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`persona_id`),
  ADD UNIQUE KEY `uq_persona_documento` (`tipo_documento_id`,`numero_documento`),
  ADD UNIQUE KEY `uq_persona_cod_emp` (`cod_emp_novasoft`),
  ADD KEY `estado_civil_id` (`estado_civil_id`),
  ADD KEY `nacionalidad_id` (`nacionalidad_id`),
  ADD KEY `pais_exp_documento_id` (`pais_exp_documento_id`),
  ADD KEY `ciudad_exp_documento_id` (`ciudad_exp_documento_id`),
  ADD KEY `pais_emisor_pasaporte_id` (`pais_emisor_pasaporte_id`),
  ADD KEY `ciudad_residencia_id` (`ciudad_residencia_id`),
  ADD KEY `ciudad_nacimiento_id` (`ciudad_nacimiento_id`),
  ADD KEY `eps_id` (`eps_id`),
  ADD KEY `afp_id` (`afp_id`),
  ADD KEY `fondo_cesantias_id` (`fondo_cesantias_id`),
  ADD KEY `ccf_id` (`ccf_id`),
  ADD KEY `arl_id` (`arl_id`),
  ADD KEY `idx_personas_estado` (`estado`),
  ADD KEY `idx_personas_nombre` (`primer_apellido`,`primer_nombre`),
  ADD KEY `idx_personas_cargo` (`cargo_id`);

--
-- Indices de la tabla `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`migration`);

--
-- Indices de la tabla `tipos_contrato`
--
ALTER TABLE `tipos_contrato`
  ADD PRIMARY KEY (`tipo_contrato_id`);

--
-- Indices de la tabla `tipos_cotizante`
--
ALTER TABLE `tipos_cotizante`
  ADD PRIMARY KEY (`tipo_cotizante_id`);

--
-- Indices de la tabla `tipos_documento`
--
ALTER TABLE `tipos_documento`
  ADD PRIMARY KEY (`tipo_documento_id`),
  ADD UNIQUE KEY `codigo_novasoft` (`codigo_novasoft`);

--
-- Indices de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  ADD PRIMARY KEY (`usuario_id`),
  ADD UNIQUE KEY `uq_usuarios_nombre_usuario` (`nombre_usuario`),
  ADD UNIQUE KEY `uq_usuarios_correo` (`correo`),
  ADD KEY `idx_usuarios_estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `auditoria_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `bancos`
--
ALTER TABLE `bancos`
  MODIFY `banco_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `bonificaciones`
--
ALTER TABLE `bonificaciones`
  MODIFY `bonificacion_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cargos`
--
ALTER TABLE `cargos`
  MODIFY `cargo_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `ciudades`
--
ALTER TABLE `ciudades`
  MODIFY `ciudad_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `configuracion_nomina_novasoft`
--
ALTER TABLE `configuracion_nomina_novasoft`
  MODIFY `config_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=308;

--
-- AUTO_INCREMENT de la tabla `contratos`
--
ALTER TABLE `contratos`
  MODIFY `contrato_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fondos`
--
ALTER TABLE `fondos`
  MODIFY `fondo_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `historial_ausentismos`
--
ALTER TABLE `historial_ausentismos`
  MODIFY `historial_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_nomina`
--
ALTER TABLE `historial_nomina`
  MODIFY `historial_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_seg_social`
--
ALTER TABLE `historial_seg_social`
  MODIFY `historial_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_vacaciones`
--
ALTER TABLE `historial_vacaciones`
  MODIFY `historial_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paises`
--
ALTER TABLE `paises`
  MODIFY `pais_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `personas`
--
ALTER TABLE `personas`
  MODIFY `persona_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  MODIFY `usuario_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_contrato_vigente`
--
DROP TABLE IF EXISTS `vista_contrato_vigente`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_contrato_vigente`  AS SELECT `p`.`persona_id` AS `persona_id`, `p`.`nombre_completo_nombres_primero` AS `nombre_completo`, `ct`.`contrato_id` AS `contrato_id`, `ct`.`numero_contrato` AS `numero_contrato`, `tc`.`descripcion` AS `tipo_contrato`, `ct`.`fecha_inicio` AS `fecha_inicio`, `ct`.`salario_basico` AS `salario_basico`, `ct`.`proyecto` AS `proyecto`, `ct`.`ods` AS `ods` FROM ((`personas` `p` join `contratos` `ct` on(`ct`.`persona_id` = `p`.`persona_id` and `ct`.`fecha_terminacion` is null)) left join `tipos_contrato` `tc` on(`tc`.`tipo_contrato_id` = `ct`.`tipo_contrato_id`)) WHERE `p`.`estado` = 'Activo' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_personas_activas`
--
DROP TABLE IF EXISTS `vista_personas_activas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_personas_activas`  AS SELECT `p`.`persona_id` AS `persona_id`, `td`.`abreviatura_puntuada` AS `tipo_documento`, `p`.`numero_documento` AS `numero_documento`, `p`.`nombre_completo_nombres_primero` AS `nombre_completo`, `p`.`fecha_nacimiento_date` AS `fecha_nacimiento`, `c`.`nombre_cargo` AS `cargo`, `ciu`.`nombre` AS `ciudad_residencia`, `p`.`celular` AS `celular`, `p`.`correo_personal` AS `correo_personal`, `p`.`correo_corporativo` AS `correo_corporativo`, `p`.`estado` AS `estado` FROM (((`personas` `p` left join `tipos_documento` `td` on(`td`.`tipo_documento_id` = `p`.`tipo_documento_id`)) left join `cargos` `c` on(`c`.`cargo_id` = `p`.`cargo_id`)) left join `ciudades` `ciu` on(`ciu`.`ciudad_id` = `p`.`ciudad_residencia_id`)) WHERE `p`.`estado` = 'Activo' ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`usuario_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `bonificaciones`
--
ALTER TABLE `bonificaciones`
  ADD CONSTRAINT `bonificaciones_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ciudades`
--
ALTER TABLE `ciudades`
  ADD CONSTRAINT `ciudades_ibfk_1` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`pais_id`);

--
-- Filtros para la tabla `configuracion_nomina_novasoft`
--
ALTER TABLE `configuracion_nomina_novasoft`
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_2` FOREIGN KEY (`banco_id`) REFERENCES `bancos` (`banco_id`),
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_3` FOREIGN KEY (`ciudad_labor_id`) REFERENCES `ciudades` (`ciudad_id`),
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_4` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`forma_pago_id`),
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_5` FOREIGN KEY (`periodicidad_liquidacion_id`) REFERENCES `periodicidades_liquidacion` (`periodicidad_id`),
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_6` FOREIGN KEY (`modo_liquidacion_id`) REFERENCES `modos_liquidacion` (`modo_liquidacion_id`),
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_7` FOREIGN KEY (`metodo_retencion_id`) REFERENCES `metodos_retencion` (`metodo_retencion_id`),
  ADD CONSTRAINT `configuracion_nomina_novasoft_ibfk_8` FOREIGN KEY (`tipo_cotizante_id`) REFERENCES `tipos_cotizante` (`tipo_cotizante_id`);

--
-- Filtros para la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`persona_id`),
  ADD CONSTRAINT `contratos_ibfk_2` FOREIGN KEY (`contrato_anterior_id`) REFERENCES `contratos` (`contrato_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contratos_ibfk_3` FOREIGN KEY (`tipo_contrato_id`) REFERENCES `tipos_contrato` (`tipo_contrato_id`),
  ADD CONSTRAINT `contratos_ibfk_4` FOREIGN KEY (`ciudad_firma_id`) REFERENCES `ciudades` (`ciudad_id`);

--
-- Filtros para la tabla `historial_ausentismos`
--
ALTER TABLE `historial_ausentismos`
  ADD CONSTRAINT `historial_ausentismos_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`persona_id`);

--
-- Filtros para la tabla `historial_nomina`
--
ALTER TABLE `historial_nomina`
  ADD CONSTRAINT `historial_nomina_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`persona_id`);

--
-- Filtros para la tabla `historial_seg_social`
--
ALTER TABLE `historial_seg_social`
  ADD CONSTRAINT `historial_seg_social_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`persona_id`);

--
-- Filtros para la tabla `historial_vacaciones`
--
ALTER TABLE `historial_vacaciones`
  ADD CONSTRAINT `historial_vacaciones_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`persona_id`);

--
-- Filtros para la tabla `personas`
--
ALTER TABLE `personas`
  ADD CONSTRAINT `personas_ibfk_1` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`tipo_documento_id`),
  ADD CONSTRAINT `personas_ibfk_10` FOREIGN KEY (`eps_id`) REFERENCES `fondos` (`fondo_id`),
  ADD CONSTRAINT `personas_ibfk_11` FOREIGN KEY (`afp_id`) REFERENCES `fondos` (`fondo_id`),
  ADD CONSTRAINT `personas_ibfk_12` FOREIGN KEY (`fondo_cesantias_id`) REFERENCES `fondos` (`fondo_id`),
  ADD CONSTRAINT `personas_ibfk_13` FOREIGN KEY (`ccf_id`) REFERENCES `fondos` (`fondo_id`),
  ADD CONSTRAINT `personas_ibfk_14` FOREIGN KEY (`arl_id`) REFERENCES `fondos` (`fondo_id`),
  ADD CONSTRAINT `personas_ibfk_2` FOREIGN KEY (`estado_civil_id`) REFERENCES `estados_civiles` (`estado_civil_id`),
  ADD CONSTRAINT `personas_ibfk_3` FOREIGN KEY (`nacionalidad_id`) REFERENCES `nacionalidades` (`nacionalidad_id`),
  ADD CONSTRAINT `personas_ibfk_4` FOREIGN KEY (`pais_exp_documento_id`) REFERENCES `paises` (`pais_id`),
  ADD CONSTRAINT `personas_ibfk_5` FOREIGN KEY (`ciudad_exp_documento_id`) REFERENCES `ciudades` (`ciudad_id`),
  ADD CONSTRAINT `personas_ibfk_6` FOREIGN KEY (`pais_emisor_pasaporte_id`) REFERENCES `paises` (`pais_id`),
  ADD CONSTRAINT `personas_ibfk_7` FOREIGN KEY (`ciudad_residencia_id`) REFERENCES `ciudades` (`ciudad_id`),
  ADD CONSTRAINT `personas_ibfk_8` FOREIGN KEY (`ciudad_nacimiento_id`) REFERENCES `ciudades` (`ciudad_id`),
  ADD CONSTRAINT `personas_ibfk_9` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`cargo_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
