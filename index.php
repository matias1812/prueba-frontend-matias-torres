<?php
// index.php - Portal Inmobiliario Principal conectado a la Base de Datos con Auto-seeding
require_once 'db.php';
require_once 'validaciones.php';

// Iniciar sesión para saber si hay un usuario logueado en la cabecera
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// SEEDER AUTOMÁTICO DE PROPIEDADES DE MUESTRA
// ==========================================
// Si el usuario carga el sistema por primera vez, garantizamos que se vea idéntico al prototipo original
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM propiedades");
    if ($stmt->fetchColumn() == 0) {
        // Obtener el ID del administrador por defecto
        $stmt_admin = $pdo->query("SELECT id FROM usuarios WHERE rol = 'administrador' LIMIT 1");
        $admin_id = $stmt_admin->fetchColumn();

        if ($admin_id) {
            $mockups = [
                [
                    'tipo' => 'casa',
                    'descripcion' => 'Hermosa casa moderna de dos pisos en zona residencial exclusiva de Las Condes.',
                    'dormitorios' => 4,
                    'banos' => 3,
                    'area_terreno' => 500,
                    'area_construida' => 180,
                    'precio_clp' => 313650000,
                    'precio_uf' => 8500.00,
                    'amenidades' => json_encode(['bodega', 'estacionamiento', 'piscina', 'antejardin']),
                    'foto' => 'img/casa-moderna.png',
                    'ubicacion' => 'Las Condes, Santiago'
                ],
                [
                    'tipo' => 'departamento',
                    'descripcion' => 'Departamento de lujo con gran conectividad y terminaciones de primera en Providencia.',
                    'dormitorios' => 2,
                    'banos' => 2,
                    'area_terreno' => 85,
                    'area_construida' => 85,
                    'precio_clp' => 191880000,
                    'precio_uf' => 5200.00,
                    'amenidades' => json_encode(['bodega', 'estacionamiento', 'cocina-amoblada']),
                    'foto' => 'img/departamento-lujo.png',
                    'ubicacion' => 'Providencia, Santiago'
                ],
                [
                    'tipo' => 'terreno',
                    'descripcion' => 'Terreno completamente plano listo para construir en sector residencial consolidado de Pirque.',
                    'dormitorios' => 0,
                    'banos' => 0,
                    'area_terreno' => 4500,
                    'area_construida' => 0,
                    'precio_clp' => 140220000,
                    'precio_uf' => 3800.00,
                    'amenidades' => json_encode([]),
                    'foto' => 'img/terreno-verde.png',
                    'ubicacion' => 'Pirque, Cordillera'
                ],
                [
                    'tipo' => 'casa',
                    'descripcion' => 'Acogedora casa familiar con patio trasero, antejardín y logia en sector Ñuñoa.',
                    'dormitorios' => 3,
                    'banos' => 2,
                    'area_terreno' => 250,
                    'area_construida' => 140,
                    'precio_clp' => 228780000,
                    'precio_uf' => 6200.00,
                    'amenidades' => json_encode(['estacionamiento', 'logia', 'antejardin', 'patio-trasero']),
                    'foto' => 'img/casa-familiar.png',
                    'ubicacion' => 'Ñuñoa, Santiago'
                ],
                [
                    'tipo' => 'departamento',
                    'descripcion' => 'Studio moderno ideal para inversionistas o estudiantes en el centro neurálgico de Santiago.',
                    'dormitorios' => 1,
                    'banos' => 1,
                    'area_terreno' => 45,
                    'area_construida' => 45,
                    'precio_clp' => 103320000,
                    'precio_uf' => 2800.00,
                    'amenidades' => json_encode(['cocina-amoblada']),
                    'foto' => 'img/depto-interior.png',
                    'ubicacion' => 'Santiago Centro, Santiago'
                ],
                [
                    'tipo' => 'terreno',
                    'descripcion' => 'Espectacular terreno comercial en sector industrial consolidado de Chicureo.',
                    'dormitorios' => 0,
                    'banos' => 0,
                    'area_terreno' => 10000,
                    'area_construida' => 0,
                    'precio_clp' => 442800000,
                    'precio_uf' => 12000.00,
                    'amenidades' => json_encode([]),
                    'foto' => 'img/terreno-grande.png',
                    'ubicacion' => 'Chicureo, Chacabuco'
                ]
            ];

            foreach ($mockups as $mk) {
                // Insertar propiedad
                $stmt_ins = $pdo->prepare("INSERT INTO propiedades (tipo, fecha_publicacion, descripcion, dormitorios, banos, area_terreno, area_construida, precio_clp, precio_uf, amenidades, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([
                    $mk['tipo'],
                    date('Y-m-d'),
                    $mk['descripcion'] . ' (Ubicación: ' . $mk['ubicacion'] . ')',
                    $mk['dormitorios'],
                    $mk['banos'],
                    $mk['area_terreno'],
                    $mk['area_construida'],
                    $mk['precio_clp'],
                    $mk['precio_uf'],
                    $mk['amenidades'],
                    $admin_id
                ]);
                $new_prop_id = $pdo->lastInsertId();

                // Insertar foto
                $stmt_foto = $pdo->prepare("INSERT INTO propiedad_fotos (propiedad_id, foto_ruta) VALUES (?, ?)");
                $stmt_foto->execute([$new_prop_id, $mk['foto']]);
            }
        }
    }
} catch (PDOException $e) {
    // Si falla la inicialización o consulta, no bloqueamos la página
    error_log("Error en seeder de inicio: " . $e->getMessage());
}

// ==========================================
// CARGAR Y FILTRAR PROPIEDADES DINÁMICAMENTE
// ==========================================
$comuna_filter = sanitizarEntrada($_GET['comuna'] ?? '');
$tipo_filter = sanitizarEntrada($_GET['tipo'] ?? '');

try {
    $sql = "SELECT p.*, 
            (SELECT foto_ruta FROM propiedad_fotos WHERE propiedad_id = p.id LIMIT 1) as foto_destacada 
            FROM propiedades p WHERE 1=1";
    $params = [];

    // Filtrar por tipo si se especifica
    if (!empty($tipo_filter)) {
        $sql .= " AND p.tipo = ?";
        $params[] = $tipo_filter;
    }

    // Filtrar por comuna/provincia si se especifica
    if (!empty($comuna_filter)) {
        $sql .= " AND p.descripcion LIKE ?";
        $params[] = "%" . $comuna_filter . "%";
    }

    $stmt = $pdo->prepare($sql . " ORDER BY p.fecha_creacion DESC");
    $stmt->execute($params);
    $propiedades = $stmt->fetchAll();
} catch (PDOException $e) {
    $propiedades = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="PNK Inmobiliaria — Encuentra tu propiedad ideal en Chile. Casas, departamentos y terrenos.">
  <title>PNK Inmobiliaria — Inicio</title>
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
      <div class="navbar-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('show')">
        <span></span><span></span><span></span>
      </div>
      <div class="navbar-menu" id="navMenu">
        <a href="index.php" class="active">Inicio</a>
        <a href="registro-propietario.php">Registro Propietario</a>
        <a href="registro-gestor.php">Registro Gestor</a>
        <a href="dashboard.php">Panel Admin</a>
        <a href="contacto.php">Contacto</a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
          <!-- Si hay sesión activa, muestra botón al panel en lugar de Iniciar Sesión -->
          <a href="dashboard.php" class="btn-nav" style="background:var(--accent); color:#fff;">Mi Panel Admin</a>
        <?php else: ?>
          <a href="login.php" class="btn-nav">Iniciar Sesión</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- ========== HERO ========== -->
  <section class="hero" id="hero">
    <div class="container">
      <div class="hero-content">
        <div class="hero-badge">🏠 Plataforma Inmobiliaria Líder</div>
        <h1>Encuentra el <span class="highlight">hogar perfecto</span> para ti y tu familia</h1>
        <p>Explora nuestra selección de casas, departamentos y terrenos en las mejores ubicaciones de Chile. Tu próxima inversión comienza aquí.</p>
      </div>

      <!-- ========== BUSCADOR (Formulario de Filtro) ========== -->
      <form class="search-box" id="searchBox" method="GET" action="index.php">
        <div class="search-field">
          <label for="search-comuna">Ubicación / Comuna</label>
          <select id="search-comuna" name="comuna" class="form-control">
            <option value="">Todas las comunas</option>
            <option value="las condes" <?php echo $comuna_filter == 'las condes' ? 'selected' : ''; ?>>Las Condes</option>
            <option value="providencia" <?php echo $comuna_filter == 'providencia' ? 'selected' : ''; ?>>Providencia</option>
            <option value="nunoa" <?php echo $comuna_filter == 'nunoa' ? 'selected' : ''; ?>>Ñuñoa</option>
            <option value="santiago" <?php echo $comuna_filter == 'santiago' ? 'selected' : ''; ?>>Santiago</option>
            <option value="pirque" <?php echo $comuna_filter == 'pirque' ? 'selected' : ''; ?>>Pirque</option>
            <option value="chicureo" <?php echo $comuna_filter == 'chicureo' ? 'selected' : ''; ?>>Chicureo</option>
          </select>
        </div>
        
        <div class="search-field">
          <label for="search-tipo">Tipo de Propiedad</label>
          <select id="search-tipo" name="tipo" class="form-control">
            <option value="">Todos los tipos</option>
            <option value="casa" <?php echo $tipo_filter == 'casa' ? 'selected' : ''; ?>>Casa</option>
            <option value="departamento" <?php echo $tipo_filter == 'departamento' ? 'selected' : ''; ?>>Departamento</option>
            <option value="terreno" <?php echo $tipo_filter == 'terreno' ? 'selected' : ''; ?>>Terreno</option>
            <option value="oficina" <?php echo $tipo_filter == 'oficina' ? 'selected' : ''; ?>>Oficina</option>
            <option value="local-comercial" <?php echo $tipo_filter == 'local-comercial' ? 'selected' : ''; ?>>Local Comercial</option>
          </select>
        </div>

        <button type="submit" class="btn-search" id="btnBuscar">🔍 Filtrar</button>
      </form>
    </div>
  </section>

  <!-- ========== PROPIEDADES ========== -->
  <section class="properties-section" id="properties">
    <div class="container">
      <h2 class="section-title text-center">Propiedades <span class="text-accent">Disponibles</span></h2>
      <p class="section-subtitle text-center">Explora las mejores oportunidades inmobiliarias ingresadas en el sistema</p>

      <div class="properties-grid">
        <?php if (empty($propiedades)): ?>
          <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-xl); color: var(--gray-600);">
            <h3>🔍 No se encontraron propiedades</h3>
            <p>Intenta cambiar los filtros de búsqueda.</p>
          </div>
        <?php else: ?>
          <?php foreach ($propiedades as $prop): ?>
            <?php
            // Extraer ubicación aproximada del texto
            $ubicacion = 'Santiago, Chile';
            if (preg_match('/Ubicación:\s*([^)]+)/i', $prop['descripcion'], $matches)) {
                $ubicacion = trim($matches[1]);
            }
            
            // Limpiar la descripción para la tarjeta
            $desc_tarjeta = preg_replace('/\(Ubicación:[^)]+\)/i', '', $prop['descripcion']);
            $desc_tarjeta = htmlspecialchars(substr($desc_tarjeta, 0, 80)) . (strlen($desc_tarjeta) > 80 ? '...' : '');

            // Foto por defecto si no hay
            $foto_ruta = $prop['foto_destacada'] ?: 'img/casa-moderna.png';
            ?>
            <article class="property-card" id="property-<?php echo $prop['id']; ?>">
              <div class="property-card-img">
                <img src="<?php echo htmlspecialchars($foto_ruta); ?>" alt="<?php echo htmlspecialchars($prop['tipo']); ?>">
                <span class="property-badge"><?php echo htmlspecialchars(ucfirst($prop['tipo'])); ?></span>
              </div>
              <div class="property-card-body">
                <h3><?php echo htmlspecialchars(ucfirst($prop['tipo']) . ' en ' . explode(',', $ubicacion)[0]); ?></h3>
                <div class="property-location">📍 <?php echo htmlspecialchars($ubicacion); ?></div>
                
                <p style="font-size:0.85rem; color:var(--gray-600); margin: var(--space-sm) 0; height: 35px; overflow:hidden;">
                    <?php echo $desc_tarjeta; ?>
                </p>

                <div class="property-features">
                  <div class="feat"><strong><?php echo $prop['dormitorios'] ?: '—'; ?></strong> Dorms.</div>
                  <div class="feat"><strong><?php echo $prop['banos'] ?: '—'; ?></strong> Baños</div>
                  <div class="feat"><strong><?php echo number_format($prop['area_construida'] ?: $prop['area_terreno'], 0, ',', '.'); ?></strong> m²</div>
                </div>
                
                <div class="property-price-row">
                  <div class="property-price">
                    <span class="uf">UF <?php echo number_format($prop['precio_uf'], 0, ',', '.'); ?></span>
                    <span class="clp">$<?php echo number_format($prop['precio_clp'], 0, ',', '.'); ?> CLP</span>
                  </div>
                  <a href="#" class="btn-ver-mas" onclick="mostrarDetallesPropiedad(event, '<?php echo htmlspecialchars(ucfirst($prop['tipo'])); ?>', '<?php echo addslashes(htmlspecialchars($prop['descripcion'])); ?>', '<?php echo $prop['precio_uf']; ?>', '<?php echo $prop['precio_clp']; ?>')">Ver Más</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ========== FOOTER ========== -->
  <footer class="footer" id="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.php" class="navbar-brand">
            <div class="logo-icon">PNK</div>
            <span style="color:#fff; font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:700;">PNK <span style="color:var(--accent);">Inmobiliaria</span></span>
          </a>
          <p>Plataforma inmobiliaria líder en Chile. Conectamos propietarios, gestores y compradores en un solo lugar.</p>
        </div>
        <div>
          <h4>Navegación</h4>
          <ul>
            <li><a href="index.php">Inicio</a></li>
            <li><a href="login.php">Iniciar Sesión</a></li>
            <li><a href="contacto.php">Contacto</a></li>
          </ul>
        </div>
        <div>
          <h4>Registro</h4>
          <ul>
            <li><a href="registro-propietario.php">Propietario</a></li>
            <li><a href="registro-gestor.php">Gestor Free</a></li>
          </ul>
        </div>
        <div>
          <h4>Contacto</h4>
          <ul>
            <li><a href="mailto:info@pnkinmobiliaria.cl">info@pnkinmobiliaria.cl</a></li>
            <li><a href="tel:+56912345678">+56 9 1234 5678</a></li>
            <li><a href="#">Santiago, Chile</a></li>
          </ul>
        </div>
      </div>
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

  <script>
    /**
     * SweetAlert2 interactivo para ver detalles de la propiedad
     */
    function mostrarDetallesPropiedad(event, tipo, descripcion, precioUf, precioClp) {
        event.preventDefault();
        
        // Limpiar etiqueta de Ubicación para mostrar limpia la descripción
        const descLimpia = descripcion.replace(/\(Ubicación:[^)]+\)/i, '');
        
        Swal.fire({
            title: tipo,
            html: `
                <div style="text-align: left; font-family: var(--font-body); font-size: 0.95rem;">
                    <p style="margin-bottom: 12px; color: var(--gray-700); line-height: 1.5;">${descLimpia}</p>
                    <hr style="border: 0; border-top: 1px solid var(--gray-200); margin: 12px 0;">
                    <div style="display:flex; justify-content:space-between; font-weight:bold;">
                        <span style="color: var(--accent);">Valor UF:</span>
                        <span>${Number(precioUf).toLocaleString('es-CL')} UF</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-weight:bold; margin-top: 6px;">
                        <span style="color: var(--gray-600);">Equivalente CLP:</span>
                        <span>$${Number(precioClp).toLocaleString('es-CL')} CLP</span>
                    </div>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: 'var(--accent, #e056fd)',
            background: 'var(--card-bg, #ffffff)',
            color: 'var(--text-main, #2d3748)'
        });
    }
  </script>

</body>
</html>
