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
    const buttonsEtapa = document.querySelectorAll('[data-add-etapa]');

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

            if (select && select.value && inputCant && inputCant.value) {
                totalItems++;
                const cantidad = parseFloat(inputCant.value) || 0;
                const merma = parseFloat(inputMerma.value) || 0;

                // Obtener el costo unitario referencial desde el atributo 'data-costo'
                const optionSelected = select.options[select.selectedIndex];
                const costoUnitario = parseFloat(optionSelected.getAttribute('data-costo')) || 0;

                // Calcular el costo real incluyendo la merma
                const cantidadReal = cantidad * (1 + (merma / 100));
                costoTotal += (cantidadReal * costoUnitario);
            }
        });

        if (resumenItems) {
            resumenItems.textContent = `${totalItems} insumos/semielaborados agregados.`;
        }
        if (costoTotalEl) {
            costoTotalEl.textContent = `S/ ${costoTotal.toFixed(4)}`;
        }
    };

    const crearFilaInsumo = (etapa, container) => {
        if (!templateInsumo) return;
        const fragment = templateInsumo.content.cloneNode(true);
        const row = fragment.querySelector('.detalle-row');

        // Asignar etapa
        const etapaInput = row.querySelector('.input-etapa-hidden');
        if (etapaInput) etapaInput.value = etapa;

        // Asignar listeners para actualizar costos si el usuario edita
        row.querySelector('.select-insumo').addEventListener('change', calcularResumenYCostos);
        row.querySelector('.input-cantidad').addEventListener('input', calcularResumenYCostos);
        row.querySelector('.input-merma').addEventListener('input', calcularResumenYCostos);

        container.appendChild(fragment);
        calcularResumenYCostos();
    };

    // Event delegation para botones "Agregar insumo a X etapa" en el Acordeón
    document.addEventListener('click', function(e) {
        const btnAdd = e.target.closest('[data-add-etapa]');
        if (btnAdd) {
            const etapa = btnAdd.getAttribute('data-add-etapa');
            const container = btnAdd.closest('.accordion-body').querySelector('[data-etapa-container]');
            if (container) {
                crearFilaInsumo(etapa, container);
            }
        }
    });

    // Delegación para eliminar fila de insumo
    document.addEventListener('click', function(e) {
        const btnRemove = e.target.closest('.js-remove-row');
        if (btnRemove) {
            const row = btnRemove.closest('.detalle-row');
            if (row) {
                row.remove();
                calcularResumenYCostos();
            }
        }
    });
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
        document.querySelectorAll('.lista-insumos-etapa').forEach(c => c.innerHTML = '');
        
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

        if (inputCodigo) inputCodigo.value = codigo;
        if (inputVersion) inputVersion.value = version;
        if (inputProducto) inputProducto.value = idProducto;
        if (inputDescripcion) inputDescripcion.value = 'Fórmula inicial de ' + productoNombre;
        if (inputRendimiento) inputRendimiento.value = '1';

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
