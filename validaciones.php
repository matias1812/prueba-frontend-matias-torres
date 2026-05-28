<?php
// validaciones.php - Validaciones robustas del lado del servidor

/**
 * Valida un RUT chileno usando el algoritmo Módulo 11
 * Admite formatos con o sin puntos/guion (ej: 12.345.678-9, 12345678-9, 123456789)
 */
function validarRutChileno($rut) {
    // Eliminar todo lo que no sea número o k/K
    $rutLimpiado = preg_replace('/[^0-9kK]/', '', $rut);
    
    // Validar longitud mínima
    if (strlen($rutLimpiado) < 2) {
        return false;
    }
    
    $numero = substr($rutLimpiado, 0, -1);
    $dv = strtoupper(substr($rutLimpiado, -1));
    
    // Validar que el cuerpo sea numérico
    if (!is_numeric($numero)) {
        return false;
    }
    
    // Algoritmo Módulo 11
    $factor = 2;
    $suma = 0;
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += $numero[$i] * $factor;
        $factor = ($factor == 7) ? 2 : $factor + 1;
    }
    
    $resto = 11 - ($suma % 11);
    $dv_esperado = ($resto == 11) ? '0' : (($resto == 10) ? 'K' : (string)$resto);
    
    return $dv === $dv_esperado;
}

/**
 * Formatea un RUT al formato estándar 12.345.678-9
 */
function formatearRutChileno($rut) {
    $rutLimpiado = preg_replace('/[^0-9kK]/', '', $rut);
    if (strlen($rutLimpiado) < 2) {
        return $rut;
    }
    
    $cuerpo = substr($rutLimpiado, 0, -1);
    $dv = strtoupper(substr($rutLimpiado, -1));
    
    $cuerpoFormateado = number_format((int)$cuerpo, 0, ',', '.');
    return $cuerpoFormateado . '-' . $dv;
}

/**
 * Valida que una contraseña cumpla con los requisitos mínimos
 * En este caso: mínimo 8 caracteres
 */
function validarPassword($password) {
    return strlen($password) >= 8;
}

/**
 * Valida el formato del Correo Electrónico
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Inicia la sesión si no está activa.
 */
function iniciarSesionSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Genera/retorna un token CSRF seguro en sesión.
 */
function generarTokenCSRF() {
    iniciarSesionSegura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF enviado en formularios.
 */
function validarTokenCSRF($token) {
    iniciarSesionSegura();
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Devuelve un campo hidden con el token CSRF.
 */
function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Sanitiza entradas de texto para evitar ataques XSS
 */
function sanitizarEntrada($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
