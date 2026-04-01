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
    const filtroClienteEstadoCuenta = document.getElementById('filtroClienteEstadoCuenta');
    const autoSubmitEstadoCuenta = (() => {
      let timer = null;
      return (delay = 350) => {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(() => formEstadoCuenta.requestSubmit(), delay);
      };
    })();

    if (filtroClienteEstadoCuenta && typeof TomSelect !== 'undefined' && !filtroClienteEstadoCuenta.tomselect) {
      new TomSelect(filtroClienteEstadoCuenta, {
        create: false,
        allowEmptyOption: true,
        plugins: ['clear_button'],
        placeholder: 'Buscar por nombre...',
        sortField: { field: 'text', direction: 'asc' },
        onChange: () => autoSubmitEstadoCuenta()
      });
    }
    
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
    formEstadoCuenta.querySelectorAll('input, select').forEach(field => {
      const tipo = String(field.type || '').toLowerCase();

      field.addEventListener('change', () => autoSubmitEstadoCuenta());

      if (tipo === 'date' || tipo === 'search' || tipo === 'text') {
        field.addEventListener('input', () => autoSubmitEstadoCuenta());
      }
    });
  }

})();
