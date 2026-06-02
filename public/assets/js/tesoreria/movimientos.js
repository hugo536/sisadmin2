/**
 * Lógica específica para Tesorería - Movimientos
 * Archivo: assets/js/tesoreria/movimientos.js
 */
(function arrancarMovimientos() {
    'use strict';
    
    console.log("🚀 INICIANDO JS DE MOVIMIENTOS...");

    const app = document.getElementById('tesoreriaMovimientosApp');
    if (!app) {
        console.warn("⚠️ Abortando: No se encontró la caja principal 'tesoreriaMovimientosApp'");
        return;
    }

    const formFiltros = document.getElementById('formFiltrosMovimientos');
    const contenedorDinamico = document.getElementById('contenedorDinamicoMovimientos');
    let timerFiltro = null;

    // ========================================================================
    // NUEVO: DETECTAR REDIRECCIONES DIRECTAS (EJ: DESDE CxC o CxP)
    // ========================================================================
    if (formFiltros) {
        const urlParams = new URLSearchParams(window.location.search);
        const tieneOrigen = urlParams.has('origen') && urlParams.has('id_origen');
        
        // Si venimos de un enlace de "Ver Historial", las fechas por defecto del mes actual
        // pueden ocultar pagos antiguos. Si es el caso, limpiamos las fechas para ver TODO.
        if (tieneOrigen) {
            console.log("📍 Redirección detectada. Limpiando filtros de fecha predeterminados para asegurar visibilidad...");
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

    // 1. INICIALIZAR TOOLTIPS (Primera carga)
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    // --- DIAGNÓSTICO EN CONSOLA ---
    if (!formFiltros) console.error("❌ ERROR FATAL: No se encuentra el ID 'formFiltrosMovimientos' en la vista PHP.");
    if (!contenedorDinamico) console.error("❌ ERROR FATAL: No se encuentra el ID 'contenedorDinamicoMovimientos' en la vista PHP. (¿Olvidaste envolver la tabla y la tarjeta en el nuevo div?)");

    if (formFiltros && contenedorDinamico) {
        console.log("✅ Elementos DOM detectados correctamente. Filtros listos para actuar.");
        
        const cargarDatosAjax = async (urlStr) => {
            contenedorDinamico.style.opacity = '0.4';
            contenedorDinamico.style.pointerEvents = 'none';

            try {
                const urlObj = new URL(urlStr);
                urlObj.searchParams.set('ajax', '1'); // Forzamos un parámetro para evitar caché

                window.history.replaceState({}, '', urlStr);
                const response = await fetch(urlObj.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                
                if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevoContenedor = doc.getElementById('contenedorDinamicoMovimientos');
                
                if (nuevoContenedor) {
                    contenedorDinamico.innerHTML = nuevoContenedor.innerHTML;
                    console.log("🔄 Tabla actualizada con éxito.");
                } else {
                    console.error("❌ No se encontró la tabla en la respuesta del servidor.");
                }

                // Recargar tooltips de los nuevos elementos traídos por AJAX
                if (typeof bootstrap !== 'undefined') {
                    [].slice.call(contenedorDinamico.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }
                if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                    window.ERPTable.autoInitFromDataset(app);
                }
            } catch (error) {
                console.error('❌ Error AJAX en Movimientos:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.message, confirmButtonText: 'Entendido' });
                }
            } finally {
                contenedorDinamico.style.opacity = '1';
                contenedorDinamico.style.pointerEvents = 'auto';
            }
        };

        const procesarFiltros = () => {
            const formData = new FormData(formFiltros);
            const urlObj = new URL(formFiltros.action);
            
            formData.forEach((value, key) => {
                if (value.trim() !== '') urlObj.searchParams.set(key, value.trim());
                else urlObj.searchParams.delete(key);
            });
            
            // Si el usuario cambia los filtros manualmente, nos aseguramos de borrar el id_origen
            // de la URL para que no se quede pegado buscando eternamente esa factura.
            const currentUrlParams = new URLSearchParams(window.location.search);
            if (currentUrlParams.has('id_origen')) {
                 urlObj.searchParams.delete('id_origen');
                 urlObj.searchParams.delete('id_tercero');
            }

            console.log("📍 AJAX Enviado a:", urlObj.toString());
            cargarDatosAjax(urlObj.toString());
        };

        // Escuchamos el cambio en los filtros
        formFiltros.addEventListener('change', (e) => {
            console.log("Filtro modificado:", e.target.name);
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

        contenedorDinamico.addEventListener('click', (e) => {
            const linkPaginacion = e.target.closest('.pagination a.page-link');
            if (linkPaginacion) {
                e.preventDefault();
                cargarDatosAjax(linkPaginacion.href);
            }
        });

        // 2. INTERCEPTAR BOTONES DE ANULAR (Delegación de eventos para que funcione con AJAX)
        contenedorDinamico.addEventListener('submit', (e) => {
            const formConfirm = e.target.closest('.js-form-confirm');
            
            if (formConfirm) {
                e.preventDefault(); // Pausamos el envío al servidor

                Swal.fire({
                    title: '¿Estás completamente seguro?',
                    text: "Se anulará este movimiento de tesorería y el saldo de la cuenta se recalculará. Esta acción no se puede deshacer.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-slash-circle me-1"></i> Sí, anular',
                    cancelButtonText: 'Cancelar',
                    customClass: { popup: 'rounded-4 shadow-lg' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Anulando...',
                            text: 'Por favor espera un momento.',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        // Enviamos el formulario específico que activó el evento
                        formConfirm.submit(); 
                    }
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
    const selectOrigen = document.getElementById('selectCuentaOrigenTransferencia');
    const inputMonto = document.getElementById('inputMontoTransferencia');

    if (selectOrigen && inputMonto) {
        const formTransferencia = selectOrigen.closest('form');

        // 1. Lógica dinámica: Ajustar el monto máximo cuando el usuario cambia de cuenta
        selectOrigen.addEventListener('change', function() {
            const opcionSeleccionada = this.options[this.selectedIndex];
            const saldoDisponible = parseFloat(opcionSeleccionada.getAttribute('data-saldo')) || 0;
            
            // Le ponemos el límite al input en tiempo real
            inputMonto.setAttribute('max', saldoDisponible);
            
            // Si el usuario ya había escrito un monto y cambia a una cuenta con menos plata, 
            // le borramos el monto para que no haya errores
            if (parseFloat(inputMonto.value) > saldoDisponible) {
                inputMonto.value = ''; 
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Monto reajustado',
                        text: 'El monto ingresado superaba el saldo de la nueva cuenta seleccionada.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                }
            }
        });

        // 2. Lógica de envío: Validar por última vez antes de mandar al servidor
        if (formTransferencia) {
            formTransferencia.addEventListener('submit', function(e) {
                const opcionSeleccionada = selectOrigen.options[selectOrigen.selectedIndex];
                const saldoDisponible = parseFloat(opcionSeleccionada.getAttribute('data-saldo')) || 0;
                const montoIngresado = parseFloat(inputMonto.value) || 0;

                // Bloqueo: Monto cero o negativo
                if (montoIngresado <= 0) {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'El monto debe ser mayor a 0.' });
                    return;
                }

                // Bloqueo: Monto superior al saldo (Evita cuenta en negativo)
                if (montoIngresado > saldoDisponible) {
                    e.preventDefault();
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Saldo insuficiente', 
                        text: `La cuenta de origen solo dispone de ${saldoDisponible.toFixed(2)}. No puedes transferir ${montoIngresado.toFixed(2)}.` 
                    });
                }
            });
        }
    }
});
})();