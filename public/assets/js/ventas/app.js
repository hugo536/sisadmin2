// ==============================================================
// MÓDULO PRINCIPAL VENTAS: app.js (Orquestador SPA)
// ==============================================================

// Nota: Asegúrate de que 'recargarPagina' esté exportado en tu config.js igual que en Compras.
// Si tu proyecto usa 'recargarTabla' en Ventas, puedes cambiar el nombre en la importación.
import { recargarPagina, postJsonConCarga } from './config.js'; 
import { postJson } from '../api.js';
import { initVentas, abrirModalVenta, abrirModalResumenVenta, revertirBorrador } from './venta.js';
import { initPagos } from './pagos.js';
import { abrirModalDespacho, abrirModalDevolucionVenta, initLogistica } from './logistica.js';

// Envolvemos todo en una función de "Arranque"
function arrancarModuloVentas() {
    const app = document.getElementById('ventasApp');
    if (!app) return; // Si estamos en otra vista, se detiene aquí

    // 1. Inicializamos submódulos (Esto refresca las variables del DOM)
    initVentas();
    initPagos();
    if (typeof initLogistica === 'function') initLogistica();

    // 2. Filtros y Búsquedas
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                recargarPagina();
            }
        });
    }

    const filtrosSelect = ['filtroEstado', 'filtroOrdenFecha'];
    filtrosSelect.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', recargarPagina);
    });

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
    }

    // 3. Delegación de Eventos (Tabla Principal)
    const tbodyTabla = document.querySelector('#tablaVentas tbody');
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
            const estadoFila = Number(fila?.dataset?.estado || 0);
            
            if (!id) return Swal.fire('Error', 'Identificador no encontrado.', 'error');

            // A. Módulo Venta / Resumen
            if (target.classList.contains('btn-editar')) {
                // Si el pedido está Cerrado (3), Devuelto (4, 5) o Anulado (9), abrimos el Resumen en lugar de la edición
                if (estadoFila === 3 || estadoFila === 4 || estadoFila === 5 || estadoFila === 9) {
                    if (typeof abrirModalResumenVenta === 'function') {
                        abrirModalResumenVenta(id);
                    } else {
                        abrirModalVenta(id, target); // Fallback de seguridad
                    }
                } else {
                    abrirModalVenta(id, target);
                }
                return;
            }

            // B. Acciones Rápidas
            if (target.classList.contains('btn-aprobar')) {
                const confirm = await Swal.fire({ title: '¿Aprobar Pedido?', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
                if (!confirm.isConfirmed) return;
                try {
                    const res = await postJsonConCarga(app.dataset.urlAprobar, { id }, target);
                    await Swal.fire('Aprobado', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            if (target.classList.contains('btn-revertir')) {
                const confirm = await Swal.fire({ title: '¿Revertir a borrador?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, revertir' });
                if (!confirm.isConfirmed) return;
                try {
                    // Delegamos a la función de venta.js (o usamos la URL directa si en el futuro la agregas al HTML)
                    if (typeof revertirBorrador === 'function') {
                        await revertirBorrador(id);
                    }
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            if (target.classList.contains('btn-anular')) {
                const confirm = await Swal.fire({ title: '¿Anular Pedido?', icon: 'error', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular' });
                if (!confirm.isConfirmed) return;
                try {
                    const res = await postJson(app.dataset.urlAnular, { id }); 
                    await Swal.fire('Anulado', res.mensaje, 'success');
                    recargarPagina();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
                return;
            }

            // C. Módulo Logística
            if (target.classList.contains('btn-despachar')) {
                abrirModalDespacho(id);
                return;
            }

            if (target.classList.contains('btn-devolucion')) {
                abrirModalDevolucionVenta(id);
                return;
            }

            // D. Impresión (Utiliza la función global declarada en el HTML)
            if (target.closest('.btn-imprimir-modal')) {
                if (typeof window.imprimirPedido === 'function') {
                    window.imprimirPedido(id);
                }
                return;
            }
        });
    }
}

// MAGIA SPA: Corremos la función si entraste con F5
document.addEventListener('DOMContentLoaded', arrancarModuloVentas);

// MAGIA SPA: Corremos la función cada vez que el menú lateral carga la vista por AJAX
document.addEventListener('sisadmin:route-loaded', arrancarModuloVentas);