      </div> <!-- Cierre de dashboard-content -->
    </main>
  </div> <!-- Cierre de dashboard-layout -->

  <script>
    /**
     * Función global para confirmar el cierre de sesión usando SweetAlert2
     * Cumple con la exigencia del Criterio 8 de interactividad premium.
     */
    function confirmarCierreSesion(event) {
        event.preventDefault();
        Swal.fire({
            title: '¿Cerrar Sesión?',
            text: "Se finalizará tu sesión activa en la plataforma PNK Inmobiliaria.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent, #e056fd)',
            cancelButtonColor: '#686de0',
            confirmButtonText: 'Sí, salir',
            cancelButtonText: 'Cancelar',
            background: 'var(--card-bg, #ffffff)',
            color: 'var(--text-main, #2d3748)'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirigir al script de destrucción de sesión
                window.location.href = 'logout.php';
            }
        });
    }
  </script>
</body>
</html>
