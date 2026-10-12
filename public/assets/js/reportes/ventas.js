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
            // Interceptar el submit general, PERO permitir exportar PDF (formtarget="_blank") nativamente
            if(event.submitter && (event.submitter.name === 'exportar_pdf' || event.submitter.formTarget === '_blank')) {
                return; 
            }
            event.preventDefault();
            submitReporteFiltros();
        });

        // AUTO-FILTRADO EXCLUSIVO PARA SELECTS (Excluye inputs de fecha)
        const selectsFiltro = formReporte.querySelectorAll('select');
        selectsFiltro.forEach((select) => {
            select.addEventListener('change', () => autoSubmitReporte(100));
        });

        // BOTÓN BORRADOR DE FECHAS (El botón rojo)
        const btnBorrarFechas = formReporte.querySelector('.btn-danger'); // Asegúrate de que tu botón rojo use esta clase
        if (btnBorrarFechas) {
            btnBorrarFechas.addEventListener('click', (e) => {
                e.preventDefault();
                const inputDesde = formReporte.querySelector('input[name="fecha_desde"]');
                const inputHasta = formReporte.querySelector('input[name="fecha_hasta"]');
                
                let cambio = false;
                if (inputDesde && inputDesde.value !== '') { inputDesde.value = ''; cambio = true; }
                if (inputHasta && inputHasta.value !== '') { inputHasta.value = ''; cambio = true; }
                
                // Solo recargamos si realmente había fechas que borrar
                if (cambio) autoSubmitReporte(100);
            });
        }

        // --- 3. LÓGICA DE PESTAÑAS (TABS) ---
        const botonesTabs = document.querySelectorAll('.btn-tab-seccion');
        const inputSeccion = document.getElementById('input_seccion_activa');

        botonesTabs.forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault(); // ESTO EVITA EL PARPADEO Y SALTO DE PÁGINA
                const seccionSeleccionada = this.getAttribute('data-seccion');
                if (inputSeccion && inputSeccion.value !== seccionSeleccionada) {
                    inputSeccion.value = seccionSeleccionada;
                    // Deshabilitar 'required' temporalmente para navegar libremente
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

            const initTomSelectBuscador = (elemento, accionBackend, searchFields) => {
                if (elemento && !elemento.tomselect) {
                    new TomSelect(elemento, {
                        valueField: 'id', 
                        labelField: searchFields[0], 
                        searchField: searchFields,
                        placeholder: 'Buscar...', 
                        maxOptions: 50, 
                        create: false,
                        allowEmptyOption: true,
                        load(query, callback) {
                            const u = new URL(window.location.origin + window.location.pathname);
                            u.searchParams.set('ruta', 'reportes/ventas');
                            u.searchParams.set('accion', accionBackend);
                            u.searchParams.set('q', query || '');
                            if (tipoTerceroSelect) u.searchParams.set('tipo_tercero', tipoTerceroSelect.value);
                            
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

            initTomSelectBuscador(clienteSelect, 'buscar_clientes', ['nombre_completo', 'num_doc']);
            initTomSelectBuscador(productoSelect, 'buscar_productos', ['nombre', 'sku']);
        };
        inicializarTomSelect();

        // --- 5. INICIALIZACIÓN DE LOS GRÁFICOS (CHART.JS - FIX PARA SPA) ---
        
        // 5.1. Gráfico de Tendencias (Líneas o Barras)
        const canvasPeriodo = document.getElementById('ventasPeriodoChart');
        if (canvasPeriodo) {
            if (window.graficoPeriodoInst instanceof Chart) window.graficoPeriodoInst.destroy();
            try {
                const dataRaw = canvasPeriodo.getAttribute('data-chart-data');
                const arr = dataRaw ? JSON.parse(dataRaw) : [];
                if (arr.length > 0) {
                    const tipo = canvasPeriodo.getAttribute('data-chart-type') || 'line';
                    
                    // --- FORMATEO DE FECHA A DD/MM/YYYY ---
                    const labels = arr.map(r => {
                        let etiqueta = String(r.etiqueta ?? '');
                        // Si la fecha viene como YYYY-MM-DD, la invertimos
                        if (/^\d{4}-\d{2}-\d{2}$/.test(etiqueta)) {
                            const partes = etiqueta.split('-');
                            return `${partes[2]}/${partes[1]}/${partes[0]}`;
                        }
                        return etiqueta;
                    });
                    const data = arr.map(r => Number(r.total_vendido ?? 0));

                    window.graficoPeriodoInst = new Chart(canvasPeriodo, {
                        type: tipo,
                        data: {
                            labels: labels, // <- Usamos nuestras etiquetas formateadas
                            datasets: [{
                                label: 'Total vendido (S/)',
                                data: data,
                                borderColor: '#198754',
                                backgroundColor: tipo === 'line' ? 'rgba(25,135,84,.15)' : 'rgba(25,135,84,.35)',
                                tension: .25,
                                fill: tipo === 'line',
                                pointRadius: tipo === 'line' ? 3 : 0,
                                borderRadius: tipo === 'bar' ? 4 : 0,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false },
                                tooltip: { callbacks: { label(ctx) { return `S/ ${Number(ctx.parsed.y ?? 0).toFixed(2)}`; } } }
                            },
                            scales: { 
                                y: { beginAtZero: true, ticks: { callback(value) { return `S/ ${Number(value).toFixed(0)}`; } } } 
                            }
                        }
                    });
                }
            } catch (e) { console.error("Error Chart Periodo:", e); }
        }

        // 5.2. Gráfico de Clientes (Dona / Pastel)
        const canvasClientes = document.getElementById('ventasClientesChart');
        if (canvasClientes) {
            if (window.graficoClientesInst instanceof Chart) window.graficoClientesInst.destroy();
            try {
                const dataRaw = canvasClientes.getAttribute('data-chart-data');
                const arr = dataRaw ? JSON.parse(dataRaw) : [];
                if (arr.length > 0) {
                    window.graficoClientesInst = new Chart(canvasClientes, {
                        type: 'doughnut',
                        data: {
                            labels: arr.map(r => String(r.cliente ?? 'Desconocido')),
                            datasets: [{
                                data: arr.map(r => Number(r.total_vendido ?? 0)),
                                backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#0dcaf0', '#adb5bd'],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'right' } } // Leyenda a la derecha
                        }
                    });
                }
            } catch (e) { console.error("Error Chart Clientes:", e); }
        }

        // 5.3. Gráfico de Top Productos (Barras Horizontales)
        const canvasProductos = document.getElementById('ventasProductosChart');
        if (canvasProductos) {
            if (window.graficoProductosInst instanceof Chart) window.graficoProductosInst.destroy();
            try {
                const dataRaw = canvasProductos.getAttribute('data-chart-data');
                const arr = dataRaw ? JSON.parse(dataRaw) : [];
                if (arr.length > 0) {
                    window.graficoProductosInst = new Chart(canvasProductos, {
                        type: 'bar',
                        data: {
                            labels: arr.map(r => String(r.producto ?? 'Desconocido')),
                            datasets: [{
                                label: 'Monto Generado (S/)',
                                data: arr.map(r => Number(r.total_monto ?? 0)),
                                backgroundColor: 'rgba(13, 110, 253, 0.75)',
                                borderColor: '#0d6efd',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            indexAxis: 'y', // ESTO CONVIERTE EL GRÁFICO EN BARRAS HORIZONTALES
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true } }
                        }
                    });
                }
            } catch (e) { console.error("Error Chart Productos:", e); }
        }
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE (SOPORTA SPA Y F5) ---
    document.addEventListener('DOMContentLoaded', window.inicializarModuloReporteVentas);
    document.addEventListener('sisadmin:route-loaded', window.inicializarModuloReporteVentas);
}