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

    // 1. Escuchar el cambio en el selector de orden de fecha
    const filtroOrdenFecha = document.getElementById('filtroOrdenFecha');
    if (filtroOrdenFecha) filtroOrdenFecha.addEventListener('change', recargarPagina);

    // 2. Lógica AJAX para las Pestañas (Tabs)
    const tabsCompras = document.querySelectorAll('.js-tab-compras');
    const inputVistaGlobal = document.getElementById('inputVistaGlobal');
    const tituloTabla = document.querySelector('#contenedorTablaCompras h2');
    
    // Capturamos los inputs de fecha
    const inputDesde = document.getElementById('filtroFechaDesde');
    const inputHasta = document.getElementById('filtroFechaHasta');

    tabsCompras.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            
            // A. Quitar clases activas de todas las pestañas
            tabsCompras.forEach(t => {
                t.classList.remove('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');
                t.classList.add('text-secondary', 'bg-light', 'border-0');
            });
            
            // B. Poner clases activas a la pestaña seleccionada
            tab.classList.remove('text-secondary', 'bg-light', 'border-0');
            tab.classList.add('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');
            
            const vista = tab.dataset.vista;
            
            // C. Gestión dinámica de fechas según la pestaña
            if (vista === 'pendientes') {
                // Limpiamos las fechas para no ocultar trabajo rezagado
                if (inputDesde) inputDesde.value = '';
                if (inputHasta) inputHasta.value = '';
            } else {
                // Si pasamos al historial y las fechas están vacías, inyectamos los últimos 30 días
                if (inputDesde && inputHasta && (!inputDesde.value || !inputHasta.value)) {
                    const hoy = new Date();
                    const hace30Dias = new Date();
                    hace30Dias.setDate(hoy.getDate() - 30);
                    
                    const formatoLocal = (fecha) => {
                        const offset = fecha.getTimezoneOffset() * 60000;
                        return new Date(fecha.getTime() - offset).toISOString().split('T')[0];
                    };

                    inputDesde.value = formatoLocal(hace30Dias);
                    inputHasta.value = formatoLocal(hoy);
                }
            }
            
            // D. Actualizar el input oculto y FORZAR el evento change para el manager
            if (inputVistaGlobal) {
                inputVistaGlobal.value = vista;
                inputVistaGlobal.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            // E. Actualizar la URL del navegador silenciosamente
            const url = new URL(window.location);
            url.searchParams.set('vista', vista);
            
            // Reflejamos las fechas en la URL
            if (inputDesde?.value) url.searchParams.set('fecha_desde', inputDesde.value);
            else url.searchParams.delete('fecha_desde');
            
            if (inputHasta?.value) url.searchParams.set('fecha_hasta', inputHasta.value);
            else url.searchParams.delete('fecha_hasta');

            window.history.pushState({}, '', url);
            
            // F. Cambiar dinámicamente el título de la tabla
            if (tituloTabla) {
                if (vista === 'pendientes') tituloTabla.textContent = 'Órdenes Pendientes de Recepción';
                else if (vista === 'completados') tituloTabla.textContent = 'Órdenes Recepcionadas / Cerradas';
                else tituloTabla.textContent = 'Todas las Órdenes de Compra';
            }
            
            // G. Disparar la recarga de la tabla vía AJAX
            recargarPagina();
        });
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