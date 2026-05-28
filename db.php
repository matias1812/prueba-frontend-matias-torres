<?php
// db.php - Conexión centralizada a la base de datos con PDO y auto-seeding

// Configuraciones para AWS / Local Environment variables
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'pnk_inmobiliaria');

try {
    // Intentar conectar con la base de datos especificada
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Si la base de datos no existe (por ejemplo, primera ejecución en localhost)
    try {
        // Conectar sin especificar base de datos para intentar crearla
        $temp_pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
        $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Reintentar conexión con la base de datos recién creada
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $ex) {
        die("Error crítico de base de datos: " . $ex->getMessage());
    }
}

// ==========================================
// AUTO-CREACIÓN DE TABLAS SI NO EXISTEN
// ==========================================
// Esto garantiza que el sistema funcione en AWS o local aunque no se importe manualmente el schema.sql
try {
    // Tabla de Usuarios
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut VARCHAR(15) UNIQUE NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        fecha_nacimiento DATE NOT NULL,
        sexo VARCHAR(30) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        telefono VARCHAR(30) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        rol VARCHAR(30) NOT NULL,
        estado VARCHAR(20) DEFAULT 'pendiente',
        certificado_ruta VARCHAR(255) DEFAULT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Tabla de Propiedades
    $pdo->exec("CREATE TABLE IF NOT EXISTS propiedades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        fecha_publicacion DATE NOT NULL,
        descripcion TEXT NOT NULL,
        dormitorios INT NOT NULL DEFAULT 0,
        banos INT NOT NULL DEFAULT 0,
        area_terreno INT NOT NULL DEFAULT 0,
        area_construida INT NOT NULL DEFAULT 0,
        precio_clp BIGINT NOT NULL DEFAULT 0,
        precio_uf DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        amenidades TEXT DEFAULT NULL,
        creado_por INT NOT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Tabla de Fotos de Propiedades
    $pdo->exec("CREATE TABLE IF NOT EXISTS propiedad_fotos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        propiedad_id INT NOT NULL,
        foto_ruta VARCHAR(255) NOT NULL,
        fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // AUTO-SEEDING: Insertar Administrador si la tabla de usuarios está vacía
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    if ($stmt->fetchColumn() == 0) {
        $admin_rut = '12.345.678-9';
        $admin_nombre = 'Admin Torres';
        $admin_nacimiento = '1990-01-01';
        $admin_sexo = 'masculino';
        $admin_email = 'admin@pnkinmobiliaria.cl';
        $admin_telefono = '+56 9 1234 5678';
        // Hash de 'admin12345'
        $admin_hash = password_hash('admin12345', PASSWORD_BCRYPT);
        $admin_rol = 'administrador';
        $admin_estado = 'activo';

        $insert = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, sexo, email, telefono, password_hash, rol, estado) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([$admin_rut, $admin_nombre, $admin_nacimiento, $admin_sexo, $admin_email, $admin_telefono, $admin_hash, $admin_rol, $admin_estado]);
    }
} catch (PDOException $e) {
    // Si falla la inicialización automática de tablas, no bloqueamos la app, pero queda el registro
    error_log("Error inicializando tablas: " . $e->getMessage());
}
