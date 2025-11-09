-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3309
-- Tiempo de generación: 09-11-2025 a las 22:24:37
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `codechoco_denuncias`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores`
--

CREATE TABLE `administradores` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('Super Admin','Admin','Moderador') DEFAULT 'Admin',
  `estado` enum('Activo','Inactivo','Suspendido') DEFAULT 'Activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `intentos_login` int(11) DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `administradores`
--

INSERT INTO `administradores` (`id`, `username`, `email`, `password_hash`, `nombre_completo`, `telefono`, `rol`, `estado`, `ultimo_acceso`, `fecha_creacion`, `fecha_actualizacion`, `creado_por`, `intentos_login`, `bloqueado_hasta`) VALUES
(1, 'admin', 'admin@codechoco.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Principal', NULL, 'Super Admin', 'Activo', NULL, '2025-06-02 12:35:06', '2025-10-27 16:51:28', NULL, 6, '2025-10-27 16:56:28'),
(2, 'alexchala', 'alexchala@gmail.com', 'ALEXDAVID009', 'Alex Chala', '3228758709', 'Super Admin', 'Activo', NULL, '2025-09-06 13:07:52', '2025-10-27 16:46:21', NULL, 1, NULL),
(3, 'chala22', 'chalaalex26@gmail.com', '$2y$10$o1DHsOjc/fEyToGS0CkuvuFXmBBU5UZIe9qAF2HOUAGu4M7Ui5u6y', 'Alex David Chala', '3228758709', 'Admin', 'Activo', '2025-09-11 19:41:04', '2025-09-06 13:52:34', '2025-09-11 19:41:04', NULL, 0, NULL),
(4, 'eddy24', 'eddy@gmail.com', '$2y$10$D/xIpY1kyXIl1qcUd5Z9O.7eJp30VsNabqkq7KqAGkLdFOlI85MRa', 'Edilma', '311313312', 'Admin', 'Activo', NULL, '2025-09-06 14:16:31', '2025-09-06 14:16:31', NULL, 0, NULL),
(5, 'eddi24', 'eddi@gmail.com', '$2y$10$XwZwAcNzTOqWZ4ox6sbgYOSt0l.zEiqIDC60QXxh.1YKkNKLSYctW', 'Edilma', '3228758709', 'Admin', 'Activo', '2025-11-06 23:21:52', '2025-10-27 16:52:45', '2025-11-06 23:21:52', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin_actividades`
--

CREATE TABLE `admin_actividades` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `descripcion` text NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin_sesiones`
--

CREATE TABLE `admin_sesiones` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT current_timestamp(),
  `fecha_expira` datetime NOT NULL,
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `denuncias`
--

CREATE TABLE `denuncias` (
  `id` int(11) NOT NULL,
  `tipo` enum('acoso','seguridad','etico','discriminacion','corrupcion','laboral','ambiental','otro') NOT NULL,
  `descripcion` text NOT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `estado` enum('pendiente','en_proceso','resuelto','cerrado','archivado') NOT NULL DEFAULT 'pendiente',
  `estado_anterior` varchar(50) DEFAULT NULL,
  `codigo_seguimiento` varchar(20) DEFAULT NULL,
  `nombre_denunciante` varchar(100) DEFAULT NULL,
  `contacto_denunciante` varchar(20) DEFAULT NULL,
  `email_denunciante` varchar(100) DEFAULT NULL,
  `urgencia` varchar(20) DEFAULT 'media'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `denuncias`
--

INSERT INTO `denuncias` (`id`, `tipo`, `descripcion`, `latitud`, `longitud`, `fecha`, `fecha_actualizacion`, `estado`, `estado_anterior`, `codigo_seguimiento`, `nombre_denunciante`, `contacto_denunciante`, `email_denunciante`, `urgencia`) VALUES
(1, '', 'me robaron dentro de la sede', 5.69658300, -76.65064500, '2025-05-07 00:00:00', '2025-08-28 02:58:32', 'pendiente', NULL, 'CC-2025-2882', NULL, NULL, NULL, 'media'),
(2, '', 'me acosaron en la sede', 5.67786100, -76.65062000, '2025-05-01 00:00:00', '2025-08-28 02:58:32', 'en_proceso', NULL, 'CC-2025-5020', 'Alex', '3228758709', 'alex@email.com', 'media'),
(3, '', 'mineria ilegal por los lados de icho', 5.69789400, -76.64920200, '2025-05-17 00:00:00', '2025-08-28 02:58:32', 'resuelto', NULL, 'CC-2025-7287', NULL, NULL, NULL, 'media'),
(4, '', 'estan contaminando los rios con mercurio', 5.69661700, -76.65208900, '2025-05-29 00:00:00', '2025-09-12 00:22:23', 'pendiente', NULL, 'CC-2025-0248', 'david', '3237899012', 'david@gmail.com', 'media'),
(5, '', 'atrato esta siendo contaminado', 5.69661700, -76.65208900, '2025-06-01 00:00:00', '2025-09-12 00:01:20', 'en_proceso', NULL, 'CC-2025-8106', 'edilma', '3213311341', 'edilma22@gmail.com', 'media'),
(6, '', 'me toco la nalga y me dijo cosas feas', 3.43081000, -76.51655700, '2025-06-03 00:00:00', '2025-08-28 04:13:14', 'en_proceso', NULL, 'CC-2025-9110', 'yeiner', '3228758709', 'yeiner@gmail.com', 'media'),
(7, '', 'mi compañero de trabajo se mete a los baños de las mujeres a morbosear', 6.23575000, -75.58266900, '2025-08-25 00:00:00', '2025-08-28 02:59:57', 'resuelto', NULL, 'CC-2025-0894', 'eddy', '43132131', 'chalaalex26@gmail.com', 'media'),
(8, '', 'estoy notando que en las horas de la noche no hay personal de seguridad', 6.23575000, -75.57939200, '2025-08-26 00:00:00', '2025-09-12 00:11:19', 'resuelto', NULL, 'CC-2025-3835', 'Alex David', '3213311341', 'david@gmail.com', 'media'),
(9, '', 'el guardia de seguridad se mete a los baños de las mujeres a morbosearlas.', 6.23575000, -75.57939200, '2025-08-28 00:00:00', '2025-09-06 19:49:12', 'resuelto', NULL, 'CC-2025-8007', 'esney', '3213311341', 'esney@gmail.com', 'media'),
(10, '', 'en la nueva sede de la uniclaretiana hay muy poca seguridad', NULL, NULL, '2025-08-28 00:00:00', '2025-08-29 01:35:37', 'resuelto', NULL, 'CC-2025-3199', 'deiner', '3237899012', 'deiner@email.com', 'media'),
(11, '', 'en los ultimos dias han estado robando cerca de la empresa y hay poca seguridad', 5.69786800, -76.65064500, '2025-10-27 00:00:00', '2025-11-07 04:39:55', 'resuelto', NULL, 'CC-2025-9409', NULL, NULL, NULL, 'media'),
(12, '', 'estan talando arboles por tanando', 5.69786800, -76.65064500, '2025-11-06 00:00:00', NULL, 'pendiente', NULL, 'CC-2025-8976', NULL, NULL, NULL, 'media'),
(13, 'seguridad', 'estan talando arboles por condoto', 5.69786800, -76.65064500, '2025-11-06 00:00:00', '2025-11-07 04:46:13', 'archivado', 'resuelto', 'CC-2025-6152', NULL, NULL, NULL, 'media'),
(14, 'discriminacion', 'estan discriminando a un compañerode trabajo', 5.69786800, -76.65064500, '2025-11-04 00:00:00', '2025-11-07 04:31:13', 'en_proceso', NULL, 'CC-2025-1588', NULL, NULL, NULL, 'media');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `denuncia_actualizaciones`
--

CREATE TABLE `denuncia_actualizaciones` (
  `id` int(11) NOT NULL,
  `denuncia_id` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `responsable` varchar(100) DEFAULT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `denuncia_actualizaciones`
--

INSERT INTO `denuncia_actualizaciones` (`id`, `denuncia_id`, `descripcion`, `fecha`, `responsable`, `estado_anterior`, `estado_nuevo`) VALUES
(1, 2, 'Denuncia recibida y registrada en el sistema.', '2025-06-01 12:07:11', 'Sistema', NULL, NULL),
(2, 2, 'esta en proceso', '2025-06-01 12:34:18', 'Administrador', NULL, NULL),
(3, 2, 'ya esta resuelto', '2025-06-01 12:34:53', 'Administrador', NULL, NULL),
(4, 2, 'estamos anallizando los archivos', '2025-06-01 13:03:47', 'Administrador', NULL, NULL),
(5, 1, 'estamos anallizando los archivos', '2025-06-01 13:06:11', 'Administrador', NULL, NULL),
(6, 1, 'estamos investigando', '2025-06-01 13:12:41', 'Administrador', NULL, NULL),
(7, 2, 'jum', '2025-06-01 13:20:14', 'Administrador', NULL, NULL),
(8, 2, 'esta en proceso', '2025-06-01 13:29:35', 'Administrador', NULL, NULL),
(9, 3, 'Denuncia recibida y registrada en el sistema.', '2025-06-02 11:09:20', 'Sistema', NULL, NULL),
(10, 2, 'calma', '2025-06-03 19:12:09', 'Administrador', NULL, NULL),
(11, 4, 'Denuncia recibida y registrada en el sistema.', '2025-06-03 19:18:51', 'Sistema', NULL, NULL),
(12, 4, 'ya recibimos la informacion', '2025-06-03 19:20:35', 'Administrador', NULL, NULL),
(13, 5, 'Denuncia recibida y registrada en el sistema.', '2025-06-03 20:41:06', 'Sistema', NULL, NULL),
(14, 5, 'ya esta resuelto su denuncia', '2025-06-04 07:59:35', 'Administrador', NULL, NULL),
(15, 6, 'Denuncia recibida y registrada en el sistema.', '2025-06-04 12:24:59', 'Sistema', NULL, NULL),
(16, 6, 'estamos investigando', '2025-06-04 12:27:18', 'Administrador', NULL, NULL),
(17, 6, 'esta siendo procesada su denuncia', '2025-08-13 16:56:25', 'Administrador', NULL, NULL),
(18, 2, 'estamos anallizando los archivos y procesando su denuncia', '2025-08-13 16:56:54', 'Administrador', NULL, NULL),
(19, 6, 'ya esta resuelto su denuncia', '2025-08-13 16:58:37', 'Administrador', NULL, NULL),
(20, 4, 'resuelto', '2025-08-13 17:07:17', 'Administrador', NULL, NULL),
(21, 5, 'esta en proceso', '2025-08-13 17:15:17', 'Administrador', NULL, NULL),
(22, 5, 'estamos investigando', '2025-08-13 17:24:19', 'Administrador', NULL, NULL),
(23, 2, 'ya esta resuelto', '2025-08-13 17:24:46', 'Administrador', NULL, NULL),
(24, 6, 'ya esta resuelto su denuncia', '2025-08-13 17:30:59', 'Administrador', NULL, NULL),
(25, 2, 'ya esta resuelto', '2025-08-13 17:40:56', 'Administrador', NULL, NULL),
(26, 2, 'ya esta resuelto', '2025-08-13 17:41:16', 'Administrador', NULL, NULL),
(27, 6, 'estamos anallizando los archivos', '2025-08-13 17:41:30', 'Administrador', NULL, NULL),
(28, 7, 'Denuncia recibida y registrada en el sistema.', '2025-08-25 11:49:33', 'Sistema', NULL, NULL),
(29, 7, 'estamos investigando los hechos', '2025-08-25 12:00:31', 'Administrador', NULL, NULL),
(30, 7, 'estamos procesando la informacion', '2025-08-25 12:32:12', 'Administrador', 'pendiente', 'en_proceso'),
(31, 7, 'estamos procesando la informacion', '2025-08-25 12:32:14', 'Administrador', 'pendiente', 'en_proceso'),
(32, 7, 'estamos procesando la informacion', '2025-08-25 12:32:16', 'Administrador', 'pendiente', 'en_proceso'),
(33, 7, 'estamos procesando la informacion', '2025-08-25 12:32:18', 'Administrador', 'pendiente', 'en_proceso'),
(34, 7, 'estamos procesando la informacion', '2025-08-25 12:32:20', 'Administrador', 'pendiente', 'en_proceso'),
(35, 7, 'estamos procesando la informacion', '2025-08-25 12:32:23', 'Administrador', 'pendiente', 'en_proceso'),
(36, 7, 'estamos procesando la informacion', '2025-08-25 12:32:25', 'Administrador', 'pendiente', 'en_proceso'),
(37, 7, 'estamos procesando la informacion', '2025-08-25 12:32:27', 'Administrador', 'pendiente', 'en_proceso'),
(38, 7, 'estamos procesando la informacion', '2025-08-25 12:32:29', 'Administrador', 'pendiente', 'en_proceso'),
(39, 7, 'estamos procesando la informacion', '2025-08-25 12:32:31', 'Administrador', 'pendiente', 'en_proceso'),
(40, 7, 'estamos procesando la informacion', '2025-08-25 12:32:33', 'Administrador', 'pendiente', 'en_proceso'),
(41, 7, 'estamos procesando la informacion', '2025-08-25 12:32:36', 'Administrador', 'pendiente', 'en_proceso'),
(42, 7, 'estamos procesando la informacion', '2025-08-25 12:32:39', 'Administrador', 'pendiente', 'en_proceso'),
(43, 7, 'estamos procesando la informacion', '2025-08-25 12:32:41', 'Administrador', 'pendiente', 'en_proceso'),
(44, 7, 'estamos procesando la informacion', '2025-08-25 12:32:43', 'Administrador', 'pendiente', 'en_proceso'),
(45, 7, 'estamos procesando la informacion', '2025-08-25 12:32:45', 'Administrador', 'pendiente', 'en_proceso'),
(46, 7, 'estamos procesando la informacion', '2025-08-25 12:32:47', 'Administrador', NULL, NULL),
(47, 7, 'esta en proceso', '2025-08-27 21:41:24', 'Administrador', NULL, NULL),
(48, 7, 'ya esta resuelto su denuncia', '2025-08-27 21:59:57', 'Administrador', NULL, NULL),
(49, 8, 'Denuncia recibida y registrada en el sistema.', '2025-08-27 22:07:00', 'Sistema', NULL, NULL),
(50, 9, 'Denuncia recibida y registrada en el sistema.', '2025-08-27 22:23:27', 'Sistema', NULL, NULL),
(51, 9, 'estamos investigando', '2025-08-27 22:24:59', 'Administrador', NULL, NULL),
(52, 6, 'estamos anallizando los archivos', '2025-08-27 23:13:14', 'Administrador', NULL, NULL),
(53, 10, 'Denuncia recibida y registrada en el sistema.', '2025-08-28 09:00:13', 'Sistema', NULL, NULL),
(54, 10, 'ya esta resuelto', '2025-08-28 20:35:37', 'Administrador', NULL, NULL),
(55, 8, 'esta en proceso', '2025-08-28 20:44:42', 'Administrador', NULL, NULL),
(56, 9, 'ya esta resuelto su denuncia', '2025-09-06 14:49:12', 'Administrador', NULL, NULL),
(57, 5, 'estamos analizando las pruebas en estos momentos', '2025-09-11 19:01:20', 'Administrador', NULL, NULL),
(58, 8, 'ya esta resuelto su denuncia', '2025-09-11 19:11:19', 'Administrador', NULL, NULL),
(59, 11, 'Denuncia recibida y registrada en el sistema.', '2025-10-27 16:45:13', 'Sistema', NULL, NULL),
(60, 11, 'ya esta resuelto', '2025-10-27 17:07:48', 'Administrador', NULL, NULL),
(61, 12, 'Denuncia recibida y registrada en el sistema.', '2025-11-06 16:42:26', 'Sistema', NULL, NULL),
(62, 13, 'Denuncia recibida y registrada en el sistema.', '2025-11-06 16:53:38', 'Sistema', NULL, NULL),
(63, 14, 'Denuncia recibida y registrada en el sistema.', '2025-11-06 17:25:35', 'Sistema', NULL, NULL),
(64, 13, 'ya esta resuelto', '2025-11-06 17:56:32', 'Administrador', NULL, NULL),
(65, 14, 'estamos investigando', '2025-11-06 23:31:13', 'Administrador', NULL, NULL),
(66, 14, 'estamos investigando', '2025-11-06 23:37:44', 'Administrador', 'en_proceso', 'en_proceso'),
(67, 11, 'ARCHIVADO - Caso cerrado - Resuelto satisfactoriamente', '2025-11-06 23:38:49', 'Administrador', 'resuelto', 'archivado'),
(68, 11, 'DESARCHIVADO - Restaurado a: Resuelto', '2025-11-06 23:39:55', 'Administrador', 'archivado', 'resuelto'),
(69, 13, 'ARCHIVADO - Caso cerrado - Resuelto satisfactoriamente', '2025-11-06 23:46:13', 'Administrador', 'resuelto', 'archivado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `denuncia_fotos`
--

CREATE TABLE `denuncia_fotos` (
  `id` int(11) NOT NULL,
  `denuncia_id` int(11) NOT NULL,
  `foto_path` varchar(255) NOT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `denuncia_fotos`
--

INSERT INTO `denuncia_fotos` (`id`, `denuncia_id`, `foto_path`, `fecha_subida`) VALUES
(1, 4, 'uploads/evidencias/evidencia_4_0_1748996331.jpg', '2025-06-03 19:18:51'),
(2, 5, 'uploads/evidencias/evidencia_5_0_1749001266.jpg', '2025-06-03 20:41:06'),
(3, 6, 'uploads/evidencias/evidencia_6_0_1749057899.jpg', '2025-06-04 12:24:59'),
(4, 10, 'uploads/evidencias/evidencia_10_0_1756389613.jpg', '2025-08-28 09:00:13');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `fk_creado_por` (`creado_por`);

--
-- Indices de la tabla `admin_actividades`
--
ALTER TABLE `admin_actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_accion` (`accion`);

--
-- Indices de la tabla `admin_sesiones`
--
ALTER TABLE `admin_sesiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_activa` (`activa`);

--
-- Indices de la tabla `denuncias`
--
ALTER TABLE `denuncias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_seguimiento` (`codigo_seguimiento`),
  ADD KEY `idx_codigo_seguimiento` (`codigo_seguimiento`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_denuncias_estado` (`estado`),
  ADD KEY `idx_denuncias_fecha` (`fecha`);

--
-- Indices de la tabla `denuncia_actualizaciones`
--
ALTER TABLE `denuncia_actualizaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_denuncia_actualizaciones_denuncia_id` (`denuncia_id`);

--
-- Indices de la tabla `denuncia_fotos`
--
ALTER TABLE `denuncia_fotos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `denuncia_id` (`denuncia_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `admin_actividades`
--
ALTER TABLE `admin_actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `admin_sesiones`
--
ALTER TABLE `admin_sesiones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `denuncias`
--
ALTER TABLE `denuncias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `denuncia_actualizaciones`
--
ALTER TABLE `denuncia_actualizaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT de la tabla `denuncia_fotos`
--
ALTER TABLE `denuncia_fotos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD CONSTRAINT `fk_admin_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `admin_actividades`
--
ALTER TABLE `admin_actividades`
  ADD CONSTRAINT `fk_actividad_admin` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `admin_sesiones`
--
ALTER TABLE `admin_sesiones`
  ADD CONSTRAINT `fk_sesion_admin` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `denuncia_actualizaciones`
--
ALTER TABLE `denuncia_actualizaciones`
  ADD CONSTRAINT `denuncia_actualizaciones_ibfk_1` FOREIGN KEY (`denuncia_id`) REFERENCES `denuncias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `denuncia_fotos`
--
ALTER TABLE `denuncia_fotos`
  ADD CONSTRAINT `denuncia_fotos_ibfk_1` FOREIGN KEY (`denuncia_id`) REFERENCES `denuncias` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
