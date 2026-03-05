document.addEventListener('DOMContentLoaded', () => {

    const formFiltros = document.getElementById('formFiltrosPlanillas');
    const searchInput = document.getElementById('searchPlanilla');
    const tablaPlanillas = document.getElementById('planillasTable'); // Capturamos la tabla

    // --- LÓGICA: Preparar Modal de Pago (Delegación de Eventos Segura) ---
    if (tablaPlanillas) {
        tablaPlanillas.addEventListener('click', (e) => {
            // Buscamos si el clic fue exactamente en el botón o dentro de él (ej. el ícono)
            const botonPago = e.target.closest('button[data-bs-target="#modalPagarPlanilla"]');
            
            if (botonPago) {
                // Pasamos los datos del botón al Modal
                document.getElementById('pagoIdEmpleado').value = botonPago.getAttribute('data-id-empleado');
                document.getElementById('pagoMontoTotal').value = botonPago.getAttribute('data-monto-pagar');
                document.getElementById('pagoFechaDesde').value = botonPago.getAttribute('data-fecha-desde');
                document.getElementById('pagoFechaHasta').value = botonPago.getAttribute('data-fecha-hasta');
                
                // Actualizamos las etiquetas visuales del modal
                document.getElementById('lblEmpleadoNombre').textContent = botonPago.getAttribute('data-nombre-empleado');
                document.getElementById('lblPeriodo').textContent = botonPago.getAttribute('data-fecha-desde') + ' al ' + botonPago.getAttribute('data-fecha-hasta');
            }
        });
    }

    // 1. CANDADO AL BUSCADOR (Evitar que el enter recargue la página)
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });
    }

    if (!formFiltros) return;

    // 2. CANDADO AL FORMULARIO (Manejar el submit manual por si presionan enter en algún input)
    formFiltros.addEventListener('submit', (e) => {
        e.preventDefault();
        triggerAutoSubmit();
    });

    // 3. CAPTURAMOS LOS FILTROS
    const inputDesde = formFiltros.querySelector('input[name="desde"]');
    const inputHasta = formFiltros.querySelector('input[name="hasta"]');
    const selectFrecuencia = formFiltros.querySelector('select[name="frecuencia_pago"]'); // NUEVO
    const selectEmpleado = formFiltros.querySelector('select[name="id_tercero"]');

    // --- EL DEBOUNCE (Retraso inteligente para no saturar el servidor) ---
    let debounceTimer;

    const autoSubmitForm = async () => {
        
        const paginationInfo = document.getElementById('planillasPaginationInfo');
        if (paginationInfo) paginationInfo.textContent = 'Calculando nómina...';

        // Mostrar el Spinner de carga de tu renderizador
        if (window.planillasManager) window.planillasManager.showLoading();

        const url = new URL(window.location.href);
        
        // Asignamos las fechas
        if (inputDesde) url.searchParams.set('desde', inputDesde.value);
        if (inputHasta) url.searchParams.set('hasta', inputHasta.value);
        
        // Asignamos Frecuencia (o lo quitamos de la URL si eligió "Todas")
        if (selectFrecuencia) {
            if (selectFrecuencia.value === "") {
                url.searchParams.delete('frecuencia_pago');
            } else {
                url.searchParams.set('frecuencia_pago', selectFrecuencia.value);
            }
        }

        // Asignamos Empleado (o lo quitamos de la URL si eligió "Todos")
        if (selectEmpleado) {
            if (selectEmpleado.value === "") {
                url.searchParams.delete('id_tercero');
            } else {
                url.searchParams.set('id_tercero', selectEmpleado.value);
            }
        }

        try {
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Error al conectar con el servidor');
            const html = await response.text();

            const parser = new DOMParser();
            const docVirtual = parser.parseFromString(html, 'text/html');

            // 1. Reemplazamos los Totales (Tarjetas de arriba)
            ['totalPlanilla', 'totalExtras', 'totalDescuentos'].forEach(id => {
                const el = document.getElementById(id);
                const virtualEl = docVirtual.getElementById(id);
                if (el && virtualEl) el.innerHTML = virtualEl.innerHTML;
            });

            // 2. Reemplazamos la Tabla y el Badge de contadores
            const currentTbody = document.querySelector('#planillasTable tbody');
            const newTbody = docVirtual.querySelector('#planillasTable tbody');
            if (currentTbody && newTbody) currentTbody.innerHTML = newTbody.innerHTML;

            const badge = document.querySelector('.badge.bg-primary-subtle');
            const newBadge = docVirtual.querySelector('.badge.bg-primary-subtle');
            if (badge && newBadge) badge.innerHTML = newBadge.innerHTML;

            // 3. Refrescamos la paginación global y los tooltips visuales
            if (window.planillasManager && typeof window.planillasManager.refresh === 'function') {
                window.planillasManager.refresh();
            }

            if (window.ERPTable && typeof window.ERPTable.initTooltips === 'function') {
                window.ERPTable.initTooltips();
            }

            // Actualizamos la URL en el navegador sin recargar (para que si el usuario recarga, se quede donde estaba)
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Error en la actualización AJAX:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Aviso', 'No se pudo actualizar en tiempo real.', 'warning');
            }
        } finally {
            // Ocultar el Spinner de carga
            if (window.planillasManager) window.planillasManager.hideLoading();
        }
    };

    // Función que dispara el AJAX con un ligero retraso
    const triggerAutoSubmit = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            autoSubmitForm(); 
        }, 150); 
    };

    // ESCUCHAMOS CAMBIOS EN TODOS LOS FILTROS Y DISPARAMOS LA BÚSQUEDA
    if (inputDesde) inputDesde.addEventListener('change', triggerAutoSubmit);
    if (inputHasta) inputHasta.addEventListener('change', triggerAutoSubmit);
    if (selectFrecuencia) selectFrecuencia.addEventListener('change', triggerAutoSubmit); // NUEVO
    if (selectEmpleado) selectEmpleado.addEventListener('change', triggerAutoSubmit);

});