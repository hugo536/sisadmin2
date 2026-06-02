/**
 * Lógica específica para Reporte de Tesorería - Movimientos
 * Archivo: public/assets/js/reportes/tesoreria_movimiento.js
 */
(function arrancarReporteMovimientos() {
    'use strict';
    
    console.log("🚀 INICIANDO JS DE REPORTE DE MOVIMIENTOS...");

    const app = document.getElementById('reporteTesoreriaMovApp');
    if (!app) {
        console.warn("⚠️ Abortando: No se encontró la caja principal 'reporteTesoreriaMovApp'");
        return;
    }

    const formFiltros = document.getElementById('formFiltrosMovimientos');
    const contenedorDinamico = document.getElementById('contenedorTablaMovimientos');
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
            console.log("📍 Redirección detectada. Limpiando filtros de fecha predeterminados...");
            const inputDesde = formFiltros.querySelector('input[name="fecha_desde"]');
            const inputHasta = formFiltros.querySelector('input[name="fecha_hasta"]');
            const selectOrigen = formFiltros.querySelector('select[name="origen"]');

            if (inputDesde) inputDesde.value = '';
            if (inputHasta) inputHasta.value = '';
            
            if (selectOrigen && urlParams.get('origen')) {
                selectOrigen.value = urlParams.get('origen').toUpperCase();
            }
        }
    }

    // ========================================================================
    // 2. INICIALIZAR TOOLTIPS (Primera carga)
    // ========================================================================
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    if (!formFiltros) console.error("❌ ERROR: No se encuentra el ID 'formFiltrosMovimientos'.");
    if (!contenedorDinamico) console.error("❌ ERROR: No se encuentra el ID 'contenedorTablaMovimientos'.");

    // ========================================================================
    // 3. LÓGICA DE FILTRADO Y RECARGA AJAX (SIN PARPADEO)
    // ========================================================================
    if (formFiltros && contenedorDinamico) {
        console.log("✅ Elementos DOM detectados correctamente. Filtros listos para actuar.");
        
        const cargarDatosAjax = async (urlStr) => {
            contenedorDinamico.style.opacity = '0.4';
            contenedorDinamico.style.pointerEvents = 'none';

            try {
                const urlObj = new URL(urlStr);
                urlObj.searchParams.set('ajax', '1'); // Forzamos un parámetro para evitar caché

                // Cambiamos la URL en el navegador para que el botón "Atrás" funcione
                window.history.replaceState({}, '', urlStr);
                
                const response = await fetch(urlObj.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                
                if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevoContenedor = doc.getElementById('contenedorTablaMovimientos');
                
                if (nuevoContenedor) {
                    contenedorDinamico.innerHTML = nuevoContenedor.innerHTML;
                } else {
                    console.error("❌ No se encontró la tabla en la respuesta del servidor.");
                }

                // Recargar tooltips de los nuevos elementos traídos por AJAX
                if (typeof bootstrap !== 'undefined') {
                    [].slice.call(contenedorDinamico.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }
                
                // Reinicializar script de tablas (búsqueda local y paginación) si existe en tu ecosistema
                if (typeof initProTables === 'function') {
                    initProTables();
                } else if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                    window.ERPTable.autoInitFromDataset(app);
                }
            } catch (error) {
                console.error('❌ Error AJAX en Movimientos:', error);
                // Si falla el AJAX, hace un fallback enviando el formulario normal
                formFiltros.submit(); 
            } finally {
                contenedorDinamico.style.opacity = '1';
                contenedorDinamico.style.pointerEvents = 'auto';
            }
        };

        const procesarFiltros = () => {
            const formData = new FormData(formFiltros);
            const urlObj = new URL(formFiltros.action);
            
            formData.forEach((value, key) => {
                if (value.trim() !== '') {
                    // Si la clave ya existe (ej. select múltiple), añadimos el nuevo valor sin sobreescribir
                    if (urlObj.searchParams.has(key)) {
                        urlObj.searchParams.append(key, value.trim());
                    } else {
                        urlObj.searchParams.set(key, value.trim());
                    }
                }
            });
            
            // Si el usuario cambia los filtros manualmente, nos aseguramos de borrar 
            // el id_origen de la URL para que no se quede pegado buscando siempre esa factura.
            const currentUrlParams = new URLSearchParams(window.location.search);
            if (currentUrlParams.has('id_origen')) {
                 urlObj.searchParams.delete('id_origen');
                 urlObj.searchParams.delete('id_tercero');
            }

            cargarDatosAjax(urlObj.toString());
        };

        // ========================================================================
        // 4. LÓGICA DE SELECCIONAR TODOS (MENÚS MÚLTIPLES CON BOTÓN)
        // ========================================================================
        document.querySelectorAll('.dropdown-multi').forEach(dropdown => {
            const chkTodos = dropdown.querySelector('.chk-todos');
            const chkItems = dropdown.querySelectorAll('.chk-item');
            const btnAplicar = dropdown.querySelector('.btn-aplicar-filtro');
            
            if(chkTodos && chkItems.length > 0) {
                
                // Solo actualiza la parte visual (casilla padre), NO hace AJAX
                const updateTodos = () => {
                    const todosMarcados = Array.from(chkItems).every(c => c.checked);
                    chkTodos.checked = todosMarcados;
                };
                
                // Evento para "Seleccionar Todas"
                chkTodos.addEventListener('change', function() {
                    const estadoFiltro = this.checked;
                    chkItems.forEach(c => c.checked = estadoFiltro);
                });
                
                // Evento para cada casilla individual
                chkItems.forEach(c => {
                    c.addEventListener('change', updateTodos);
                });

                // Iniciar estado visual
                updateTodos();

                // EVENTO MÁGICO: Botón "Aplicar Filtro"
                if (btnAplicar) {
                    btnAplicar.addEventListener('click', () => {
                        // 1. Cerrar el menú desplegable automáticamente
                        if (typeof bootstrap !== 'undefined') {
                            const btnToggle = dropdown.querySelector('.dropdown-toggle');
                            const bsDropdown = bootstrap.Dropdown.getInstance(btnToggle) || new bootstrap.Dropdown(btnToggle);
                            bsDropdown.hide();
                        }
                        
                        // 2. Disparar la recarga AJAX
                        if (formFiltros.checkValidity()) {
                            procesarFiltros(); 
                        } else {
                            formFiltros.reportValidity();
                        }
                    });
                }
            }
        });

        // Escuchar elementos que tengan la clase auto-submit (los selects y fechas en la vista)
        formFiltros.querySelectorAll('.auto-submit').forEach(input => {
            input.addEventListener('change', function() {
                if(formFiltros.checkValidity()) {
                    procesarFiltros();
                } else {
                    formFiltros.reportValidity();
                }
            });
        });

        formFiltros.addEventListener('submit', (e) => {
            // Excepción Vital: Si el clic fue en el botón de "PDF", permitimos el flujo normal
            if (e.submitter && e.submitter.value === '1' && e.submitter.name === 'exportar_pdf') {
                return; 
            }
            e.preventDefault();
            procesarFiltros();
        });

        // ========================================================================
        // 5. INTERCEPTAR EVENTOS DENTRO DE LA TABLA (Delegación)
        // ========================================================================
        
        // A) Paginación vía AJAX
        contenedorDinamico.addEventListener('click', (e) => {
            const linkPaginacion = e.target.closest('.pagination a.page-link');
            if (linkPaginacion) {
                e.preventDefault();
                cargarDatosAjax(linkPaginacion.href);
            }
        });

        // B) Botones de Anulación
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
})();