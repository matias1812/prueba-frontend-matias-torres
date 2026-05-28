<?php
// login.php - Autenticación segura de usuarios con manejo de sesiones y SweetAlert2
require_once 'db.php';
require_once 'validaciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir al panel administrativo
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $alerta = [
            'type' => 'error',
            'title' => 'Solicitud Inválida',
            'text' => 'El token de seguridad no es válido. Por favor recarga la página e intenta de nuevo.'
        ];
    } else {
        $usuario_input = sanitizarEntrada($_POST['usuario'] ?? '');
        $password_input = $_POST['password'] ?? '';

        if (empty($usuario_input) || empty($password_input)) {
            $alerta = [
                'type' => 'error',
                'title' => 'Campos Vacíos',
                'text' => 'Por favor, ingrese sus credenciales de acceso.'
            ];
        } else {
        try {
            // Intentar formatear como RUT por si ingresaron un RUT
            $rut_formateado = formatearRutChileno($usuario_input);

            // Buscar por email o por RUT
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? OR rut = ?");
            $stmt->execute([$usuario_input, $rut_formateado]);
            $user = $stmt->fetch();

            if ($user && password_verify($password_input, $user['password_hash'])) {
                // Verificar el estado de la cuenta (Criterio 4 y 5)
                if ($user['estado'] === 'activo') {
                    // Establecer variables de sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nombre'] = $user['nombre'];
                    $_SESSION['user_rol'] = $user['rol'];
                    $_SESSION['user_email'] = $user['email'];

                    // Redirección exitosa con SweetAlert2
                    $alerta = [
                        'type' => 'success',
                        'title' => '¡Bienvenido de vuelta!',
                        'text' => 'Ingreso exitoso a PNK Inmobiliaria.',
                        'redirect' => 'dashboard.php'
                    ];
                } elseif ($user['estado'] === 'pendiente') {
                    $alerta = [
                        'type' => 'warning',
                        'title' => 'Cuenta Pendiente',
                        'text' => 'Tu cuenta aún está en revisión. Un administrador debe verificar tus antecedentes.'
                    ];
                } else { // inactivo
                    $alerta = [
                        'type' => 'error',
                        'title' => 'Cuenta Inactiva',
                        'text' => 'Tu cuenta se encuentra deshabilitada. Contacta al administrador.'
                    ];
                }
            } else {
                // Credenciales incorrectas
                $alerta = [
                    'type' => 'error',
                    'title' => 'Acceso Denegado',
                    'text' => 'Usuario o contraseña incorrectos. Inténtalo de nuevo.'
                ];
            }
        } catch (PDOException $e) {
            $alerta = [
                'type' => 'error',
                'title' => 'Error de Base de Datos',
                'text' => 'No se pudo conectar al servidor: ' . $e->getMessage()
            ];
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Inicia sesión en PNK Inmobiliaria para acceder a tu panel administrativo.">
  <title>Iniciar Sesión — PNK Inmobiliaria</title>
  <link rel="stylesheet" href="styles.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="form-page">

  <!-- ========== NAVBAR ========== -->
  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="index.php" class="navbar-brand">
        <div class="logo-icon">PNK</div>
        <span>PNK <span class="brand-accent">Inmobiliaria</span></span>
      </a>
      <div class="navbar-toggle" onclick="document.getElementById('navMenu').classList.toggle('show')">
        <span></span><span></span><span></span>
      </div>
      <div class="navbar-menu" id="navMenu">
        <a href="index.php">Inicio</a>
        <a href="registro-propietario.php">Registro Propietario</a>
        <a href="registro-gestor.php">Registro Gestor</a>
        <a href="dashboard.php">Panel Admin</a>
        <a href="contacto.php">Contacto</a>
        <a href="login.php" class="btn-nav active">Iniciar Sesión</a>
      </div>
    </div>
  </nav>

  <!-- ========== LOGIN FORM ========== -->
  <div class="form-wrapper">
    <div class="form-card" id="loginCard">
      <h2>Bienvenido de vuelta</h2>
      <p class="form-subtitle">Ingresa tus credenciales para acceder al panel</p>

      <form id="loginForm" action="login.php" method="POST">
        <?php echo csrfInput(); ?>
        <div class="form-group">
          <label for="login-usuario">Usuario / Correo / RUT <span class="required">*</span></label>
          <input type="text" id="login-usuario" name="usuario" class="form-control" placeholder="12.345.678-9 o correo@mail.com" required
                 value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
        </div>

        <div class="form-group">
          <label for="login-password">Contraseña <span class="required">*</span></label>
          <input type="password" id="login-password" name="password" class="form-control" placeholder="Ingresa tu contraseña" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="btnIngresar">Ingresar</button>
      </form>

      <div class="form-footer">
        <p>¿Olvidaste tu contraseña? <a href="recuperar.php" id="linkRecuperar">Recuperar Contraseña</a></p>
        <p style="margin-top: 8px;">¿No tienes cuenta? <a href="registro-propietario.php">Regístrate aquí</a></p>
      </div>
    </div>
  </div>

  <!-- SweetAlert2 Backend Injector (Criterio 8) -->
  <?php if ($alerta): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?php echo $alerta['type']; ?>',
            title: '<?php echo addslashes($alerta['title']); ?>',
            text: '<?php echo addslashes($alerta['text']); ?>',
            confirmButtonColor: 'var(--accent, #e056fd)',
            background: 'var(--card-bg, #ffffff)',
            color: 'var(--text-main, #2d3748)'
        })<?php echo isset($alerta['redirect']) ? ".then(function() { window.location.href = '" . $alerta['redirect'] . "'; })" : ""; ?>;
    });
  </script>
  <?php endif; ?>

</body>
</html>
