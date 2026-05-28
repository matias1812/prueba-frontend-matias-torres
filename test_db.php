<?php
require_once __DIR__ . '/db.php';

try {
    // Si $pdo existe, la conexión funcionó y db.php creó la BD/tablas si hacía falta
    if (isset($pdo)) {
        echo "OK: conectado a " . DB_NAME . "\n";
    } else {
        echo "ERROR: \$pdo no definido\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
