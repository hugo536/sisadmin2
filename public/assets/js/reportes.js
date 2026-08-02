(() => {
  // 1. VALIDACIÓN GENERAL DE FECHAS
  const forms = document.querySelectorAll('form[action*="reportes/"]');
  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      const desde = form.querySelector('input[name="fecha_desde"]');
      const hasta = form.querySelector('input[name="fecha_hasta"]');
      
      if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
        e.preventDefault();
        // Ya que usamos SweetAlert, lo aprovechamos también aquí
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error', 
                title: 'Rango de fechas inválido', 
                text: 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".',
                confirmButtonColor: '#0B5ED7'
            });
        } else {
            alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
        }
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
          // 1. Limpiar el select del cliente
          if (typeof TomSelect !== 'undefined' && filtroTerceroEstadoCuenta && filtroTerceroEstadoCuenta.tomselect) {
            filtroTerceroEstadoCuenta.tomselect.clear(true); 
          } else if (filtroTerceroEstadoCuenta) {
            filtroTerceroEstadoCuenta.value = '';
          }

          // 2. Limpiar las fechas 
          const inputDesde = formEstadoCuenta.querySelector('input[name="fecha_desde"]');
          const inputHasta = formEstadoCuenta.querySelector('input[name="fecha_hasta"]');
          if (inputDesde) inputDesde.value = '';
          if (inputHasta) inputHasta.value = '';

          // 3. Preparar la URL base para el SPA
          const baseUrl = formEstadoCuenta.action.split('?')[0];
          const destino = new URL(baseUrl, window.location.origin);
          
          const inputRuta = formEstadoCuenta.querySelector('input[name="ruta"]');
          if (inputRuta) destino.searchParams.set('ruta', inputRuta.value);

          // 4. Navegar sin recargar
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

    // --- INTEGRACIÓN DE TOMSELECT ---
    if (filtroTerceroEstadoCuenta) {
      if (typeof TomSelect !== 'undefined') {
        new TomSelect(filtroTerceroEstadoCuenta, {
          create: false,
          placeholder: "Buscar cliente o distribuidor...",
          onChange: function() {
            autoSubmitEstadoCuenta();
          }
        });
      } else {
        filtroTerceroEstadoCuenta.addEventListener('change', () => autoSubmitEstadoCuenta());
      }
    }
    
    // --- LÓGICA DE EXPORTACIÓN (EXCEL, CSV, PDF) Y VALIDACIÓN SWEETALERT ---
    const tablaDetalle = document.getElementById('tablaEstadoCuentaDetalle') || document.querySelector('table');
    
    // Obtenemos el total de registros priorizando el atributo data, o contando las filas de datos como respaldo
    const filasReales = document.querySelectorAll('tbody tr[data-search]').length;
    const totalRegistros = tablaDetalle ? parseInt(tablaDetalle.getAttribute('data-total-rows') || filasReales, 10) : filasReales;

    // Función interceptora con SweetAlert
    const verificarDatosVacios = (e) => {
      if (totalRegistros === 0) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'warning',
            title: 'Sin datos para exportar',
            text: 'No hay movimientos registrados en el periodo y filtros seleccionados.',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#0B5ED7',
            showClass: { popup: 'animate__animated animate__fadeInDown animate__faster' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp animate__faster' }
          });
        } else {
          alert('No hay movimientos registrados para exportar.');
        }
        return true; // Retorna true indicando que bloqueó la acción
      }
      return false; // Retorna false indicando que tiene datos y puede continuar
    };

    // 1. Exportar a PDF (Corregido para usar la variable btnPdfId)
    const btnExportarPdf = document.getElementById(btnPdfId) || document.getElementById('btnExportarPdfLimitado');
    if (btnExportarPdf) {
      btnExportarPdf.addEventListener('click', (e) => {
        e.preventDefault();

        // VALIDACIÓN SWEETALERT (BLOQUEA SI ESTÁ VACÍO)
        if (verificarDatosVacios(e)) return;

        // Límite Estricto (Bloqueo)
        if (totalRegistros >= 2500) {
          alert(`⚠️ LÍMITE EXCEDIDO: El reporte contiene ${totalRegistros} movimientos.\n\nGenerar un PDF de este tamaño (más de 70 páginas) colapsará el servidor. Por favor, utiliza la opción de exportar a Excel o CSV para descargar esta cantidad de datos masivos.`);
          return; 
        }

        // Límite Suave (Advertencia)
        if (totalRegistros >= 1000) {
          const continuar = confirm(`⚠️ ATENCIÓN: Estás intentando exportar ${totalRegistros} movimientos a PDF.\n\nEste proceso puede tardar varios segundos y generar más de 30 páginas. Para reportes de este tamaño se recomienda usar Excel o CSV.\n\n¿Estás seguro de que deseas generar el PDF?`);
          if (!continuar) return;
        }

        // Si pasa las validaciones, genera el PDF
        const params = new URLSearchParams(new FormData(formEstadoCuenta));
        params.set('accion', pdfAction); 
        const baseUrl = formEstadoCuenta.action.split('?')[0]; 
        const urlCompleta = `${baseUrl}?${params.toString()}`;
        window.open(urlCompleta, '_blank');
      });
    }

    // 2. Exportar a Excel
    const btnExportarExcel = document.getElementById('btnExportarExcel');
    if (btnExportarExcel) {
      btnExportarExcel.addEventListener('click', (e) => {
        e.preventDefault();
        
        // VALIDACIÓN SWEETALERT (BLOQUEA SI ESTÁ VACÍO)
        if (verificarDatosVacios(e)) return;

        const params = new URLSearchParams(new FormData(formEstadoCuenta));
        params.set('accion', 'exportar_excel_estado_cuenta'); 
        const baseUrl = formEstadoCuenta.action.split('?')[0]; 
        const urlCompleta = `${baseUrl}?${params.toString()}`;
        window.open(urlCompleta, '_blank');
      });
    }

    // 3. Exportar a CSV
    const btnExportarCsv = document.getElementById('btnExportarCsv');
    if (btnExportarCsv) {
      btnExportarCsv.addEventListener('click', (e) => {
        e.preventDefault();

        // VALIDACIÓN SWEETALERT (BLOQUEA SI ESTÁ VACÍO)
        if (verificarDatosVacios(e)) return;

        const params = new URLSearchParams(new FormData(formEstadoCuenta));
        params.set('accion', 'exportar_csv_estado_cuenta'); 
        const baseUrl = formEstadoCuenta.action.split('?')[0]; 
        const urlCompleta = `${baseUrl}?${params.toString()}`;
        window.open(urlCompleta, '_blank');
      });
    }

    // --- AUTO-SUBMIT CONTROLADO ---
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
  };

  initReporteInventarioFiltros();

})();