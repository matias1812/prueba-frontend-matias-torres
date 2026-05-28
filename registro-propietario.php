<?php
// registro-propietario.php - Registro de Propietarios con validación en servidor e interactividad con SweetAlert2
require_once 'db.php';
require_once 'validaciones.php';

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $alerta = [
            'type' => 'error',
            'title' => 'Solicitud Inválida',
            'text' => 'Token de seguridad inválido. Por favor recargue la página e intente de nuevo.'
        ];
    } else {
        // Sanitizar y recibir entradas
        $rut = sanitizarEntrada($_POST['rut'] ?? '');
        $nombre = sanitizarEntrada($_POST['nombre'] ?? '');
        $fecha_nacimiento = sanitizarEntrada($_POST['fecha_nacimiento'] ?? '');
        $sexo = sanitizarEntrada($_POST['sexo'] ?? '');
        $email = sanitizarEntrada($_POST['email'] ?? '');
        $telefono = sanitizarEntrada($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validaciones estrictas del lado del servidor (Criterio 2 y 7)
    if (empty($rut) || empty($nombre) || empty($fecha_nacimiento) || empty($sexo) || empty($email) || empty($telefono) || empty($password)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Campos Incompletos',
            'text' => 'Por favor, complete todos los campos marcados con asterisco (*).'
        ];
    } elseif (!validarRutChileno($rut)) {
        $alerta = [
            'type' => 'error',
            'title' => 'RUT Inválido',
            'text' => 'El RUT chileno ingresado es incorrecto o tiene dígito verificador inválido.'
        ];
    } elseif (!validarEmail($email)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Correo Inválido',
            'text' => 'El formato del correo electrónico ingresado es incorrecto.'
        ];
    } elseif (!validarPassword($password)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Contraseña Corta',
            'text' => 'La contraseña debe tener un largo mínimo de 8 caracteres.'
        ];
    } else {
        // Formatear RUT consistentemente a 12.345.678-9
        $rut_formateado = formatearRutChileno($rut);

        try {
            // Verificar si el RUT ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = ?");
            $stmt->execute([$rut_formateado]);
            if ($stmt->fetch()) {
                $alerta = [
                    'type' => 'error',
                    'title' => 'RUT Registrado',
                    'text' => 'El RUT ingresado ya se encuentra registrado en el sistema.'
                ];
            } else {
                // Verificar si el correo ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $alerta = [
                        'type' => 'error',
                        'title' => 'Correo Registrado',
                        'text' => 'La dirección de correo electrónico ya está registrada.'
                    ];
                } else {
                    // Cifrar la contraseña
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);

                    // Insertar Propietario en la base de datos (estado inicial 'pendiente')
                    $stmt = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, sexo, email, telefono, password_hash, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'propietario', 'pendiente')");
                    $stmt->execute([$rut_formateado, $nombre, $fecha_nacimiento, $sexo, $email, $telefono, $password_hash]);

                    $alerta = [
                        'type' => 'success',
                        'title' => '¡Registro Exitoso!',
                        'text' => 'Tu cuenta de Propietario ha sido creada. Quedará en estado "Pendiente" hasta que un administrador la apruebe.',
                        'redirect' => 'login.php'
                    ];
                }
            }
        } catch (PDOException $e) {
            $alerta = [
                'type' => 'error',
                'title' => 'Error de Servidor',
                'text' => 'Hubo un inconveniente al guardar los datos: ' . $e->getMessage()
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
  <meta name="description" content="Regístrate como propietario en PNK Inmobiliaria para publicar tus inmuebles.">
  <title>Registro Propietario — PNK Inmobiliaria</title>
  <link rel="stylesheet" href="styles.css">
  <!-- SweetAlert2 para interacciones premium (Criterio 8) -->
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
        <a href="registro-propietario.php" class="active">Registro Propietario</a>
        <a href="registro-gestor.php">Registro Gestor</a>
        <a href="dashboard.php">Panel Admin</a>
        <a href="contacto.php">Contacto</a>
        <a href="login.php" class="btn-nav">Iniciar Sesión</a>
      </div>
    </div>
  </nav>

  <!-- ========== REGISTRO PROPIETARIO ========== -->
  <div class="form-wrapper">
    <div class="form-card wide" id="registroPropietarioCard">
      <h2>Registro de Propietario</h2>
      <p class="form-subtitle">Completa tus datos para crear tu cuenta como propietario</p>

      <div class="alert alert-warning">
        ⚠️ Tu cuenta quedará en estado <strong>"Pendiente"</strong> hasta que un administrador la apruebe.
      </div>

      <form id="formRegistroPropietario" action="registro-propietario.php" method="POST">
        <?php echo csrfInput(); ?>
        <div class="form-row">
          <div class="form-group">
            <label for="prop-rut">RUT <span class="required">*</span></label>
            <!-- Mantenemos el campo y agregamos clase para validación JS -->
            <input type="text" id="prop-rut" name="rut" class="form-control" placeholder="12.345.678-9" required 
                   value="<?php echo isset($_POST['rut']) ? htmlspecialchars($_POST['rut']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="prop-nombre">Nombre Completo <span class="required">*</span></label>
            <input type="text" id="prop-nombre" name="nombre" class="form-control" placeholder="Juan Pérez López" required
                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="prop-nacimiento">Fecha de Nacimiento <span class="required">*</span></label>
            <input type="date" id="prop-nacimiento" name="fecha_nacimiento" class="form-control" required
                   value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="prop-sexo">Sexo <span class="required">*</span></label>
            <select id="prop-sexo" name="sexo" class="form-control" required>
              <option value="">Seleccionar...</option>
              <option value="masculino" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
              <option value="femenino" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
              <option value="otro" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] == 'otro') ? 'selected' : ''; ?>>Otro</option>
              <option value="no-especifica" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] == 'no-especifica') ? 'selected' : ''; ?>>Prefiero no especificar</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="prop-email">Correo Electrónico <span class="required">*</span></label>
            <input type="email" id="prop-email" name="email" class="form-control" placeholder="correo@ejemplo.com" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="prop-telefono">Teléfono Móvil <span class="required">*</span></label>
            <input type="tel" id="prop-telefono" name="telefono" class="form-control" placeholder="+56 9 1234 5678" required
                   value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="prop-password">Contraseña <span class="required">*</span></label>
          <input type="password" id="prop-password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="8">
        </div>

        <button type="submit" class="btn btn-accent btn-block" id="btnRegistroProp">Crear Cuenta de Propietario</button>
      </form>

      <div class="form-footer">
        <p>¿Ya tienes cuenta? <a href="login.php">Iniciar Sesión</a></p>
        <p style="margin-top: 6px;">¿Eres gestor inmobiliario? <a href="registro-gestor.php">Regístrate como Gestor</a></p>
      </div>
    </div>
  </div>

  <!-- Scripts de Validación Front-End -->
  <script src="validaciones.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enlazar validador de RUT dinámico en tiempo real (Criterio 7)
        aplicarValidacionRut('prop-rut');
        
        // Validación interactiva antes de enviar el formulario
        const form = document.getElementById('formRegistroPropietario');
        form.addEventListener('submit', function(e) {
            const rut = document.getElementById('prop-rut').value;
            const pass = document.getElementById('prop-password').value;
            
            if (!validarRutChileno(rut)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Formulario Inválido',
                    text: 'Por favor, ingrese un RUT chileno válido antes de continuar.',
                    confirmButtonColor: 'var(--accent, #e056fd)'
                });
                return;
            }
            
            if (pass.length < 8) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Contraseña Débil',
                    text: 'La contraseña debe contener al menos 8 caracteres.',
                    confirmButtonColor: 'var(--accent, #e056fd)'
                });
                return;
            }
        });
    });
  </script>

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
