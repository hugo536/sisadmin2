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
    }
})();