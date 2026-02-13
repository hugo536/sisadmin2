/**
 * SISTEMA SISADMIN2 - Módulo de Producción
 * Archivo: public/assets/js/produccion.js
 * Descripción: Maneja la lógica de recetas dinámicas, filtros y ejecución de órdenes.
 */

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosTablas();
    initFormularioRecetas();
    initModalEjecucion();
});

// --------------------------------------------------------------------------
// 1. Lógica para Filtros de Búsqueda (Recetas y Órdenes)
// --------------------------------------------------------------------------
function initFiltrosTablas() {
    // Configuración para Recetas
    setupFiltro('recetaSearch', 'recetaFiltroEstado', 'tablaRecetas');
    
    // Configuración para Órdenes
    setupFiltro('opSearch', 'opFiltroEstado', 'tablaOrdenes');
}

function setupFiltro(inputId, selectId, tableId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    
    // Si no existen los elementos en esta vista, salimos (evita errores)
    if (!input || !select) return;

    const filterFn = () => {
        const term = input.value.toLowerCase();
        const estado = select.value;
        const rows = document.querySelectorAll(`#${tableId} tbody tr`);

        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const estadoData = row.getAttribute('data-estado') || '';
            
            const matchText = searchData.includes(term);
            const matchEstado = estado === '' || estadoData === estado;

            row.style.display = (matchText && matchEstado) ? '' : 'none';
        });
    };

    input.addEventListener('keyup', filterFn);
    select.addEventListener('change', filterFn);
}

// --------------------------------------------------------------------------
// 2. Lógica para Formulario de Recetas (Filas Dinámicas)
// --------------------------------------------------------------------------
function initFormularioRecetas() {
    const btnAdd = document.getElementById('btnAgregarDetalleReceta');
    const wrapper = document.getElementById('detalleRecetaWrapper');

    if (!btnAdd || !wrapper) return;

    // Agregar nueva fila
    btnAdd.addEventListener('click', function() {
        const rowTemplate = wrapper.querySelector('.detalle-row');
        if (!rowTemplate) return;

        const newRow = rowTemplate.cloneNode(true);
        
        // Limpiar inputs de la nueva fila
        newRow.querySelectorAll('input').forEach(input => input.value = '');
        newRow.querySelectorAll('select').forEach(select => select.value = '');
        
        // Resetear merma a 0 por defecto
        const mermaInput = newRow.querySelector('input[name="detalle_merma[]"]');
        if(mermaInput) mermaInput.value = '0';

        wrapper.appendChild(newRow);
    });

    // Eliminar fila (Delegación de eventos para elementos dinámicos)
    wrapper.addEventListener('click', function(e) {
        if (e.target.closest('.js-remove-row')) {
            const rows = wrapper.querySelectorAll('.detalle-row');
            if (rows.length > 1) {
                e.target.closest('.detalle-row').remove();
            } else {
                // Opcional: Mostrar alerta si intenta borrar la única fila
                // alert("La receta debe tener al menos un insumo.");
            }
        }
    });
}

// --------------------------------------------------------------------------
// 3. Lógica para Modal de Ejecución de Órdenes
// --------------------------------------------------------------------------
function initModalEjecucion() {
    // Detectamos si el modal existe en el DOM
    const modalEl = document.getElementById('modalEjecutarOP');
    if (!modalEl) return;

    // Usamos la API de Bootstrap 5
    const modalEjecutar = new bootstrap.Modal(modalEl);

    // Delegación de eventos para botones "Ejecutar" en la tabla
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-abrir-ejecucion');
        if (btn) {
            // Llenar datos en el modal
            document.getElementById('execIdOrden').value = btn.getAttribute('data-id');
            document.getElementById('execCodigo').value = btn.getAttribute('data-codigo');
            document.getElementById('execCantidad').value = btn.getAttribute('data-planificada');
            
            // Generar sugerencia de Lote (Fecha invertida + ID)
            // Formato: LYYMMDD-ID
            const today = new Date().toISOString().slice(2, 10).replace(/-/g, '');
            document.getElementById('execLote').value = 'L' + today + '-' + btn.getAttribute('data-id');

            // Mostrar modal
            modalEjecutar.show();
        }
    });
}