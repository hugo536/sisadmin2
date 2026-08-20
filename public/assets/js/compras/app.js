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
    if (!app) return; // Si estamos en otra vista, se detiene aquí

    // 1. Inicializamos submódulos (Esto refresca las variables del DOM)
    initCompras();
    initPagosCompras();
    initLogistica();

    // ==========================================
    // 2. Filtros y Búsquedas (Secuestro SPA)
    // ==========================================
    
    // BLOQUEAMOS EL ENVÍO NATIVO DEL FORMULARIO
    const formFiltros = document.getElementById('formFiltrosCompras'); // Asegúrate que tu <form> en compras.php tenga este ID
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault(); 
            recargarPagina();
        });
    }

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

    // Múltiples selectores que recargan
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

    // 3. Delegación de Eventos (Tabla Principal)
    const tbodyTabla = document.querySelector('#tablaCompras tbody');
    if (tbodyTabla) {
        tbodyTabla.addEventListener('click', async (e) => {
            const target = e.target.closest('button');
            if (!target) return;

            target.blur();
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltip = bootstrap.Tooltip.getInstance(target);
                if (tooltip) tooltip.hide();
            }

            const fila = target.closest('tr');
            const id = Number(target.dataset.id || fila?.dataset?.id || 0);
            // Obtenemos el estado actual de la fila desde el atributo data-estado que pusimos en la vista
            const estadoFila = Number(fila?.dataset?.estado || 0);
            
            if (!id) return Swal.fire('Error', 'Identificador no encontrado.', 'error');

            // A. Módulo Compra / Resumen
            if (target.classList.contains('btn-editar')) {
                // Si la orden ya está recepcionada (3) o anulada (9), abrimos el Resumen en lugar de la edición
                if (estadoFila === 3 || estadoFila === 9) {
                    abrirModalResumenCompra(id);
                } else {
                    abrirModalCompra(id, target);
                }
                return;
            }

            // B. Acciones Rápidas
            if (target.classList.contains('btn-aprobar')) {
                const confirm = await Swal.fire({ title: '¿Aprobar Orden?', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
                if (!confirm.isConfirmed) return;
                try {
                    const res = await postJsonConCarga(app.dataset.urlAprobar, { id }, target);
                    await Swal.fire('Aprobada', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            if (target.classList.contains('btn-revertir-borrador')) {
                const confirm = await Swal.fire({ title: '¿Revertir a borrador?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, revertir' });
                if (!confirm.isConfirmed) return;
                try {
                    const res = await postJsonConCarga(app.dataset.urlRevertirBorrador, { id }, target);
                    await Swal.fire('Revertida', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            if (target.classList.contains('btn-anular')) {
                const confirm = await Swal.fire({ title: '¿Anular Orden?', icon: 'error', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular' });
                if (!confirm.isConfirmed) return;
                try {
                    const res = await postJson(app.dataset.urlAnular, { id }); 
                    await Swal.fire('Anulada', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            // C. Módulo Logística
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
}

// MAGIA SPA: Corremos la función si entraste con F5
document.addEventListener('DOMContentLoaded', arrancarModuloCompras);

// MAGIA SPA: Corremos la función cada vez que el menú lateral carga la vista por AJAX
document.addEventListener('sisadmin:route-loaded', arrancarModuloCompras);