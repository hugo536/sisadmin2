(() => {
  // 1. VALIDACIÓN GENERAL DE FECHAS (Tu código original)
  const forms = document.querySelectorAll('form[action*="reportes/"]');
  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      const desde = form.querySelector('input[name="fecha_desde"]');
      const hasta = form.querySelector('input[name="fecha_hasta"]');
      if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
        e.preventDefault();
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
      }
    });
  });

  // 2. LÓGICA ESPECÍFICA PARA EL ESTADO DE CUENTA
  const formEstadoCuenta = document.getElementById('estadoCuentaFiltrosForm');
  if (formEstadoCuenta) {
    
    // --- Lógica del botón Exportar PDF ---
    const btnExportarPdf = document.getElementById('btnExportarPdf');
    if (btnExportarPdf) {
      btnExportarPdf.addEventListener('click', () => {
        // Recolectamos todos los filtros seleccionados (fechas, cliente, etc.)
        const params = new URLSearchParams(new FormData(formEstadoCuenta));
        
        // Agregamos el parámetro que le dirá a tu PHP que genere el PDF
        params.set('accion', 'imprimir_estado_cuenta'); 
        
        // Construimos la URL limpia
        const baseUrl = formEstadoCuenta.action.split('?')[0]; 
        const urlCompleta = `${baseUrl}?${params.toString()}`;
        
        // Abrimos el PDF en una nueva pestaña
        window.open(urlCompleta, '_blank');
      });
    }

    // --- Lógica de Auto-Filtrado (Mejora la UX) ---
    let timer = null;
    formEstadoCuenta.querySelectorAll('input, select').forEach(field => {
      // Para cajas de texto/búsqueda, filtramos al presionar Enter
      if (field.type === 'search' || field.type === 'text') {
        field.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            formEstadoCuenta.submit();
          }
        });
      } else {
        // Para fechas y selects, filtramos automáticamente al cambiar con un ligero retraso
        field.addEventListener('change', () => {
          if (timer) window.clearTimeout(timer);
          timer = window.setTimeout(() => formEstadoCuenta.submit(), 450);
        });
      }
    });
  }

})();