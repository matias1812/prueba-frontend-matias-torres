<?php
// includes/header-admin.php - Fragmento de cabecera seguro y dinámico para el panel de administración
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteger la vista privada: Redirigir al login si no hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nombre = $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol = $_SESSION['user_rol'] ?? 'Usuario';
$user_email = $_SESSION['user_email'] ?? '';

// Obtener iniciales para el avatar de forma dinámica
$palabras = explode(" ", trim($user_nombre));
$iniciales = "";
if (count($palabras) >= 2) {
    $iniciales = strtoupper(substr($palabras[0], 0, 1) . substr($palabras[1], 0, 1));
} else if (count($palabras) >= 1 && !empty($palabras[0])) {
    $iniciales = strtoupper(substr($palabras[0], 0, 2));
} else {
    $iniciales = "US";
}

// Formatear el rol para mostrarlo de forma elegante en el frontend
$rol_legible = 'Usuario';
if ($user_rol === 'administrador') {
    $rol_legible = 'Administrador';
} elseif ($user_rol === 'propietario') {
    $rol_legible = 'Propietario';
} elseif ($user_rol === 'gestor-free') {
    $rol_legible = 'Gestor Free';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Mantenedor — PNK Inmobiliaria.'; ?>">
  <title><?php echo isset($page_title) ? $page_title : 'Panel de Administración — PNK Inmobiliaria'; ?></title>
  <link rel="stylesheet" href="styles.css">
  <!-- SweetAlert2 para notificaciones e interacciones elegantes (Criterio 8 de la rúbrica) -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="dashboard-layout" id="dashboardLayout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo-icon">PNK</div>
        <span>PNK Inmobiliaria</span>
      </div>
      <nav class="sidebar-nav" id="sidebarNav">
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><span class="nav-icon">🏠</span> Dashboard</a>
        
        <!-- Restringir mantenedor de usuarios solo a Administradores -->
        <?php if ($user_rol === 'administrador'): ?>
        <a href="crud-usuarios.php" class="<?php echo $current_page == 'crud-usuarios.php' ? 'active' : ''; ?>"><span class="nav-icon">👥</span> Mantenedor de Usuarios</a>
        <?php endif; ?>
        
        <!-- Acceso al mantenedor de propiedades para todos los logueados -->
        <a href="crud-propiedades.php" class="<?php echo $current_page == 'crud-propiedades.php' ? 'active' : ''; ?>"><span class="nav-icon">🏢</span> Mantenedor de Propiedades</a>
        
        <a href="index.php"><span class="nav-icon">🌐</span> Ver Sitio Web</a>
      </nav>
      <div class="sidebar-footer">
        <div class="sidebar-user">
          <div class="avatar"><?php echo $iniciales; ?></div>
          <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($user_nombre); ?></div>
            <div class="role"><?php echo htmlspecialchars($rol_legible); ?></div>
          </div>
        </div>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="dashboard-main" id="dashboardMain">
      <div class="dashboard-topbar" id="topbar">
        <div class="topbar-left">
          <h2><?php echo isset($section_title) ? $section_title : 'Panel de Control'; ?></h2>
          <span class="breadcrumb">Panel Admin / <?php echo isset($breadcrumb) ? $breadcrumb : 'Dashboard'; ?></span>
        </div>
        <div class="topbar-right">
          <!-- Cierre de sesión protegido con confirmación en SweetAlert2 -->
          <a href="#" class="btn-logout" id="btnCerrarSesion" onclick="confirmarCierreSesion(event)">🚪 Cerrar Sesión</a>
        </div>
      </div>
      
      <div class="dashboard-content" id="dashboardContent">
