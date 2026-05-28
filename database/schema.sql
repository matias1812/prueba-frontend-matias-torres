-- Base de datos para PNK Inmobiliaria
CREATE DATABASE IF NOT EXISTS pnk_inmobiliaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pnk_inmobiliaria;

-- 1. TABLA DE USUARIOS
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(15) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo VARCHAR(30) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(30) NOT NULL, -- 'administrador', 'propietario', 'gestor-free'
    estado VARCHAR(20) DEFAULT 'pendiente', -- 'activo', 'pendiente', 'inactivo'
    certificado_ruta VARCHAR(255) DEFAULT NULL, -- PDF o Imagen para Gestores
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. TABLA DE PROPIEDADES
CREATE TABLE IF NOT EXISTS propiedades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL, -- 'casa', 'departamento', 'terreno', 'oficina', 'local-comercial'
    fecha_publicacion DATE NOT NULL,
    descripcion TEXT NOT NULL,
    dormitorios INT NOT NULL DEFAULT 0,
    banos INT NOT NULL DEFAULT 0,
    area_terreno INT NOT NULL DEFAULT 0,
    area_construida INT NOT NULL DEFAULT 0,
    precio_clp BIGINT NOT NULL DEFAULT 0,
    precio_uf DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amenidades TEXT DEFAULT NULL, -- Comma-separated list (ej: "bodega,piscina") o JSON
    creado_por INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. TABLA DE FOTOS DE PROPIEDADES
CREATE TABLE IF NOT EXISTS propiedad_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT NOT NULL,
    foto_ruta VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. INSERTAR USUARIO ADMINISTRADOR DE PRUEBA
-- Credenciales:
-- RUT: 12.345.678-9
-- Email: admin@pnkinmobiliaria.cl
-- Contraseña: admin12345
-- Hash Bcrypt generado: $2y$10$d6h6K2kLw.5xI24wT7C91OXoA3/nJ0Vn7t1X5J6tH0L0oT0L0oT0L
INSERT INTO usuarios (rut, nombre, fecha_nacimiento, sexo, email, telefono, password_hash, rol, estado)
VALUES (
    '12.345.678-9',
    'Admin Torres',
    '1990-01-01',
    'masculino',
    'admin@pnkinmobiliaria.cl',
    '+56 9 1234 5678',
    '$2y$10$v7g9W9P.0vT7d.yN/QkUkuE1g2a4eM4n9fR3J.nQx47D4C3i1Hde.',
    'administrador',
    'activo'
) ON DUPLICATE KEY UPDATE id=id;
