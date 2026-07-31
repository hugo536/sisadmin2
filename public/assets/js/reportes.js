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

  // 2. LÓGICA ESPECÍFICA PARA ESTADOS DE CUENTA (CLIENTES / PROVEEDORES)
  const initEstadoCuenta = ({ formId, terceroSelectId, btnPdfId, pdfAction }) => {
    const formEstadoCuenta = document.getElementById(formId);
    if (!formEstadoCuenta) {
      return;
    }

    const filtroTerceroEstadoCuenta = document.getElementById(terceroSelectId);
    const submitEstadoCuentaFiltros = () => {
      const params = new URLSearchParams(new FormData(formEstadoCuenta));
      const baseUrl = formEstadoCuenta.action.split('?')[0];
      const destino = new URL(baseUrl, window.location.origin);
      destino.search = params.toString();

      if (typeof window.navigateWithoutReload === 'function') {
        window.navigateWithoutReload(destino, true);
      } else {
        window.location.href = destino.toString();
      }
    };

    const autoSubmitEstadoCuenta = (() => {
      let timer = null;
      return (delay = 350) => {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(() => submitEstadoCuentaFiltros(), delay);
      };
    })();

    formEstadoCuenta.addEventListener('submit', (event) => {
      event.preventDefault();
      submitEstadoCuentaFiltros();
    });

    // --- AQUÍ INTEGRAMOS TOMSELECT ---
    if (filtroTerceroEstadoCuenta) {
      // Verificamos si la librería TomSelect está cargada en el proyecto
      if (typeof TomSelect !== 'undefined') {
        new TomSelect(filtroTerceroEstadoCuenta, {
          create: false,
          placeholder: "Buscar cliente o distribuidor...",
          onChange: function() {
            // Cuando cambie el valor en TomSelect, ejecutamos el auto-submit
            autoSubmitEstadoCuenta();
          }
        });
      } else {
        // Fallback clásico por si falla la carga de TomSelect
        filtroTerceroEstadoCuenta.addEventListener('change', () => autoSubmitEstadoCuenta());
      }
    }
    
    // --- Lógica del botón Exportar PDF ---
    const btnExportarPdf = document.getElementById(btnPdfId);
    if (btnExportarPdf) {
      btnExportarPdf.addEventListener('click', () => {
        const params = new URLSearchParams(new FormData(formEstadoCuenta));
        params.set('accion', pdfAction);
        const baseUrl = formEstadoCuenta.action.split('?')[0]; 
        const urlCompleta = `${baseUrl}?${params.toString()}`;
        window.open(urlCompleta, '_blank');
      });
    }

    // CÓDIGO CORREGIDO (Solo la vista hará auto-submit):
    const filtrosAutoSubmit = formEstadoCuenta.querySelectorAll('[name="vista"]');

    filtrosAutoSubmit.forEach((field) => {
      const tipo = String(field.type || '').toLowerCase();
      field.addEventListener('change', () => autoSubmitEstadoCuenta());
      if (tipo === 'date') {
        field.addEventListener('input', () => autoSubmitEstadoCuenta());
      }
    });
  };

  initEstadoCuenta({
    formId: 'estadoCuentaFiltrosForm',
    terceroSelectId: 'filtroClienteEstadoCuenta',
    btnPdfId: 'btnExportarPdf',
    pdfAction: 'imprimir_estado_cuenta'
  });

  initEstadoCuenta({
    formId: 'estadoCuentaProveedoresFiltrosForm',
    terceroSelectId: 'filtroProveedorEstadoCuenta',
    btnPdfId: 'btnExportarPdfProveedores',
    pdfAction: 'imprimir_estado_cuenta_proveedores'
  });

  // 3. LÓGICA PARA FILTROS DE REPORTE DE INVENTARIO (auto-submit)
  const initReporteInventarioFiltros = () => {
    const appInventario = document.getElementById('reportesInventarioApp');
    if (!appInventario) return;

    const formInventario = appInventario.querySelector('form[action*="reportes/inventario"]');
    if (!formInventario) return;

    const submitInventarioFiltros = () => {
      if (formInventario.requestSubmit) {
        formInventario.requestSubmit();
        return;
      }
      formInventario.submit();
    };

    const autoSubmitInventario = (() => {
      let timer = null;
      return (delay = 250) => {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(() => submitInventarioFiltros(), delay);
      };
    })();

    const filtrosAutoSubmit = formInventario.querySelectorAll('input[name="fecha_desde"], input[name="fecha_hasta"], select[name="id_categoria"], select[name="tipo_item"], select[name="id_almacen"], select[name="situacion_alerta"], input[name="solo_bajo_minimo"], input[name="secciones[]"]');

    filtrosAutoSubmit.forEach((field) => {
      const tipo = String(field.type || '').toLowerCase();
      field.addEventListener('change', () => autoSubmitInventario());

      if (tipo === 'date') {
        field.addEventListener('input', () => autoSubmitInventario());
      }
    });
  };

  initReporteInventarioFiltros();

})();
