/**
 * LÓGICA CENTRALIZADA PARA REPORTES DE COMPRAS
 * Archivo: public/assets/js/reportes/compras.js
 */

if (typeof window.inicializarModuloReporteCompras === 'undefined') {

    // 1. FUNCIÓN INDEPENDIENTE PARA BUSCADORES AJAX EN COMPRAS
    window.inicializarBuscadoresAjaxCompras = function() {
        if (typeof window.TomSelect === 'undefined') return;

        // Capturamos los selects por su atributo name (ya que se usan en las vistas de insumos/variación)
        const formReporte = document.getElementById('formFiltrosReporteCompras');
        if (!formReporte) return;

        const productoSelect = formReporte.querySelector('select[name="id_item"]');
        const proveedorSelect = formReporte.querySelector('select[name="id_proveedor"]');
        const categoriaSelect = formReporte.querySelector('select[name="id_categoria"]');

        const initTS = (el, actionStr) => {
            if (el && !el.tomselect) {
                new TomSelect(el, {
                    valueField: 'id', 
                    labelField: 'text', 
                    searchField: ['text'], 
                    placeholder: 'Escriba para buscar...', 
                    maxOptions: 50, 
                    create: false,
                    allowEmptyOption: true,
                    plugins: ['clear_button'],
                    
                    render: {
                        item: function(data, escape) {
                            return '<div class="text-truncate" title="' + escape(data.text) + '" style="max-width: calc(100% - 24px);">' + escape(data.text) + '</div>';
                        },
                        option: function(data, escape) {
                            return '<div class="text-truncate" title="' + escape(data.text) + '">' + escape(data.text) + '</div>';
                        }
                    },

                    load(query, callback) {
                        const u = new URL(window.location.origin + window.location.pathname);
                        u.searchParams.set('ruta', 'reportes/compras'); // Ajustado para compras
                        u.searchParams.set('accion', actionStr);
                        u.searchParams.set('q', query || '');
                        
                        // Si estamos buscando productos, enviamos la categoría seleccionada al backend
                        if (actionStr === 'buscar_insumos' && categoriaSelect && categoriaSelect.value) {
                            u.searchParams.set('id_categoria', categoriaSelect.value);
                        }
                        
                        fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(json => {
                                const data = Array.isArray(json?.data) ? json.data : [];
                                const mappedData = data.map(item => ({
                                    id: item.id,
                                    text: item.nombre || item.nombre_completo,
                                    ...item
                                }));
                                callback(mappedData);
                            })
                            .catch(() => callback());
                    },
                    onChange: function() { 
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    },
                    onInitialize() { 
                        if (!this.getValue()) this.clear(true); 
                    }
                });
            }
        };

        if (productoSelect) {
            // Cambiamos la acción a buscar_insumos
            initTS(productoSelect, 'buscar_insumos');
        }
        if (proveedorSelect) {
            initTS(proveedorSelect, 'buscar_proveedores');
        }
    };

    // 2. FUNCIÓN PRINCIPAL DEL MÓDULO DE COMPRAS
    window.inicializarModuloReporteCompras = function() {
        
        // Inicializamos los buscadores
        setTimeout(window.inicializarBuscadoresAjaxCompras, 100);

        const formId = 'formFiltrosReporteCompras';
        const formReporte = document.getElementById(formId);

        if (!formReporte || formReporte.getAttribute('data-js-init') === 'true') {
            return;
        }
        formReporte.setAttribute('data-js-init', 'true');

        // --- FUNCIÓN PARA ENVIAR FILTROS ---
        const submitReporteFiltros = () => {
            const desde = formReporte.querySelector('input[name="fecha_desde"]');
            const hasta = formReporte.querySelector('input[name="fecha_hasta"]');
            
            if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Rango inválido',
                        text: 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".',
                        confirmButtonColor: '#0d6efd' // Color primary azul
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

        formReporte.addEventListener('submit', (event) => {
            if(event.submitter && (event.submitter.name === 'exportar_pdf' || event.submitter.name === 'exportar_excel' || event.submitter.formTarget === '_blank')) {
                return; 
            }
            event.preventDefault();
            submitReporteFiltros();
        });

        const filtrosAutoSubmit = formReporte.querySelectorAll('.auto-submit');
        filtrosAutoSubmit.forEach((field) => {
            field.addEventListener('change', () => autoSubmitReporte(150));
        });

        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                const seccion = document.getElementById('input_seccion_activa').value;
                const baseUrl = formReporte.action.split('?')[0];
                const destino = new URL(baseUrl, window.location.origin);
                
                destino.searchParams.set('ruta', 'reportes/compras');
                destino.searchParams.set('seccion_activa', seccion);
                
                if (typeof window.navigateWithoutReload === 'function') {
                    window.navigateWithoutReload(destino, true);
                } else {
                    window.location.href = destino.toString();
                }
            });
        }

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

        // =======================================================
        // INICIALIZACIÓN SEGURA DE GRÁFICOS (CHART.JS PARA SPA)
        // =======================================================
        if (typeof Chart !== 'undefined') {

            const crearGraficoSeguroSPA = (canvasElement, config) => {
                const chartInstance = new Chart(canvasElement, config);
                const observer = new MutationObserver(() => {
                    if (!document.body.contains(canvasElement)) {
                        chartInstance.destroy(); 
                        observer.disconnect();   
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                return chartInstance;
            };

            // 1. GRÁFICO DE TENDENCIAS DE COMPRAS
            const canvasTendencias = document.getElementById('comprasPeriodoChart');
            if (canvasTendencias) {
                try {
                    const chartData = JSON.parse(canvasTendencias.getAttribute('data-chart-data') || '[]');
                    const tipoGrafico = canvasTendencias.getAttribute('data-chart-type') || 'bar';
                    
                    if(chartData.length > 0) {
                        const labels = chartData.map(r => {
                            let etiqueta = String(r.etiqueta ?? '');
                            if (/^\d{4}-\d{2}-\d{2}$/.test(etiqueta)) {
                                const partes = etiqueta.split('-');
                                return `${partes[2]}/${partes[1]}/${partes[0]}`;
                            }
                            return etiqueta;
                        });
                        const data = chartData.map(r => Number(r.total_comprado ?? 0)); // Usando total_comprado

                        crearGraficoSeguroSPA(canvasTendencias, {
                            type: tipoGrafico,
                            data: {
                                labels,
                                datasets: [{
                                    label: 'Total comprado (S/)',
                                    data,
                                    borderColor: '#0d6efd', // Azul primario
                                    backgroundColor: tipoGrafico === 'line' ? 'rgba(13,110,253,.15)' : 'rgba(13,110,253,.8)',
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
                } catch (err) { console.error("Error Chart Tendencias Compras:", err); }
            }

            // 2. GRÁFICO TOP INSUMOS (DOUGHNUT)
            const canvasInsumos = document.getElementById('comprasInsumosChart');
            if (canvasInsumos) {
                try {
                    const chartData = JSON.parse(canvasInsumos.getAttribute('data-chart-data') || '[]');
                    if (chartData.length > 0) {
                        const topN = chartData.slice(0, 5);
                        const otros = chartData.slice(5).reduce((acc, curr) => acc + Number(curr.total_monto || 0), 0);
                        
                        const labels = topN.map(r => {
                            let nombre = r.producto || '';
                            return nombre.length > 25 ? nombre.substring(0, 25) + '...' : nombre; 
                        });
                        const data = topN.map(r => Number(r.total_monto || 0));
                        
                        if (otros > 0) {
                            labels.push('OTROS INSUMOS');
                            data.push(otros);
                        }

                        crearGraficoSeguroSPA(canvasInsumos, {
                            type: 'doughnut',
                            data: {
                                labels: labels,
                                datasets: [{
                                    data: data,
                                    backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#fd7e14', '#6f42c1', '#adb5bd'],
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '65%',
                                plugins: {
                                    legend: { position: 'bottom' },
                                    tooltip: {
                                        callbacks: {
                                            label(ctx) { return ` S/ ${Number(ctx.parsed).toFixed(2)}`; }
                                        }
                                    }
                                }
                            }
                        });
                    }
                } catch (err) { console.error("Error Chart Insumos:", err); }
            }
            // 3. GRÁFICO CONCENTRACIÓN DE PROVEEDORES (DOUGHNUT)
            const canvasProveedores = document.getElementById('comprasProveedoresChart');
            if (canvasProveedores) {
                try {
                    const chartData = JSON.parse(canvasProveedores.getAttribute('data-chart-data') || '[]');
                    if (chartData.length > 0) {
                        // Tomamos los 5 proveedores principales y agrupamos el resto en "Otros"
                        const topN = chartData.slice(0, 5);
                        const otros = chartData.slice(5).reduce((acc, curr) => acc + Number(curr.total_recibido || 0), 0);
                        
                        const labels = topN.map(r => {
                            let nombre = r.proveedor || '';
                            return nombre.length > 25 ? nombre.substring(0, 25) + '...' : nombre; 
                        });
                        const data = topN.map(r => Number(r.total_recibido || 0));
                        
                        if (otros > 0) {
                            labels.push('OTROS PROVEEDORES');
                            data.push(otros);
                        }

                        crearGraficoSeguroSPA(canvasProveedores, {
                            type: 'doughnut',
                            data: {
                                labels: labels,
                                datasets: [{
                                    data: data,
                                    // Usamos la misma paleta de colores de tu sistema
                                    backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#fd7e14', '#6f42c1', '#adb5bd'],
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '65%', // Esto hace que parezca una Dona y no un pastel entero
                                plugins: {
                                    legend: { position: 'bottom' },
                                    tooltip: {
                                        callbacks: {
                                            label(ctx) { return ` S/ ${Number(ctx.parsed).toFixed(2)}`; }
                                        }
                                    }
                                }
                            }
                        });
                    }
                } catch (err) { console.error("Error Chart Proveedores:", err); }
            }
        }
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE ---
    document.addEventListener('DOMContentLoaded', window.inicializarModuloReporteCompras);
    document.addEventListener('sisadmin:route-loaded', window.inicializarModuloReporteCompras);
}
