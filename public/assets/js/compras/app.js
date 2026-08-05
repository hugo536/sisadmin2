// ==============================================================
// MÓDULO PRINCIPAL COMPRAS: app.js (Orquestador)
// ==============================================================

import { app, urls, recargarPagina, postJsonConCarga } from './config.js';
import { postJson } from '../api.js';
import { initCompras, abrirModalCompra } from './compra.js';
import { initPagosCompras } from './pagos.js';
import { abrirModalRecepcion, abrirModalDevolucion } from './logistica.js';

// ==========================================
// 1. UTILIDADES LOCALES
// ==========================================
function ocultarTooltipBoton(boton) {
    if (!boton || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    const tooltip = bootstrap.Tooltip.getInstance(boton);
    if (tooltip) {
        tooltip.hide();
    }
}

// ==========================================
// 2. INICIALIZACIÓN Y EVENTOS GLOBALES
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    if (!app) return;

    // Inicializar submódulos
    initCompras();
    initPagosCompras();

    // --- LÓGICA DE FILTROS ---
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                recargarPagina();
            }
        });
    }

    const filtroEstado = document.getElementById('filtroEstado');
    if (filtroEstado) filtroEstado.addEventListener('change', recargarPagina);

    const btnFiltrarFechas = document.getElementById('btnFiltrarFechas'); 
    if (btnFiltrarFechas) btnFiltrarFechas.addEventListener('click', recargarPagina);

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

        if (filtroFechaDesde.value) filtroFechaHasta.min = filtroFechaDesde.value;
        if (filtroFechaHasta.value) filtroFechaDesde.max = filtroFechaHasta.value;
    }

    // --- LÓGICA DE DELEGACIÓN (CLICS EN TABLA PRINCIPAL) ---
    const tbodyTabla = document.querySelector('#tablaCompras tbody');
    if (tbodyTabla) {
        tbodyTabla.addEventListener('click', async (e) => {
            const target = e.target.closest('button');
            if (!target) return;

            target.blur();
            ocultarTooltipBoton(target);

            const fila = target.closest('tr');
            const id = Number(target.dataset.id || fila?.dataset?.id || 0);
            
            if (!id) {
                Swal.fire('Error', 'No se pudo identificar la orden seleccionada. Recarga la página e inténtalo de nuevo.', 'error');
                return;
            }

            // A. Acciones de Edición/Vistas (Módulo compra.js)
            if (target.classList.contains('btn-editar')) {
                abrirModalCompra(id, target);
                return;
            }

            // B. Acciones Rápidas (Aprobar, Anular, Revertir)
            if (target.classList.contains('btn-aprobar')) {
                const confirm = await Swal.fire({
                    title: '¿Aprobar Orden?', text: 'Una orden aprobada quedará lista para recepción y ya no será editable.',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar',
                });
                if (!confirm.isConfirmed) return;

                try {
                    const res = await postJsonConCarga(urls.aprobar, { id }, target);
                    await Swal.fire('Aprobada', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            if (target.classList.contains('btn-revertir-borrador')) {
                const confirm = await Swal.fire({
                    title: '¿Revertir a borrador?',
                    text: 'La orden volverá al estado inicial para que puedas editarla antes de recepción.',
                    icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, revertir',
                });
                if (!confirm.isConfirmed) return;

                try {
                    const res = await postJsonConCarga(urls.revertirBorrador, { id }, target);
                    await Swal.fire('Revertida', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            if (target.classList.contains('btn-anular')) {
                const confirm = await Swal.fire({
                    title: '¿Anular Orden?', text: 'Esta acción no se puede deshacer.',
                    icon: 'error', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular',
                });
                if (!confirm.isConfirmed) return;

                try {
                    const res = await postJson(urls.anular, { id }); // Sin botón de carga por ser destructivo rápido
                    await Swal.fire('Anulada', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            // C. Acciones de Logística (Módulo logistica.js)
            if (target.classList.contains('btn-recepcionar')) {
                abrirModalRecepcion(id);
                return;
            }

            if (target.classList.contains('btn-devolver')) {
                abrirModalDevolucion(id);
                return;
            }
        });
    }
});