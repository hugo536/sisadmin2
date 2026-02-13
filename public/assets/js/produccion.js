/**
 * SISTEMA SISADMIN2 - Módulo de Producción
 */

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosTablas();
    initFormularioRecetas();
    initModalEjecucion();
});

function initFiltrosTablas() {
    setupFiltro('recetaSearch', 'recetaFiltroEstado', 'tablaRecetas');
    setupFiltro('opSearch', 'opFiltroEstado', 'tablaOrdenes');
}

function setupFiltro(inputId, selectId, tableId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    if (!input || !select) return;

    const filterFn = () => {
        const term = input.value.toLowerCase();
        const estado = select.value;
        const rows = document.querySelectorAll(`#${tableId} tbody tr`);

        rows.forEach(row => {
            const matchText = (row.getAttribute('data-search') || '').includes(term);
            const matchEstado = estado === '' || (row.getAttribute('data-estado') || '') === estado;
            row.style.display = (matchText && matchEstado) ? '' : 'none';
        });
    };

    input.addEventListener('keyup', filterFn);
    select.addEventListener('change', filterFn);
}

function initFormularioRecetas() {
    const btnAdd = document.getElementById('btnAgregarDetalleReceta');
    const template = document.getElementById('detalleRecetaTemplate');
    const resumen = document.getElementById('bomResumen');
    const buttonsEtapa = document.querySelectorAll('[data-add-etapa]');
    const etapaContainers = document.querySelectorAll('[data-etapa-container]');

    if (!btnAdd || !template || etapaContainers.length === 0) return;

    const crearFila = (etapa) => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.detalle-row');
        row.dataset.etapa = etapa;
        const etapaInput = row.querySelector('input[name="detalle_etapa[]"]');
        etapaInput.value = etapa;

        row.querySelectorAll('input, select').forEach(el => {
            if (el.name === 'detalle_etapa[]') return;
            el.value = el.name === 'detalle_merma[]' ? '0' : '';
        });

        const container = document.querySelector(`[data-etapa-container="${CSS.escape(etapa)}"]`);
        if (container) container.appendChild(fragment);
        actualizarResumen();
    };

    const actualizarResumen = () => {
        const rows = document.querySelectorAll('.detalle-row');
        resumen.textContent = `${rows.length} líneas cargadas.`;
    };

    btnAdd.addEventListener('click', function() {
        const primerContenedor = etapaContainers[0];
        if (primerContenedor) crearFila(primerContenedor.dataset.etapaContainer);
    });

    buttonsEtapa.forEach(btn => {
        btn.addEventListener('click', function() {
            crearFila(btn.dataset.addEtapa);
        });
    });

    document.addEventListener('click', function(e) {
        const btnRemove = e.target.closest('.js-remove-row');
        if (btnRemove) {
            btnRemove.closest('.detalle-row')?.remove();
            actualizarResumen();
        }
    });

    etapaContainers.forEach(c => {
        if (c.dataset.etapaContainer === 'Tratamiento Agua') {
            crearFila('Tratamiento Agua');
        }
    });
}

function initModalEjecucion() {
    const modalEl = document.getElementById('modalEjecutarOP');
    if (!modalEl) return;
    const modalEjecutar = new bootstrap.Modal(modalEl);

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-abrir-ejecucion');
        if (!btn) return;

        document.getElementById('execIdOrden').value = btn.getAttribute('data-id');
        document.getElementById('execCodigo').value = btn.getAttribute('data-codigo');
        document.getElementById('execCantidad').value = btn.getAttribute('data-planificada');

        const today = new Date().toISOString().slice(2, 10).replace(/-/g, '');
        document.getElementById('execLote').value = 'L' + today + '-' + btn.getAttribute('data-id');
        modalEjecutar.show();
    });
}
