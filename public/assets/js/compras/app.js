// ==============================================================
// MÓDULO PRINCIPAL COMPRAS: app.js (Orquestador SPA)
// ==============================================================

import { recargarPagina, postJsonConCarga } from './config.js';
import { postJson } from '../api.js';
import { initCompras, abrirModalCompra, abrirModalResumenCompra } from './compra.js';
import { initPagosCompras } from './pagos.js';
import { abrirModalRecepcion, abrirModalDevolucion, initLogistica } from './logistica.js';

// Envolvemos todo en una función de "Arranque"
function arrancarModuloCompras() {
    const app = document.getElementById('comprasApp');
    if (!app) return; // Si estamos en otra vista (ej. Ventas), se detiene aquí.

    // Extraemos las URLs directamente del DOM "fresco"
    const urls = {
        index: app.dataset.urlIndex,
        aprobar: app.dataset.urlAprobar,
        revertirBorrador: app.dataset.urlRevertirBorrador,
        anular: app.dataset.urlAnular
    };

    // ==========================================
    // 1. INICIALIZACIÓN DE SUBMÓDULOS
    // ==========================================
    initCompras();
    initPagosCompras();
    initLogistica();

    // NOTA: Si en un futuro agregas impresión de órdenes de compra, 
    // el bloque de configuración "window.imprimirPedido" iría aquí, idéntico a Ventas.

    // ==========================================
    // 2. CONFIGURACIÓN DE FILTROS
    // ==========================================

    // 1. BLOQUEAMOS EL ENVÍO NATIVO DEL FORMULARIO (Evita que el Sidebar parpadee)
    const formFiltros = document.getElementById('formFiltrosCompras');
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault(); 
            recargarPagina();
        });
    }

    // 2. BLOQUEAMOS EL ENTER EN EL BUSCADOR
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                recargarPagina();
            }
        });
    }

    const btnFiltrarFechas = document.getElementById('btnFiltrarFechas'); 
    if (btnFiltrarFechas) btnFiltrarFechas.addEventListener('click', recargarPagina);

    // Múltiples selectores que recargan automáticamente
    const filtrosChange = ['filtroEstado', 'filtroOrdenFecha'];
    filtrosChange.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', recargarPagina);
    });

    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    if (filtroFechaDesde && filtroFechaHasta) {
        filtroFechaDesde.addEventListener('change', () => {
            if (filtroFechaDesde.value) {
                filtroFechaHasta.min = filtroFechaDesde.value; 
                if (filtroFechaHasta.value && filtroFechaHasta.value < filtroFechaDesde.value) {
                    filtroFechaHasta.value = filtroFechaDesde.value;
                }
            } else {
                filtroFechaHasta.min = '';
            }
        });

        filtroFechaHasta.addEventListener('change', () => {
            if (filtroFechaHasta.value) {
                filtroFechaDesde.max = filtroFechaHasta.value; 
                if (filtroFechaDesde.value && filtroFechaDesde.value > filtroFechaHasta.value) {
                    filtroFechaDesde.value = filtroFechaHasta.value;
                }
            } else {
                filtroFechaDesde.max = '';
            }
        });
    }

    // ==========================================
    // 3. DELEGACIÓN DE EVENTOS (TABLA PRINCIPAL)
    // ==========================================
    const tbodyTabla = document.querySelector('#tablaCompras tbody');
    if (tbodyTabla) {
        tbodyTabla.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            btn.blur();
            // Limpiamos los tooltips de bootstrap para que no se queden congelados en pantalla
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltip = bootstrap.Tooltip.getInstance(btn);
                if (tooltip) tooltip.hide();
            }

            const fila = btn.closest('tr');
            const id = Number(btn.dataset.id || fila?.dataset?.id || 0);
            const estadoFila = Number(fila?.dataset?.estado || 0);
            
            if (!id) {
                Swal.fire('Error', 'No se encontró el identificador de la orden.', 'error');
                return;
            }

            // A. Acciones del módulo COMPRA (compra.js / resumen)
            if (btn.classList.contains('btn-editar')) {
                // Si la orden ya está recepcionada (3) o anulada (9), abrimos el Resumen en lugar de la edición
                if (estadoFila === 3 || estadoFila === 9) {
                    abrirModalResumenCompra(id);
                } else {
                    abrirModalCompra(id, btn);
                }
                return;
            }

            // B. Acciones Rápidas (Aprobar / Revertir / Anular)
            if (btn.classList.contains('btn-aprobar')) {
                const confirm = await Swal.fire({ title: '¿Aprobar Orden?', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
                if (confirm.isConfirmed) {
                    try {
                        const res = await postJsonConCarga(urls.aprobar, { id }, btn);
                        await Swal.fire('Aprobada', res.mensaje, 'success');
                        recargarPagina();
                    } catch (err) { 
                        // 👇 Formato alineado a Ventas, usando la propiedad 'html' para el backend
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede aprobar',
                            html: err.message
                        });
                    }
                }
                return;
            }

            if (btn.classList.contains('btn-revertir-borrador')) {
                const confirm = await Swal.fire({ title: '¿Revertir a borrador?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, revertir' });
                if (confirm.isConfirmed) {
                    try {
                        const res = await postJsonConCarga(urls.revertirBorrador, { id }, btn);
                        await Swal.fire('Revertida', res.mensaje, 'success');
                        recargarPagina();
                    } catch (err) { 
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede revertir',
                            html: err.message
                        });
                    }
                }
                return;
            }

            if (btn.classList.contains('btn-anular')) {
                const confirm = await Swal.fire({ title: '¿Anular Orden?', icon: 'error', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular' });
                if (confirm.isConfirmed) {
                    try {
                        const res = await postJson(urls.anular, { id }); 
                        await Swal.fire('Anulada', res.mensaje, 'success');
                        recargarPagina();
                    } catch (err) { 
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede anular',
                            html: err.message
                        }); 
                    }
                }
                return;
            }

            // C. Acciones del módulo LOGÍSTICA (logistica.js)
            if (btn.classList.contains('btn-recepcionar')) {
                try { 
                    await abrirModalRecepcion(id); 
                } catch (err) { 
                    Swal.fire('Error', err.message, 'error'); 
                }
                return;
            }

            if (btn.classList.contains('btn-devolver')) {
                try { 
                    await abrirModalDevolucion(id); 
                } catch (err) { 
                    Swal.fire('Error', err.message, 'error'); 
                }
                return;
            }
        });
    }
}

// MAGIA SPA: Corremos la función si entraste con F5
document.addEventListener('DOMContentLoaded', arrancarModuloCompras);

// MAGIA SPA: Corremos la función cada vez que el menú lateral carga la vista por AJAX
document.addEventListener('sisadmin:route-loaded', arrancarModuloCompras);