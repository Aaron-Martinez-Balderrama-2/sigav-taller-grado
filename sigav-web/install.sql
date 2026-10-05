-- ========================================================
-- SIGAV-UAGRM | Script de Instalación Completo
-- Ejecutar en phpMyAdmin o en la terminal de MySQL.
-- ========================================================
CREATE DATABASE IF NOT EXISTS uagrm_control_vehicular;
USE uagrm_control_vehicular;

-- ========================================================
-- 1. TABLA DE ROLES
-- ========================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL,
    puede_editar BOOLEAN DEFAULT FALSE,
    puede_eliminar BOOLEAN DEFAULT FALSE
);

INSERT INTO roles (nombre_rol, puede_editar, puede_eliminar) VALUES 
('Administrador', TRUE, TRUE),
('Guardia de Garita', FALSE, FALSE)
ON DUPLICATE KEY UPDATE nombre_rol = VALUES(nombre_rol);

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
    FOREIGN KEY (id_rol) REFERENCES roles(id)
);

-- Usuario Administrador por defecto
-- Usuario: admin | Contraseña: sigav2026
INSERT INTO usuarios (id_rol, nombres, usuario, password_hash) VALUES 
(1, 'Administrador SIGAV', 'admin', '$2y$10$4xO2.2veH5/wloQ3OW16BuPk5fztiq2hB6LhAEbHjUMR0DadrbeNK')
ON DUPLICATE KEY UPDATE nombres = VALUES(nombres);

-- ========================================================
-- 3. TABLA DEL PADRÓN VEHICULAR
-- ========================================================
CREATE TABLE IF NOT EXISTS padron_vehicular (
    placa VARCHAR(15) PRIMARY KEY,
    tipo_vehiculo VARCHAR(20),
    propietario VARCHAR(100) NOT NULL,
    tipo_propietario VARCHAR(50),
    facultad VARCHAR(100),
    estado_acceso ENUM('PERMITIDO', 'DENEGADO', 'LISTA_NEGRA') DEFAULT 'PERMITIDO',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ========================================================
-- 4. TABLA DE BITÁCORA FORENSE
-- ========================================================
CREATE TABLE IF NOT EXISTS bitacora_accesos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(15) NOT NULL,
    tipo_vehiculo_ia VARCHAR(20),
    confianza_ia DECIMAL(5,2),
    hora_ingreso DATETIME NOT NULL,
    puerta_ingreso VARCHAR(50) NOT NULL,
    evidencia_ingreso_foto VARCHAR(255),
    evidencia_ingreso_placa VARCHAR(255),
    hora_salida DATETIME NULL,
    puerta_salida VARCHAR(50) NULL,
    evidencia_salida_foto VARCHAR(255) NULL,
    alerta_disparada BOOLEAN DEFAULT FALSE
);

-- Índices de rendimiento
CREATE INDEX idx_placa_busqueda ON bitacora_accesos(placa);
CREATE INDEX idx_vehiculos_adentro ON bitacora_accesos(hora_salida);

-- ========================================================
-- 5. DATOS DE PRUEBA (Para la demostración)
-- ========================================================
-- Vehículos en el padrón
INSERT INTO padron_vehicular (placa, tipo_vehiculo, propietario, tipo_propietario, facultad, estado_acceso) VALUES
('233BTP', 'Auto', 'Juan Carlos Mendoza', 'Docente', 'Ingeniería', 'PERMITIDO'),
('4825ZHH', 'Auto', 'María López Quispe', 'Estudiante', 'Ciencias Económicas', 'PERMITIDO'),
('ABC1234', 'Moto', 'Roberto Fernández', 'Administrativo', 'Rectorado', 'PERMITIDO'),
('ROBADO1', 'Auto', 'VEHÍCULO REPORTADO', 'Desconocido', '-', 'LISTA_NEGRA')
ON DUPLICATE KEY UPDATE propietario = VALUES(propietario);

-- Algunos accesos de ejemplo
INSERT INTO bitacora_accesos (placa, tipo_vehiculo_ia, confianza_ia, hora_ingreso, puerta_ingreso, hora_salida, puerta_salida, alerta_disparada) VALUES
('233BTP', 'car', 98.50, NOW() - INTERVAL 3 HOUR, 'Puerta 1 - Av. Busch', NOW() - INTERVAL 1 HOUR, 'Puerta 3 - Av. 26 de Febrero', 0),
('4825ZHH', 'car', 95.20, NOW() - INTERVAL 2 HOUR, 'Puerta 2 - Av. Sanabria', NULL, NULL, 0),
('ABC1234', 'motorcycle', 88.70, NOW() - INTERVAL 30 MINUTE, 'Puerta 1 - Av. Busch', NULL, NULL, 0);
