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
    const parseNumero = (valor) => {
        if (typeof valor === 'number') return Number.isFinite(valor) ? valor : 0;
        const normalizado = String(valor ?? '').trim().replace(',', '.');
        const numero = parseFloat(normalizado);
        return Number.isFinite(numero) ? numero : 0;
    };

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

    const limpiarFormularioReceta = () => {
        const form = document.getElementById('formCrearReceta');
        if (form) form.reset();

        listaInsumos.innerHTML = '';
        contenedorParametros.innerHTML = '';
        if (emptyParametros) emptyParametros.style.display = 'block';

        const resumen = document.getElementById('bomResumen');
        if (resumen) resumen.textContent = '0 insumos agregados.';
        if (costoTotalEl) costoTotalEl.textContent = 'S/ 0.0000';

        const inputCodigo = document.getElementById('newCodigo');
        if (inputCodigo) inputCodigo.removeAttribute('readonly');

        const inputIdRecetaBase = document.getElementById('newIdRecetaBase');
        if (inputIdRecetaBase) inputIdRecetaBase.value = '0';
    };

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

            if (select && select.value && inputCant) {
                totalItems++;
                
                // Leemos los valores directamente de los inputs
                const cantidad = parseNumero(inputCant.value);
                const merma = parseNumero(inputMerma.value);
                const costoUnitario = parseNumero(inputCostoUnitario.value);

                // Fórmula matemática: Cantidad * (1 + porcentaje de merma) * Costo
                const cantidadReal = cantidad * (1 + (merma / 100));
                const costoItem = cantidadReal * costoUnitario;
                costoTotal += costoItem;

                if (inputCostoItem) {
                    inputCostoItem.value = costoItem.toFixed(4);
                }
            } else {
                if (inputCostoItem) inputCostoItem.value = '0.0000';
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

    const inicializarBuscadorInsumo = (select, row) => {
        if (!select || typeof TomSelect === 'undefined') return;

        const tom = new TomSelect(select, {
            create: false,
            maxItems: 1,
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            sortField: [{ field: 'text', direction: 'asc' }],
            onChange: function(value) {
                // Cuando el usuario elige un item, sacamos su costo de la base de datos (data-costo)
                const opt = select.querySelector(`option[value="${value}"]`);
                const costoBD = opt ? parseNumero(opt.getAttribute('data-costo')) : 0;
                
                // Actualizamos el input de costo unitario
                const inputCostoUni = row.querySelector('.input-costo-unitario');
                if (inputCostoUni) {
                    inputCostoUni.value = costoBD.toFixed(4);
                }
                
                sincronizarOpcionesInsumo();
                calcularResumenYCostos();
            }
        });

        instanciasTomSelect.set(select, tom);
    };

    const crearFilaInsumo = () => {
        // 1. Bloqueo de seguridad: Obligar a seleccionar producto primero
        if (!inputProducto || !inputProducto.value) {
            alert('Por favor, seleccione el "Producto Destino" primero para evitar circularidad.');
            if (inputProducto) inputProducto.focus();
            return;
        }

        if (!templateInsumo || !listaInsumos) return;
        const fragment = templateInsumo.content.cloneNode(true);
        const row = fragment.querySelector('.detalle-row');
        const selectInsumo = row.querySelector('.select-insumo');

        // 2. PREVENIR CIRCULARIDAD: Eliminar el producto actual de la lista ANTES de Tom Select
        const idProductoActual = inputProducto.value;
        const opcionMismoProducto = selectInsumo.querySelector(`option[value="${idProductoActual}"]`);
        if (opcionMismoProducto) {
            opcionMismoProducto.remove(); 
        }

        // 3. Permitir escribir el costo a mano (ya que en tu BD están en 0.00)
        const inputCostoUni = row.querySelector('.input-costo-unitario');
        if (inputCostoUni) {
            inputCostoUni.removeAttribute('readonly'); 
            inputCostoUni.addEventListener('input', calcularResumenYCostos);
        }

        // 4. Listeners de cantidad y merma
        row.querySelector('.input-cantidad').addEventListener('input', calcularResumenYCostos);
        row.querySelector('.input-merma').addEventListener('input', calcularResumenYCostos);

        listaInsumos.appendChild(fragment);
        
        // Pasamos 'row' como segundo parámetro para que TomSelect actualice el costo de esta fila
        inicializarBuscadorInsumo(selectInsumo, row);
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

    const crearFilaParametro = (data = null) => {
        if (!templateParametro || !contenedorParametros) return;
        const fragment = templateParametro.content.cloneNode(true);
        const row = fragment.querySelector('.parametro-row');
        if (!row) return;

        const selectParametro = row.querySelector('select[name="parametro_id[]"]');
        const inputValor = row.querySelector('input[name="parametro_valor[]"]');

        if (selectParametro && data && data.id_parametro) {
            selectParametro.value = String(data.id_parametro);
        }
        if (inputValor && data && data.valor_objetivo !== undefined) {
            inputValor.value = parseNumero(data.valor_objetivo).toFixed(4);
        }

        contenedorParametros.appendChild(fragment);
        if (emptyParametros) {
            emptyParametros.style.display = contenedorParametros.querySelectorAll('.parametro-row').length === 0 ? 'block' : 'none';
        }
    };

    const crearFilaInsumoConData = (detalle = null) => {
        crearFilaInsumo();
        if (!detalle) return;

        const rows = listaInsumos.querySelectorAll('.detalle-row');
        const row = rows[rows.length - 1];
        if (!row) return;

        const select = row.querySelector('.select-insumo');
        const inputEtapa = row.querySelector('.input-etapa-hidden');
        const inputCantidad = row.querySelector('.input-cantidad');
        const inputMerma = row.querySelector('.input-merma');
        const inputCostoUnitario = row.querySelector('.input-costo-unitario');

        if (select && detalle.id_insumo) {
            const tom = instanciasTomSelect.get(select);
            if (tom) {
                tom.setValue(String(detalle.id_insumo), true);
            } else {
                select.value = String(detalle.id_insumo);
            }
        }
        if (inputEtapa) inputEtapa.value = String(detalle.etapa || 'General');
        if (inputCantidad) inputCantidad.value = parseNumero(detalle.cantidad_por_unidad).toFixed(4);
        if (inputMerma) inputMerma.value = parseNumero(detalle.merma_porcentaje).toFixed(4);
        if (inputCostoUnitario) inputCostoUnitario.value = parseNumero(detalle.costo_unitario).toFixed(4);

        calcularResumenYCostos();
    };

    window.produccionRecetaFormAPI = {
        limpiarFormularioReceta,
        setCabecera(data) {
            const inputCodigo = document.getElementById('newCodigo');
            const inputVersion = document.getElementById('newVersion');
            const inputProducto = document.getElementById('newProducto');
            const inputDescripcion = document.getElementById('newDescripcion');
            const inputRendimiento = document.getElementById('newRendimientoBase');
            const inputUnidad = document.getElementById('newUnidadRendimiento');

            if (inputCodigo) {
                inputCodigo.value = String(data.codigo || '');
                inputCodigo.setAttribute('readonly', 'readonly');
            }
            if (inputVersion) inputVersion.value = String(data.version || 1);
            if (inputProducto) inputProducto.value = String(data.id_producto || '');
            if (inputDescripcion) inputDescripcion.value = String(data.descripcion || '');
            if (inputRendimiento) inputRendimiento.value = parseNumero(data.rendimiento_base).toFixed(4);
            if (inputUnidad) inputUnidad.value = String(data.unidad_rendimiento || '');

            sincronizarOpcionesInsumo();
        },
        setIdRecetaBase(idReceta) {
            const inputIdRecetaBase = document.getElementById('newIdRecetaBase');
            if (inputIdRecetaBase) inputIdRecetaBase.value = String(idReceta || 0);
        },
        cargarDetalles(detalles) {
            listaInsumos.innerHTML = '';
            (Array.isArray(detalles) ? detalles : []).forEach((detalle) => {
                crearFilaInsumoConData(detalle);
            });
            calcularResumenYCostos();
        },
        cargarParametros(parametros) {
            contenedorParametros.innerHTML = '';
            (Array.isArray(parametros) ? parametros : []).forEach((parametro) => {
                crearFilaParametro(parametro);
            });
            if (emptyParametros) {
                emptyParametros.style.display = contenedorParametros.querySelectorAll('.parametro-row').length === 0 ? 'block' : 'none';
            }
        },
    };
}

function initAccionesRecetaPendiente() {
    const modalEl = document.getElementById('modalCrearReceta');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const form = document.getElementById('formCrearReceta');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const selectVersionBase = document.getElementById('newVersionBase');
    const contenedorVersionesPrevias = document.getElementById('contenedorVersionesPrevias');
    const modalTitle = document.getElementById('modalCrearRecetaTitle');

    const postAccion = async (accion, data = {}) => {
        const body = new URLSearchParams();
        body.append('accion', accion);
        Object.entries(data).forEach(([k, v]) => body.append(k, String(v)));

        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString(),
        });

        const json = await resp.json();
        if (!json || json.success !== true) {
            throw new Error((json && json.message) || 'No se pudo completar la operación.');
        }
        return json.data;
    };

    const cargarRecetaEnFormulario = async (idReceta) => {
        const api = window.produccionRecetaFormAPI;
        if (!api) return;

        const dataReceta = await postAccion('obtener_receta_version_ajax', { id_receta: idReceta });
        api.setCabecera(dataReceta);
        api.setIdRecetaBase(idReceta);
        api.cargarDetalles(dataReceta.detalles || []);
        api.cargarParametros(dataReceta.parametros || []);
    };

    // Limpiar el formulario y las filas dinámicas al cerrar el modal
    modalEl.addEventListener('hidden.bs.modal', function () {
        const api = window.produccionRecetaFormAPI;
        if (api) {
            api.limpiarFormularioReceta();
        } else if (form) {
            form.reset();
        }

        if (selectVersionBase) {
            selectVersionBase.innerHTML = '<option value="">Seleccione versión...</option>';
            selectVersionBase.value = '';
        }

        if (contenedorVersionesPrevias) contenedorVersionesPrevias.style.display = 'none';
        if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva receta';
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

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-nueva-version');
        if (!btn || !form) return;

        const idRecetaBase = parseInt(btn.getAttribute('data-id-receta') || '0', 10);
        if (!idRecetaBase) return;

        try {
            if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-files me-2"></i>Nueva versión de receta';
            if (contenedorVersionesPrevias) contenedorVersionesPrevias.style.display = '';

            const versiones = await postAccion('listar_versiones_receta_ajax', { id_receta_base: idRecetaBase });

            if (selectVersionBase) {
                selectVersionBase.innerHTML = '';
                (Array.isArray(versiones) ? versiones : []).forEach((version) => {
                    const option = document.createElement('option');
                    option.value = String(version.id);
                    option.textContent = `v.${version.version} - ${version.codigo}${Number(version.estado) === 1 ? ' (Activa)' : ''}`;
                    selectVersionBase.appendChild(option);
                });

                if (selectVersionBase.options.length > 0) {
                    selectVersionBase.value = selectVersionBase.options[0].value;
                }
            }

            const idVersionInicial = selectVersionBase?.value ? parseInt(selectVersionBase.value, 10) : idRecetaBase;
            await cargarRecetaEnFormulario(idVersionInicial);
            modal.show();
        } catch (error) {
            alert(error.message || 'No se pudo cargar la receta para versionar.');
        }
    });

    selectVersionBase?.addEventListener('change', async function () {
        const idReceta = parseInt(this.value || '0', 10);
        if (!idReceta) return;

        try {
            await cargarRecetaEnFormulario(idReceta);
        } catch (error) {
            alert(error.message || 'No se pudo cargar la versión seleccionada.');
        }
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
