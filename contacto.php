<?php
// contacto.php - Formulario de Contacto interactivo con SweetAlert2
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
        $nombre = sanitizarEntrada($_POST['nombre'] ?? '');
        $email = sanitizarEntrada($_POST['email'] ?? '');
        $asunto = sanitizarEntrada($_POST['asunto'] ?? '');
        $mensaje = sanitizarEntrada($_POST['mensaje'] ?? '');

        if (empty($nombre) || empty($email) || empty($asunto) || empty($mensaje)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Campos Faltantes',
            'text' => 'Por favor, complete todos los campos marcados con asterisco (*).'
        ];
    } elseif (!validarEmail($email)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Correo Inválido',
            'text' => 'El formato del correo electrónico ingresado es incorrecto.'
        ];
    } else {
        // En una aplicación real, aquí se enviaría un correo vía mail() o phpmailer, 
        // y se podría registrar en la base de datos. Simulamos éxito completo.
        $alerta = [
            'type' => 'success',
            'title' => '¡Mensaje Enviado!',
            'text' => 'Gracias por escribirnos. Nuestro equipo se pondrá en contacto contigo a la brevedad.',
            'redirect' => 'index.php'
        ];
    }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Contacta al equipo de PNK Inmobiliaria.">
  <title>Contacto — PNK Inmobiliaria</title>
  <link rel="stylesheet" href="styles.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

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
        <a href="contacto.php" class="active">Contacto</a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="dashboard.php" class="btn-nav" style="background:var(--accent); color:#fff;">Mi Panel Admin</a>
        <?php else: ?>
          <a href="login.php" class="btn-nav">Iniciar Sesión</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- ========== CONTACTO ========== -->
  <section class="contact-section" id="contactSection">
    <div class="container">
      <h2 class="section-title text-center">Contáctenos</h2>
      <p class="section-subtitle text-center">Estamos aquí para ayudarte. Envíanos un mensaje y te responderemos a la brevedad.</p>

      <div class="contact-grid">
        <!-- Info Card -->
        <div class="contact-info-card" id="contactInfo">
          <h3>Información de Contacto</h3>
          <p>No dudes en comunicarte con nosotros por cualquiera de estos medios.</p>

          <div class="contact-info-item">
            <div class="icon">📍</div>
            <div class="details">
              <h4>Dirección</h4>
              <p>Av. Libertador Bernardo O'Higgins 1234, Santiago, Chile</p>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="icon">📞</div>
            <div class="details">
              <h4>Teléfono</h4>
              <p>+56 9 1234 5678</p>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="icon">📧</div>
            <div class="details">
              <h4>Correo Electrónico</h4>
              <p>info@pnkinmobiliaria.cl</p>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="icon">🕐</div>
            <div class="details">
              <h4>Horario de Atención</h4>
              <p>Lunes a Viernes: 09:00 — 18:00 hrs</p>
            </div>
          </div>
        </div>

        <!-- Contact Form -->
        <div class="contact-form-card" id="contactForm">
          <h3>Envíanos un Mensaje</h3>
          <form action="contacto.php" method="POST" id="formContacto">
            <?php echo csrfInput(); ?>
            <div class="form-row">
              <div class="form-group">
                <label for="contact-nombre">Nombre <span class="required">*</span></label>
                <input type="text" id="contact-nombre" name="nombre" class="form-control" placeholder="Tu nombre" required
                       value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
              </div>
              <div class="form-group">
                <label for="contact-email">Correo <span class="required">*</span></label>
                <input type="email" id="contact-email" name="email" class="form-control" placeholder="correo@ejemplo.com" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="contact-asunto">Asunto <span class="required">*</span></label>
              <input type="text" id="contact-asunto" name="asunto" class="form-control" placeholder="¿En qué podemos ayudarte?" required
                     value="<?php echo isset($_POST['asunto']) ? htmlspecialchars($_POST['asunto']) : ''; ?>">
            </div>

            <div class="form-group">
              <label for="contact-mensaje">Mensaje <span class="required">*</span></label>
              <textarea id="contact-mensaje" name="mensaje" class="form-control" rows="5" placeholder="Escribe tu mensaje aquí..." required><?php echo isset($_POST['mensaje']) ? htmlspecialchars($_POST['mensaje']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="btnEnviarContacto">Enviar Mensaje</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- ========== FOOTER ========== -->
  <footer class="footer" id="footer">
    <div class="container">
      <div class="footer-bottom">
        <span>© 2025 PNK Inmobiliaria. Todos los derechos reservados.</span>
        <div class="social-links">
          <a href="#" aria-label="Facebook">📘</a>
          <a href="#" aria-label="Instagram">📷</a>
          <a href="#" aria-label="LinkedIn">💼</a>
        </div>
      </div>
    </div>
  </footer>

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
