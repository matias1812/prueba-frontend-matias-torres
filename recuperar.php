<?php
// recuperar.php - Simulación e implementación de recuperación de contraseña con SweetAlert2
require_once 'db.php';
require_once 'validaciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $alerta = [
            'type' => 'error',
            'title' => 'Solicitud Inválida',
            'text' => 'Token de seguridad inválido. Por favor recargue la página e intente de nuevo.'
        ];
    } else {
        $email = sanitizarEntrada($_POST['email'] ?? '');

        if (empty($email)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Correo Requerido',
            'text' => 'Por favor, ingrese su correo electrónico registrado.'
        ];
    } elseif (!validarEmail($email)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Correo Inválido',
            'text' => 'El formato del correo electrónico ingresado es incorrecto.'
        ];
    } else {
        try {
            // Verificar si el correo existe en la base de datos
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user_exists = $stmt->fetch();

            if ($user_exists) {
                // En una app real, generar un token seguro y enviarlo por email
                $alerta = [
                    'type' => 'success',
                    'title' => '¡Correo Enviado!',
                    'text' => 'Hemos enviado las instrucciones de recuperación a tu correo electrónico.',
                    'redirect' => 'login.php'
                ];
            } else {
                $alerta = [
                    'type' => 'error',
                    'title' => 'Correo No Registrado',
                    'text' => 'No encontramos ninguna cuenta asociada a este correo electrónico.'
                ];
            }
        } catch (PDOException $e) {
            $alerta = [
                'type' => 'error',
                'title' => 'Error de Servidor',
                'text' => 'No se pudo procesar la solicitud: ' . $e->getMessage()
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
  <meta name="description" content="Recupera tu contraseña en PNK Inmobiliaria.">
  <title>Recuperar Contraseña — PNK Inmobiliaria</title>
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
        <a href="login.php" class="btn-nav">Iniciar Sesión</a>
      </div>
    </div>
  </nav>

  <!-- ========== RECUPERAR FORM ========== -->
  <div class="form-wrapper">
    <div class="form-card" id="recuperarCard">
      <h2>Recuperar Contraseña</h2>
      <p class="form-subtitle">Ingresa tu correo electrónico y te enviaremos instrucciones para restablecer tu contraseña</p>

      <div class="alert alert-info">
        ℹ️ Recibirás un enlace de recuperación en tu bandeja de entrada.
      </div>

      <form id="recuperarForm" action="recuperar.php" method="POST">
        <?php echo csrfInput(); ?>
        <div class="form-group">
          <label for="recuperar-email">Correo Electrónico <span class="required">*</span></label>
          <input type="email" id="recuperar-email" name="email" class="form-control" placeholder="ejemplo@correo.com" required
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="btnRecuperar">Enviar Enlace de Recuperación</button>
      </form>

      <div class="form-footer">
        <p>¿Recordaste tu contraseña? <a href="login.php">Volver al Login</a></p>
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
