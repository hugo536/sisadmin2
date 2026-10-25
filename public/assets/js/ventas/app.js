// ==============================================================
// MÓDULO PRINCIPAL VENTAS: app.js (Orquestador SPA)
// ==============================================================

import { recargarTabla } from './config.js'; 
import { postJson } from '../api.js';
import { initVentas, abrirModalVenta, revertirBorrador } from './venta.js';
import { initPagos } from './pagos.js';
import { abrirModalDespacho, abrirModalDevolucionVenta } from './logistica.js';

// Envolvemos todo en una función de "Arranque"
function arrancarModuloVentas() {
    const app = document.getElementById('ventasApp');
    if (!app) return; // Si estamos en otra vista (ej. Compras), se detiene aquí.

    // Extraemos las URLs directamente del DOM "fresco"
    const urls = {
        index: app.dataset.urlIndex,
        anular: app.dataset.urlAnular,
        aprobar: app.dataset.urlAprobar
    };

    // ==========================================
    // 1. INICIALIZACIÓN DE SUBMÓDULOS
    // ==========================================
    initVentas();
    initPagos();
    // NOTA: Si en ventas/logistica.js creas un initLogistica(), puedes llamarlo aquí también.

    // ==========================================
    // 2. IMPRESIÓN (Expuesto a window para modales)
    // ==========================================
    window.pedidoIdPendienteImpresion = window.pedidoIdPendienteImpresion || 0;

    window.imprimirPedido = function(id) {
        window.pedidoIdPendienteImpresion = Number(id) || 0;
        const inputPaginas = document.getElementById('cantidadPaginasPedido');
        const selectTipo = document.getElementById('tipoDocumentoImprimir');
        
        if (inputPaginas) inputPaginas.value = 1;
        if (selectTipo) selectTipo.value = 'imprimir'; 

        const modalEl = document.getElementById('modalImpresionPedido');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    const btnConfirmarImpresion = document.getElementById('btnConfirmarImpresionPedido');
    if (btnConfirmarImpresion) {
        // En SPA clonamos el botón para evitar que se acumulen múltiples "clics" ocultos
        const nuevoBtnConfirmar = btnConfirmarImpresion.cloneNode(true);
        btnConfirmarImpresion.parentNode.replaceChild(nuevoBtnConfirmar, btnConfirmarImpresion);
        
        nuevoBtnConfirmar.addEventListener('click', () => {
            const inputPaginas = document.getElementById('cantidadPaginasPedido');
            const selectTipo = document.getElementById('tipoDocumentoImprimir');
            
            if (!app || !inputPaginas || window.pedidoIdPendienteImpresion <= 0) return;

            const baseUrl = app.dataset.urlIndex;
            const paginas = Math.max(1, Math.min(20, Number(inputPaginas.value) || 1));
            const accionImpresion = selectTipo ? selectTipo.value : 'imprimir';

            const modalEl = document.getElementById('modalImpresionPedido');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }

            window.open(`${baseUrl}&accion=${accionImpresion}&id=${window.pedidoIdPendienteImpresion}&paginas=${paginas}`, '_blank');
        });
    }

    // ==========================================
    // 3. CONFIGURACIÓN DE FILTROS
    // ==========================================

    // 1. BLOQUEAMOS EL ENVÍO NATIVO DEL FORMULARIO (Evita que el Sidebar parpadee)
    const formFiltros = document.getElementById('formFiltrosVentas');
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault(); 
            recargarTabla();
        });
    }

    // 2. BLOQUEAMOS EL ENTER EN EL BUSCADOR
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault(); 
                recargarTabla();
            }
        });
    }

    const btnFiltrarFechas = document.getElementById('btnFiltrarFechas');
    if (btnFiltrarFechas) btnFiltrarFechas.addEventListener('click', recargarTabla);

// 1. Escuchar el cambio en el selector de orden de fecha
    const filtroOrdenFecha = document.getElementById('filtroOrdenFecha');
    if (filtroOrdenFecha) filtroOrdenFecha.addEventListener('change', recargarTabla);

    // 2. Lógica AJAX para las Pestañas (Tabs)
    const tabsVentas = document.querySelectorAll('.js-tab-ventas');
    const inputVistaGlobal = document.getElementById('inputVistaGlobal');
    const tituloTabla = document.querySelector('#contenedorTablaVentas h2');
    
    // Capturamos los inputs de fecha
    const inputDesde = document.getElementById('filtroFechaDesde');
    const inputHasta = document.getElementById('filtroFechaHasta');

    tabsVentas.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            
            tabsVentas.forEach(t => {
                t.classList.remove('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');
                t.classList.add('text-secondary', 'bg-light', 'border-0');
            });
            
            tab.classList.remove('text-secondary', 'bg-light', 'border-0');
            tab.classList.add('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');
            
            const vista = tab.dataset.vista;
            
            // --- MAGIA UX: Gestión dinámica de fechas según la pestaña ---
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
                    
                    // Ajuste de zona horaria local a formato YYYY-MM-DD
                    const formatoLocal = (fecha) => {
                        const offset = fecha.getTimezoneOffset() * 60000;
                        return new Date(fecha.getTime() - offset).toISOString().split('T')[0];
                    };

                    inputDesde.value = formatoLocal(hace30Dias);
                    inputHasta.value = formatoLocal(hoy);
                }
            }
            
            if (inputVistaGlobal) {
                inputVistaGlobal.value = vista;
                inputVistaGlobal.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            const url = new URL(window.location);
            url.searchParams.set('vista', vista);
            
            // Reflejamos las fechas en la URL para que no se pierdan si se recarga la página
            if (inputDesde?.value) url.searchParams.set('fecha_desde', inputDesde.value);
            else url.searchParams.delete('fecha_desde');
            
            if (inputHasta?.value) url.searchParams.set('fecha_hasta', inputHasta.value);
            else url.searchParams.delete('fecha_hasta');

            window.history.pushState({}, '', url);
            
            if (tituloTabla) {
                if (vista === 'pendientes') tituloTabla.textContent = 'Pedidos Pendientes de Atención';
                else if (vista === 'completados') tituloTabla.textContent = 'Pedidos Despachados / Cerrados';
                else tituloTabla.textContent = 'Todos los Pedidos';
            }
            
            recargarTabla();
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
    // 4. DELEGACIÓN DE EVENTOS (TABLA PRINCIPAL)
    // ==========================================
    const tablaVentas = document.querySelector('#tablaVentas tbody');
    if (tablaVentas) {
        tablaVentas.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            btn.blur();
            // Limpiamos los tooltips de bootstrap para que no se queden congelados en pantalla
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltip = bootstrap.Tooltip.getInstance(btn);
                if (tooltip) tooltip.hide();
            }
            
            const tr = btn.closest('tr');
            const id = Number(btn.dataset.id || tr?.dataset.id || 0);

            if (!id) {
                Swal.fire('Error', 'No se encontró el identificador del pedido.', 'error');
                return;
            }

            // A. Acciones del módulo VENTA (venta.js)
            if (btn.classList.contains('btn-editar')) abrirModalVenta(id, tr);
            if (btn.classList.contains('btn-revertir')) revertirBorrador(id);

            // B. Acciones Rápidas (Aprobar / Anular)
            if (btn.classList.contains('btn-anular')) {
                const ok = await Swal.fire({ icon: 'warning', title: '¿Anular pedido?', showCancelButton: true, confirmButtonText: 'Sí, anular', confirmButtonColor: '#d33' });
                if (ok.isConfirmed) {
                    try {
                        const res = await postJson(urls.anular, { id });
                        await Swal.fire('Anulado', res.mensaje, 'success');
                        recargarTabla();
                    } catch (err) { 
                        // 👇 CAMBIO AQUÍ: Usamos formato de objeto y la propiedad 'html'
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede anular',
                            html: err.message
                        }); 
                    }
                }
            }
            if (btn.classList.contains('btn-aprobar')) {
                const ok = await Swal.fire({ icon: 'question', title: '¿Aprobar pedido?', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
                if (ok.isConfirmed) {
                    try {
                        const res = await postJson(urls.aprobar, { id });
                        await Swal.fire('Aprobado', res.mensaje, 'success');
                        recargarTabla();
                    } catch (err) { 
                        // 👇 CAMBIO AQUÍ TAMBIÉN
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede aprobar',
                            html: err.message
                        }); 
                    }
                }
            }

            // C. Acciones del módulo LOGÍSTICA (logistica.js)
            if (btn.classList.contains('btn-despachar')) {
                try { await abrirModalDespacho(id); } catch (err) { Swal.fire('Error', err.message, 'error'); }
            }
            if (btn.classList.contains('btn-devolucion')) {
                try { await abrirModalDevolucionVenta(id); } catch (err) { Swal.fire('Error', err.message, 'error'); }
            }

            // D. Impresión
            if (btn.closest('.btn-imprimir-modal')) {
                window.imprimirPedido(id);
            }
        });
    }
}

// MAGIA SPA: Corremos la función si entraste con F5
document.addEventListener('DOMContentLoaded', arrancarModuloVentas);

// MAGIA SPA: Corremos la función cada vez que el menú lateral carga la vista por AJAX
document.addEventListener('sisadmin:route-loaded', arrancarModuloVentas);