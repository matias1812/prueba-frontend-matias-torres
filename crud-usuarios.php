<?php
// crud-usuarios.php - Mantenedor de Usuarios completo con validaciones y SweetAlert2 (solo para Administradores)
require_once 'db.php';
require_once 'validaciones.php';

$page_title = "Mantenedor de Usuarios — PNK Inmobiliaria";
$page_description = "Mantenedor de Usuarios — PNK Inmobiliaria.";
$section_title = "Mantenedor de Usuarios";
$breadcrumb = "Usuarios";

// Incluir cabecera (verifica sesión activa)
require_once 'includes/header-admin.php';

// Criterio 6: Restringir acceso al rol Administrador
if ($user_rol !== 'administrador') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Acceso Denegado',
            text: 'No tienes permisos suficientes para ingresar a este mantenedor.',
            confirmButtonColor: 'var(--accent, #e056fd)'
        }).then(function() {
            window.location.href = 'dashboard.php';
        });
    </script>";
    exit();
}

$alerta = null;

// ==========================================
// PROCESAMIENTO DE OPERACIONES CRUD (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $alerta = [
            'type' => 'error',
            'title' => 'Solicitud Inválida',
            'text' => 'Token de seguridad inválido. Por favor recargue la página e intente de nuevo.'
        ];
    } else {
        $action = $_POST['action'];

    if ($action === 'crear') {
        $rut = sanitizarEntrada($_POST['rut'] ?? '');
        $nombre = sanitizarEntrada($_POST['nombre'] ?? '');
        $email = sanitizarEntrada($_POST['email'] ?? '');
        $telefono = sanitizarEntrada($_POST['telefono'] ?? '');
        $rol = sanitizarEntrada($_POST['rol'] ?? '');
        $estado = sanitizarEntrada($_POST['estado'] ?? 'pendiente');
        $password = $_POST['password'] ?? '';

        // Validaciones en servidor
        if (empty($rut) || empty($nombre) || empty($email) || empty($rol) || empty($password)) {
            $alerta = [
                'type' => 'error',
                'title' => 'Campos Faltantes',
                'text' => 'Por favor complete todos los campos obligatorios.'
            ];
        } elseif (!validarRutChileno($rut)) {
            $alerta = [
                'type' => 'error',
                'title' => 'RUT Inválido',
                'text' => 'El RUT chileno ingresado es inválido.'
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
                'title' => 'Contraseña Débil',
                'text' => 'La contraseña debe tener un largo mínimo de 8 caracteres.'
            ];
        } else {
            $rut_formateado = formatearRutChileno($rut);

            try {
                // Verificar si ya existe RUT
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = ?");
                $stmt->execute([$rut_formateado]);
                if ($stmt->fetch()) {
                    $alerta = [
                        'type' => 'error',
                        'title' => 'RUT Registrado',
                        'text' => 'El RUT ingresado ya está registrado en el sistema.'
                    ];
                } else {
                    // Verificar si ya existe Correo
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $alerta = [
                            'type' => 'error',
                            'title' => 'Correo Registrado',
                            'text' => 'El correo electrónico ya está en uso.'
                        ];
                    } else {
                        // Crear usuario
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, sexo, email, telefono, password_hash, rol, estado) VALUES (?, ?, '1990-01-01', 'no-especifica', ?, ?, ?, ?, ?)");
                        $stmt->execute([$rut_formateado, $nombre, $email, $telefono, $password_hash, $rol, $estado]);

                        $alerta = [
                            'type' => 'success',
                            'title' => '¡Usuario Creado!',
                            'text' => 'El usuario ha sido registrado correctamente.'
                        ];
                    }
                }
            } catch (PDOException $e) {
                $alerta = [
                    'type' => 'error',
                    'title' => 'Error de Servidor',
                    'text' => 'No se pudo registrar al usuario: ' . $e->getMessage()
                ];
            }
        }
    } elseif ($action === 'editar') {
        $rut = sanitizarEntrada($_POST['rut'] ?? ''); // Identificador único (RUT)
        $nombre = sanitizarEntrada($_POST['nombre'] ?? '');
        $email = sanitizarEntrada($_POST['email'] ?? '');
        $telefono = sanitizarEntrada($_POST['telefono'] ?? '');
        $rol = sanitizarEntrada($_POST['rol'] ?? '');
        $estado = sanitizarEntrada($_POST['estado'] ?? 'pendiente');
        $password = $_POST['password'] ?? '';

        if (empty($rut) || empty($nombre) || empty($email) || empty($rol)) {
            $alerta = [
                'type' => 'error',
                'title' => 'Campos Faltantes',
                'text' => 'Por favor complete todos los campos requeridos.'
            ];
        } elseif (!validarEmail($email)) {
            $alerta = [
                'type' => 'error',
                'title' => 'Correo Inválido',
                'text' => 'El formato del correo es inválido.'
            ];
        } else {
            try {
                // Verificar si el correo es usado por otra persona
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND rut != ?");
                $stmt->execute([$email, $rut]);
                if ($stmt->fetch()) {
                    $alerta = [
                        'type' => 'error',
                        'title' => 'Correo Duplicado',
                        'text' => 'El correo ingresado ya está asignado a otro usuario.'
                    ];
                } else {
                    if (!empty($password)) {
                        if (!validarPassword($password)) {
                            $alerta = [
                                'type' => 'error',
                                'title' => 'Contraseña Débil',
                                'text' => 'La nueva contraseña debe tener mínimo 8 caracteres.'
                            ];
                        } else {
                            $password_hash = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, rol = ?, estado = ?, password_hash = ? WHERE rut = ?");
                            $stmt->execute([$nombre, $email, $telefono, $rol, $estado, $password_hash, $rut]);
                            
                            // Si se edita a sí mismo, actualizar nombre y rol en sesión
                            if ($rut === $currentUserRut) {
                                $_SESSION['user_nombre'] = $nombre;
                                $_SESSION['user_rol'] = $rol;
                            }

                            $alerta = [
                                'type' => 'success',
                                'title' => '¡Usuario Actualizado!',
                                'text' => 'Se aplicaron los cambios correctamente con la nueva clave.'
                            ];
                        }
                    } else {
                        // Sin modificar contraseña
                        $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, rol = ?, estado = ? WHERE rut = ?");
                        $stmt->execute([$nombre, $email, $telefono, $rol, $estado, $rut]);

                        if ($rut === $currentUserRut) {
                            $_SESSION['user_nombre'] = $nombre;
                            $_SESSION['user_rol'] = $rol;
                        }

                        $alerta = [
                            'type' => 'success',
                            'title' => '¡Usuario Actualizado!',
                            'text' => 'Los cambios han sido guardados con éxito.'
                        ];
                    }
                }
            } catch (PDOException $e) {
                $alerta = [
                    'type' => 'error',
                    'title' => 'Error de Servidor',
                    'text' => 'No se pudo guardar la edición: ' . $e->getMessage()
                ];
            }
        }
    } elseif ($action === 'eliminar') {
        $rut = sanitizarEntrada($_POST['rut'] ?? '');

        try {
            // Verificar que no sea el mismo en sesión (Criterio 6)
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = ?");
            $stmt->execute([$rut]);
            $usr = $stmt->fetch();

            if ($usr && $usr['id'] == $_SESSION['user_id']) {
                $alerta = [
                    'type' => 'error',
                    'title' => 'Acción Bloqueada',
                    'text' => 'No puedes eliminar tu propia cuenta de administrador en sesión.'
                ];
            } else {
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE rut = ?");
                $stmt->execute([$rut]);
                $alerta = [
                    'type' => 'success',
                    'title' => '¡Eliminado!',
                    'text' => 'El usuario ha sido eliminado de forma permanente.'
                ];
            }
        } catch (PDOException $e) {
            $alerta = [
                'type' => 'error',
                'title' => 'Error de Servidor',
                'text' => 'No fue posible eliminar al usuario: ' . $e->getMessage()
            ];
        }
    }
}
}

// Cargar la lista completa de usuarios
try {
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY fecha_registro DESC");
    $lista_usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $lista_usuarios = [];
}
?>

<div class="table-card" id="usersTableCard">
  <div class="table-header">
    <h3>Listado de Usuarios</h3>
    <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;align-items:center;">
      <div class="table-search">
        <!-- Buscador Reactivo por JS -->
        <input type="text" id="searchUsuarios" placeholder="Buscar usuario...">
      </div>
      <button class="btn btn-accent btn-sm" id="btnNuevoUsuario">+ Nuevo Usuario</button>
    </div>
  </div>
  
  <div class="table-responsive">
    <table id="tablaUsuarios">
      <thead>
        <tr>
          <th>RUT</th>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Teléfono</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lista_usuarios)): ?>
          <tr>
            <td colspan="7" style="text-align: center;">No hay usuarios registrados.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($lista_usuarios as $usr): ?>
            <?php
            // Determinar clase de CSS para el badge del estado
            $status_class = '';
            if ($usr['estado'] === 'activo') {
                $status_class = 'status-active';
            } elseif ($usr['estado'] === 'pendiente') {
                $status_class = 'status-pending';
            } else {
                $status_class = 'status-inactive';
            }

            // Nombre comprensible del rol
            $rol_label = 'Usuario';
            if ($usr['rol'] === 'administrador') $rol_label = 'Administrador';
            elseif ($usr['rol'] === 'propietario') $rol_label = 'Propietario';
            elseif ($usr['rol'] === 'gestor-free') $rol_label = 'Gestor Free';
            ?>
            <tr>
              <td><?php echo htmlspecialchars($usr['rut']); ?></td>
              <td><?php echo htmlspecialchars($usr['nombre']); ?></td>
              <td><?php echo htmlspecialchars($usr['email']); ?></td>
              <td><?php echo htmlspecialchars($usr['telefono'] ?: '—'); ?></td>
              <td><?php echo htmlspecialchars($rol_label); ?></td>
              <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($usr['estado']); ?></span></td>
              <td>
                <div class="btn-group">
                  <!-- Botón de edición con data-attributes para poblar el modal sin peticiones adicionales -->
                  <button class="btn btn-primary btn-sm btn-edit" 
                          data-rut="<?php echo htmlspecialchars($usr['rut']); ?>"
                          data-nombre="<?php echo htmlspecialchars($usr['nombre']); ?>"
                          data-email="<?php echo htmlspecialchars($usr['email']); ?>"
                          data-telefono="<?php echo htmlspecialchars($usr['telefono']); ?>"
                          data-rol="<?php echo htmlspecialchars($usr['rol']); ?>"
                          data-estado="<?php echo htmlspecialchars($usr['estado']); ?>">
                    ✏️ Editar
                  </button>
                  <button class="btn btn-danger btn-sm" 
                          onclick="confirmarEliminar('<?php echo htmlspecialchars($usr['rut']); ?>', <?php echo $usr['id']; ?>, <?php echo $_SESSION['user_id']; ?>)">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <div class="table-footer">
    <span>Mostrando <?php echo count($lista_usuarios); ?> usuarios registrados</span>
  </div>
</div>

<!-- ========== MODAL INTERACTIVO DE USUARIO ========== -->
<div class="modal-overlay hidden" id="modalUsuario">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">Nuevo Usuario</h3>
      <button class="modal-close" onclick="document.getElementById('modalUsuario').classList.add('hidden')">✕</button>
    </div>
    <div class="modal-body">
      <form id="formCrudUsuario" method="POST" action="crud-usuarios.php">
        <?php echo csrfInput(); ?>
        <!-- Campos ocultos de acción e ID (RUT) -->
        <input type="hidden" name="action" id="crud-action" value="crear">
        
        <div class="form-row">
          <div class="form-group">
            <label for="crud-rut">RUT *</label>
            <input type="text" id="crud-rut" name="rut" class="form-control" placeholder="12.345.678-9" required>
          </div>
          <div class="form-group">
            <label for="crud-nombre">Nombre Completo *</label>
            <input type="text" id="crud-nombre" name="nombre" class="form-control" placeholder="Carlos Méndez López" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="crud-email">Correo Electrónico *</label>
            <input type="email" id="crud-email" name="email" class="form-control" placeholder="correo@ejemplo.com" required>
          </div>
          <div class="form-group">
            <label for="crud-telefono">Teléfono</label>
            <input type="tel" id="crud-telefono" name="telefono" class="form-control" placeholder="+56 9 1234 5678">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="crud-rol">Rol *</label>
            <select id="crud-rol" name="rol" class="form-control" required>
              <option value="">Seleccionar...</option>
              <option value="administrador">Administrador</option>
              <option value="propietario">Propietario</option>
              <option value="gestor-free">Gestor Free</option>
            </select>
          </div>
          <div class="form-group">
            <label for="crud-estado">Estado *</label>
            <select id="crud-estado" name="estado" class="form-control" required>
              <option value="activo">Activo</option>
              <option value="pendiente">Pendiente</option>
              <option value="inactivo">Inactivo</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label for="crud-password" id="label-password">Contraseña *</label>
          <input type="password" id="crud-password" name="password" class="form-control" placeholder="Mínimo 8 caracteres">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="document.getElementById('modalUsuario').classList.add('hidden')">Cancelar</button>
      <button class="btn btn-accent" id="btnGuardarUsuario" onclick="document.getElementById('formCrudUsuario').requestSubmit()">Guardar</button>
    </div>
  </div>
</div>

<script src="validaciones.js"></script>
<script>
  const csrfToken = '<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>';
  document.addEventListener('DOMContentLoaded', function() {
      // Enlazar validador de RUT (Criterio 7)
      aplicarValidacionRut('crud-rut');

      // Buscador dinámico de usuarios en tiempo real en la tabla
      const searchInput = document.getElementById('searchUsuarios');
      searchInput.addEventListener('input', function() {
          const query = this.value.toLowerCase().trim();
          const rows = document.querySelectorAll('#tablaUsuarios tbody tr');
          
          rows.forEach(row => {
              if (row.cells.length < 2) return; // Saltarse fila de "No hay usuarios"
              
              // Buscar coincidencia en RUT, Nombre o Correo
              const rutText = row.cells[0].textContent.toLowerCase();
              const nombreText = row.cells[1].textContent.toLowerCase();
              const emailText = row.cells[2].textContent.toLowerCase();
              
              if (rutText.includes(query) || nombreText.includes(query) || emailText.includes(query)) {
                  row.style.display = '';
              } else {
                  row.style.display = 'none';
              }
          });
      });

      // Configurar Modal para crear Nuevo Usuario
      const btnNuevo = document.getElementById('btnNuevoUsuario');
      btnNuevo.addEventListener('click', function() {
          document.getElementById('formCrudUsuario').reset();
          document.getElementById('crud-action').value = 'crear';
          document.getElementById('crud-rut').readOnly = false;
          document.getElementById('crud-rut').style.backgroundColor = '';
          document.getElementById('label-password').innerText = 'Contraseña *';
          document.getElementById('crud-password').required = true;
          document.getElementById('crud-password').placeholder = 'Mínimo 8 caracteres';
          document.getElementById('modal-title').innerText = 'Nuevo Usuario';
          document.getElementById('modalUsuario').classList.remove('hidden');
      });

      // Configurar Modal para Editar Usuario
      document.querySelectorAll('.btn-edit').forEach(btn => {
          btn.addEventListener('click', function() {
              const rut = this.getAttribute('data-rut');
              const nombre = this.getAttribute('data-nombre');
              const email = this.getAttribute('data-email');
              const telefono = this.getAttribute('data-telefono');
              const rol = this.getAttribute('data-rol');
              const estado = this.getAttribute('data-estado');

              document.getElementById('crud-action').value = 'editar';
              document.getElementById('crud-rut').value = rut;
              // El RUT no es editable una vez creado por consistencia de base de datos
              document.getElementById('crud-rut').readOnly = true;
              document.getElementById('crud-rut').style.backgroundColor = '#f7fafc';
              
              document.getElementById('crud-nombre').value = nombre;
              document.getElementById('crud-email').value = email;
              document.getElementById('crud-telefono').value = telefono;
              document.getElementById('crud-rol').value = rol;
              document.getElementById('crud-estado').value = estado;
              
              document.getElementById('label-password').innerText = 'Contraseña (Opcional)';
              document.getElementById('crud-password').required = false;
              document.getElementById('crud-password').value = '';
              document.getElementById('crud-password').placeholder = 'Dejar vacío para conservar la actual';

              document.getElementById('modal-title').innerText = 'Editar Usuario';
              document.getElementById('modalUsuario').classList.remove('hidden');
          });
      });

      // Validación intermedia antes de enviar el formulario
      const form = document.getElementById('formCrudUsuario');
      form.addEventListener('submit', function(e) {
          const action = document.getElementById('crud-action').value;
          const rut = document.getElementById('crud-rut').value;
          const pass = document.getElementById('crud-password').value;

          if (action === 'crear' && !validarRutChileno(rut)) {
              e.preventDefault();
              Swal.fire({
                  icon: 'error',
                  title: 'RUT Inválido',
                  text: 'Ingrese un RUT chileno válido antes de guardar.',
                  confirmButtonColor: 'var(--accent, #e056fd)'
              });
              return;
          }

          if (action === 'crear' && pass.length < 8) {
              e.preventDefault();
              Swal.fire({
                  icon: 'error',
                  title: 'Contraseña Corta',
                  text: 'La contraseña del usuario debe tener al menos 8 caracteres.',
                  confirmButtonColor: 'var(--accent, #e056fd)'
              });
              return;
          }

          if (action === 'editar' && pass !== '' && pass.length < 8) {
              e.preventDefault();
              Swal.fire({
                  icon: 'error',
                  title: 'Contraseña Corta',
                  text: 'La nueva contraseña debe tener al menos 8 caracteres.',
                  confirmButtonColor: 'var(--accent, #e056fd)'
              });
              return;
          }
      });
  });

  /**
   * Cierre con SweetAlert2 para eliminación segura (Criterio 6 y 8)
   */
  function confirmarEliminar(rut, id, sesionId) {
      if (id === sesionId) {
          Swal.fire({
              icon: 'error',
              title: 'Operación Denegada',
              text: 'No es posible auto-eliminarse de la base de datos.',
              confirmButtonColor: 'var(--accent, #e056fd)',
              background: 'var(--card-bg, #ffffff)',
              color: 'var(--text-main, #2d3748)'
          });
          return;
      }

      Swal.fire({
          title: '¿Eliminar Usuario?',
          text: `Se borrará permanentemente al usuario con RUT: ${rut} y todas sus propiedades asociadas.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: 'var(--danger, #ff4d4d)',
          cancelButtonColor: '#686de0',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
          background: 'var(--card-bg, #ffffff)',
          color: 'var(--text-main, #2d3748)'
      }).then((result) => {
          if (result.isConfirmed) {
              // Simular POST de eliminación de manera dinámica
              const delForm = document.createElement('form');
              delForm.method = 'POST';
              delForm.action = 'crud-usuarios.php';
              
              const actInp = document.createElement('input');
              actInp.type = 'hidden';
              actInp.name = 'action';
              actInp.value = 'eliminar';
              
              const rutInp = document.createElement('input');
              rutInp.type = 'hidden';
              rutInp.name = 'rut';
              rutInp.value = rut;

              const csrfInp = document.createElement('input');
              csrfInp.type = 'hidden';
              csrfInp.name = 'csrf_token';
              csrfInp.value = csrfToken;

              delForm.appendChild(actInp);
              delForm.appendChild(rutInp);
              delForm.appendChild(csrfInp);
              document.body.appendChild(delForm);
              delForm.submit();
          }
      });
  }
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
      });
  });
</script>
<?php endif; ?>

<?php
// Incluir pie de página
require_once 'includes/footer-admin.php';
?>
