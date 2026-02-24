/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Recetas BOM)
 * Archivo exclusivo para la gestión de fórmulas, listas de materiales y parámetros.
 */

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosTablas();
    initFormularioRecetas();
    initAccionesRecetaPendiente();
    initGestionParametrosCatalogo();
});

function initFiltrosTablas() {
    setupFiltro('recetaSearch', 'recetaFiltroEstado', 'tablaRecetas');
}

function setupFiltro(inputId, selectId, tableId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    const table = document.getElementById(tableId);
    
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
    // --- Elementos de Insumos ---
    const templateInsumo = document.getElementById('detalleRecetaTemplate');
    const resumenItems = document.getElementById('bomResumen');
    const costoTotalEl = document.getElementById('costoTotalCalculado');
    const btnAgregarInsumo = document.getElementById('btnAgregarInsumo');
    const listaInsumos = document.getElementById('listaInsumosReceta');
    const inputProducto = document.getElementById('newProducto');

    const instanciasTomSelect = new WeakMap();

    // --- Elementos de Parámetros IPC ---
    const btnAgregarParametro = document.getElementById('btnAgregarParametro');
    const contenedorParametros = document.getElementById('contenedorParametros');
    const templateParametro = document.getElementById('parametroTemplate');
    const emptyParametros = document.getElementById('emptyParametros');

    // =========================================================================
    // 1. GESTIÓN DE PARÁMETROS DINÁMICOS
    // =========================================================================
    if (btnAgregarParametro && templateParametro && contenedorParametros) {
        const actualizarEstadoParametros = () => {
            const rows = contenedorParametros.querySelectorAll('.parametro-row');
            if (emptyParametros) {
                emptyParametros.style.display = rows.length === 0 ? 'block' : 'none';
            }
        };

        btnAgregarParametro.addEventListener('click', function() {
            const fragment = templateParametro.content.cloneNode(true);
            contenedorParametros.appendChild(fragment);
            actualizarEstadoParametros();
        });

        // Delegación de eventos para eliminar parámetro
        document.addEventListener('click', function(e) {
            const btnRemove = e.target.closest('.js-remove-param');
            if (btnRemove) {
                const row = btnRemove.closest('.parametro-row');
                if (row) {
                    row.remove();
                    actualizarEstadoParametros();
                }
            }
        });
    }

    // =========================================================================
    // 2. GESTIÓN DE INSUMOS (BOM) Y COSTOS EN TIEMPO REAL
    // =========================================================================
    const calcularResumenYCostos = () => {
        const rows = document.querySelectorAll('.detalle-row');
        let totalItems = 0;
        let costoTotal = 0;

        rows.forEach(row => {
            const select = row.querySelector('.select-insumo');
            const inputCant = row.querySelector('.input-cantidad');
            const inputMerma = row.querySelector('.input-merma');
            const inputCostoUnitario = row.querySelector('.input-costo-unitario');
            const inputCostoItem = row.querySelector('.input-costo-item');

            const optionSelected = select && select.value ? select.options[select.selectedIndex] : null;
            const costoUnitario = optionSelected ? (parseFloat(optionSelected.getAttribute('data-costo')) || 0) : 0;

            if (inputCostoUnitario) {
                inputCostoUnitario.value = costoUnitario.toFixed(4);
            }

            if (select && select.value && inputCant) {
                totalItems++;
                const cantidad = parseFloat(inputCant.value) || 0;
                const merma = parseFloat(inputMerma.value) || 0;

                // Calcular el costo real incluyendo la merma
                const cantidadReal = cantidad * (1 + (merma / 100));
                const costoItem = cantidadReal * costoUnitario;
                costoTotal += costoItem;

                if (inputCostoItem) {
                    inputCostoItem.value = costoItem.toFixed(4);
                }
            } else {
                if (inputCostoItem) {
                    inputCostoItem.value = '0.0000';
                }
            }
        });

        if (resumenItems) {
            resumenItems.textContent = `${totalItems} insumos/semielaborados agregados.`;
        }
        if (costoTotalEl) {
            costoTotalEl.textContent = `S/ ${costoTotal.toFixed(4)}`;
        }
    };

    const sincronizarOpcionesInsumo = () => {
        const idProducto = (inputProducto && inputProducto.value) ? String(inputProducto.value) : '';
        const usados = new Set();

        listaInsumos?.querySelectorAll('.select-insumo').forEach(select => {
            if (select.value) {
                usados.add(String(select.value));
            }
        });

        listaInsumos?.querySelectorAll('.select-insumo').forEach(select => {
            const seleccionadoActual = String(select.value || '');

            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                const isPropioProducto = idProducto !== '' && option.value === idProducto;
                const isDuplicado = option.value !== seleccionadoActual && usados.has(option.value);
                option.disabled = isPropioProducto || isDuplicado;
            });

            const tom = instanciasTomSelect.get(select);
            if (tom) {
                tom.sync();
            }
        });
    };

    const inicializarBuscadorInsumo = (select) => {
        if (!select || typeof TomSelect === 'undefined') return;

        const tom = new TomSelect(select, {
            create: false,
            maxItems: 1,
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            sortField: [{ field: 'text', direction: 'asc' }],
        });

        instanciasTomSelect.set(select, tom);
    };

    const crearFilaInsumo = () => {
        if (!templateInsumo || !listaInsumos) return;
        const fragment = templateInsumo.content.cloneNode(true);
        const row = fragment.querySelector('.detalle-row');
        const selectInsumo = row.querySelector('.select-insumo');

        // Asignar listeners para actualizar costos si el usuario edita
        selectInsumo.addEventListener('change', () => {
            sincronizarOpcionesInsumo();
            calcularResumenYCostos();
        });
        row.querySelector('.input-cantidad').addEventListener('input', calcularResumenYCostos);
        row.querySelector('.input-merma').addEventListener('input', calcularResumenYCostos);

        listaInsumos.appendChild(fragment);
        inicializarBuscadorInsumo(selectInsumo);
        sincronizarOpcionesInsumo();
        calcularResumenYCostos();
    };

    if (btnAgregarInsumo) {
        btnAgregarInsumo.addEventListener('click', crearFilaInsumo);
    }

    // Delegación para eliminar fila de insumo
    document.addEventListener('click', function(e) {
        const btnRemove = e.target.closest('.js-remove-row');
        if (btnRemove) {
            const row = btnRemove.closest('.detalle-row');
            if (row) {
                const select = row.querySelector('.select-insumo');
                const tom = select ? instanciasTomSelect.get(select) : null;
                if (tom) tom.destroy();
                row.remove();
                sincronizarOpcionesInsumo();
                calcularResumenYCostos();
            }
        }
    });

    if (inputProducto) {
        inputProducto.addEventListener('change', sincronizarOpcionesInsumo);
    }
}

function initAccionesRecetaPendiente() {
    const modalEl = document.getElementById('modalCrearReceta');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const form = document.getElementById('formCrearReceta');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    // Limpiar el formulario y las filas dinámicas al cerrar el modal
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (form) form.reset();
        
        // Limpiar Insumos
        const contenedorInsumos = document.getElementById('listaInsumosReceta');
        if (contenedorInsumos) contenedorInsumos.innerHTML = '';
        
        // Limpiar Parámetros
        const paramCont = document.getElementById('contenedorParametros');
        if (paramCont) paramCont.innerHTML = '';
        
        const emptyParametros = document.getElementById('emptyParametros');
        if (emptyParametros) emptyParametros.style.display = 'block';

        // Resetear Totales
        const resumen = document.getElementById('bomResumen');
        if (resumen) resumen.textContent = '0 insumos agregados.';
        const costoTotalEl = document.getElementById('costoTotalCalculado');
        if (costoTotalEl) costoTotalEl.textContent = 'S/ 0.0000';

        const inputCodigo = document.getElementById('newCodigo');
        if (inputCodigo) {
            inputCodigo.removeAttribute('readonly');
        }
    });

    // Capturar clics en la tabla para "Agregar receta"
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-agregar-receta');
        if (!btn || !form) return;

        const idProducto = btn.getAttribute('data-id-producto') || '';
        const codigo = btn.getAttribute('data-codigo') || '';
        const version = btn.getAttribute('data-version') || '1';
        const productoNombre = btn.getAttribute('data-producto') || '';

        const inputCodigo = document.getElementById('newCodigo');
        const inputVersion = document.getElementById('newVersion');
        const inputProducto = document.getElementById('newProducto');
        const inputDescripcion = document.getElementById('newDescripcion');
        const inputRendimiento = document.getElementById('newRendimientoBase');
        const inputUnidad = document.getElementById('newUnidadRendimiento');

        if (inputCodigo) inputCodigo.value = codigo;
        if (inputCodigo) inputCodigo.setAttribute('readonly', 'readonly');
        if (inputVersion) inputVersion.value = version;
        if (inputProducto) inputProducto.value = idProducto;
        if (inputDescripcion) inputDescripcion.value = 'Fórmula inicial de ' + productoNombre;
        if (inputRendimiento) inputRendimiento.value = '1';
        if (inputUnidad) inputUnidad.value = 'UND';

        modal.show();
    });
}

function initGestionParametrosCatalogo() {
    const form = document.getElementById('formGestionParametroCatalogo');
    if (!form) return;

    const idInput = document.getElementById('idParametroCatalogo');
    const accionInput = document.getElementById('accionParametroCatalogo');
    const nombreInput = document.getElementById('nombreParametroCatalogo');
    const unidadInput = document.getElementById('unidadParametroCatalogo');
    const descripcionInput = document.getElementById('descripcionParametroCatalogo');
    const btnGuardar = document.getElementById('btnGuardarParametroCatalogo');
    const btnReset = document.getElementById('btnResetParametroCatalogo');
    const modal = document.getElementById('modalGestionParametrosCatalogo');

    const resetForm = () => {
        form.reset();
        if (idInput) idInput.value = '';
        if (accionInput) accionInput.value = 'crear_parametro_catalogo';
        if (btnGuardar) btnGuardar.textContent = 'Guardar';
    };

    btnReset?.addEventListener('click', resetForm);
    modal?.addEventListener('show.bs.modal', resetForm);

    document.addEventListener('click', function (e) {
        const btnEdit = e.target.closest('.js-editar-param-catalogo');
        if (!btnEdit) return;

        if (idInput) idInput.value = btnEdit.getAttribute('data-id') || '';
        if (accionInput) accionInput.value = 'editar_parametro_catalogo';
        if (nombreInput) nombreInput.value = btnEdit.getAttribute('data-nombre') || '';
        if (unidadInput) unidadInput.value = btnEdit.getAttribute('data-unidad') || '';
        if (descripcionInput) descripcionInput.value = btnEdit.getAttribute('data-descripcion') || '';
        if (btnGuardar) btnGuardar.textContent = 'Actualizar';
    });
}
