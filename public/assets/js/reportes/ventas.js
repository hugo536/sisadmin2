/**
 * LÓGICA CENTRALIZADA PARA REPORTES DE VENTAS
 * Archivo: public/assets/js/reportes/ventas.js
 */

if (typeof window.inicializarModuloReporteVentas === 'undefined') {

    // 1. FUNCIÓN INDEPENDIENTE PARA BUSCADORES AJAX
    window.inicializarBuscadoresAjax = function() {
        if (typeof window.TomSelect === 'undefined') return;

        const clienteSelect = document.getElementById('filtroVentasCliente');
        const tipoTerceroSelect = document.getElementById('filtroVentasTipoTercero');
        const productoSelect = document.getElementById('filtroVentasProducto');

        const initTS = (el, actionStr, depEl = null, depParam = '') => {
            if (el && !el.tomselect) {
                new TomSelect(el, {
                    valueField: 'id', 
                    // ¡EL SECRETO ESTÁ AQUÍ! TomSelect lee el HTML como 'text'
                    labelField: 'text', 
                    searchField: ['text'], 
                    placeholder: 'Escriba para buscar...', 
                    maxOptions: 50, 
                    create: false,
                    allowEmptyOption: true,
                    plugins: ['clear_button'], // Agregamos el botón "X" para limpiar filtro
                    load(query, callback) {
                        const u = new URL(window.location.origin + window.location.pathname);
                        u.searchParams.set('ruta', 'reportes/ventas');
                        u.searchParams.set('accion', actionStr);
                        u.searchParams.set('q', query || '');
                        if(depEl && depParam) u.searchParams.set(depParam, depEl.value || '');
                        
                        fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(json => {
                                const data = Array.isArray(json?.data) ? json.data : [];
                                // Mapeamos el 'nombre' de tu BD a 'text' para que TomSelect lo entienda
                                const mappedData = data.map(item => ({
                                    id: item.id,
                                    text: actionStr === 'buscar_productos' ? item.nombre : item.nombre_completo,
                                    ...item
                                }));
                                callback(mappedData);
                            })
                            .catch(() => callback());
                    },
                    onChange: function() { 
                        // Al seleccionar un producto, disparamos el auto-submit
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    },
                    onInitialize() { 
                        if (!this.getValue()) this.clear(true); 
                    }
                });
            }
        };

        initTS(clienteSelect, 'buscar_clientes', tipoTerceroSelect, 'tipo_tercero');
        initTS(productoSelect, 'buscar_productos');
    };


    // 2. FUNCIÓN PRINCIPAL DEL MÓDULO
    window.inicializarModuloReporteVentas = function() {
        
        // Inicializamos los buscadores (100ms de retraso para asegurar que el DOM cargó)
        setTimeout(window.inicializarBuscadoresAjax, 100);

        const formId = 'formFiltrosReporteVentas';
        const formReporte = document.getElementById(formId);

        // Candado para evitar duplicar eventos globales
        if (!formReporte || formReporte.getAttribute('data-js-init') === 'true') {
            return;
        }
        formReporte.setAttribute('data-js-init', 'true');

        // --- FUNCIÓN PARA ENVIAR FILTROS (SOPORTE SPA) ---
        const submitReporteFiltros = () => {
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

        // --- TEMPORIZADOR AUTO-SUBMIT ---
        const autoSubmitReporte = (() => {
            let timer = null;
            return (delay = 350) => {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(() => submitReporteFiltros(), delay);
            };
        })();

        // --- EVENTOS DEL FORMULARIO ---
        formReporte.addEventListener('submit', (event) => {
            if(event.submitter && (event.submitter.name === 'exportar_pdf' || event.submitter.name === 'exportar_excel' || event.submitter.formTarget === '_blank')) {
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

        // --- BOTÓN LIMPIAR FILTROS ---
        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                const seccion = document.getElementById('input_seccion_activa').value;
                const baseUrl = formReporte.action.split('?')[0];
                const destino = new URL(baseUrl, window.location.origin);
                
                destino.searchParams.set('ruta', 'reportes/ventas');
                destino.searchParams.set('seccion_activa', seccion);
                
                if (typeof window.navigateWithoutReload === 'function') {
                    window.navigateWithoutReload(destino, true);
                } else {
                    window.location.href = destino.toString();
                }
            });
        }

        // --- LÓGICA DE PESTAÑAS (TABS) EN SPA ---
        const linksTabs = document.querySelectorAll('.nav-tabs .nav-link');
        const inputSeccion = document.getElementById('input_seccion_activa');

        linksTabs.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href.includes('seccion_activa=')) {
                    e.preventDefault(); 
                    const urlObj = new URL(href, window.location.origin);
                    const seccionSeleccionada = urlObj.searchParams.get('seccion_activa');
                    
                    if (inputSeccion && inputSeccion.value !== seccionSeleccionada) {
                        inputSeccion.value = seccionSeleccionada;
                        formReporte.querySelectorAll('input[required]').forEach(f => f.required = false);
                        submitReporteFiltros();
                    }
                }
            });
        });

        // --- INICIALIZACIÓN DEL GRÁFICO (CHART.JS) ---
        const canvasGrafico = document.getElementById('ventasPeriodoChart');
        if (canvasGrafico && typeof Chart !== 'undefined') {
            try {
                const chartDataStr = canvasGrafico.getAttribute('data-chart-data');
                const chartData = chartDataStr ? JSON.parse(chartDataStr) : [];
                const tipoGrafico = canvasGrafico.getAttribute('data-chart-type') || 'bar';
                
                if(chartData.length > 0) {
                    const labels = chartData.map(r => {
                        let etiqueta = String(r.etiqueta ?? '');
                        if (/^\d{4}-\d{2}-\d{2}$/.test(etiqueta)) {
                            const partes = etiqueta.split('-');
                            return `${partes[2]}/${partes[1]}/${partes[0]}`;
                        }
                        return etiqueta;
                    });
                    const data = chartData.map(r => Number(r.total_vendido ?? 0));

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
            } catch (err) {
                console.error("Error inicializando Chart.js:", err);
            }
        }
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE ---
    document.addEventListener('DOMContentLoaded', window.inicializarModuloReporteVentas);
    document.addEventListener('sisadmin:route-loaded', window.inicializarModuloReporteVentas);
}