// ==============================================================
// MÓDULO PRINCIPAL: app.js (Orquestador)
// ==============================================================

import { app, urls, recargarTabla } from './config.js';
import { postJson } from '../api.js';
import { initVentas, abrirModalVenta, revertirBorrador } from './venta.js';
import { initPagos } from './pagos.js';
import { abrirModalDespacho, abrirModalDevolucionVenta } from './logistica.js';

// Si no estamos en la vista de ventas, abortamos la ejecución
if (app) {
    // ==========================================
    // 1. INICIALIZACIÓN DE SUBMÓDULOS
    // ==========================================
    initVentas();
    initPagos();

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
        btnConfirmarImpresion.addEventListener('click', () => {
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
    const btnFiltrarFechas = document.getElementById('btnFiltrarFechas');
    if (btnFiltrarFechas) btnFiltrarFechas.addEventListener('click', recargarTabla);

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') recargarTabla();
        });
    }

    const filtrosChange = ['filtroEstado', 'filtroOrdenFecha'];
    filtrosChange.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', recargarTabla);
    });

    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    if (filtroFechaDesde && filtroFechaHasta) {
        filtroFechaDesde.addEventListener('change', () => {
            if (filtroFechaDesde.value) {
                filtroFechaHasta.min = filtroFechaDesde.value; 
                // Fix aplicado: filstroFechaHasta -> filtroFechaHasta
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

        if (filtroFechaDesde.value) filtroFechaHasta.min = filtroFechaDesde.value;
        if (filtroFechaHasta.value) filtroFechaDesde.max = filtroFechaHasta.value;
    }

    // ==========================================
    // 4. DELEGACIÓN DE EVENTOS (TABLA PRINCIPAL)
    // ==========================================
    const tablaVentas = document.querySelector('#tablaVentas tbody');
    if (tablaVentas) {
        tablaVentas.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            
            const tr = btn.closest('tr');
            const id = Number(btn.dataset.id || tr?.dataset.id || 0);

            if (!id) {
                Swal.fire('Error', 'No se encontró el identificador del pedido.', 'error');
                return;
            }

            // A. Acciones del módulo VENTA (venta.js)
            if (btn.classList.contains('btn-editar')) {
                abrirModalVenta(id, tr);
            }
            if (btn.classList.contains('btn-revertir')) {
                revertirBorrador(id);
            }

            // B. Acciones Rápidas (Aprobar / Anular)
            if (btn.classList.contains('btn-anular')) {
                const ok = await Swal.fire({ icon: 'warning', title: '¿Anular pedido?', showCancelButton: true, confirmButtonText: 'Sí, anular', confirmButtonColor: '#d33' });
                if (ok.isConfirmed) {
                    try {
                        const res = await postJson(urls.anular, { id });
                        await Swal.fire('Anulado', res.mensaje, 'success');
                        recargarTabla();
                    } catch (err) { Swal.fire('Error', err.message, 'error'); }
                }
            }
            if (btn.classList.contains('btn-aprobar')) {
                const ok = await Swal.fire({ icon: 'question', title: '¿Aprobar pedido?', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
                if (ok.isConfirmed) {
                    try {
                        const res = await postJson(urls.aprobar, { id });
                        await Swal.fire('Aprobado', res.mensaje, 'success');
                        recargarTabla();
                    } catch (err) { Swal.fire('Error', err.message, 'error'); }
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