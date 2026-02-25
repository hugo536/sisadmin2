/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes de Producción)
 * Archivo actualizado para manejo multi-almacén y ejecución dinámica.
 */

// CANDADO: Evitar que el JS se ejecute dos veces si se recarga la vista dinámicamente
if (!window.produccionJsInitialized) {
    window.produccionJsInitialized = true;

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
    // 2. MODAL DE EJECUCIÓN DE PRODUCCIÓN (Múltiples Consumos e Ingresos)
    // =========================================================================
    function initModalEjecucion() {
        const modalEl = document.getElementById('modalEjecutarOP');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        let modalEjecutar;

        // Usamos UN SOLO listener global para evitar eventos duplicados (Filas dobles)
        document.addEventListener('click', async function(e) {
            
            // --- A. Botón "Ejecutar" en la tabla ---
            const btnAbrir = e.target.closest('.js-abrir-ejecucion');
            if (btnAbrir) {
                const idOrden = btnAbrir.getAttribute('data-id') || '';
                const codigo = btnAbrir.getAttribute('data-codigo') || '';
                const idReceta = btnAbrir.getAttribute('data-receta') || '';
                const planificada = parseFloat(btnAbrir.getAttribute('data-planificada')) || 0;
                
                document.getElementById('execIdOrden').value = idOrden;
                document.getElementById('lblExecCodigo').textContent = codigo;

                const tbodyConsumos = document.querySelector('#tablaConsumosDynamic tbody');
                const tbodyIngresos = document.querySelector('#tablaIngresosDynamic tbody');
                
                // Estado de carga visual
                tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-primary fw-bold"><i class="bi bi-arrow-repeat spin"></i> Calculando receta e inventario...</td></tr>';
                tbodyIngresos.innerHTML = '';

                // Instanciar y mostrar el modal
                if (!modalEjecutar) modalEjecutar = new bootstrap.Modal(modalEl);
                modalEjecutar.show();

                try {
                    // LLAMADA AJAX SEGURA VÍA POST a la ruta actual
                    const formData = new FormData();
                    formData.append('accion', 'obtener_receta_ajax');
                    formData.append('id_receta', idReceta);
                    formData.append('cantidad', planificada);

                    // fetch a la misma URL de la página actual, el controlador atrapará el POST
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    tbodyConsumos.innerHTML = ''; 

                    if (result.success && result.data.length > 0) {
                        result.data.forEach(item => {
                            // Enviar datos al creador de filas
                            addConsumoRow(item.id_insumo, item.insumo_nombre, item.cantidad_calculada, '', item.stock_disponible);
                        });
                    } else {
                        tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">No se cargaron insumos. Añádalos manualmente.</td></tr>';
                    }
                } catch (error) {
                    console.error("Error AJAX:", error);
                    tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de conexión. Añada insumos manualmente.</td></tr>';
                }

                addIngresoRow(planificada);
                return; // Salir para no procesar otros clics
            }

            // --- B. Botón "Fila Extra" (Consumos) ---
            if (e.target.closest('#btnAgregarConsumo')) {
                addConsumoRow();
                return;
            }

            // --- C. Botón "Agregar Destino" (Ingresos) ---
            if (e.target.closest('#btnAgregarIngreso')) {
                addIngresoRow();
                return;
            }

            // --- D. Botón Eliminar Fila ---
            const btnRemove = e.target.closest('.js-remove-row');
            if (btnRemove) {
                const tr = btnRemove.closest('tr');
                if (tr) tr.remove();
                return;
            }
        });

        // Resetear form y pestañas al cerrar el modal
        modalEl.addEventListener('hidden.bs.modal', function () {
            const form = modalEl.querySelector('form');
            if (form) form.reset();
            const firstTab = modalEl.querySelector('.nav-tabs .nav-link');
            if (firstTab && typeof bootstrap !== 'undefined') new bootstrap.Tab(firstTab).show();
        });
    }

    // =========================================================================
    // 3. FUNCIONES GENERADORAS DE FILAS HTML
    // =========================================================================

    function addConsumoRow(insumoId = '', insumoNombre = '', cantidad = '', lote = '', stock = null) {
        const tbody = document.querySelector('#tablaConsumosDynamic tbody');
        const templateAlmacenes = document.getElementById('tplSelectAlmacenes').innerHTML;

        let inputNombreHtml = '';
        let alertaHtml = '';
        let inputCantidadClass = 'form-control form-control-sm';

        // Si viene desde la base de datos (con ID y Nombre)
        if (insumoNombre !== '') {
            inputNombreHtml = `
                <input type="hidden" name="consumo_id_insumo[]" value="${insumoId}">
                <input type="text" class="form-control form-control-sm bg-light fw-bold" value="${insumoNombre}" readonly>
            `;
            
            // Lógica de Advertencia de Stock
            if (stock !== null) {
                const cantRequerida = parseFloat(cantidad);
                const stockReal = parseFloat(stock);
                
                if (cantRequerida > stockReal) {
                    // Falta stock: Advertencia roja
                    const faltante = (cantRequerida - stockReal).toFixed(2);
                    alertaHtml = `<div class="text-danger small mt-1 fw-bold"><i class="bi bi-exclamation-triangle"></i> Stock: ${stockReal} (Falta: ${faltante})</div>`;
                    inputCantidadClass += ' border-danger text-danger bg-danger-subtle';
                } else {
                    // Hay stock suficiente
                    alertaHtml = `<div class="text-success small mt-1"><i class="bi bi-check-circle"></i> Stock: ${stockReal}</div>`;
                }
            }
        } else {
            // Fila extra manual
            inputNombreHtml = `<input type="number" name="consumo_id_insumo[]" class="form-control form-control-sm" placeholder="Escriba ID" required>`;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${inputNombreHtml}
                ${alertaHtml}
            </td>
            <td>
                <select name="consumo_id_almacen[]" class="form-select form-select-sm" required>
                    ${templateAlmacenes}
                </select>
            </td>
            <td>
                <input type="number" step="0.0001" name="consumo_cantidad[]" class="${inputCantidadClass}" placeholder="Ej. 100.5" required value="${cantidad}">
            </td>
            <td>
                <input type="text" name="consumo_id_lote[]" class="form-control form-control-sm" placeholder="Lote (Opc)" value="${lote}">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm text-danger border-0 js-remove-row" title="Quitar fila">
                    <i class="bi bi-trash fs-5"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function addIngresoRow(cantidadDefecto = '') {
        const tbody = document.querySelector('#tablaIngresosDynamic tbody');
        const templateAlmacenes = document.getElementById('tplSelectAlmacenes').innerHTML;

        // Autogenerar una sugerencia de lote basada en la fecha actual
        const today = new Date().toISOString().slice(2, 10).replace(/-/g, '');
        const randomSufix = Math.floor(Math.random() * 90 + 10); // Número entre 10 y 99
        const autoLote = 'L' + today + '-' + randomSufix;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="ingreso_id_almacen[]" class="form-select form-select-sm" required>
                    ${templateAlmacenes}
                </select>
            </td>
            <td>
                <input type="number" step="0.0001" name="ingreso_cantidad[]" class="form-control form-control-sm" required value="${cantidadDefecto}">
            </td>
            <td>
                <input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm" value="${autoLote}">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm text-danger border-0 js-remove-row" title="Quitar fila">
                    <i class="bi bi-trash fs-5"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }
}