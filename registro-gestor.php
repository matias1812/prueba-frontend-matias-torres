<?php
// registro-gestor.php - Registro de Gestores con subida de archivo y SweetAlert2
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
        // Sanitizar y recibir entradas de texto
        $rut = sanitizarEntrada($_POST['rut'] ?? '');
        $nombre = sanitizarEntrada($_POST['nombre'] ?? '');
        $fecha_nacimiento = sanitizarEntrada($_POST['fecha_nacimiento'] ?? '');
        $sexo = sanitizarEntrada($_POST['sexo'] ?? '');
        $email = sanitizarEntrada($_POST['email'] ?? '');
        $telefono = sanitizarEntrada($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validaciones estrictas del lado del servidor (Criterio 3 y 7)
    if (empty($rut) || empty($nombre) || empty($fecha_nacimiento) || empty($sexo) || empty($email) || empty($telefono) || empty($password)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Campos Incompletos',
            'text' => 'Por favor, complete todos los campos obligatorios.'
        ];
    } elseif (!validarRutChileno($rut)) {
        $alerta = [
            'type' => 'error',
            'title' => 'RUT Inválido',
            'text' => 'El RUT chileno ingresado no es válido.'
        ];
    } elseif (!validarEmail($email)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Correo Inválido',
            'text' => 'El formato del correo electrónico es incorrecto.'
        ];
    } elseif (!validarPassword($password)) {
        $alerta = [
            'type' => 'error',
            'title' => 'Contraseña Corta',
            'text' => 'La contraseña debe tener al menos 8 caracteres.'
        ];
    } elseif (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
        $alerta = [
            'type' => 'error',
            'title' => 'Archivo Faltante',
            'text' => 'El certificado de antecedentes es obligatorio para registrarse.'
        ];
    } else {
        $rut_formateado = formatearRutChileno($rut);

        try {
            // Verificar si el RUT ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = ?");
            $stmt->execute([$rut_formateado]);
            if ($stmt->fetch()) {
                $alerta = [
                    'type' => 'error',
                    'title' => 'RUT Registrado',
                    'text' => 'El RUT ingresado ya se encuentra registrado.'
                ];
            } else {
                // Verificar si el correo ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $alerta = [
                        'type' => 'error',
                        'title' => 'Correo Registrado',
                        'text' => 'Este correo electrónico ya está registrado.'
                    ];
                } else {
                    // Procesar y validar la subida del archivo (Criterio 3)
                    $file = $_FILES['certificado'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
                    $max_size = 5 * 1024 * 1024; // 5 Megabytes

                    if (!in_array($ext, $allowed_exts)) {
                        $alerta = [
                            'type' => 'error',
                            'title' => 'Formato Inválido',
                            'text' => 'Solo se admiten archivos en formato PDF, JPG, JPEG o PNG.'
                        ];
                    } elseif ($file['size'] > $max_size) {
                        $alerta = [
                            'type' => 'error',
                            'title' => 'Archivo Excedido',
                            'text' => 'El tamaño del archivo no puede superar los 5 MB.'
                        ];
                    } else {
                        // Crear directorio de certificados si no existe (Criterio 9, estable para AWS)
                        $upload_dir = 'uploads/certificados/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // Generar nombre de archivo único para evitar sobreescritura y sanitizar
                        $filename = uniqid('cert_', true) . '.' . $ext;
                        $dest_path = $upload_dir . $filename;

                        if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                            // Cifrar clave
                            $password_hash = password_hash($password, PASSWORD_BCRYPT);

                            // Insertar Gestor en base de datos con estado 'pendiente' y ruta del certificado
                            $stmt = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, sexo, email, telefono, password_hash, rol, estado, certificado_ruta) VALUES (?, ?, ?, ?, ?, ?, ?, 'gestor-free', 'pendiente', ?)");
                            $stmt->execute([$rut_formateado, $nombre, $fecha_nacimiento, $sexo, $email, $telefono, $password_hash, $dest_path]);

                            $alerta = [
                                'type' => 'success',
                                'title' => '¡Registro Exitoso!',
                                'text' => 'Tu cuenta de Gestor ha sido creada. Quedará en estado "Pendiente" hasta que validemos tu certificado de antecedentes.',
                                'redirect' => 'login.php'
                            ];
                        } else {
                            $alerta = [
                                'type' => 'error',
                                'title' => 'Error de Servidor',
                                'text' => 'No fue posible guardar el archivo certificado. Revisa los permisos en AWS.'
                            ];
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $alerta = [
                'type' => 'error',
                'title' => 'Error de Servidor',
                'text' => 'Error al procesar la solicitud: ' . $e->getMessage()
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
  <meta name="description" content="Regístrate como Gestor Inmobiliario Free en PNK Inmobiliaria.">
  <title>Registro Gestor Inmobiliario — PNK Inmobiliaria</title>
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
        <a href="registro-gestor.php" class="active">Registro Gestor</a>
        <a href="dashboard.php">Panel Admin</a>
        <a href="contacto.php">Contacto</a>
        <a href="login.php" class="btn-nav">Iniciar Sesión</a>
      </div>
    </div>
  </nav>

  <!-- ========== REGISTRO GESTOR ========== -->
  <div class="form-wrapper">
    <div class="form-card wide" id="registroGestorCard">
      <h2>Registro Gestor Inmobiliario Free</h2>
      <p class="form-subtitle">Completa tus datos y adjunta tu certificado de antecedentes para crear tu cuenta</p>

      <div class="alert alert-warning">
        ⚠️ Tu cuenta quedará en estado <strong>"Pendiente"</strong> hasta que un administrador la apruebe y valide tu certificado.
      </div>

      <form id="formRegistroGestor" action="registro-gestor.php" method="POST" enctype="multipart/form-data">
        <?php echo csrfInput(); ?>
        <div class="form-row">
          <div class="form-group">
            <label for="gestor-rut">RUT <span class="required">*</span></label>
            <input type="text" id="gestor-rut" name="rut" class="form-control" placeholder="12.345.678-9" required
                   value="<?php echo isset($_POST['rut']) ? htmlspecialchars($_POST['rut']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="gestor-nombre">Nombre Completo <span class="required">*</span></label>
            <input type="text" id="gestor-nombre" name="nombre" class="form-control" placeholder="María González Ruiz" required
                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="gestor-nacimiento">Fecha de Nacimiento <span class="required">*</span></label>
            <input type="date" id="gestor-nacimiento" name="fecha_nacimiento" class="form-control" required
                   value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="gestor-sexo">Sexo <span class="required">*</span></label>
            <select id="gestor-sexo" name="sexo" class="form-control" required>
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
            <label for="gestor-email">Correo Electrónico <span class="required">*</span></label>
            <input type="email" id="gestor-email" name="email" class="form-control" placeholder="correo@ejemplo.com" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="gestor-telefono">Teléfono Móvil <span class="required">*</span></label>
            <input type="tel" id="gestor-telefono" name="telefono" class="form-control" placeholder="+56 9 1234 5678" required
                   value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="gestor-password">Contraseña <span class="required">*</span></label>
          <input type="password" id="gestor-password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="8">
        </div>

        <!-- Campo de Certificado de Antecedentes -->
        <div class="form-group">
          <label>Certificado de Antecedentes <span class="required">*</span></label>
          <div class="file-upload" id="fileUploadCertificado">
            <input type="file" id="gestor-certificado" name="certificado" accept=".pdf,.jpg,.jpeg,.png" required>
            <div class="upload-icon">📄</div>
            <p>Arrastra tu archivo aquí o <strong>haz clic para seleccionar</strong></p>
            <p class="upload-hint">Formatos aceptados: PDF, JPG, PNG — Máximo 5 MB</p>
          </div>
          <!-- Label dinámico para mostrar el nombre del archivo seleccionado -->
          <div id="file-selected-name" style="margin-top: 8px; font-size: 0.9rem; font-weight: bold; color: var(--accent);"></div>
        </div>

        <button type="submit" class="btn btn-accent btn-block" id="btnRegistroGestor">Crear Cuenta de Gestor Free</button>
      </form>

      <div class="form-footer">
        <p>¿Ya tienes cuenta? <a href="login.php">Iniciar Sesión</a></p>
        <p style="margin-top: 6px;">¿Eres propietario? <a href="registro-propietario.php">Regístrate como Propietario</a></p>
      </div>
    </div>
  </div>

  <script src="validaciones.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enlazar validador de RUT dinámico (Criterio 7)
        aplicarValidacionRut('gestor-rut');

        // Mostrar nombre del archivo subido en tiempo real
        const fileInput = document.getElementById('gestor-certificado');
        const fileNameDiv = document.getElementById('file-selected-name');
        if (fileInput && fileNameDiv) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileNameDiv.innerHTML = "📎 Archivo seleccionado: " + this.files[0].name;
                } else {
                    fileNameDiv.innerHTML = "";
                }
            });
        }

        // Validación interactiva antes de enviar (SweetAlert2)
        const form = document.getElementById('formRegistroGestor');
        form.addEventListener('submit', function(e) {
            const rut = document.getElementById('gestor-rut').value;
            const pass = document.getElementById('gestor-password').value;
            const cert = document.getElementById('gestor-certificado').value;
            
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

            if (!cert) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Archivo Requerido',
                    text: 'Debe adjuntar su Certificado de Antecedentes.',
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
