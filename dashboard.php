<?php
// dashboard.php - Panel administrativo seguro con indicadores dinámicos (KPIs) de base de datos
require_once 'db.php';

$page_title = "Dashboard — PNK Inmobiliaria";
$page_description = "Panel administrativo de PNK Inmobiliaria.";
$section_title = "Dashboard";
$breadcrumb = "Inicio";

// Incluir la cabecera (esto verifica la sesión automáticamente)
require_once 'includes/header-admin.php';

// Consultas dinámicas a la Base de Datos para los KPIs
try {
    // 1. Usuarios Registrados (Total)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $stat_usuarios = $stmt->fetchColumn();

    // 2. Propiedades Activas
    $stmt = $pdo->query("SELECT COUNT(*) FROM propiedades");
    $stat_propiedades = $stmt->fetchColumn();

    // 3. Gestores Verificados (Activos)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'gestor-free' AND estado = 'activo'");
    $stat_gestores = $stmt->fetchColumn();

    // 4. Cuentas Pendientes (Administrador puede verlas, otros ven las propias si quisieran)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'pendiente'");
    $stat_pendientes = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Valores por defecto ante fallas en BD
    $stat_usuarios = 0;
    $stat_propiedades = 0;
    $stat_gestores = 0;
    $stat_pendientes = 0;
}
?>

<!-- Welcome Banner -->
<div class="welcome-banner" id="welcomeBanner">
  <h1>Bienvenido: <span style="color:var(--accent);"><?php echo htmlspecialchars($user_nombre); ?></span></h1>
  <p>
    <?php if ($user_rol === 'administrador'): ?>
      Administra usuarios, propiedades y el funcionamiento general de la plataforma PNK Inmobiliaria.
    <?php else: ?>
      Gestiona tus publicaciones inmobiliarias y revisa el estado de tu cuenta en PNK Inmobiliaria.
    <?php endif; ?>
  </p>
</div>

<!-- Stats (KPIs Dinámicos - Criterio 6) -->
<div class="stats-grid" id="statsGrid">
  <div class="stat-card">
    <div class="stat-icon blue">👥</div>
    <div class="stat-info">
      <div class="stat-number"><?php echo $stat_usuarios; ?></div>
      <div class="stat-label">Usuarios Registrados</div>
    </div>
  </div>
  
  <div class="stat-card">
    <div class="stat-icon gold">🏠</div>
    <div class="stat-info">
      <div class="stat-number"><?php echo $stat_propiedades; ?></div>
      <div class="stat-label">Propiedades Publicadas</div>
    </div>
  </div>
  
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-number"><?php echo $stat_gestores; ?></div>
      <div class="stat-label">Gestores Verificados</div>
    </div>
  </div>
  
  <div class="stat-card">
    <div class="stat-icon red">⏳</div>
    <div class="stat-info">
      <div class="stat-number"><?php echo $stat_pendientes; ?></div>
      <div class="stat-label">Cuentas Pendientes</div>
    </div>
  </div>
</div>

<!-- Quick Access -->
<h3 style="font-family:var(--font-body); font-size:1.1rem; font-weight:600; color:var(--gray-800); margin-top:var(--space-xl); margin-bottom:var(--space-lg);">
  Accesos Rápidos
</h3>
<div class="quick-access" id="quickAccess">
  <!-- Mostrar mantenedor de usuarios únicamente al Administrador -->
  <?php if ($user_rol === 'administrador'): ?>
  <a href="crud-usuarios.php" class="access-card" id="accessUsuarios">
    <div class="card-icon users-icon">👥</div>
    <h3>Mantenedor de Usuarios</h3>
    <p>Administra cuentas de Administradores, Propietarios y Gestores Inmobiliarios Free.</p>
  </a>
  <?php endif; ?>
  
  <a href="crud-propiedades.php" class="access-card" id="accessPropiedades">
    <div class="card-icon props-icon">🏢</div>
    <h3>Mantenedor de Propiedades</h3>
    <p>Publica, edita y elimina propiedades. Gestiona fotos, precios y características.</p>
  </a>
</div>

<?php
// Incluir el pie de página
require_once 'includes/footer-admin.php';
?>
