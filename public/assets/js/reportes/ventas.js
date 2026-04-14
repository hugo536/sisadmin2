document.addEventListener('DOMContentLoaded', function() {
    
    const form = document.getElementById('formFiltrosReporteVentas');
    if(!form) return;

    // ==========================================
    // 1. LÓGICA DE PESTAÑAS (TABS)
    // ==========================================
    const botonesTabs = document.querySelectorAll('.btn-tab-seccion');
    const inputSeccion = document.getElementById('input_seccion_activa');

    botonesTabs.forEach(boton => {
        boton.addEventListener('click', function() {
            const seccionSeleccionada = this.getAttribute('data-seccion');
            if (inputSeccion.value !== seccionSeleccionada) {
                inputSeccion.value = seccionSeleccionada;
                // Deshabilitar 'required' temporalmente para navegar libremente sin que el navegador bloquee
                form.querySelectorAll('input[required]').forEach(f => f.required = false);
                form.submit();
            }
        });
    });

    // ==========================================
    // 2. LÓGICA DE AUTO-SUBMIT EN FILTROS
    // ==========================================
    let autoSubmitTimer = null;
    const enviarFiltros = () => {
        if(!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        // Usamos un pequeño delay (debounce) para evitar múltiples envíos si escribe rápido
        if (autoSubmitTimer) clearTimeout(autoSubmitTimer);
        autoSubmitTimer = setTimeout(() => form.submit(), 150);
    };

    // Escuchar cambios en inputs y selects normales
    form.querySelectorAll('.auto-submit').forEach(input => {
        input.addEventListener('change', enviarFiltros);
    });

    // ==========================================
    // 3. LÓGICA DE BÚSQUEDA AJAX CON TOMSELECT
    // ==========================================
    const inicializarTomSelect = async () => {
        // Esperamos a que la librería TomSelect termine de cargar en el navegador
        for (let i = 0; i < 20; i++) {
            if (typeof window.TomSelect !== 'undefined') break;
            await new Promise(r => setTimeout(r, 120));
        }
        if(typeof window.TomSelect === 'undefined') return;

        const clienteSelect = document.getElementById('filtroVentasCliente');
        const tipoTerceroSelect = document.getElementById('filtroVentasTipoTercero');
        const productoSelect = document.getElementById('filtroVentasProducto');

        // Inicializar buscador de Clientes
        if (clienteSelect && !clienteSelect.tomselect) {
            new TomSelect(clienteSelect, {
                valueField: 'id', 
                labelField: 'nombre_completo', 
                searchField: ['nombre_completo', 'num_doc'],
                placeholder: 'Todos...', 
                maxOptions: 50, 
                create: false,
                allowEmptyOption: true,
                load(query, callback) {
                    const u = new URL(window.location.href);
                    u.searchParams.set('ruta', 'reportes/ventas');
                    u.searchParams.set('accion', 'buscar_clientes');
                    u.searchParams.set('q', query || '');
                    u.searchParams.set('tipo_tercero', tipoTerceroSelect?.value || '');
                    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(r => callback(Array.isArray(r?.data) ? r.data : []))
                        .catch(() => callback());
                },
                onChange: enviarFiltros,
                onInitialize() {
                    if (!this.getValue()) {
                        this.clear(true);
                    }
                }
            });
        }

        // Inicializar buscador de Productos
        if (productoSelect && !productoSelect.tomselect) {
            new TomSelect(productoSelect, {
                valueField: 'id', 
                labelField: 'nombre', 
                searchField: ['nombre', 'sku'],
                placeholder: 'Todos...', 
                maxOptions: 50, 
                create: false,
                allowEmptyOption: true,
                load(query, callback) {
                    const u = new URL(window.location.href);
                    u.searchParams.set('ruta', 'reportes/ventas');
                    u.searchParams.set('accion', 'buscar_productos');
                    u.searchParams.set('q', query || '');
                    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(r => callback(Array.isArray(r?.data) ? r.data : []))
                        .catch(() => callback());
                },
                onChange: enviarFiltros,
                onInitialize() {
                    if (!this.getValue()) {
                        this.clear(true);
                    }
                }
            });
        }
    };
    inicializarTomSelect();

    // ==========================================
    // 4. INICIALIZACIÓN DEL GRÁFICO (CHART.JS)
    // ==========================================
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
});