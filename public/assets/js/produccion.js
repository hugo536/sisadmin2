/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Recetas BOM)
 */

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosTablas();
    initFormularioRecetas();
    initAccionesRecetaPendiente();
    initModalEjecucion();
});

function initFiltrosTablas() {
    setupFiltro('recetaSearch', 'recetaFiltroEstado', 'tablaRecetas');
    // Si en esta vista no existe tablaOrdenes, no dará error gracias a la validación interna
    setupFiltro('opSearch', 'opFiltroEstado', 'tablaOrdenes');
}

function setupFiltro(inputId, selectId, tableId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    const table = document.getElementById(tableId);
    
    // Si no existe el input de búsqueda o la tabla en el DOM actual, salimos silenciosamente
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

function initFormularioRecetas() {
    const template = document.getElementById('detalleRecetaTemplate');
    const resumen = document.getElementById('bomResumen');
    const buttonsEtapa = document.querySelectorAll('[data-add-etapa]');
    const etapaContainers = document.querySelectorAll('[data-etapa-container]');

    if (!template || etapaContainers.length === 0) return;

    const actualizarResumen = () => {
        const rows = document.querySelectorAll('.detalle-row');
        if (resumen) {
            resumen.textContent = `${rows.length} insumos/semielaborados agregados.`;
        }
    };

    const crearFila = (etapa) => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.detalle-row');
        
        // Asignar la etapa al atributo data y al input hidden para el POST de PHP
        row.dataset.etapa = etapa;
        const etapaInput = row.querySelector('.input-etapa-hidden');
        if (etapaInput) etapaInput.value = etapa;

        // Limpiar valores del template clonado
        row.querySelectorAll('input:not([type="hidden"]), select').forEach(el => {
            if (el.classList.contains('input-merma')) {
                el.value = '0.00';
            } else {
                el.value = '';
            }
        });

        // Buscar el contenedor exacto de la etapa donde se hizo clic
        const container = document.querySelector(`[data-etapa-container="${CSS.escape(etapa)}"]`);
        if (container) {
            container.appendChild(fragment);
            actualizarResumen();
        } else {
            console.error('No se encontró el contenedor para la etapa:', etapa);
        }
    };

    // Agregar eventos a los botones de "Agregar insumo a X etapa" dentro de los acordeones
    buttonsEtapa.forEach(btn => {
        btn.addEventListener('click', function() {
            crearFila(this.dataset.addEtapa);
        });
    });

    // Delegación de eventos para eliminar filas (mejor rendimiento que asignar evento a cada botón)
    document.addEventListener('click', function(e) {
        const btnRemove = e.target.closest('.js-remove-row');
        if (btnRemove) {
            const row = btnRemove.closest('.detalle-row');
            if (row) {
                row.remove();
                actualizarResumen();
            }
        }
    });
}

function initAccionesRecetaPendiente() {
    const modalEl = document.getElementById('modalCrearReceta');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const form = document.getElementById('formCrearReceta');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-agregar-receta');
        if (!btn || !form) return;

        // Capturar datos del botón en la tabla
        const idProducto = btn.getAttribute('data-id-producto') || '';
        const codigo = btn.getAttribute('data-codigo') || '';
        const version = btn.getAttribute('data-version') || '1';
        const productoNombre = btn.getAttribute('data-producto') || '';

        // Poblar el formulario
        const inputCodigo = document.getElementById('newCodigo');
        const inputVersion = document.getElementById('newVersion');
        const inputProducto = document.getElementById('newProducto');
        const inputDescripcion = document.getElementById('newDescripcion');
        const inputRendimiento = document.getElementById('newRendimientoBase');

        if (inputCodigo) inputCodigo.value = codigo;
        if (inputVersion) inputVersion.value = version;
        if (inputProducto) inputProducto.value = idProducto;
        if (inputDescripcion && !inputDescripcion.value.trim()) {
            inputDescripcion.value = 'Receta inicial de ' + productoNombre;
        }
        if (inputRendimiento && !inputRendimiento.value) {
            inputRendimiento.value = '1';
        }

        modal.show();
    });
}

function initModalEjecucion() {
    const modalEl = document.getElementById('modalEjecutarOP');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    
    const modalEjecutar = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-abrir-ejecucion');
        if (!btn) return;

        const execIdOrden = document.getElementById('execIdOrden');
        const execCodigo = document.getElementById('execCodigo');
        const execCantidad = document.getElementById('execCantidad');
        const execLote = document.getElementById('execLote');

        if (execIdOrden) execIdOrden.value = btn.getAttribute('data-id');
        if (execCodigo) execCodigo.value = btn.getAttribute('data-codigo');
        if (execCantidad) execCantidad.value = btn.getAttribute('data-planificada');

        // Generar un número de lote sugerido automáticamente basado en la fecha y el ID
        if (execLote) {
            const today = new Date().toISOString().slice(2, 10).replace(/-/g, '');
            execLote.value = 'L' + today + '-' + btn.getAttribute('data-id');
        }
        
        modalEjecutar.show();
    });
}