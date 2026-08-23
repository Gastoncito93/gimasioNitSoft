-- ==============================================================================
-- GYM PRO SaaS - PLATAFORMA DE GESTIÓN DE GIMNASIOS
-- Arquitectura Multi-Tenant de 3 Capas (Nitsof Pattern)
-- Capa 1: SuperAdmin (Plataforma Global, SaaS, Control y Auditoría de Sedes)
-- Capa 2: Gimnasios / Inquilinos (Tenants con gimnasio_id aislado)
-- Capa 3: Miembros (Dueño de Gimnasio, Coach, Alumno)
-- ==============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ------------------------------------------------------------------------------
-- 1. TABLA: `gimnasios` (Control SaaS, Inquilinos, Suscripciones y Tokens)
-- ------------------------------------------------------------------------------

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
  `plan_tipo` enum('standard','pro') NOT NULL DEFAULT 'standard',
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

-- ------------------------------------------------------------------------------
-- 2. TABLA: `profesores` (Coaches y Entrenadores Aislados por Gimnasio)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `profesores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `nombre` varchar(120) NOT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `cuota_mensual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tipo_remuneracion` varchar(30) NOT NULL DEFAULT 'sueldo_fijo',
  `porcentaje_comision` decimal(5,2) NOT NULL DEFAULT '0.00',
  `monto_por_alumno` decimal(10,2) NOT NULL DEFAULT '0.00',
  `canon_mensual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `dia_pago_canon` int NOT NULL DEFAULT '10',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_pago` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prof_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `profesores` (`id`, `gimnasio_id`, `nombre`, `telefono`, `cuota_mensual`, `fecha_pago`, `observaciones`, `created_at`) VALUES
(1, 1, 'María Pérez', '+54 266 123456', 80000.00, '2025-12-05', 'Profesora de Musculación y Funcional', '2025-12-05 12:16:15'),
(4, 1, 'Gastón Sosa', '+54 266 987654', 90000.00, '2025-12-05', 'Profesor de Crossfit y Spinning', '2025-12-06 02:06:08')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ------------------------------------------------------------------------------
-- 3. TABLA: `alumnos` (Socios / Miembros con DNI, Plan y Estado de Membresía)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `alumnos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `nombre` varchar(120) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `plan` enum('3x','full','clase') NOT NULL DEFAULT '3x',
  `actividades` varchar(200) DEFAULT 'Musculación, Funcional',
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('activo','vencido','pausado') NOT NULL DEFAULT 'activo',
  `profesor_id` int DEFAULT NULL,
  `es_del_gym` tinyint(1) NOT NULL DEFAULT '1',
  `id_users` int UNSIGNED NOT NULL DEFAULT '0',
  `notas_alumno` text DEFAULT NULL,
  `notas_coach` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alu_gym` (`gimnasio_id`),
  KEY `idx_alu_dni` (`dni`),
  KEY `fk_alumno_profesor` (`profesor_id`),
  CONSTRAINT `fk_alumno_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `alumnos` (`id`, `gimnasio_id`, `nombre`, `dni`, `telefono`, `email`, `plan`, `actividades`, `fecha_inicio`, `fecha_vencimiento`, `estado`, `profesor_id`, `es_del_gym`, `created_at`) VALUES
(1, 1, 'Florencia Carreño', '38456123', '2657506957', 'florencia@gmail.com', '3x', 'Musculación, Funcional, Spinning', '2025-12-05', '2026-09-10', 'activo', 1, 1, '2025-12-05 12:17:28'),
(2, 1, 'Carlos Ruiz', '35789123', '+54 266 777888', 'carlos.ruiz@gmail.com', 'full', 'Crossfit, Musculación Libre', '2025-11-04', '2026-09-10', 'activo', 4, 1, '2025-12-05 12:19:58'),
(3, 1, 'Victoria López', '39123456', '2657506957', 'victoria@gmail.com', 'clase', 'Pilates, Cardio, Funcional', '2025-12-05', '2026-09-10', 'activo', 4, 1, '2025-12-05 14:48:46')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `dni` = VALUES(`dni`);

-- ------------------------------------------------------------------------------
-- 4. TABLA: `users` (RBAC: 4 Roles, BCrypt Cost 12, Reset Tokens y Vínculos)
-- ------------------------------------------------------------------------------

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
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expira` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `idx_user_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Credenciales demo con BCrypt (Cost: 12):
-- superadmin:        admin123
-- dueno_carlos:      dueno123
-- dueno_esteban:     dueno123
-- dueno_ramiro:      dueno123 (cuenta inactiva para prueba de bloqueo)
-- coach_gaston:      coach123
-- coach_maria:       coach123
-- alumno_florencia:  alumno123
-- alumno_carlos:     alumno123
-- alumno_victoria:   alumno123

INSERT INTO `users` (`id`, `nombre_usuario`, `email`, `telefono`, `password_hash`, `rol`, `is_superadmin`, `gimnasio_id`, `profesor_id`, `alumno_id`, `activo`) VALUES
(1, 'superadmin', 'superadmin@gymplatform.com', '+54 266 4000000', '$2y$12$dEJuztLDRvgo1Py6nhnrluGcV3WMsJ91kmqSh83HGpU4kq33BJViO', 'admin_general', 1, 1, NULL, NULL, 1),
(2, 'dueno_carlos', 'carlos.dueno@olympusgym.com', '+54 266 4112233', '$2y$12$GWj0.5m7gMQKc8MVb14uFOqMWLOZ5CR6caCa/a5/b0fhI.zXkpJcO', 'dueno', 0, 1, NULL, NULL, 1),
(3, 'dueno_esteban', 'esteban@titanfitness.com', '+54 266 4998877', '$2y$12$GWj0.5m7gMQKc8MVb14uFOqMWLOZ5CR6caCa/a5/b0fhI.zXkpJcO', 'dueno', 0, 2, NULL, NULL, 1),
(4, 'dueno_ramiro', 'ramiro@powergym.com', '+54 266 4334455', '$2y$12$GWj0.5m7gMQKc8MVb14uFOqMWLOZ5CR6caCa/a5/b0fhI.zXkpJcO', 'dueno', 0, 3, NULL, NULL, 0),
(5, 'coach_gaston', 'gaston@olympusgym.com', '+54 266 987654', '$2y$12$XDfPdZZCoJs3xMnEpYxaW.74BeqqCKi0Ks3e95ZxT0SoCX7x.3wBO', 'coach', 0, 1, 4, NULL, 1),
(6, 'coach_maria', 'maria@olympusgym.com', '+54 266 123456', '$2y$12$XDfPdZZCoJs3xMnEpYxaW.74BeqqCKi0Ks3e95ZxT0SoCX7x.3wBO', 'coach', 0, 1, 1, NULL, 1),
(7, 'alumno_florencia', 'florencia@gmail.com', '2657506957', '$2y$12$nasdbkG5J/Aczq/000.7yO0J1pPGmTwx1yhmdD71TQxfqIvc8cgae', 'alumno', 0, 1, NULL, 1, 1),
(8, 'alumno_carlos', 'carlos.ruiz@gmail.com', '+54 266 777888', '$2y$12$nasdbkG5J/Aczq/000.7yO0J1pPGmTwx1yhmdD71TQxfqIvc8cgae', 'alumno', 0, 1, NULL, 2, 1),
(9, 'alumno_victoria', 'victoria@gmail.com', '2657506957', '$2y$12$nasdbkG5J/Aczq/000.7yO0J1pPGmTwx1yhmdD71TQxfqIvc8cgae', 'alumno', 0, 1, NULL, 3, 1)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `rol` = VALUES(`rol`), `is_superadmin` = VALUES(`is_superadmin`);

-- ------------------------------------------------------------------------------
-- 5. TABLA: `invitaciones` (Tokens de Registro Directo por Gimnasio)
-- ------------------------------------------------------------------------------

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

-- ------------------------------------------------------------------------------
-- 6. TABLA: `plan_precios` (Precios Base de Membresías)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `plan_precios` (
  `plan` enum('3x','full','clase') NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `plan_precios` (`plan`, `precio`) VALUES
('3x', 25000.00),
('full', 35000.00),
('clase', 5000.00)
ON DUPLICATE KEY UPDATE `precio` = VALUES(`precio`);

-- ------------------------------------------------------------------------------
-- 7. TABLA: `pagos` (Cobros a Alumnos y Liquidaciones a Profesores por Gimnasio)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `tipo` varchar(50) NOT NULL,
  `alumno_id` int DEFAULT NULL,
  `profesor_id` int DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `plan` enum('3x','full','clase') DEFAULT NULL,
  `medio_pago` enum('efectivo','transferencia','tarjeta','otro') NOT NULL DEFAULT 'efectivo',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pagos_gym` (`gimnasio_id`),
  KEY `fk_pago_alumno` (`alumno_id`),
  KEY `fk_pago_profesor` (`profesor_id`),
  CONSTRAINT `fk_pago_alumno` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pago_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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

-- ------------------------------------------------------------------------------
-- 8. TABLA: `pagos_plataforma` (Dueños de Gimnasio -> Administrador SaaS)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `pagos_plataforma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL,
  `dueno_id` int UNSIGNED NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `periodo_mes` varchar(7) NOT NULL,
  `medio_pago` enum('transferencia','mercadopago','efectivo','tarjeta','otro') NOT NULL DEFAULT 'transferencia',
  `comprobante` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pp_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pagos_plataforma` (`id`, `gimnasio_id`, `dueno_id`, `monto`, `fecha_pago`, `periodo_mes`, `medio_pago`, `comprobante`, `observaciones`, `created_at`) VALUES
(1, 1, 2, 45000.00, '2026-08-01', '2026-08', 'transferencia', 'TRF-987213', 'Pago suscripción mensual SaaS al día', '2026-08-11 02:39:16'),
(2, 2, 3, 45000.00, '2026-07-07', '2026-07', 'mercadopago', 'MP-554412', 'Mes anterior pagado. Venciendo mes corriente.', '2026-08-11 02:39:16')
ON DUPLICATE KEY UPDATE `monto` = VALUES(`monto`);

-- ------------------------------------------------------------------------------
-- 9. TABLA: `asistencias` (Control de Ingreso / Turnos Diario por Gimnasio)
-- ------------------------------------------------------------------------------

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

INSERT INTO `asistencias` (`id`, `alumno_id`, `gimnasio_id`, `coach_id`, `fecha`, `hora`, `observaciones`) VALUES
(1, 1, 1, 1, '2026-08-10', '18:30:00', 'Entrenamiento tren superior'),
(2, 1, 1, 1, '2026-08-08', '18:15:00', 'Entrenamiento piernas y cardio'),
(3, 2, 1, 4, '2026-08-10', '09:00:00', 'Crossfit WOD A'),
(4, 3, 1, 4, '2026-08-09', '17:00:00', 'Clase individual de Pilates')
ON DUPLICATE KEY UPDATE `observaciones` = VALUES(`observaciones`);

-- ------------------------------------------------------------------------------
-- 10. TABLA: `rutinas` (Rutinas Personalizadas de Entrenamiento por Socio)
-- ------------------------------------------------------------------------------

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

INSERT INTO `rutinas` (`id`, `gimnasio_id`, `alumno_id`, `coach_id`, `titulo`, `objetivo`, `dias_semana`, `detalles`, `fecha_asignacion`, `estado`) VALUES
(1, 1, 1, 1, 'Rutina Tonificación y Fuerza 4 Días', 'Tonificación muscular y resistencia', 'Lunes, Martes, Jueves, Viernes', 'DÍA 1 (Piernas & Glúteos):\n- Sentadillas: 4x12\n- Prensa 45°: 4x10\n- Hip Thrust: 4x12\n\nDÍA 2 (Espalda & Bíceps):\n- Jalón al pecho: 4x12\n- Remo con mancuerna: 4x10\n- Curl bíceps: 3x12', '2026-08-11', 'activa'),
(2, 1, 2, 4, 'Rutina Hipertrofia & Fuerza Máxima', 'Aumento de masa muscular y fuerza', 'Lunes a Sábado (Push / Pull / Leg)', 'DÍA A (Push - Empuje):\n- Press banca plano: 5x5\n- Press inclinado: 4x8\n- Fondos en paralelas: 4x10\n\nDÍA B (Pull - Tracción):\n- Dominadas: 4x8\n- Peso muerto: 4x6\n- Remo con barra: 4x8', '2026-08-11', 'activa')
ON DUPLICATE KEY UPDATE `titulo` = VALUES(`titulo`);

-- ------------------------------------------------------------------------------
-- 11. TABLA: `planes_nutricionales` (Planes Alimentarios por Alumno)
-- ------------------------------------------------------------------------------

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

INSERT INTO `planes_nutricionales` (`id`, `gimnasio_id`, `alumno_id`, `coach_id`, `titulo`, `calorias_aprox`, `detalles`, `fecha_asignacion`, `estado`) VALUES
(1, 1, 1, 1, 'Plan de Alimentación Equilibrado & Rendimiento', 2100, '🍳 DESAYUNO (08:00 hs):\n- 2 huevos revueltos + tostadas integrales\n- Café o infusión\n\n🥗 ALMUERZO (13:00 hs):\n- 180g de pechuga de pollo grillada con arroz integral y verduras verdes\n\n🍎 MERIENDA (17:00 hs):\n- Yogur natural con frutos secos y avena\n\n🍗 CENA (21:30 hs):\n- 200g de pescado al horno con vegetales al vapor', '2026-08-11', 'activo'),
(2, 1, 2, 4, 'Plan Nutricional Hipertrofia y Volumen Limpio', 2900, '🍳 DESAYUNO:\n- 4 claras + 2 huevos revueltos con avena y fruta\n\n🥗 ALMUERZO:\n- 250g de carne magra + 200g de arroz o fideos integrales + ensalada\n\n🥪 MERIENDA / POST-ENTRENO:\n- Batido de proteína Whey con banana y tostadas integrales\n\n🍗 CENA:\n- 250g de pollo o salmón con puré de batata', '2026-08-11', 'activo')
ON DUPLICATE KEY UPDATE `titulo` = VALUES(`titulo`);

-- ------------------------------------------------------------------------------
-- 12. TABLA: `login_attempts` (Rate Limiting Anti Fuerza Bruta)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------------------
-- 13. VISTAS SQL (Reportes, Resúmenes y Estadísticas)
-- ------------------------------------------------------------------------------

CREATE OR REPLACE VIEW `v_alumnos_resumen` AS
SELECT 
    a.id,
    a.gimnasio_id,
    a.nombre,
    a.dni,
    a.telefono,
    a.email,
    a.plan,
    pp.precio AS precio_plan,
    a.fecha_inicio,
    a.fecha_vencimiento,
    a.estado,
    a.profesor_id,
    p.nombre AS profesor_nombre,
    a.es_del_gym,
    DATEDIFF(a.fecha_vencimiento, CURDATE()) AS dias_restantes,
    a.created_at
FROM alumnos a
LEFT JOIN plan_precios pp ON pp.plan = a.plan
LEFT JOIN profesores p ON p.id = a.profesor_id;

CREATE OR REPLACE VIEW `v_ingresos_mensuales` AS
SELECT 
    DATE_FORMAT(fecha_pago, '%Y-%m') AS periodo,
    gimnasio_id,
    tipo,
    medio_pago,
    COUNT(id) AS cantidad_pagos,
    SUM(monto) AS total_recaudado
FROM pagos
GROUP BY DATE_FORMAT(fecha_pago, '%Y-%m'), gimnasio_id, tipo, medio_pago;


-- ------------------------------------------------------------------------------
-- 10. TABLA: `ejercicios_catalogo` (Nivel 1: Catálogo Maestro de Ejercicios)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ejercicios_catalogo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `grupo_muscular` enum('pecho','espalda','piernas','hombros','biceps','triceps','core','cardio','cuerpo_completo') NOT NULL,
  `descripcion` text DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ej_grupo` (`grupo_muscular`),
  KEY `idx_ej_gym` (`gimnasio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------------------
-- 11. TABLA: `rutinas_programas` (Nivel 2: Programas / Plantillas 1-7 Días)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rutinas_programas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `titulo` varchar(180) NOT NULL,
  `objetivo` varchar(120) DEFAULT 'Hipertrofia Muscular',
  `nivel` enum('principiante','intermedio','avanzado') NOT NULL DEFAULT 'intermedio',
  `dias_count` tinyint UNSIGNED NOT NULL DEFAULT 3,
  `descripcion` text DEFAULT NULL,
  `es_plantilla` tinyint(1) NOT NULL DEFAULT 1,
  `alumno_id` int DEFAULT NULL,
  `coach_id` int DEFAULT NULL,
  `estado` enum('activa','inactiva','archivada') NOT NULL DEFAULT 'activa',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prog_gym` (`gimnasio_id`),
  KEY `idx_prog_alu` (`alumno_id`),
  KEY `idx_prog_plantilla` (`es_plantilla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------------------
-- 12. TABLA: `rutinas_dias` (Nivel 3: Días de Sesión de Entrenamiento)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rutinas_dias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `programa_id` int NOT NULL,
  `numero_dia` tinyint UNSIGNED NOT NULL DEFAULT 1,
  `nombre_dia` varchar(80) NOT NULL DEFAULT 'Día 1',
  `enfoque` varchar(150) DEFAULT NULL,
  `orden` int NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dia_prog` (`programa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------------------
-- 13. TABLA: `rutinas_ejercicios` (Nivel 4: Detalle del Ejercicio & Bloques)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rutinas_ejercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dia_id` int NOT NULL,
  `ejercicio_id` int NOT NULL,
  `bloque` enum('calentamiento','principal','cardio','vuelta_calma') NOT NULL DEFAULT 'principal',
  `series` tinyint UNSIGNED NOT NULL DEFAULT 4,
  `repeticiones` varchar(50) NOT NULL DEFAULT '10-12',
  `descanso_seg` int UNSIGNED DEFAULT 60,
  `carga_sugerida` varchar(80) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `orden` int NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_re_dia` (`dia_id`),
  KEY `idx_re_ej` (`ejercicio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------------------
-- 14. TABLA: `rutinas_checkins` (Entrenamientos Completados y Feedback)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rutinas_checkins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gimnasio_id` int NOT NULL DEFAULT 1,
  `alumno_id` int NOT NULL,
  `programa_id` int DEFAULT NULL,
  `dia_id` int DEFAULT NULL,
  `rutina_nombre` varchar(180) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `ejercicios_completados` int NOT NULL DEFAULT 0,
  `duracion_min` int NOT NULL DEFAULT 60,
  `nivel_esfuerzo` tinyint NOT NULL DEFAULT 3,
  `observaciones` text DEFAULT NULL,
  `coach_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rc_gym` (`gimnasio_id`),
  KEY `idx_rc_alu` (`alumno_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SEED: CATÁLOGO DE EJERCICIOS MAESTRO
INSERT INTO `ejercicios_catalogo` (`id`, `nombre`, `grupo_muscular`, `descripcion`) VALUES
(1, 'Press de Banca Plano con Barra', 'pecho', 'Desarrollo pectoral mayor general'),
(2, 'Press Inclinado con Mancuernas', 'pecho', 'Pectoral superior y clavicular'),
(3, 'Press Declinado con Barra', 'pecho', 'Pectoral inferior'),
(4, 'Aperturas / Flyes con Mancuernas', 'pecho', 'Estiramiento y aislamiento pectoral'),
(5, 'Cruces en Polea (Crossover)', 'pecho', 'Tensión constante pectoral medio/inferior'),
(6, 'Pec Deck / Contractor de Pecho', 'pecho', 'Máquina de aislamiento pectoral'),
(7, 'Fondos en Paralelas (Dips Pecho)', 'pecho', 'Inclinación frontal para pectoral inferior'),
(8, 'Flexiones de Brazo (Push Ups)', 'pecho', 'Calistenia pectoral y tríceps'),
(9, 'Jalón al Pecho en Polea', 'espalda', 'Dorsal ancho y amplitud'),
(10, 'Dominadas Pronas / Neutras', 'espalda', 'Fuerza funcional tren superior'),
(11, 'Remo con Barra 45°', 'espalda', 'Grosor dorsal y espalda media'),
(12, 'Remo Unilateral con Mancuerna (Serrucho)', 'espalda', 'Aislamiento dorsal por lado'),
(13, 'Remo en Polea Baja (Girona)', 'espalda', 'Espalda media y romboides'),
(14, 'Remo en Barra T', 'espalda', 'Densidad de espalda'),
(15, 'Pullover en Polea Alta con Cuerda', 'espalda', 'Aislamiento dorsal sin bíceps'),
(16, 'Hiperextensiones Lumbares', 'espalda', 'Erectores espinales y cadena posterior'),
(17, 'Sentadilla Trasera con Barra (Squat)', 'piernas', 'Rey de piernas: cuádriceps, glúteos y core'),
(18, 'Sentadilla Goblet con Mancuerna', 'piernas', 'Ideal para principiantes y movilidad'),
(19, 'Prensa de Piernas 45°', 'piernas', 'Sobrecarga de cuádriceps sin carga axial'),
(20, 'Silla de Cuádriceps (Extensiones)', 'piernas', 'Aislamiento directo de cuádriceps'),
(21, 'Camilla de Femorales Tumbado', 'piernas', 'Isquiotibiales en flexión de rodilla'),
(22, 'Sillón de Femorales Sentado', 'piernas', 'Isquios con cadera flexionada'),
(23, 'Peso Muerto Rumano con Mancuernas/Barra', 'piernas', 'Isquiotibiales y glúteos'),
(24, 'Estocadas / Zancadas Caminando', 'piernas', 'Trabajo unilateral de glúteo y cuádriceps'),
(25, 'Sentadilla Búlgara', 'piernas', 'Unilateral intenso glúteo/cuádriceps'),
(26, 'Elevación de Talones en Máquina (Gemelos)', 'piernas', 'Gastrocnemio y sóleo'),
(27, 'Hip Thrust con Barra / Máquina', 'piernas', 'Aislamiento máximo de glúteo mayor'),
(28, 'Press Militar con Barra', 'hombros', 'Deltoides anterior y fuerza overhead'),
(29, 'Press de Hombros Sentado con Mancuernas', 'hombros', 'Desarrollo de masa en deltoides'),
(30, 'Vuelos Laterales con Mancuernas', 'hombros', 'Aislamiento deltoides lateral (amplitud)'),
(31, 'Elevaciones Laterales en Polea', 'hombros', 'Tensión constante deltoides lateral'),
(32, 'Pájaros / Vuelos Posteriores', 'hombros', 'Deltoides posterior y postura'),
(33, 'Face Pull con Cuerda en Polea', 'hombros', 'Salud articular del manguito rotador y deltoides post'),
(34, 'Press Arnold con Mancuernas', 'hombros', 'Rotación y activación completa del hombro'),
(35, 'Elevaciones Frontales con Disco/Mancuerna', 'hombros', 'Deltoides anterior'),
(36, 'Curl de Bíceps con Barra Z / Recta', 'biceps', 'Masa general del bíceps'),
(37, 'Curl Alterno con Mancuernas en Banco Inclinado', 'biceps', 'Cabeza larga y pico de bíceps'),
(38, 'Curl Martillo (Hammer Curl)', 'biceps', 'Braquial y braquiorradial (grosor de brazo)'),
(39, 'Curl Predicador / Banco Scott', 'biceps', 'Aislamiento estricto sin balanceo'),
(40, 'Curl Concentrado con Mancuerna', 'biceps', 'Pico de bíceps'),
(41, 'Curl en Polea Baja con Cuerda', 'biceps', 'Tensión constante'),
(42, 'Extensión de Tríceps en Polea con Cuerda', 'triceps', 'Cabeza lateral del tríceps'),
(43, 'Extensión de Tríceps con Barra Recta en Polea', 'triceps', 'Sobrecarga de tríceps'),
(44, 'Press Francés con Barra Z en Banco Plano', 'triceps', 'Cabeza larga del tríceps'),
(45, 'Fondos en Banco o Paralelas para Tríceps', 'triceps', 'Fuerza de empuje tríceps'),
(46, 'Extensión de Tríceps Copa a Dos Manos', 'triceps', 'Estiramiento de cabeza larga'),
(47, 'Patada de Tríceps con Mancuerna/Polea', 'triceps', 'Contracción final'),
(48, 'Plancha Abdominal Prona (Plank)', 'core', 'Resistencia isométrica del core anterior'),
(49, 'Plancha Lateral', 'core', 'Estabilidad oblicua y cuadrado lumbar'),
(50, 'Crunch Abdominal en Polea / Suelo', 'core', 'Flexión de tronco recto abdominal'),
(51, 'Elevación de Piernas Colgado en Barra', 'core', 'Abdomen inferior y flexores'),
(52, 'Rueda Abdominal (Ab Wheel Rollout)', 'core', 'Extensión y fuerza avanzada del core'),
(53, 'Giros Rusos (Russian Twists)', 'core', 'Rotación y oblicuos'),
(54, 'Bird Dog (Perro de Caza)', 'core', 'Estabilidad lumbo-pélvica y salud de espalda'),
(55, 'Deadbug (Bicho Muerto)', 'core', 'Activación del transverso abdominal'),
(56, 'Peso Muerto Convencional', 'cuerpo_completo', 'Fuerza total: cadena posterior y core'),
(57, 'Kettlebell Swing', 'cuerpo_completo', 'Potencia de cadera y cardio metabólico'),
(58, 'Clean and Press (Cargada y Press)', 'cuerpo_completo', 'Fuerza y potencia olímpica'),
(59, 'Cinta de Correr (Caminata / Running)', 'cardio', 'Cardio continuo o intervalos LISS/HIIT'),
(60, 'Bicicleta Fija / Spinning', 'cardio', 'Bajo impacto articular'),
(61, 'Elíptico', 'cardio', 'Cardio cuerpo completo suave para rodillas'),
(62, 'Remoergómetro (Máquina de Remo Concept)', 'cardio', 'Cardio de alta demanda metabólica'),
(63, 'Salto a la Soga', 'cardio', 'Coordinación, pantorrillas y HIIT'),
(64, 'Burpees', 'cardio', 'Acondicionamiento metabólico con peso corporal'),
(65, 'Mountain Climbers (Escaladores)', 'cardio', 'Cardio dinámico y activación abdominal'),
(66, 'Battle Ropes (Cuerdas de Combate)', 'cardio', 'HIIT de tren superior y hombros')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);


CREATE OR REPLACE VIEW `v_prof_alumnos` AS
SELECT 
    p.id AS profesor_id,
    p.gimnasio_id,
    p.nombre AS profesor_nombre,
    p.cuota_mensual,
    COUNT(a.id) AS total_alumnos
FROM profesores p
LEFT JOIN alumnos a ON a.profesor_id = p.id
GROUP BY p.id, p.gimnasio_id, p.nombre, p.cuota_mensual;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;