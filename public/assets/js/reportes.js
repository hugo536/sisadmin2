/**
 * LÓGICA GENERAL DE REPORTES E INVENTARIO
 * Archivo: public/assets/js/reportes/reportes.js
 */
(() => {
  // 1. VALIDACIÓN GENERAL DE FECHAS (Aplica a todos los reportes del ERP)
  const forms = document.querySelectorAll('form[action*="reportes/"]');
  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      const desde = form.querySelector('input[name="fecha_desde"]');
      const hasta = form.querySelector('input[name="fecha_hasta"]');
      
      if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
        e.preventDefault();
        
        // Alerta profesional con SweetAlert
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

  // 2. LÓGICA PARA FILTROS DE REPORTE DE INVENTARIO
  // (Nota: La lógica de Estado de Cuentas fue movida a su propio archivo: estado_cuentas.js)
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

    // Temporizador para evitar múltiples peticiones al servidor al clickear rápido
    const autoSubmitInventario = (() => {
      let timer = null;
      return (delay = 250) => {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(() => submitInventarioFiltros(), delay);
      };
    })();

    // Seleccionamos los filtros que deben recargar la tabla automáticamente al cambiar
    const filtrosAutoSubmit = formInventario.querySelectorAll('select[name="id_categoria"], select[name="tipo_item"], select[name="id_almacen"], select[name="situacion_alerta"], input[name="solo_bajo_minimo"], input[name="secciones[]"]');

    filtrosAutoSubmit.forEach((field) => {
      field.addEventListener('change', () => autoSubmitInventario());
    });
  };

  // Inicializamos el módulo de inventario
  initReporteInventarioFiltros();

})();