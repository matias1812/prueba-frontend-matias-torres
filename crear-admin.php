<?php
// crear-admin.php - Inserta un administrador en la base de datos si no existe
require_once 'db.php';

// Parámetros: php crear-admin.php [email] [password] [rut] [nombre]
$email = $argv[1] ?? 'admin@pnkinmobiliaria.cl';
$password = $argv[2] ?? 'admin12345';
$rut = $argv[3] ?? '12.345.678-9';
$nombre = $argv[4] ?? 'Admin Torres';

try {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "Admin already exists: $email\n";
        exit(0);
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $insert = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, sexo, email, telefono, password_hash, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $rut,
        $nombre,
        '1990-01-01',
        'masculino',
        $email,
        '+56 9 1234 5678',
        $password_hash,
        'administrador',
        'activo'
    ]);

    echo "Admin created: $email\n";
} catch (PDOException $e) {
    echo "Error creating admin: " . $e->getMessage() . "\n";
    exit(1);
}
