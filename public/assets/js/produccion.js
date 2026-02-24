/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes de Producción)
 * Archivo exclusivo para la gestión y ejecución de Órdenes de Producción.
 */

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosOrdenes();
    initModalEjecucion();
});

// =========================================================================
// 1. FILTROS DE TABLA DE ÓRDENES
// =========================================================================
function initFiltrosOrdenes() {
    const input = document.getElementById('opSearch');
    const select = document.getElementById('opFiltroEstado');
    const table = document.getElementById('tablaOrdenes');
    
    if (!input || !table) return;

    const filterFn = () => {
        const term = input.value.toLowerCase();
        const estado = select ? select.value : '';
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const matchText = (row.getAttribute('data-search') || '').toLowerCase().includes(term);
            const matchEstado = estado === '' || (row.getAttribute('data-estado') || '') === estado;
            row.style.display = (matchText && matchEstado) ? '' : 'none';
        });
    };

    input.addEventListener('keyup', filterFn);
    if (select) {
        select.addEventListener('change', filterFn);
    }
}

// =========================================================================
// 2. MODAL DE EJECUCIÓN DE PRODUCCIÓN
// =========================================================================
function initModalEjecucion() {
    const modalEl = document.getElementById('modalEjecutarOP');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    
    const modalEjecutar = bootstrap.Modal.getOrCreateInstance(modalEl);

    // Limpiar el formulario al cerrar el modal para evitar datos atascados
    modalEl.addEventListener('hidden.bs.modal', function () {
        const form = modalEl.querySelector('form');
        if (form) form.reset();
    });

    // Escuchar el clic en el botón de la tabla
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-abrir-ejecucion');
        if (!btn) return;

        // Referencias a los inputs del modal
        const execIdOrden = document.getElementById('execIdOrden');
        const execCodigo = document.getElementById('execCodigo');
        const execCantidad = document.getElementById('execCantidad');
        const execLote = document.getElementById('execLote');

        // Llenar datos desde los atributos data-* del botón
        if (execIdOrden) execIdOrden.value = btn.getAttribute('data-id') || '';
        if (execCodigo) execCodigo.value = btn.getAttribute('data-codigo') || '';
        if (execCantidad) execCantidad.value = btn.getAttribute('data-planificada') || '';

        // Generar un número de lote sugerido automáticamente (Ej: L260224-15)
        if (execLote) {
            const today = new Date().toISOString().slice(2, 10).replace(/-/g, '');
            const id = btn.getAttribute('data-id') || '0';
            execLote.value = 'L' + today + '-' + id;
        }
        
        modalEjecutar.show();
    });
}