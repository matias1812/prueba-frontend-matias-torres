<?php
require_once 'db.php';
$email = 'admin@pnkinmobiliaria.cl';
$pass = 'damin1234';
try {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $email]);
    echo "UPDATED\n";

    // Verificar
    $s = $pdo->prepare('SELECT password_hash FROM usuarios WHERE email = ?');
    $s->execute([$email]);
    $h = $s->fetchColumn();
    echo password_verify($pass, $h) ? "VERIFIED\n" : "FAILED\n";
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
