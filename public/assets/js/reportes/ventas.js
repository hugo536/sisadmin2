/**
 * LÓGICA CENTRALIZADA PARA REPORTES DE VENTAS
 * Archivo: public/assets/js/reportes/ventas.js
 */

if (typeof window.inicializarModuloReporteVentas === 'undefined') {
    
    window.inicializarModuloReporteVentas = function() {
        const formId = 'formFiltrosReporteVentas';
        const formReporte = document.getElementById(formId);

        // Validamos que el formulario exista y que no se haya inicializado ya (vital para SPA)
        if (!formReporte || formReporte.getAttribute('data-js-init') === 'true') {
            return;
        }
        formReporte.setAttribute('data-js-init', 'true');

        // --- 1. FUNCIÓN PARA ENVIAR FILTROS (SOPORTE SPA) ---
        const submitReporteFiltros = () => {
            // Validación de fechas antes de enviar
            const desde = formReporte.querySelector('input[name="fecha_desde"]');
            const hasta = formReporte.querySelector('input[name="fecha_hasta"]');
            
            if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Rango inválido',
                        text: 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".',
                        confirmButtonColor: '#0B5ED7'
                    });
                } else {
                    alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
                }
                return false;
            }

            const params = new URLSearchParams(new FormData(formReporte));
            const baseUrl = formReporte.action.split('?')[0];
            const destino = new URL(baseUrl, window.location.origin);
            destino.search = params.toString();

            if (typeof window.navigateWithoutReload === 'function') {
                window.navigateWithoutReload(destino, true);
            } else {
                window.location.href = destino.toString();
            }
        };

        // --- 2. TEMPORIZADOR Y EVENTOS DE FORMULARIO (AUTO-SUBMIT) ---
        const autoSubmitReporte = (() => {
            let timer = null;
            return (delay = 350) => {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(() => submitReporteFiltros(), delay);
            };
        })();

        formReporte.addEventListener('submit', (event) => {
            // Interceptar el submit general, PERO permitir que los botones de "Exportar a PDF" 
            // (que usan formtarget="_blank") funcionen de forma nativa sin que SPA los bloquee.
            if(event.submitter && (event.submitter.name === 'exportar_pdf' || event.submitter.formTarget === '_blank')) {
                return; 
            }
            event.preventDefault();
            submitReporteFiltros();
        });

        // Escuchar cambios en campos estáticos
        const filtrosAutoSubmit = formReporte.querySelectorAll('.auto-submit');
        filtrosAutoSubmit.forEach((field) => {
            field.addEventListener('change', () => autoSubmitReporte(150));
        });

        // --- 3. LÓGICA DE PESTAÑAS (TABS) ---
        const botonesTabs = document.querySelectorAll('.btn-tab-seccion');
        const inputSeccion = document.getElementById('input_seccion_activa');

        botonesTabs.forEach(boton => {
            boton.addEventListener('click', function() {
                const seccionSeleccionada = this.getAttribute('data-seccion');
                if (inputSeccion && inputSeccion.value !== seccionSeleccionada) {
                    inputSeccion.value = seccionSeleccionada;
                    // Deshabilitar 'required' temporalmente para navegar entre pestañas libremente
                    formReporte.querySelectorAll('input[required]').forEach(f => f.required = false);
                    submitReporteFiltros();
                }
            });
        });

        // --- 4. INTEGRACIÓN DE TOMSELECT (BÚSQUEDA AJAX) ---
        const inicializarTomSelect = async () => {
            for (let i = 0; i < 20; i++) {
                if (typeof window.TomSelect !== 'undefined') break;
                await new Promise(r => setTimeout(r, 120));
            }
            if(typeof window.TomSelect === 'undefined') return;

            const clienteSelect = document.getElementById('filtroVentasCliente');
            const tipoTerceroSelect = document.getElementById('filtroVentasTipoTercero');
            const productoSelect = document.getElementById('filtroVentasProducto');

            // Buscador de Clientes
            if (clienteSelect && !clienteSelect.tomselect) {
                new TomSelect(clienteSelect, {
                    valueField: 'id', 
                    labelField: 'nombre_completo', 
                    searchField: ['nombre_completo', 'num_doc'],
                    placeholder: 'Buscar...', 
                    maxOptions: 50, 
                    create: false,
                    allowEmptyOption: true,
                    load(query, callback) {
                        const u = new URL(window.location.origin + window.location.pathname);
                        u.searchParams.set('ruta', 'reportes/ventas');
                        u.searchParams.set('accion', 'buscar_clientes');
                        u.searchParams.set('q', query || '');
                        u.searchParams.set('tipo_tercero', tipoTerceroSelect ? tipoTerceroSelect.value : '');
                        fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(r => callback(Array.isArray(r?.data) ? r.data : []))
                            .catch(() => callback());
                    },
                    onChange: () => autoSubmitReporte(150),
                    onInitialize() { if (!this.getValue()) this.clear(true); }
                });
            }

            // Buscador de Productos
            if (productoSelect && !productoSelect.tomselect) {
                new TomSelect(productoSelect, {
                    valueField: 'id', 
                    labelField: 'nombre', 
                    searchField: ['nombre', 'sku'],
                    placeholder: 'Buscar...', 
                    maxOptions: 50, 
                    create: false,
                    allowEmptyOption: true,
                    load(query, callback) {
                        const u = new URL(window.location.origin + window.location.pathname);
                        u.searchParams.set('ruta', 'reportes/ventas');
                        u.searchParams.set('accion', 'buscar_productos');
                        u.searchParams.set('q', query || '');
                        fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(r => callback(Array.isArray(r?.data) ? r.data : []))
                            .catch(() => callback());
                    },
                    onChange: () => autoSubmitReporte(150),
                    onInitialize() { if (!this.getValue()) this.clear(true); }
                });
            }
        };
        inicializarTomSelect();

        // --- 5. INICIALIZACIÓN DEL GRÁFICO (CHART.JS) ---
        const canvasGrafico = document.getElementById('ventasPeriodoChart');
        if (canvasGrafico && window.datosReporteVentas) {
            const chartData = window.datosReporteVentas.graficoPeriodo;
            
            if(chartData.length > 0) {
                const labels = chartData.map(r => String(r.etiqueta ?? ''));
                const data = chartData.map(r => Number(r.total_vendido ?? 0));
                const tipoGrafico = window.datosReporteVentas.tipoGrafico;

                new Chart(canvasGrafico, {
                    type: tipoGrafico,
                    data: {
                        labels,
                        datasets: [{
                            label: 'Total vendido (S/)',
                            data,
                            borderColor: '#198754',
                            backgroundColor: tipoGrafico === 'line' ? 'rgba(25,135,84,.15)' : 'rgba(25,135,84,.35)',
                            tension: .25,
                            fill: tipoGrafico === 'line',
                            pointRadius: tipoGrafico === 'line' ? 3 : 0,
                            borderRadius: tipoGrafico === 'bar' ? 6 : 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { 
                                callbacks: { 
                                    label(ctx) { return `S/ ${Number(ctx.parsed.y ?? 0).toFixed(2)}`; } 
                                } 
                            }
                        },
                        scales: {
                            y: { 
                                ticks: { 
                                    callback(value) { return `S/ ${Number(value).toFixed(0)}`; } 
                                } 
                            }
                        }
                    }
                });
            }
        }

        // --- 6. LÓGICA DE ACCIONES MASIVAS (PENDIENTES DE DESPACHO) ---
        const chkAll = document.getElementById('chkAllPendientes');
        const chks = document.querySelectorAll('.chk-pendiente');
        const btnPicking = document.getElementById('btnGenerarPickingList');
        const btnRuta = document.getElementById('btnAsignarRutaMasiva');
        const countPicking = document.getElementById('countPicking');
        const countRutas = document.getElementById('countRutas');
        const lblCantRutas = document.getElementById('lblCantRutasSeleccionadas');

        function actualizarBotonesMasivos() {
            if(!btnPicking || !btnRuta) return;
            
            const seleccionados = document.querySelectorAll('.chk-pendiente:checked').length;
            
            if (seleccionados > 0) {
                btnPicking.classList.remove('d-none');
                btnRuta.classList.remove('d-none');
                
                // Actualizamos contadores en botones e interiores del modal
                if(countPicking) countPicking.textContent = seleccionados;
                if(countRutas) countRutas.textContent = seleccionados;
                if(lblCantRutas) lblCantRutas.textContent = seleccionados;
            } else {
                btnPicking.classList.add('d-none');
                btnRuta.classList.add('d-none');
            }
        }

        // Checkbox "Seleccionar Todos"
        if(chkAll) {
            chkAll.addEventListener('change', function() {
                chks.forEach(chk => {
                    const tr = chk.closest('tr');
                    // Ignora los ocultos si el usuario usó la barra de búsqueda rápida
                    if(tr && tr.style.display !== 'none') {
                        chk.checked = chkAll.checked;
                    }
                });
                actualizarBotonesMasivos();
            });
        }

        // Checkbox individual
        chks.forEach(chk => {
            chk.addEventListener('change', function() {
                if(!this.checked && chkAll) chkAll.checked = false;
                actualizarBotonesMasivos();
            });
        });

        // Evento prototipo: Generar Picking List Consolidado
        if(btnPicking) {
            btnPicking.addEventListener('click', function() {
                const ids = Array.from(document.querySelectorAll('.chk-pendiente:checked')).map(cb => cb.value);
                console.log("Generando Picking List para los IDs:", ids);
                alert(`Generando Picking List consolidado para ${ids.length} pedidos...`);
            });
        }

        // Evento prototipo: Asignación Masiva
        const btnConfirmarAsignacionRuta = document.getElementById('btnConfirmarAsignacionRuta');
        const selectRuta = document.getElementById('selectRutaAsignacion');
        
        if(btnConfirmarAsignacionRuta && selectRuta) {
            btnConfirmarAsignacionRuta.addEventListener('click', function() {
                const rutaSeleccionada = selectRuta.value;
                if(!rutaSeleccionada) {
                    alert("Por favor, seleccione una ruta o vehículo.");
                    return;
                }

                const ids = Array.from(document.querySelectorAll('.chk-pendiente:checked')).map(cb => cb.value);
                console.log(`Asignando la ruta "${rutaSeleccionada}" a los IDs:`, ids);
                alert(`Ruta "${rutaSeleccionada}" asignada correctamente a ${ids.length} pedidos.`);
                
                const modalEl = document.getElementById('modalAsignarRuta');
                if(modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getInstance(modalEl).hide();
                }
            });
        }
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE (SOPORTA SPA Y F5) ---
    document.addEventListener('DOMContentLoaded', window.inicializarModuloReporteVentas);
    document.addEventListener('sisadmin:route-loaded', window.inicializarModuloReporteVentas);
}