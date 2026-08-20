-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 11-08-2026 a las 01:02:19
-- Versión del servidor: 8.0.43
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `c2632091_templo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id` int NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plan` enum('3x','full','clase') COLLATE utf8mb4_general_ci NOT NULL DEFAULT '3x',
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('activo','vencido','pausado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activo',
  `profesor_id` int DEFAULT NULL,
  `es_del_gym` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_users` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`id`, `nombre`, `telefono`, `plan`, `fecha_inicio`, `fecha_vencimiento`, `estado`, `profesor_id`, `es_del_gym`, `created_at`, `id_users`) VALUES
(1, 'Florencia Carreño', '2657506957', '3x', '2025-12-05', '2026-01-04', 'vencido', NULL, 1, '2025-12-05 12:17:28', 0),
(2, 'Carlos Ruiz', '+54 266 777888', 'full', '2025-11-04', '2026-09-10', 'activo', NULL, 1, '2025-12-05 12:19:58', 0),
(3, 'Viki', '2657506957', 'clase', '2025-12-05', '2026-09-10', 'activo', NULL, 1, '2025-12-05 14:48:46', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gimnasios`
--

CREATE TABLE `gimnasios` (
  `id` int NOT NULL,
  `nombre` varchar(160) COLLATE utf8mb4_general_ci NOT NULL,
  `dominio` varchar(160) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `gimnasios`
--

INSERT INTO `gimnasios` (`id`, `nombre`, `dominio`, `created_at`) VALUES
(1, 'localhost', 'localhost', '2025-12-05 14:45:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int NOT NULL,
  `tipo` enum('alumno','profesor') COLLATE utf8mb4_general_ci NOT NULL,
  `alumno_id` int DEFAULT NULL,
  `profesor_id` int DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `plan` enum('3x','full','clase') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medio_pago` enum('efectivo','transferencia','tarjeta','otro') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'efectivo',
  `observaciones` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `tipo`, `alumno_id`, `profesor_id`, `monto`, `fecha_pago`, `plan`, `medio_pago`, `observaciones`, `created_at`) VALUES
(1, 'profesor', NULL, 1, 50000.00, '2025-12-05', NULL, 'efectivo', 'Pago desde ficha', '2025-12-05 12:16:15'),
(2, 'profesor', NULL, NULL, 75000.00, '2025-12-05', NULL, 'transferencia', 'Pago desde ficha', '2025-12-05 12:16:50'),
(3, 'alumno', 1, NULL, 20000.00, '2025-12-05', '3x', 'efectivo', 'Pago inicial desde ficha', '2025-12-05 12:17:28'),
(4, 'alumno', 2, NULL, 35000.00, '2025-11-05', 'full', 'transferencia', 'Pago inicial desde ficha', '2025-12-05 12:19:58'),
(5, 'alumno', 2, NULL, 35000.00, '2025-12-05', 'full', 'efectivo', '', '2025-12-05 12:42:19'),
(6, 'profesor', NULL, 1, 30000.00, '2025-12-05', NULL, 'efectivo', '', '2025-12-05 14:47:43'),
(7, 'alumno', 3, NULL, 10000.00, '2025-12-05', 'clase', 'efectivo', 'Pago inicial desde ficha', '2025-12-05 14:48:46'),
(8, 'alumno', 1, NULL, 5000.00, '2025-12-05', '3x', 'efectivo', '', '2025-12-05 14:49:10'),
(9, 'profesor', NULL, NULL, 50000.00, '2025-12-05', NULL, 'efectivo', 'Pago desde ficha', '2025-12-05 15:49:46'),
(10, 'profesor', NULL, NULL, 30000.00, '2025-12-05', NULL, 'efectivo', '', '2025-12-05 15:50:01'),
(11, 'profesor', NULL, 4, 50000.00, '2025-12-05', NULL, 'efectivo', 'Pago desde ficha', '2025-12-06 02:06:08'),
(12, 'profesor', NULL, 4, 40000.00, '2025-12-05', NULL, 'efectivo', '', '2025-12-06 02:06:26'),
(13, 'profesor', NULL, NULL, 150.00, '2026-08-11', NULL, 'tarjeta', 'Pago desde ficha', '2026-08-11 03:42:15'),
(14, 'profesor', NULL, 4, 90000.00, '2026-08-11', NULL, 'efectivo', '', '2026-08-11 03:43:34'),
(15, 'profesor', NULL, 1, 80000.00, '2026-08-11', NULL, 'efectivo', '', '2026-08-11 03:43:46'),
(16, 'alumno', 3, NULL, 20000.00, '2026-08-11', 'clase', 'efectivo', 'Pago inicial desde ficha', '2026-08-11 03:44:49'),
(17, 'alumno', 2, NULL, 20000.00, '2026-08-11', 'full', 'efectivo', 'Pago inicial desde ficha', '2026-08-11 03:45:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_precios`
--

CREATE TABLE `plan_precios` (
  `plan` enum('3x','full','clase') COLLATE utf8mb4_general_ci NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `plan_precios`
--

INSERT INTO `plan_precios` (`plan`, `precio`) VALUES
('3x', 25000.00),
('full', 35000.00),
('clase', 5000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

CREATE TABLE `profesores` (
  `id` int NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cuota_mensual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha_pago` date DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `profesores`
--

INSERT INTO `profesores` (`id`, `nombre`, `telefono`, `cuota_mensual`, `fecha_pago`, `observaciones`, `created_at`) VALUES
(1, 'María Pérez', '+54 266 123456', 80000.00, '2025-12-05', 'Profesor Gym', '2025-12-05 12:16:15'),
(4, 'Gaston Sosa', 'sdfsadfads', 90000.00, '2025-12-05', 'Profe de GYM', '2025-12-06 02:06:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `nombre_usuario` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `reset_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_expira` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `nombre_usuario`, `email`, `password_hash`, `activo`, `reset_token`, `reset_expira`, `creado_en`, `actualizado_en`) VALUES
(1, 'jose', 'jose@gmail.com', '$2y$10$wWTPHsVPmvU75sJTlcJPZOhqajmeG9vDXy4BqqzqP56.KOd0WPDnm', 1, NULL, NULL, '2025-12-09 01:26:37', '2025-12-09 01:26:37'),
(2, 'Flor7108', 'flor7108@gmail.com', '$2y$12$HWkjePrtewdDw6y2KfWdM.REG2CL3jjY0XUIp0GTNwIBleYbJFRj6', 1, NULL, NULL, '2025-12-18 09:39:29', '2025-12-18 09:39:29'),
(3, 'Gaston Sosa', 'gastonoscarsosa@gmail.com', '$2y$12$6.UQsZhn9VxE7ahqZBaTTutxRv2Xlr18yEwntgUcXZ18eUlABW96e', 1, NULL, NULL, '2026-01-05 16:54:40', '2026-01-05 16:54:40'),
(4, 'Gastoncito93', 'sosa.gaston.oscar@hotmail.com', '$2y$12$ZtfNKMVA5eC1U3qsLRkd6ef/67poCEXOEUJPbRXUIKVIHXpW/xkX.', 1, NULL, NULL, '2026-08-11 00:41:07', '2026-08-11 00:41:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(160) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `rol` enum('admin','operador') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_prof_alumnos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_prof_alumnos` (
`profesor_id` int
,`total_alumnos` bigint
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_alumno_profesor` (`profesor_id`);

--
-- Indices de la tabla `gimnasios`
--
ALTER TABLE `gimnasios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dominio` (`dominio`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pago_alumno` (`alumno_id`),
  ADD KEY `fk_pago_profesor` (`profesor_id`);

--
-- Indices de la tabla `plan_precios`
--
ALTER TABLE `plan_precios`
  ADD PRIMARY KEY (`plan`);

--
-- Indices de la tabla `profesores`
--
ALTER TABLE `profesores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nombre_usuario_2` (`nombre_usuario`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `gimnasios`
--
ALTER TABLE `gimnasios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `profesores`
--
ALTER TABLE `profesores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_prof_alumnos`
--
DROP TABLE IF EXISTS `v_prof_alumnos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`c2632091`@`%` SQL SECURITY DEFINER VIEW `v_prof_alumnos`  AS SELECT `p`.`id` AS `profesor_id`, count(`a`.`id`) AS `total_alumnos` FROM (`profesores` `p` left join `alumnos` `a` on((`a`.`profesor_id` = `p`.`id`))) GROUP BY `p`.`id` ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `fk_alumno_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_alumno` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pago_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
