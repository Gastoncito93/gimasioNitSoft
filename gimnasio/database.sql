-- Base de Datos para Plataforma de Gimnasios GYM PRO SaaS
-- Arquitectura Multi-Tenant de 3 Capas (Nitsof Pattern)
-- Capa 1: SuperAdmin (Plataforma Global, SaaS, Control de Sedes)
-- Capa 2: Gimnasios / Inquilinos (Tenants con gym_id aislado)
-- Capa 3: Miembros (Dueño, Coach, Alumno)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Estructura de tabla para `gimnasios` con control de Suscripción SaaS y Tokens de Invitación
--

CREATE TABLE IF NOT EXISTS `gimnasios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) NOT NULL,
  `invite_code` varchar(50) DEFAULT NULL,
  `dueno_id` int UNSIGNED DEFAULT NULL,
  `dominio` varchar(160) DEFAULT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `suscripcion_monto` decimal(10,2) NOT NULL DEFAULT '45000.00',
  `suscripcion_vencimiento` date DEFAULT NULL,
  `suscripcion_estado` enum('activo','proximo','vencido','suspendido') NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_invite_code` (`invite_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `gimnasios` (`id`, `nombre`, `invite_code`, `dueno_id`, `telefono`, `email`, `direccion`, `suscripcion_monto`, `suscripcion_vencimiento`, `suscripcion_estado`) VALUES
(1, 'Olympus Gym Pro', 'OLYMPUS-PRO', 2, '+54 266 4112233', 'contacto@olympusgym.com', 'Av. San Martín 850', 45000.00, '2026-08-31', 'activo'),
(2, 'Titan Fitness Center', 'TITAN-FIT', 3, '+54 266 4998877', 'admin@titanfitness.com', 'Calle Belgrano 120', 45000.00, '2026-08-13', 'proximo'),
(3, 'Spartan Power Gym', 'SPARTAN-GYM', 4, '+54 266 4334455', 'spartan@powergym.com', 'Ruta 7 Km 2', 40000.00, '2026-08-01', 'suspendido')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- --------------------------------------------------------

--
-- Estructura de tabla para `users` con los 4 Roles (RBAC), is_superadmin y BCrypt (cost: 12)
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `rol` enum('admin_general','dueno','coach','alumno') NOT NULL DEFAULT 'dueno',
  `is_superadmin` tinyint(1) NOT NULL DEFAULT 0,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `profesor_id` int DEFAULT NULL,
  `alumno_id` int DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `idx_user_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `nombre_usuario`, `email`, `telefono`, `password_hash`, `rol`, `is_superadmin`, `gimnasio_id`, `profesor_id`, `alumno_id`, `activo`) VALUES
(1, 'superadmin', 'superadmin@gymplatform.com', '+54 266 4000000', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'admin_general', 1, 1, NULL, NULL, 1),
(2, 'dueno_carlos', 'carlos.dueno@olympusgym.com', '+54 266 4112233', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'dueno', 0, 1, NULL, NULL, 1),
(3, 'dueno_esteban', 'esteban@titanfitness.com', '+54 266 4998877', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'dueno', 0, 2, NULL, NULL, 1),
(4, 'dueno_ramiro', 'ramiro@powergym.com', '+54 266 4334455', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'dueno', 0, 3, NULL, NULL, 0),
(5, 'coach_gaston', 'gaston@olympusgym.com', '+54 266 987654', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'coach', 0, 1, 4, NULL, 1),
(6, 'coach_maria', 'maria@olympusgym.com', '+54 266 123456', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'coach', 0, 1, 1, NULL, 1),
(7, 'alumno_florencia', 'florencia@gmail.com', '2657506957', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'alumno', 0, 1, NULL, 1, 1),
(8, 'alumno_carlos', 'carlos.ruiz@gmail.com', '+54 266 777888', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'alumno', 0, 1, NULL, 2, 1),
(9, 'alumno_victoria', 'victoria@gmail.com', '2657506957', '$2y$12$n7EWHCc/pwi0WSKJWdoLhu9VDebANoJ2v9kb8rI9Ljy.cBcDh/Qk6', 'alumno', 0, 1, NULL, 3, 1)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `rol` = VALUES(`rol`), `is_superadmin` = VALUES(`is_superadmin`);

-- --------------------------------------------------------

--
-- Estructura de tabla para `invitaciones` (Tokens de Registro Directo por Gimnasio)
--

CREATE TABLE IF NOT EXISTS `invitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `rol` enum('alumno','coach') NOT NULL DEFAULT 'alumno',
  `usos_restantes` int NOT NULL DEFAULT 100,
  `expira_en` datetime DEFAULT NULL,
  `creado_por` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_inv_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invitaciones` (`gimnasio_id`, `token`, `rol`, `usos_restantes`) VALUES
(1, 'OLYMPUS_ALUMNO_2026', 'alumno', 500),
(1, 'OLYMPUS_COACH_2026', 'coach', 50),
(2, 'TITAN_ALUMNO_2026', 'alumno', 500)
ON DUPLICATE KEY UPDATE `usos_restantes` = VALUES(`usos_restantes`);

-- --------------------------------------------------------

--
-- Estructura de tabla para `pagos_plataforma` (Dueños -> Admin General SaaS)
--

CREATE TABLE IF NOT EXISTS `pagos_plataforma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL,
  `dueno_id` int UNSIGNED NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `periodo_mes` varchar(7) NOT NULL,
  `medio_pago` enum('transferencia','mercadopago','efectivo','tarjeta','otro') NOT NULL DEFAULT 'transferencia',
  `comprobante` varchar(255) DEFAULT NULL,
  `observaciones` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pp_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para `asistencias` (Aislada por gimnasio_id)
--

CREATE TABLE IF NOT EXISTS `asistencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `alumno_id` int NOT NULL,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `coach_id` int DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `observaciones` varchar(255) DEFAULT 'Ingreso al gimnasio',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asist_gym` (`gimnasio_id`),
  KEY `idx_asist_alu` (`alumno_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para `rutinas` (Aislada por gimnasio_id)
--

CREATE TABLE IF NOT EXISTS `rutinas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `alumno_id` int NOT NULL,
  `coach_id` int DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `objetivo` varchar(150) DEFAULT 'Hipertrofia / Fuerza',
  `dias_semana` varchar(100) DEFAULT 'Lunes a Viernes',
  `detalles` text NOT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('activa','completada','pausada') NOT NULL DEFAULT 'activa',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rutina_gym` (`gimnasio_id`),
  KEY `idx_rutina_alu` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para `planes_nutricionales` (Aislada por gimnasio_id)
--

CREATE TABLE IF NOT EXISTS `planes_nutricionales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `alumno_id` int NOT NULL,
  `coach_id` int DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `calorias_aprox` int DEFAULT 2200,
  `detalles` text NOT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nutri_gym` (`gimnasio_id`),
  KEY `idx_plan_nutri_alu` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para `plan_precios`
--

CREATE TABLE IF NOT EXISTS `plan_precios` (
  `plan` enum('3x','full','clase') COLLATE utf8mb4_general_ci NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `plan_precios` (`plan`, `precio`) VALUES
('3x', 25000.00),
('full', 35000.00),
('clase', 5000.00)
ON DUPLICATE KEY UPDATE `precio` = VALUES(`precio`);

-- --------------------------------------------------------

--
-- Estructura de tabla para `profesores` (Aislada por gimnasio_id)
--

CREATE TABLE IF NOT EXISTS `profesores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cuota_mensual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha_pago` date DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prof_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `profesores` (`id`, `gimnasio_id`, `nombre`, `telefono`, `cuota_mensual`, `fecha_pago`, `observaciones`, `created_at`) VALUES
(1, 1, 'María Pérez', '+54 266 123456', 80000.00, '2025-12-05', 'Profesora de Musculación y Funcional', '2025-12-05 12:16:15'),
(4, 1, 'Gastón Sosa', '+54 266 987654', 90000.00, '2025-12-05', 'Profesor de Crossfit y Spinning', '2025-12-06 02:06:08')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- --------------------------------------------------------

--
-- Estructura de tabla para `alumnos` (Aislada por gimnasio_id)
--

CREATE TABLE IF NOT EXISTS `alumnos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plan` enum('3x','full','clase') COLLATE utf8mb4_general_ci NOT NULL DEFAULT '3x',
  `actividades` varchar(200) COLLATE utf8mb4_general_ci DEFAULT 'Musculación, Funcional',
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('activo','vencido','pausado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activo',
  `profesor_id` int DEFAULT NULL,
  `es_del_gym` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alu_gym` (`gimnasio_id`),
  KEY `fk_alumno_profesor` (`profesor_id`),
  CONSTRAINT `fk_alumno_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `alumnos` (`id`, `gimnasio_id`, `nombre`, `telefono`, `email`, `plan`, `actividades`, `fecha_inicio`, `fecha_vencimiento`, `estado`, `profesor_id`, `es_del_gym`, `created_at`) VALUES
(1, 1, 'Florencia Carreño', '2657506957', 'florencia@gmail.com', '3x', 'Musculación, Funcional, Spinning', '2025-12-05', '2026-09-10', 'activo', 1, 1, '2025-12-05 12:17:28'),
(2, 1, 'Carlos Ruiz', '+54 266 777888', 'carlos.ruiz@gmail.com', 'full', 'Crossfit, Musculación Libre', '2025-11-04', '2026-09-10', 'activo', 4, 1, '2025-12-05 12:19:58'),
(3, 1, 'Victoria López', '2657506957', 'victoria@gmail.com', 'clase', 'Pilates, Cardio, Funcional', '2025-12-05', '2026-09-10', 'activo', 4, 1, '2025-12-05 14:48:46')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- --------------------------------------------------------

--
-- Estructura de tabla para `pagos` (Aislada por gimnasio_id)
--

CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `tipo` enum('alumno','profesor') COLLATE utf8mb4_general_ci NOT NULL,
  `alumno_id` int DEFAULT NULL,
  `profesor_id` int DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `plan` enum('3x','full','clase') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medio_pago` enum('efectivo','transferencia','tarjeta','otro') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'efectivo',
  `observaciones` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pagos_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pagos` (`id`, `gimnasio_id`, `tipo`, `alumno_id`, `profesor_id`, `monto`, `fecha_pago`, `plan`, `medio_pago`, `observaciones`, `created_at`) VALUES
(1, 1, 'profesor', NULL, 1, 50000.00, '2025-12-05', NULL, 'efectivo', 'Pago desde ficha', '2025-12-05 12:16:15'),
(2, 1, 'profesor', NULL, 4, 75000.00, '2025-12-05', NULL, 'transferencia', 'Pago desde ficha', '2025-12-05 12:16:50'),
(3, 1, 'alumno', 1, NULL, 20000.00, '2025-12-05', '3x', 'efectivo', 'Pago inicial desde ficha', '2025-12-05 12:17:28'),
(4, 1, 'alumno', 2, NULL, 35000.00, '2025-11-05', 'full', 'transferencia', 'Pago inicial desde ficha', '2025-12-05 12:19:58'),
(5, 1, 'alumno', 3, NULL, 10000.00, '2025-12-05', 'clase', 'efectivo', 'Pago inicial desde ficha', '2025-12-05 14:48:46'),
(6, 1, 'profesor', NULL, 1, 80000.00, '2026-08-11', NULL, 'efectivo', 'Pago mensual', '2026-08-11 03:43:46'),
(7, 1, 'alumno', 1, NULL, 25000.00, '2026-08-11', '3x', 'transferencia', 'Pago cuota agosto', '2026-08-11 03:44:49'),
(8, 1, 'alumno', 2, NULL, 35000.00, '2026-08-11', 'full', 'efectivo', 'Pago cuota agosto', '2026-08-11 03:45:00')
ON DUPLICATE KEY UPDATE `monto` = VALUES(`monto`);

-- --------------------------------------------------------

--
-- Estructura de tabla para Rate Limiting (Anti Fuerza Bruta)
--

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
