<?php
// crud-propiedades.php - Mantenedor de Propiedades con subida múltiple de imágenes y SweetAlert2
require_once 'db.php';
require_once 'validaciones.php';

$page_title = "Mantenedor de Propiedades — PNK Inmobiliaria";
$page_description = "Mantenedor de Propiedades — PNK Inmobiliaria.";
$section_title = "Mantenedor de Propiedades";
$breadcrumb = "Propiedades";

// Incluir cabecera (verifica sesión activa)
require_once 'includes/header-admin.php';

$alerta = null;

// ==========================================
// PROCESAMIENTO DE PUBLICACIÓN / ELIMINACIÓN
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
        $tipo = sanitizarEntrada($_POST['tipo'] ?? '');
        $fecha_pub = sanitizarEntrada($_POST['fecha_publicacion'] ?? '');
        $descripcion = sanitizarEntrada($_POST['descripcion'] ?? '');
        $dormitorios = (int)($_POST['dormitorios'] ?? 0);
        $banos = (int)($_POST['banos'] ?? 0);
        $area_terreno = (int)($_POST['area_terreno'] ?? 0);
        $area_construida = (int)($_POST['area_construida'] ?? 0);
        $precio_clp = (float)($_POST['precio_clp'] ?? 0);
        $precio_uf = (float)($_POST['precio_uf'] ?? 0);
        
        // Recibir amenidades (array)
        $amenidades_array = $_POST['amenidades'] ?? [];
        $amenidades_json = json_encode($amenidades_array, JSON_UNESCAPED_UNICODE);

        // Validaciones en servidor
        if (empty($tipo) || empty($fecha_pub) || empty($descripcion) || $precio_clp <= 0 || $precio_uf <= 0) {
            $alerta = [
                'type' => 'error',
                'title' => 'Campos Faltantes',
                'text' => 'Por favor, complete todos los campos obligatorios del inmueble.'
            ];
        } elseif (!isset($_FILES['fotos']) || empty($_FILES['fotos']['name'][0])) {
            $alerta = [
                'type' => 'error',
                'title' => 'Imágenes Faltantes',
                'text' => 'Debe adjuntar al menos una fotografía de la propiedad.'
            ];
        } else {
            // Validar cantidad de imágenes (1 a 10)
            $cant_fotos = count($_FILES['fotos']['name']);
            if ($cant_fotos < 1 || $cant_fotos > 10) {
                $alerta = [
                    'type' => 'error',
                    'title' => 'Límite Excedido',
                    'text' => 'Puede subir un mínimo de 1 y un máximo de 10 fotografías.'
                ];
            } else {
                // Validar formatos y tamaños de todas las fotos
                $fotos_validas = true;
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                $max_size = 5 * 1024 * 1024; // 5 MB

                for ($i = 0; $i < $cant_fotos; $i++) {
                    $file_name = $_FILES['fotos']['name'][$i];
                    $file_size = $_FILES['fotos']['size'][$i];
                    $file_error = $_FILES['fotos']['error'][$i];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if ($file_error !== UPLOAD_ERR_OK || !in_array($ext, $allowed_exts) || $file_size > $max_size) {
                        $fotos_validas = false;
                        break;
                    }
                }

                if (!$fotos_validas) {
                    $alerta = [
                        'type' => 'error',
                        'title' => 'Imágenes Inválidas',
                        'text' => 'Una o más fotos superan los 5 MB o no están en formato válido (JPG, PNG, WEBP).'
                    ];
                } else {
                    try {
                        // 1. Insertar propiedad en la base de datos
                        $stmt = $pdo->prepare("INSERT INTO propiedades (tipo, fecha_publicacion, descripcion, dormitorios, banos, area_terreno, area_construida, precio_clp, precio_uf, amenidades, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$tipo, $fecha_pub, $descripcion, $dormitorios, $banos, $area_terreno, $area_construida, $precio_clp, $precio_uf, $amenidades_json, $user_id]);
                        
                        $propiedad_id = $pdo->lastInsertId();

                        // 2. Crear carpeta de destino
                        $upload_dir = 'uploads/propiedades/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // 3. Subir fotos e insertarlas
                        for ($i = 0; $i < $cant_fotos; $i++) {
                            $file_tmp = $_FILES['fotos']['tmp_name'][$i];
                            $ext = strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
                            
                            $new_filename = 'prop_' . $propiedad_id . '_' . uniqid() . '.' . $ext;
                            $dest_path = $upload_dir . $new_filename;

                            if (move_uploaded_file($file_tmp, $dest_path)) {
                                $stmt_foto = $pdo->prepare("INSERT INTO propiedad_fotos (propiedad_id, foto_ruta) VALUES (?, ?)");
                                $stmt_foto->execute([$propiedad_id, $dest_path]);
                            }
                        }

                        $alerta = [
                            'type' => 'success',
                            'title' => '¡Publicada!',
                            'text' => 'La propiedad y sus imágenes se registraron exitosamente.'
                        ];
                    } catch (PDOException $e) {
                        $alerta = [
                            'type' => 'error',
                            'title' => 'Error de Servidor',
                            'text' => 'No se pudo registrar la propiedad: ' . $e->getMessage()
                        ];
                    }
                }
            }
        }
    } elseif ($action === 'eliminar') {
        $prop_id = (int)($_POST['id'] ?? 0);

        try {
            // Verificar propiedad y pertenencia (si no es admin)
            $stmt = $pdo->prepare("SELECT creado_por FROM propiedades WHERE id = ?");
            $stmt->execute([$prop_id]);
            $owner_id = $stmt->fetchColumn();

            if ($owner_id && ($user_rol === 'administrador' || $owner_id == $user_id)) {
                // Obtener las rutas de los archivos de fotos para eliminarlos del disco
                $stmt_fotos = $pdo->prepare("SELECT foto_ruta FROM propiedad_fotos WHERE propiedad_id = ?");
                $stmt_fotos->execute([$prop_id]);
                $fotos = $stmt_fotos->fetchAll(PDO::FETCH_COLUMN);

                // Eliminar archivos físicos
                foreach ($fotos as $foto) {
                    if (file_exists($foto)) {
                        unlink($foto);
                    }
                }

                // Eliminar registro en base de datos (se eliminan fotos asociadas por CASCADE)
                $stmt_del = $pdo->prepare("DELETE FROM propiedades WHERE id = ?");
                $stmt_del->execute([$prop_id]);

                $alerta = [
                    'type' => 'success',
                    'title' => '¡Eliminada!',
                    'text' => 'Propiedad y archivos físicos eliminados correctamente.'
                ];
            } else {
                $alerta = [
                    'type' => 'error',
                    'title' => 'Permiso Denegado',
                    'text' => 'No tiene autorización para eliminar esta propiedad.'
                ];
            }
        } catch (PDOException $e) {
            $alerta = [
                'type' => 'error',
                'title' => 'Error de Servidor',
                'text' => 'Error al eliminar la propiedad: ' . $e->getMessage()
            ];
        }
    }
}
}

// ==========================================
// CARGAR LISTA DE PROPIEDADES
// ==========================================
try {
    if ($user_rol === 'administrador') {
        // Administrador ve todas
        $stmt = $pdo->query("SELECT p.*, u.nombre as creador_nombre FROM propiedades p JOIN usuarios u ON p.creado_por = u.id ORDER BY p.fecha_creacion DESC");
    } else {
        // Propietarios y Gestores ven solo las suyas
        $stmt = $pdo->prepare("SELECT p.*, u.nombre as creador_nombre FROM propiedades p JOIN usuarios u ON p.creado_por = u.id WHERE p.creado_por = ? ORDER BY p.fecha_creacion DESC");
        $stmt->execute([$user_id]);
    }
    $lista_propiedades = $stmt->fetchAll();
} catch (PDOException $e) {
    $lista_propiedades = [];
}
?>

<!-- FORMULARIO DE PROPIEDAD -->
<div class="table-card" style="margin-bottom:var(--space-xl);" id="formPropiedadCard">
  <div class="table-header">
    <h3>Publicar Nueva Propiedad</h3>
  </div>
  <div style="padding:var(--space-xl);">
    <form id="formPropiedad" method="POST" action="crud-propiedades.php" enctype="multipart/form-data">
      <?php echo csrfInput(); ?>
      <input type="hidden" name="action" value="crear">
      
      <!-- Tipo y Fecha -->
      <div class="form-row">
        <div class="form-group">
          <label for="prop-tipo">Tipo de Propiedad <span class="required">*</span></label>
          <select id="prop-tipo" name="tipo" class="form-control" required>
            <option value="">Seleccionar...</option>
            <option value="casa">Casa</option>
            <option value="departamento">Departamento</option>
            <option value="terreno">Terreno</option>
            <option value="oficina">Oficina</option>
            <option value="local-comercial">Local Comercial</option>
          </select>
        </div>
        <div class="form-group">
          <label for="prop-fecha">Fecha de Publicación <span class="required">*</span></label>
          <input type="date" id="prop-fecha" name="fecha_publicacion" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
        </div>
      </div>

      <!-- Descripción -->
      <div class="form-group">
        <label for="prop-descripcion">Descripción <span class="required">*</span></label>
        <textarea id="prop-descripcion" name="descripcion" class="form-control" rows="4" placeholder="Describa las características principales del inmueble..." required></textarea>
      </div>

      <!-- Dormitorios, Baños -->
      <div class="form-row">
        <div class="form-group">
          <label for="prop-dormitorios">Cantidad de Dormitorios <span class="required">*</span></label>
          <input type="number" id="prop-dormitorios" name="dormitorios" class="form-control" min="0" max="20" placeholder="Ej: 3" required value="0">
        </div>
        <div class="form-group">
          <label for="prop-banos">Cantidad de Baños <span class="required">*</span></label>
          <input type="number" id="prop-banos" name="banos" class="form-control" min="0" max="20" placeholder="Ej: 2" required value="0">
        </div>
      </div>

      <!-- Áreas -->
      <div class="form-row">
        <div class="form-group">
          <label for="prop-area-terreno">Área Total del Terreno (m²) <span class="required">*</span></label>
          <input type="number" id="prop-area-terreno" name="area_terreno" class="form-control" min="0" placeholder="Ej: 500" required value="0">
        </div>
        <div class="form-group">
          <label for="prop-area-construida">Área Construida (m²) <span class="required">*</span></label>
          <input type="number" id="prop-area-construida" name="area_construida" class="form-control" min="0" placeholder="Ej: 180" required value="0">
        </div>
      </div>

      <!-- Precios -->
      <div class="form-row">
        <div class="form-group">
          <label for="prop-precio-clp">Precio en $ (CLP) <span class="required">*</span></label>
          <input type="number" id="prop-precio-clp" name="precio_clp" class="form-control" min="0" placeholder="Ej: 150000000" required>
        </div>
        <div class="form-group">
          <label for="prop-precio-uf">Precio en UF <span class="required">*</span></label>
          <input type="number" id="prop-precio-uf" name="precio_uf" class="form-control" min="0" step="0.01" placeholder="Ej: 4200" required>
        </div>
      </div>

      <!-- Fotografías -->
      <div class="form-group">
        <label>Fotografías (1 a 10) <span class="required">*</span></label>
        <div class="file-upload" id="fileUploadFotos">
          <!-- Notar el name="fotos[]" múltiple -->
          <input type="file" id="prop-fotos" name="fotos[]" accept="image/*" multiple required>
          <div class="upload-icon">📸</div>
          <p>Arrastra tus imágenes aquí o <strong>haz clic para seleccionar</strong></p>
          <p class="upload-hint">Sube entre 1 y 10 fotografías — JPG, PNG, WEBP — Máx. 5 MB c/u</p>
        </div>
        <div id="fotos-selected-count" style="margin-top: 8px; font-size: 0.9rem; font-weight: bold; color: var(--accent);"></div>
      </div>

      <!-- Checkboxes de amenidades -->
      <div class="form-group">
        <label>Amenidades y Características</label>
        <div class="checkbox-group">
          <label class="checkbox-item">
            <input type="checkbox" id="chk-bodega" name="amenidades[]" value="bodega"> Bodega
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="chk-estacionamiento" name="amenidades[]" value="estacionamiento"> Estacionamiento
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="chk-logia" name="amenidades[]" value="logia"> Logia
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="chk-cocina" name="amenidades[]" value="cocina-amoblada"> Cocina amoblada
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="chk-antejardin" name="amenidades[]" value="antejardin"> Antejardín
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="chk-patio" name="amenidades[]" value="patio-trasero"> Patio trasero
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="chk-piscina" name="amenidades[]" value="piscina"> Piscina
          </label>
        </div>
      </div>

      <button type="submit" class="btn btn-accent" id="btnPublicarPropiedad" style="margin-top:var(--space-md);">Publicar Propiedad</button>
    </form>
  </div>
</div>

<!-- TABLA DE PROPIEDADES -->
<div class="table-card" id="propsTableCard">
  <div class="table-header">
    <h3>Propiedades Registradas</h3>
    <div class="table-search">
      <input type="text" id="searchProps" placeholder="Buscar propiedad...">
    </div>
  </div>
  <div class="table-responsive">
    <table id="tablaPropiedades">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tipo</th>
          <th>Descripción</th>
          <th>Dorms</th>
          <th>Baños</th>
          <th>m² (Const / Terr)</th>
          <th>UF</th>
          <th>CLP</th>
          <th>Creador</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lista_propiedades)): ?>
          <tr>
            <td colspan="10" style="text-align: center;">No hay propiedades registradas en este momento.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($lista_propiedades as $prop): ?>
            <tr>
              <td><?php echo str_pad($prop['id'], 3, '0', STR_PAD_LEFT); ?></td>
              <td><span class="property-badge" style="position:static; padding:4px 8px; font-size:0.75rem;"><?php echo ucfirst($prop['tipo']); ?></span></td>
              <td><strong><?php echo htmlspecialchars(substr($prop['descripcion'], 0, 40)) . (strlen($prop['descripcion']) > 40 ? '...' : ''); ?></strong></td>
              <td><?php echo $prop['dormitorios'] ?: '—'; ?></td>
              <td><?php echo $prop['banos'] ?: '—'; ?></td>
              <td><?php echo htmlspecialchars($prop['area_construida'] . ' / ' . $prop['area_terreno']); ?> m²</td>
              <td><?php echo number_format($prop['precio_uf'], 0, ',', '.'); ?> UF</td>
              <td>$<?php echo number_format($prop['precio_clp'], 0, ',', '.'); ?> CLP</td>
              <td><span style="font-size:0.85rem; color:var(--gray-600);"><?php echo htmlspecialchars($prop['creador_nombre']); ?></span></td>
              <td>
                <!-- Permitir eliminar si el usuario es dueño o admin (Criterio 6) -->
                <?php if ($user_rol === 'administrador' || $prop['creado_por'] == $user_id): ?>
                <div class="btn-group">
                  <button class="btn btn-danger btn-sm" onclick="confirmarEliminarPropiedad(<?php echo $prop['id']; ?>)">
                    🗑️ Eliminar
                  </button>
                </div>
                <?php else: ?>
                  <span style="font-size: 0.8rem; color: var(--gray-400);">Sin permisos</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span>Mostrando <?php echo count($lista_propiedades); ?> propiedades</span>
  </div>
</div>

<script>
  const csrfToken = '<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>';
  document.addEventListener('DOMContentLoaded', function() {
      // Mostrar la cantidad de fotos seleccionadas
      const fileInput = document.getElementById('prop-fotos');
      const fileCountDiv = document.getElementById('fotos-selected-count');
      if (fileInput && fileCountDiv) {
          fileInput.addEventListener('change', function() {
              const cant = this.files ? this.files.length : 0;
              if (cant > 0) {
                  fileCountDiv.innerHTML = "📸 " + cant + " foto(s) seleccionada(s).";
                  if (cant > 10) {
                      fileCountDiv.innerHTML += " <strong style='color:var(--danger);'>⚠️ Máximo 10 fotos permitidas.</strong>";
                  }
              } else {
                  fileCountDiv.innerHTML = "";
              }
          });
      }

      // Buscador dinámico de propiedades
      const searchPropsInput = document.getElementById('searchProps');
      searchPropsInput.addEventListener('input', function() {
          const query = this.value.toLowerCase().trim();
          const rows = document.querySelectorAll('#tablaPropiedades tbody tr');
          
          rows.forEach(row => {
              if (row.cells.length < 2) return;
              
              const descText = row.cells[2].textContent.toLowerCase();
              const tipoText = row.cells[1].textContent.toLowerCase();
              const creadorText = row.cells[8].textContent.toLowerCase();
              
              if (descText.includes(query) || tipoText.includes(query) || creadorText.includes(query)) {
                  row.style.display = '';
              } else {
                  row.style.display = 'none';
              }
          });
      });

      // Validación intermedia antes de publicar
      const form = document.getElementById('formPropiedad');
      form.addEventListener('submit', function(e) {
          const fileCount = fileInput.files ? fileInput.files.length : 0;
          if (fileCount < 1) {
              e.preventDefault();
              Swal.fire({
                  icon: 'error',
                  title: 'Imágenes Faltantes',
                  text: 'Debe adjuntar al menos una fotografía.',
                  confirmButtonColor: 'var(--accent, #e056fd)'
              });
              return;
          }
          if (fileCount > 10) {
              e.preventDefault();
              Swal.fire({
                  icon: 'error',
                  title: 'Límite Excedido',
                  text: 'Solo se admite un máximo de 10 fotografías.',
                  confirmButtonColor: 'var(--accent, #e056fd)'
              });
              return;
          }
      });
  });

  /**
   * Cierre con SweetAlert2 para eliminación de propiedad
   */
  function confirmarEliminarPropiedad(id) {
      Swal.fire({
          title: '¿Eliminar Propiedad?',
          text: "Se borrarán de forma permanente todos los registros y archivos del inmueble. Esta acción no se puede deshacer.",
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
              const delForm = document.createElement('form');
              delForm.method = 'POST';
              delForm.action = 'crud-propiedades.php';
              
              const actInp = document.createElement('input');
              actInp.type = 'hidden';
              actInp.name = 'action';
              actInp.value = 'eliminar';
              
              const idInp = document.createElement('input');
              idInp.type = 'hidden';
              idInp.name = 'id';
              idInp.value = id;

              const csrfInp = document.createElement('input');
              csrfInp.type = 'hidden';
              csrfInp.name = 'csrf_token';
              csrfInp.value = csrfToken;

              delForm.appendChild(actInp);
              delForm.appendChild(idInp);
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
