/**
 * Lógica específica para Tesorería - Movimientos (Versión Silenciosa)
 * Archivo: assets/js/tesoreria/movimientos.js
 */
(function arrancarMovimientos() {
    'use strict';
    
    console.log("🚀 INICIANDO JS DE MOVIMIENTOS...");

    const app = document.getElementById('tesoreriaMovimientosApp');
    // Previene que el script se inicialice dos veces
    if (!app || app.dataset.movimientosInit === '1') return;
    app.dataset.movimientosInit = '1';

    const formFiltros = document.getElementById('formFiltrosMovimientos');
    const contenedorTabla = document.getElementById('contenedorTablaMovimientos');
    let timerFiltro = null;

    // ========================================================================
    // 1. DETECTAR REDIRECCIONES DIRECTAS (EJ: DESDE CxC o CxP)
    // ========================================================================
    if (formFiltros) {
        const urlParams = new URLSearchParams(window.location.search);
        const tieneOrigen = urlParams.has('origen') && urlParams.has('id_origen');
        
        // Si venimos de un enlace de "Ver Historial", las fechas por defecto del mes actual
        // pueden ocultar pagos antiguos. Si es el caso, limpiamos las fechas para ver TODO.
        if (tieneOrigen) {
            const inputDesde = formFiltros.querySelector('input[name="fecha_desde"]');
            const inputHasta = formFiltros.querySelector('input[name="fecha_hasta"]');
            const selectOrigen = formFiltros.querySelector('select[name="origen"]');

            if (inputDesde) inputDesde.value = '';
            if (inputHasta) inputHasta.value = '';
            
            // Si el origen viene en la URL, nos aseguramos que el Select lo refleje visualmente
            if (selectOrigen && urlParams.get('origen')) {
                selectOrigen.value = urlParams.get('origen').toUpperCase();
            }
        }
    }

    // Inicializar Tooltips (Primera carga)
    if (typeof bootstrap !== 'undefined') {
        [...app.querySelectorAll('[data-bs-toggle="tooltip"]')].forEach(el => new bootstrap.Tooltip(el));
    }

    // ========================================================================
    // 2. FUNCIÓN DE RECARGA SILENCIOSA (AJAX)
    // ========================================================================
    const cargarDatosAjax = async (urlStr) => {
        if (!contenedorTabla) return;
        contenedorTabla.style.opacity = '0.4';
        contenedorTabla.style.pointerEvents = 'none';

        try {
            const urlObj = new URL(urlStr);
            window.history.replaceState({}, '', urlStr);
            
            const response = await fetch(urlObj.toString(), { 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } 
            });
            
            if (!response.ok) throw new Error(`Error ${response.status}`);

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Reemplazamos SOLO la tarjeta de la tabla, sin tocar los filtros
            const nuevoContenedor = doc.getElementById('contenedorTablaMovimientos');
            if (nuevoContenedor) {
                contenedorTabla.innerHTML = nuevoContenedor.innerHTML;
            }

            // Reinicializar Tooltips y Buscador Cliente (ERPTable)
            if (typeof bootstrap !== 'undefined') {
                [...contenedorTabla.querySelectorAll('[data-bs-toggle="tooltip"]')].forEach(el => new bootstrap.Tooltip(el));
            }
            if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                window.ERPTable.autoInitFromDataset(app);
            }
        } catch (error) {
            console.error('❌ Error AJAX en Movimientos:', error);
            // Salvavidas: Si algo falla por red, forzamos recarga normal
            window.location.href = urlStr; 
        } finally {
            contenedorTabla.style.opacity = '1';
            contenedorTabla.style.pointerEvents = 'auto';
        }
    };

    const procesarFiltros = () => {
        if (!formFiltros) return;
        const formData = new FormData(formFiltros);
        const urlObj = new URL(formFiltros.action || window.location.href);
        
        // Limpiamos los parámetros actuales y reconstruimos con los del form
        urlObj.search = '';
        formData.forEach((value, key) => {
            if (value.trim() !== '') urlObj.searchParams.set(key, value.trim());
        });
        
        // Si el usuario cambia filtros manualmente, borramos anclajes específicos
        const currentUrlParams = new URLSearchParams(window.location.search);
        if (currentUrlParams.has('id_origen')) {
             urlObj.searchParams.delete('id_origen');
             urlObj.searchParams.delete('id_tercero');
        }

        cargarDatosAjax(urlObj.toString());
    };

    // ========================================================================
    // 3. LISTENERS DE FILTROS Y PAGINACIÓN
    // ========================================================================
    if (formFiltros) {
        formFiltros.addEventListener('change', (e) => {
            if (e.target.tagName === 'SELECT' || e.target.type === 'date') procesarFiltros();
        });

        formFiltros.addEventListener('input', (e) => {
            if (e.target.tagName === 'INPUT' && e.target.type !== 'date') {
                clearTimeout(timerFiltro);
                timerFiltro = setTimeout(procesarFiltros, 400);
            }
        });

        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            procesarFiltros();
        });
    }

    if (contenedorTabla) {
        contenedorTabla.addEventListener('click', (e) => {
            const linkPaginacion = e.target.closest('.pagination a.page-link');
            if (linkPaginacion) {
                e.preventDefault();
                cargarDatosAjax(linkPaginacion.href);
            }
        });
    }

    // ========================================================================
    // 4. ANULACIÓN SILENCIOSA DE MOVIMIENTOS
    // ========================================================================
    app.addEventListener('submit', async (e) => {
        const formConfirm = e.target.closest('.js-form-confirm');
        if (formConfirm) {
            e.preventDefault(); // Detiene el envío que causaba el parpadeo

            const result = await Swal.fire({
                title: '¿Estás seguro?',
                text: "Se anulará este movimiento de tesorería y el saldo de la cuenta se recalculará. Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-slash-circle me-1"></i> Sí, anular',
                cancelButtonText: 'Cancelar',
                customClass: { popup: 'rounded-4 shadow-lg' }
            });

            if (result.isConfirmed) {
                try {
                    // Mostrar loading mientras procesa el PHP
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Anulando movimiento',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });

                    const formData = new FormData(formConfirm);
                    const response = await fetch(formConfirm.action, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        await Swal.fire('Anulado', data.mensaje, 'success');
                        procesarFiltros(); // Recarga la tabla sin parpadear
                    } else {
                        throw new Error(data.mensaje || 'Ocurrió un error en el servidor.');
                    }
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            }
        }
    });

    // ========================================================================
    // 5. LÓGICA DE TRANSFERENCIAS (Mantenida intacta para otras vistas)
    // ========================================================================
    const selectOrigen = document.getElementById('selectCuentaOrigenTransferencia');
    const inputMonto = document.getElementById('inputMontoTransferencia');

    if (selectOrigen && inputMonto) {
        const formTransferencia = selectOrigen.closest('form');

        selectOrigen.addEventListener('change', function() {
            const opcionSeleccionada = this.options[this.selectedIndex];
            const saldoDisponible = parseFloat(opcionSeleccionada.getAttribute('data-saldo')) || 0;
            
            inputMonto.setAttribute('max', saldoDisponible);
            
            if (parseFloat(inputMonto.value) > saldoDisponible) {
                inputMonto.value = ''; 
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Monto reajustado',
                        text: 'El monto ingresado superaba el saldo de la nueva cuenta.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                }
            }
        });

        if (formTransferencia) {
            formTransferencia.addEventListener('submit', function(e) {
                const opcionSeleccionada = selectOrigen.options[selectOrigen.selectedIndex];
                const saldoDisponible = parseFloat(opcionSeleccionada.getAttribute('data-saldo')) || 0;
                const montoIngresado = parseFloat(inputMonto.value) || 0;

                if (montoIngresado <= 0) {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'El monto debe ser mayor a 0.' });
                    return;
                }

                if (montoIngresado > saldoDisponible) {
                    e.preventDefault();
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Saldo insuficiente', 
                        text: `La cuenta de origen solo dispone de ${saldoDisponible.toFixed(2)}.` 
                    });
                }
            });
        }
    }
})();