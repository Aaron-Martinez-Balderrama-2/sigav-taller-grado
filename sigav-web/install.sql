-- ========================================================
-- SIGAV-UAGRM | Script de Instalación Completo
-- Base de Datos: uagrm_control_vehicular
-- Compatible con MariaDB / MySQL y phpMyAdmin (UTF-8)
-- ========================================================

CREATE DATABASE IF NOT EXISTS uagrm_control_vehicular
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uagrm_control_vehicular;

SET FOREIGN_KEY_CHECKS = 0;

-- ========================================================
-- 1. TABLA DE ROLES
-- ========================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL,
    puede_editar BOOLEAN DEFAULT FALSE,
    puede_eliminar BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id, nombre_rol, puede_editar, puede_eliminar) VALUES 
(1, 'Administrador', TRUE, TRUE),
(2, 'Guardia de Garita', FALSE, FALSE)
ON DUPLICATE KEY UPDATE nombre_rol = VALUES(nombre_rol),
                        puede_editar = VALUES(puede_editar),
                        puede_eliminar = VALUES(puede_eliminar);

-- ========================================================
-- 2. TABLA DE USUARIOS
-- ========================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_rol INT NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    estado BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_usuarios_roles FOREIGN KEY (id_rol) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Credenciales por defecto:
-- Usuario: admin | Contraseña: sigav2026
INSERT INTO usuarios (id, id_rol, nombres, usuario, password_hash, estado) VALUES 
(1, 1, 'Administrador SIGAV', 'admin', '$2y$10$4xO2.2veH5/wloQ3OW16BuPk5fztiq2hB6LhAEbHjUMR0DadrbeNK', 1)
ON DUPLICATE KEY UPDATE nombres = VALUES(nombres),
                        password_hash = VALUES(password_hash),
                        estado = VALUES(estado);

-- ========================================================
-- 3. TABLA DEL PADRÓN VEHICULAR
-- ========================================================
CREATE TABLE IF NOT EXISTS padron_vehicular (
    placa VARCHAR(15) PRIMARY KEY,
    tipo_vehiculo VARCHAR(20) DEFAULT NULL,
    color VARCHAR(30) DEFAULT NULL,
    propietario VARCHAR(100) NOT NULL,
    tipo_propietario VARCHAR(50) DEFAULT NULL,
    facultad VARCHAR(100) DEFAULT NULL,
    estado_acceso ENUM('PERMITIDO', 'DENEGADO', 'LISTA_NEGRA') DEFAULT 'PERMITIDO',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO padron_vehicular (placa, tipo_vehiculo, color, propietario, tipo_propietario, facultad, estado_acceso, fecha_registro) VALUES
('233BTP', 'Auto', 'Azul', 'Juan Carlos Mendoza', 'Docente', 'Ingeniería', 'PERMITIDO', '2026-10-02 03:50:03'),
('4825ZHH', 'Auto', 'Blanco', 'María López Quispe', 'Estudiante', 'Ciencias Económicas', 'PERMITIDO', '2026-10-02 03:50:03'),
('ABC1234', 'Moto', 'Negro', 'Roberto Fernández', 'Administrativo', 'Rectorado', 'PERMITIDO', '2026-10-02 03:50:03'),
('ROBADO1', 'Auto', NULL, 'VEHÍCULO REPORTADO', 'Desconocido', '-', 'LISTA_NEGRA', '2026-10-02 03:50:03')
ON DUPLICATE KEY UPDATE tipo_vehiculo = VALUES(tipo_vehiculo),
                        color = VALUES(color),
                        propietario = VALUES(propietario),
                        tipo_propietario = VALUES(tipo_propietario),
                        facultad = VALUES(facultad),
                        estado_acceso = VALUES(estado_acceso);

-- ========================================================
-- 4. TABLA DE BITÁCORA FORENSE
-- ========================================================
CREATE TABLE IF NOT EXISTS bitacora_accesos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(15) NOT NULL,
    tipo_vehiculo_ia VARCHAR(20) DEFAULT NULL,
    color_vehiculo VARCHAR(30) DEFAULT NULL,
    confianza_ia DECIMAL(5,2) DEFAULT NULL,
    hora_ingreso DATETIME NOT NULL,
    puerta_ingreso VARCHAR(50) NOT NULL,
    evidencia_ingreso_foto VARCHAR(255) DEFAULT NULL,
    evidencia_ingreso_placa VARCHAR(255) DEFAULT NULL,
    hora_salida DATETIME DEFAULT NULL,
    puerta_salida VARCHAR(50) DEFAULT NULL,
    evidencia_salida_foto VARCHAR(255) DEFAULT NULL,
    -- Niveles de alerta (RF10): 0 = Permitido, 1 = No Empadronado, 2 = Denegado, 3 = Lista Negra
    alerta_disparada TINYINT DEFAULT 0,
    INDEX idx_placa_busqueda (placa),
    INDEX idx_vehiculos_adentro (hora_salida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bitacora_accesos (id, placa, tipo_vehiculo_ia, color_vehiculo, confianza_ia, hora_ingreso, puerta_ingreso, evidencia_ingreso_foto, evidencia_ingreso_placa, hora_salida, puerta_salida, evidencia_salida_foto, alerta_disparada) VALUES
(1, '233BTP', 'car', 'Azul', 98.50, '2026-10-02 00:50:03', 'Puerta 1 - Av. Busch', 'evidencia_1_ingreso.jpg', 'evidencia_1_placa.jpg', '2026-10-02 02:50:03', 'Puerta 3 - Av. 26 de Febrero', 'evidencia_1_salida.jpg', 0),
(2, '4825ZHH', 'car', 'Blanco', 95.20, '2026-10-02 01:50:03', 'Puerta 2 - Av. Sanabria', 'evidencia_2_ingreso.jpg', 'evidencia_2_placa.jpg', NULL, NULL, NULL, 0),
(3, 'ABC1234', 'motorcycle', 'Negro', 88.70, '2026-10-02 03:20:03', 'Puerta 1 - Av. Busch', 'evidencia_3_ingreso.jpg', 'evidencia_3_placa.jpg', NULL, NULL, NULL, 0)
ON DUPLICATE KEY UPDATE placa = VALUES(placa),
                        tipo_vehiculo_ia = VALUES(tipo_vehiculo_ia),
                        color_vehiculo = VALUES(color_vehiculo),
                        confianza_ia = VALUES(confianza_ia),
                        hora_ingreso = VALUES(hora_ingreso),
                        puerta_ingreso = VALUES(puerta_ingreso),
                        evidencia_ingreso_foto = VALUES(evidencia_ingreso_foto),
                        evidencia_ingreso_placa = VALUES(evidencia_ingreso_placa),
                        hora_salida = VALUES(hora_salida),
                        puerta_salida = VALUES(puerta_salida),
                        evidencia_salida_foto = VALUES(evidencia_salida_foto),
                        alerta_disparada = VALUES(alerta_disparada);

-- ========================================================
-- 5. TABLA DE CÁMARAS DE VIDEO
-- ========================================================
CREATE TABLE IF NOT EXISTS camaras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ip_url VARCHAR(255) NOT NULL,
    sentido ENUM('ingreso', 'salida') NOT NULL,
    activa BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
