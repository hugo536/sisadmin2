/**
 * Lógica específica para Tesorería - Movimientos
 * Archivo: assets/js/tesoreria/movimientos.js
 */
(function arrancarMovimientos() {
    'use strict';

    const app = document.getElementById('tesoreriaMovimientosApp');
    if (!app) return;

    // ========================================================================
    // 1. FILTROS AUTOMÁTICOS Y PAGINACIÓN CON AJAX (SPA)
    // ========================================================================
    const formFiltros = document.getElementById('formFiltrosMovimientos');
    const contenedorTabla = document.getElementById('contenedorTablaMovimientos');
    let timerFiltro = null;

    if (formFiltros && contenedorTabla) {
        
        const cargarDatosAjax = async (urlStr) => {
            // Efecto visual de carga
            contenedorTabla.style.opacity = '0.4';
            contenedorTabla.style.pointerEvents = 'none';

            try {
                window.history.replaceState({}, '', urlStr);
                const response = await fetch(urlStr, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                
                if (!response.ok) throw new Error('Error al obtener datos de movimientos');

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Reemplazamos el contenedor completo (Tabla, Badge de registros y Paginación)
                const nuevoContenedor = doc.getElementById('contenedorTablaMovimientos');
                if (nuevoContenedor) {
                    contenedorTabla.innerHTML = nuevoContenedor.innerHTML;
                }

                // Reinicializar Tooltips y el buscador interno de ERPTable
                if (typeof bootstrap !== 'undefined') {
                    [].slice.call(contenedorTabla.querySelectorAll('[data-bs-toggle="tooltip"]'))
                        .forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }
                if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                    window.ERPTable.autoInitFromDataset(app);
                }

            } catch (error) {
                console.error('Error AJAX en Movimientos:', error);
            } finally {
                contenedorTabla.style.opacity = '1';
                contenedorTabla.style.pointerEvents = 'auto';
            }
        };

        const procesarFiltros = () => {
            const formData = new FormData(formFiltros);
            const url = new URL(window.location.origin + window.location.pathname);
            
            formData.forEach((value, key) => {
                if (value) url.searchParams.set(key, value);
            });
            
            cargarDatosAjax(url.toString());
        };

        // Escuchar cambios en Selects o inputs de tipo Date (búsqueda inmediata)
        formFiltros.addEventListener('change', (e) => {
            if (e.target.tagName === 'SELECT' || e.target.type === 'date') {
                procesarFiltros();
            }
        });

        // Escuchar tipeo en inputs de texto (búsqueda con retraso/debounce)
        formFiltros.addEventListener('input', (e) => {
            if (e.target.tagName === 'INPUT' && e.target.type !== 'date') {
                clearTimeout(timerFiltro);
                timerFiltro = setTimeout(procesarFiltros, 400);
            }
        });

        // Evitar que el formulario recargue si presionan "Enter"
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            procesarFiltros();
        });

        // Paginación por AJAX (delegación de eventos)
        contenedorTabla.addEventListener('click', (e) => {
            const linkPaginacion = e.target.closest('.pagination a.page-link');
            if (linkPaginacion) {
                e.preventDefault();
                cargarDatosAjax(linkPaginacion.href);
            }
        });
    }

})();