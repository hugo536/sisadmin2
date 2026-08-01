(() => {
  // 1. VALIDACIÓN GENERAL DE FECHAS
  const forms = document.querySelectorAll('form[action*="reportes/"]');
  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      const desde = form.querySelector('input[name="fecha_desde"]');
      const hasta = form.querySelector('input[name="fecha_hasta"]');
      
      if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
        e.preventDefault();
        // Usar un Toast o SweetAlert sería ideal aquí en el futuro
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
      }
    });
  });

  // 2. LÓGICA ESPECÍFICA PARA ESTADOS DE CUENTA (CLIENTES / PROVEEDORES)
  const initEstadoCuenta = ({ formId, terceroSelectId, btnPdfId, pdfAction }) => {
    const formEstadoCuenta = document.getElementById(formId);
    if (!formEstadoCuenta) return;

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

      // --- LÓGICA BOTÓN LIMPIAR FILTROS (SPA) ---
    const btnLimpiar = document.getElementById('btnLimpiarFiltrosEstadoCuenta');
    if (btnLimpiar) {
      btnLimpiar.addEventListener('click', () => {
        // 1. Limpiar el select del cliente (TomSelect si existe, o el input normal)
        if (typeof TomSelect !== 'undefined' && filtroTerceroEstadoCuenta && filtroTerceroEstadoCuenta.tomselect) {
          filtroTerceroEstadoCuenta.tomselect.clear(true); // 'true' evita que dispare un evento 'change'
        } else if (filtroTerceroEstadoCuenta) {
          filtroTerceroEstadoCuenta.value = '';
        }

        // 2. Limpiar las fechas (opcional, el backend le pondrá el mes actual si van vacías)
        const inputDesde = formEstadoCuenta.querySelector('input[name="fecha_desde"]');
        const inputHasta = formEstadoCuenta.querySelector('input[name="fecha_hasta"]');
        if (inputDesde) inputDesde.value = '';
        if (inputHasta) inputHasta.value = '';

        // 3. Preparar la URL base para el SPA
        const baseUrl = formEstadoCuenta.action.split('?')[0];
        const destino = new URL(baseUrl, window.location.origin);
        
        // Mantener solo la ruta principal
        const inputRuta = formEstadoCuenta.querySelector('input[name="ruta"]');
        if (inputRuta) destino.searchParams.set('ruta', inputRuta.value);

        // 4. Navegar sin recargar toda la página
        if (typeof window.navigateWithoutReload === 'function') {
          window.navigateWithoutReload(destino, true);
        } else {
          window.location.href = destino.toString();
        }
      });
    }
    };

    // Temporizador para evitar múltiples recargas rápidas (Debounce)
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

    // --- INTEGRACIÓN DE TOMSELECT (Con preparación para AJAX) ---
    if (filtroTerceroEstadoCuenta) {
      if (typeof TomSelect !== 'undefined') {
        new TomSelect(filtroTerceroEstadoCuenta, {
          create: false,
          placeholder: "Buscar cliente o distribuidor...",
          // IMPORTANTE: Cuando tengas miles de clientes, usa esta configuración AJAX:
          /*
          valueField: 'nombre', // El valor que viaja en el select
          labelField: 'nombre', // Lo que se muestra
          searchField: 'nombre',
          load: function(query, callback) {
            if (!query.length) return callback();
            // Llama a tu controlador PHP para buscar clientes
            fetch(`${window.location.origin}/public/index.php?ruta=api/clientes&q=${encodeURIComponent(query)}`)
              .then(response => response.json())
              .then(json => {
                callback(json); // json debe ser un array de objetos [{nombre: 'Juan'}, {nombre: 'Pedro'}]
              }).catch(()=>{
                callback();
              });
          },
          */
          onChange: function() {
            autoSubmitEstadoCuenta();
          }
        });
      } else {
        filtroTerceroEstadoCuenta.addEventListener('change', () => autoSubmitEstadoCuenta());
      }
    }
    
    // --- LÓGICA EXPORTAR PDF ---
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

    // --- AUTO-SUBMIT CONTROLADO ---
    // Solo hacemos auto-submit al cambiar el tipo de vista. 
    // Las fechas requerirán el botón "Filtrar" para evitar recargas mientras el usuario tipea.
    const filtrosAutoSubmit = formEstadoCuenta.querySelectorAll('[name="vista"]');
    filtrosAutoSubmit.forEach((field) => {
      field.addEventListener('change', () => autoSubmitEstadoCuenta());
    });
  };

  // Inicializar para Clientes
  initEstadoCuenta({
    formId: 'estadoCuentaFiltrosForm',
    terceroSelectId: 'filtroClienteEstadoCuenta',
    btnPdfId: 'btnExportarPdf',
    pdfAction: 'imprimir_estado_cuenta'
  });

  // Inicializar para Proveedores
  initEstadoCuenta({
    formId: 'estadoCuentaProveedoresFiltrosForm',
    terceroSelectId: 'filtroProveedorEstadoCuenta',
    btnPdfId: 'btnExportarPdfProveedores',
    pdfAction: 'imprimir_estado_cuenta_proveedores'
  });

  // 3. LÓGICA PARA FILTROS DE REPORTE DE INVENTARIO
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

    const filtrosAutoSubmit = formInventario.querySelectorAll('select[name="id_categoria"], select[name="tipo_item"], select[name="id_almacen"], select[name="situacion_alerta"], input[name="solo_bajo_minimo"], input[name="secciones[]"]');

    filtrosAutoSubmit.forEach((field) => {
      field.addEventListener('change', () => autoSubmitInventario());
    });
    
    // NOTA: Quité el evento 'input' y 'change' automático de las fechas del inventario 
    // para que funcionen con un botón de filtrar, igual que en el Estado de Cuenta.
  };

  initReporteInventarioFiltros();

})();