(function () {
    if (window.__itemsModuleInitialized) return;
    window.__itemsModuleInitialized = true;
    const ROWS_PER_PAGE = 20;
    let currentPage = 1;

    function getItemsEndpoint(extraParams = {}) {
        const url = new URL(window.location.href);

        Object.entries(extraParams).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });

        return `${url.pathname}${url.search}`;
    }

    function showError(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message || 'Ocurrió un error inesperado.'
            });
        }
        console.error(message);
        return Promise.resolve();
    }

    async function confirmAction(options = {}) {
        const { title = '¿Confirmar acción?', text = 'Esta acción no se puede deshacer.' } = options;
        if (window.Swal && typeof window.Swal.fire === 'function') {
            const result = await window.Swal.fire({
                icon: 'warning',
                title,
                text,
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            return !!result.isConfirmed;
        }
        console.warn(title);
        return false;
    }

    async function postAction(payload) {
        // Leer el token de la meta etiqueta e inyectarlo en el payload AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        payload.csrf_token = csrfToken; 

        const body = new URLSearchParams(payload);
        const response = await fetch(getItemsEndpoint(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        });

        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.mensaje || 'No se pudo completar la operación.');
        }

        return data;
    }

    async function fetchOpcionesAtributos() {
        const response = await fetch(getItemsEndpoint({ accion: 'opciones_atributos_items' }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) throw new Error('No se pudo actualizar la lista de atributos.');
        const data = await response.json();
        if (!data.ok) throw new Error(data.mensaje || 'No se pudo actualizar la lista de atributos.');
        return data;
    }

    function fillSelect(selectId, items, placeholder) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const selected = select.value;
        select.innerHTML = '';

        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);

        items.forEach((item) => {
            const opt = document.createElement('option');
            opt.value = String(item.id);
            opt.textContent = item.nombre;
            select.appendChild(opt);
        });

        if ([...select.options].some((opt) => opt.value === selected)) {
            select.value = selected;
        }
    }

    function fillSelectMapped(selectId, items, placeholder, mapValue, mapLabel) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const selected = select.value;
        select.innerHTML = '';

        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);

        items.forEach((item) => {
            const opt = document.createElement('option');
            opt.value = String(mapValue(item));
            opt.textContent = String(mapLabel(item));
            select.appendChild(opt);
        });

        if ([...select.options].some((opt) => opt.value === selected)) {
            select.value = selected;
        }
    }

    async function refreshAtributosSelectores() {
        const data = await fetchOpcionesAtributos();
        fillSelect('newRubro', data.rubros || [], 'Seleccionar...');
        fillSelect('editRubro', data.rubros || [], 'Seleccionar...');
        fillSelectMapped('newMarca', data.marcas || [], 'Seleccionar marca...', (item) => item.id, (item) => item.nombre);
        fillSelectMapped('editMarca', data.marcas || [], 'Seleccionar marca...', (item) => item.id, (item) => item.nombre);
        fillSelect('newSabor', data.sabores || [], 'Seleccionar sabor...');
        fillSelect('editSabor', data.sabores || [], 'Seleccionar sabor...');
        fillSelect('newPresentacion', data.presentaciones || [], 'Seleccionar presentación...');
        fillSelect('editPresentacion', data.presentaciones || [], 'Seleccionar presentación...');
    }

    function toggleAlertaVencimiento(inputId, containerId, diasInputId) {
        const trigger = document.getElementById(inputId);
        const diasInput = document.getElementById(diasInputId);
        
        if (!trigger || !diasInput) return;

        const applyVisibility = () => {
            const visible = trigger.checked;
            diasInput.disabled = !visible;
            
            if (!visible) {
                diasInput.value = '0';
            } else if (diasInput.value === '' || diasInput.value === '0') {
                diasInput.value = '30';
            }
        };

        if (!trigger.dataset.toggleAlertaBound) {
            trigger.addEventListener('change', applyVisibility);
            trigger.dataset.toggleAlertaBound = '1';
        }
        applyVisibility();
    }

    function toggleStockMinimo(controlaStockId, stockContainerId, stockInputId) {
        const controlaStock = document.getElementById(controlaStockId);
        const stockInput = document.getElementById(stockInputId);
        
        if (!controlaStock || !stockInput) return;

        const applyVisibility = () => {
            const visible = controlaStock.checked;
            stockInput.disabled = !visible;
            
            if (!visible) {
                stockInput.value = '0.0000';
            }
        };

        if (!controlaStock.dataset.toggleStockMinimoBound) {
            controlaStock.addEventListener('change', applyVisibility);
            controlaStock.dataset.toggleStockMinimoBound = '1';
        }
        applyVisibility();
    }

    // --- FUNCIONES DE AUTOGENERACIÓN ---
    function normalizarTextoSku(value = '') {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function obtenerPrefijo(value = '') {
        const limpio = normalizarTextoSku(value).replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        return limpio.slice(0, 2);
    }

    function obtenerTextoSeleccionado(select) {
        if (!select || !select.selectedOptions || !select.selectedOptions[0]) return '';
        if (select.value === '') return ''; 
        return (select.selectedOptions[0].textContent || '').trim();
    }

    function saborDebeOmitirse(nombreSabor = '') {
        const base = normalizarTextoSku(nombreSabor).toLowerCase();
        return base === '' || base === 'ninguno' || base === 'sin sabor';
    }

    function limpiarPresentacionSemielaborado(presentacion = '') {
        const base = normalizarTextoSku(presentacion)
            .replace(/\bx\s*\d+\b/gi, ' ')
            .replace(/\s{2,}/g, ' ')
            .trim();
        return base;
    }

    function generarSkuProducto({ tipo, categoria, marca, sabor, presentacion }) {
        const bloques = [];
        const prefCategoria = obtenerPrefijo(categoria);
        const prefMarca = obtenerPrefijo(marca);
        const prefSabor = obtenerPrefijo(sabor);
        const bloquePresentacion = tipo === 'semielaborado'
            ? limpiarPresentacionSemielaborado(presentacion)
            : normalizarTextoSku(presentacion);

        if (prefCategoria) bloques.push(prefCategoria);
        if (prefMarca) bloques.push(prefMarca);
        if (!saborDebeOmitirse(sabor) && prefSabor) bloques.push(prefSabor);
        if (bloquePresentacion) bloques.push(bloquePresentacion);

        return bloques.join('-');
    }

    function generarNombreProducto({ marca, sabor, presentacion }) {
        const partes = [marca.trim()]; 

        if (!saborDebeOmitirse(sabor)) {
            partes.push(sabor.trim());
        }

        const presLimpia = presentacion.trim();
        if (presLimpia) {
            partes.push(presLimpia);
        }

        return partes.filter((p) => p !== '').join(' ');
    }

    function bindSkuAuto(config) {
        const tipo = document.getElementById(config.tipoId);
        const sku = document.getElementById(config.skuId);
        const nombre = document.getElementById(config.nombreId);
        const autoIdentidad = document.getElementById(config.autoIdentidadId);
        const categoria = document.getElementById(config.categoriaId);
        const marca = document.getElementById(config.marcaId);
        const sabor = document.getElementById(config.saborId);
        const presentacion = document.getElementById(config.presentacionId);
        const mode = config.mode || 'new'; 
        
        if (!tipo || !sku) return;

        const apply = () => {
            const tipoVal = tipo.value;
            const hasTipo = tipoVal.trim() !== '';
            const isItemDetallado = tipoVal === 'producto_terminado' || tipoVal === 'semielaborado';
            const autoOn = autoIdentidad && autoIdentidad.checked;

            if (!hasTipo) {
                sku.readOnly = true;
                if (nombre) nombre.readOnly = true;
                if (autoIdentidad) {
                    autoIdentidad.disabled = true;
                    autoIdentidad.checked = true;
                }
                return;
            }

            if (!isItemDetallado) {
                if (nombre) nombre.readOnly = false;
                sku.readOnly = (mode === 'edit');
                if (autoIdentidad) {
                    autoIdentidad.disabled = true;
                    autoIdentidad.checked = false;
                }
                return;
            }

            if (autoIdentidad) autoIdentidad.disabled = false;

            if (autoOn) {
                if (nombre) nombre.readOnly = true;
                sku.readOnly = true; 

                const catText = obtenerTextoSeleccionado(categoria);
                const marText = obtenerTextoSeleccionado(marca);
                const sabText = obtenerTextoSeleccionado(sabor);
                const presText = obtenerTextoSeleccionado(presentacion);

                if (nombre) {
                    nombre.value = generarNombreProducto({
                        marca: marText,
                        sabor: sabText,
                        presentacion: presText
                    });
                }

                if (mode === 'new') {
                    sku.value = generarSkuProducto({
                        tipo: tipoVal,
                        categoria: catText,
                        marca: marText,
                        sabor: sabText,
                        presentacion: presText
                    });
                }

            } else {
                if (nombre) nombre.readOnly = false;
                sku.readOnly = (mode === 'edit'); 
            }
        };

        if (!tipo.dataset.skuAutoBound) {
            [tipo, categoria, marca, sabor, presentacion].forEach((el) => {
                el?.addEventListener('change', apply);
            });

            if (autoIdentidad) {
                autoIdentidad.addEventListener('change', () => {
                    if (!autoIdentidad.checked && nombre) {
                        nombre.focus();
                    }
                    apply();
                });
            }
            tipo.dataset.skuAutoBound = '1';
        }

        apply();
    }

    function bindComercialVisibility(config) {
        const tipo = document.getElementById(config.tipoId);
        const card = document.getElementById(config.cardId);
        const costo = document.getElementById(config.costoId);
        if (!tipo || !card || !costo) return;

        const tiposComercialesEditables = new Set(['materia_prima', 'insumo', 'repuestos']);

        const apply = () => {
            const value = tipo.value;
            const editable = tiposComercialesEditables.has(value);
            const isItemDetallado = value === 'producto_terminado' || value === 'semielaborado';
            const fields = card.querySelectorAll('input, select, textarea');

            card.classList.toggle('opacity-75', !editable);

            fields.forEach((field) => {
                if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
                    return;
                }

                if (field.id === config.costoId) {
                    field.readOnly = !editable || isItemDetallado;
                    field.disabled = false;
                    return;
                }

                if (field instanceof HTMLInputElement && ['checkbox', 'radio', 'hidden'].includes(field.type)) {
                    return;
                }

                if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
                    field.readOnly = !editable;
                } else {
                    field.disabled = !editable;
                }
            });
        };

        if (!tipo.dataset.comercialVisibilityBound) {
            tipo.addEventListener('change', apply);
            tipo.dataset.comercialVisibilityBound = '1';
        }

        apply();
    }

    function applyTipoItemRules(config) {
        const tipo = document.getElementById(config.tipoId);
        if (!tipo) return;

        const marcaContainer = document.getElementById(config.marcaContainerId);
        const saborContainer = document.getElementById(config.saborContainerId);
        const presentacionContainer = document.getElementById(config.presentacionContainerId);
        const saborSelect = document.getElementById(config.saborId);
        const presentacionSelect = document.getElementById(config.presentacionId);
        const stockContainer = document.getElementById(config.stockContainerId);
        const permiteDecimalesContainer = document.getElementById(config.permiteDecimalesContainerId);
        const requiereLoteContainer = document.getElementById(config.requiereLoteContainerId);
        const requiereVencimientoContainer = document.getElementById(config.requiereVencimientoContainerId);
        const controlaStock = document.getElementById(config.controlaStockId);
        const stockInput = document.getElementById(config.stockInputId);
        const permiteDecimales = document.getElementById(config.permiteDecimalesId);
        const requiereLote = document.getElementById(config.requiereLoteId);
        const requiereVencimiento = document.getElementById(config.requiereVencimientoId);
        const autoIdentidadWrap = document.getElementById(config.autoIdentidadWrapId);
        const aplicarPresetsDetallado = config.aplicarPresetsDetallado !== false;

        const apply = () => {
            const value = tipo.value;
            const isItemDetallado = value === 'producto_terminado' || value === 'semielaborado';
            const isMateriaPrima = value === 'materia_prima';
            const isMaterialEmpaque = value === 'material_empaque';
            const isServicio = value === 'servicio';

            marcaContainer?.classList.toggle('d-none', isServicio);
            saborContainer?.classList.toggle('d-none', !(isItemDetallado));
            presentacionContainer?.classList.toggle('d-none', !(isItemDetallado));
            autoIdentidadWrap?.classList.toggle('d-none', !isItemDetallado);

            if (saborSelect) {
                saborSelect.required = isItemDetallado;
                if (!isItemDetallado) saborSelect.value = '';
            }
            if (presentacionSelect) {
                presentacionSelect.required = isItemDetallado;
                if (!isItemDetallado) presentacionSelect.value = '';
            }

            if (isServicio) {
                controlaStock.checked = false;
                stockInput.value = '0.0000';
                permiteDecimales.checked = false;
                requiereLote.checked = false;
                requiereVencimiento.checked = false;
            }

            if (isMateriaPrima) {
                permiteDecimales.checked = true;
                requiereLote.checked = true;
            }

            if (isItemDetallado && aplicarPresetsDetallado) {
                controlaStock.checked = true;
                requiereLote.checked = true;
                requiereVencimiento.checked = true;
            }

            if (isMaterialEmpaque) {
                permiteDecimales.checked = false;
            }

            stockContainer?.classList.toggle('d-none', isServicio);
            permiteDecimalesContainer?.classList.toggle('d-none', isServicio);
            requiereLoteContainer?.classList.toggle('d-none', isServicio);
            requiereVencimientoContainer?.classList.toggle('d-none', isServicio);

            toggleStockMinimo(config.controlaStockId, config.stockContainerId, config.stockInputId);
            toggleAlertaVencimiento(config.requiereVencimientoId, config.diasAlertaContainerId, config.diasAlertaId);
        };

        if (!tipo.dataset.tipoRulesBound) {
            tipo.addEventListener('change', apply);
            tipo.dataset.tipoRulesBound = '1';
        }
        apply();
    }

    function bindDirtyCloseGuard(modalId, formId) {
        const modalEl = document.getElementById(modalId);
        const form = document.getElementById(formId);
        if (!modalEl || !form || modalEl.dataset.dirtyCloseGuardBound === '1') return;

        let allowDismiss = false;

        modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((btn) => {
            const allowAndClose = () => { allowDismiss = true; };
            btn.addEventListener('pointerdown', allowAndClose, true);
            btn.addEventListener('click', allowAndClose, true);
        });

        modalEl.addEventListener('shown.bs.modal', () => { allowDismiss = false; });
        modalEl.addEventListener('hidden.bs.modal', () => { allowDismiss = false; });
        modalEl.dataset.dirtyCloseGuardBound = '1';
    }

    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearItem');
        if (!modalCreate) return;

        const tipoConfig = {
            tipoId: 'newTipo',
            marcaContainerId: 'newMarcaContainer',
            saborContainerId: 'newSaborContainer',
            presentacionContainerId: 'newPresentacionContainer',
            saborId: 'newSabor',
            presentacionId: 'newPresentacion',
            controlaStockId: 'newControlaStock',
            stockContainerId: 'newStockMinContainer',
            stockInputId: 'newStockMin',
            permiteDecimalesContainerId: 'newPermiteDecimalesContainer',
            requiereLoteContainerId: 'newRequiereLoteContainer',
            requiereVencimientoContainerId: 'newRequiereVencimientoContainer',
            permiteDecimalesId: 'newPermiteDecimales',
            requiereLoteId: 'newRequiereLote',
            requiereVencimientoId: 'newRequiereVencimiento',
            diasAlertaContainerId: 'newDiasAlertaContainer',
            diasAlertaId: 'newDiasAlerta',
            autoIdentidadWrapId: 'newAutoIdentidadWrap',
            autoIdentidadId: 'newAutoIdentidad',
            aplicarPresetsDetallado: true
        };

        const skuConfig = {
            mode: 'new', 
            tipoId: 'newTipo',
            skuId: 'newSku',
            nombreId: 'newNombre',
            autoIdentidadId: 'newAutoIdentidad',
            categoriaId: 'newCategoria',
            marcaId: 'newMarca',
            saborId: 'newSabor',
            presentacionId: 'newPresentacion'
        };

        applyTipoItemRules(tipoConfig);
        bindSkuAuto(skuConfig);

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearItem');
            if (form) form.reset();
            const autoIdentidad = document.getElementById('newAutoIdentidad');
            if (autoIdentidad) {
                autoIdentidad.checked = true;
            }

            applyTipoItemRules(tipoConfig);
            bindSkuAuto(skuConfig);

            bindComercialVisibility({
                tipoId: 'newTipo',
                cardId: 'newComercialCard',
                costoId: 'newCosto'
            });
        });

        bindDirtyCloseGuard('modalCrearItem', 'formCrearItem');

        const skuInput = document.getElementById('newSku');
        let skuDebounceTimer = null;

        skuInput.addEventListener('input', function () {
            // Si está readonly (autogenerando), ignoramos
            if (this.readOnly) {
                this.classList.remove('is-valid', 'is-invalid');
                return;
            }

            clearTimeout(skuDebounceTimer);
            const skuVal = this.value.trim();
            this.classList.remove('is-valid', 'is-invalid');

            if (!skuVal) return;

            // Esperamos 500ms a que termine de escribir para no saturar el servidor
            skuDebounceTimer = setTimeout(async () => {
                try {
                    const response = await fetch(getItemsEndpoint({ accion: 'validar_sku', sku: skuVal }), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (data.ok) {
                        if (data.existe) {
                            skuInput.classList.add('is-invalid'); // Pone el borde rojo y muestra el error
                        } else {
                            skuInput.classList.add('is-valid'); // Pone el borde verde
                        }
                    }
                } catch (error) {
                    console.error("Error validando SKU:", error);
                }
            }, 500);
        });
    }

    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarItem');
        if (!modalEdit) return;

        const tipoConfig = {
            tipoId: 'editTipo',
            marcaContainerId: 'editMarcaContainer',
            saborContainerId: 'editSaborContainer',
            presentacionContainerId: 'editPresentacionContainer',
            saborId: 'editSabor',
            presentacionId: 'editPresentacion',
            controlaStockId: 'editControlaStock',
            stockContainerId: 'editStockMinimoContainer',
            stockInputId: 'editStockMinimo',
            permiteDecimalesContainerId: 'editPermiteDecimalesContainer',
            requiereLoteContainerId: 'editRequiereLoteContainer',
            requiereVencimientoContainerId: 'editRequiereVencimientoContainer',
            permiteDecimalesId: 'editPermiteDecimales',
            requiereLoteId: 'editRequiereLote',
            requiereVencimientoId: 'editRequiereVencimiento',
            diasAlertaContainerId: 'editDiasAlertaContainer',
            diasAlertaId: 'editDiasAlerta',
            autoIdentidadWrapId: 'editAutoIdentidadWrap',
            autoIdentidadId: 'editAutoIdentidad',
            aplicarPresetsDetallado: false
        };

        const skuConfig = {
            mode: 'edit', 
            tipoId: 'editTipo',
            skuId: 'editSku',
            nombreId: 'editNombre',
            autoIdentidadId: 'editAutoIdentidad',
            categoriaId: 'editCategoria',
            marcaId: 'editMarca',
            saborId: 'editSabor',
            presentacionId: 'editPresentacion'
        };

        applyTipoItemRules(tipoConfig);
        bindSkuAuto(skuConfig);

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const form = document.getElementById('formEditarItem');
            form?.reset();
            
            const autoIdentidad = document.getElementById('editAutoIdentidad');
            if (autoIdentidad) {
                autoIdentidad.checked = false;
            }

            ['editControlaStock', 'editPermiteDecimales', 'editRequiereLote', 'editRequiereVencimiento', 'editRequiereFormulaBom', 'editRequiereFactorConversion', 'editEsEnvaseRetornable'].forEach((id) => {
                const input = document.getElementById(id);
                if (input) input.checked = false;
            });

            const fields = {
                editId: 'data-id',
                editSku: 'data-sku',
                editNombre: 'data-nombre',
                editDescripcion: 'data-descripcion',
                editTipo: 'data-tipo',
                editMarca: 'data-marca',
                editUnidad: 'data-unidad',       
                editUnidadSelect: 'data-unidad', 
                editMoneda: 'data-moneda',
                editImpuesto: 'data-impuesto',
                editPrecio: 'data-precio',
                editStockMinimo: 'data-stock-minimo',
                editCosto: 'data-costo',
                editPesoKg: 'data-peso-kg',
                editRubro: 'data-rubro',
                editCategoria: 'data-categoria',
                editEstado: 'data-estado',
                editSabor: 'data-sabor',
                editPresentacion: 'data-presentacion',
                editDiasAlerta: 'data-dias-alerta-vencimiento'
            };

            Object.keys(fields).forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = btn.getAttribute(fields[id]) || '';
            });

            const checks = {
                editControlaStock: 'data-controla-stock',
                editPermiteDecimales: 'data-permite-decimales',
                editRequiereLote: 'data-requiere-lote',
                editRequiereVencimiento: 'data-requiere-vencimiento',
                editRequiereFormulaBom: 'data-requiere-formula-bom',
                editRequiereFactorConversion: 'data-requiere-factor-conversion',
                editEsEnvaseRetornable: 'data-es-envase-retornable'
            };

            Object.keys(checks).forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.checked = btn.getAttribute(checks[id]) === '1';
            });

            applyTipoItemRules(tipoConfig);
            bindSkuAuto(skuConfig);

            bindComercialVisibility({
                tipoId: 'editTipo',
                cardId: 'editComercialCard',
                costoId: 'editCosto'
            });
        });

        bindDirtyCloseGuard('modalEditarItem', 'formEditarItem');
    }

    function initTableManager() {
        const searchInput = document.getElementById('itemSearch');
        const filtroCategoria = document.getElementById('itemFiltroCategoria');
        const filtroTipo = document.getElementById('itemFiltroTipo');
        const filtroEstado = document.getElementById('itemFiltroEstado');
        const paginationControls = document.getElementById('itemsPaginationControls');
        const paginationInfo = document.getElementById('itemsPaginationInfo');
        const tableBody = document.getElementById('itemsTableBody');

        if (!tableBody) return;

        let debounceTimer = null;

        const tipoLabels = {
            'producto': 'Producto terminado',
            'producto_terminado': 'Producto terminado',
            'materia_prima': 'Materia prima',
            'material_empaque': 'Material de empaque',
            'servicio': 'Servicio',
            'semielaborado': 'Semielaborado',
            'insumo': 'Insumo'
        };

        const getCategoriaNombre = (id) => {
            if (!id || id === 0) return 'Sin categoría';
            const opt = filtroCategoria?.querySelector(`option[value="${id}"]`);
            return opt ? opt.textContent.trim() : 'Sin categoría';
        };

        const escapeHtml = (unsafe) => {
            return (unsafe || '').toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        };

        const loadTableData = async () => {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Cargando ítems...</td></tr>`;

            try {
                const params = {
                    accion: 'datatable',
                    pagina: currentPage,
                    limite: ROWS_PER_PAGE,
                    busqueda: searchInput?.value || '',
                    categoria: filtroCategoria?.value || '',
                    tipo: filtroTipo?.value || '',
                    estado: filtroEstado?.value || ''
                };

                const response = await fetch(getItemsEndpoint(params), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) throw new Error('Error al obtener datos');
                const data = await response.json();

                renderTable(data.data || []);
                renderPagination(data.recordsFiltered || 0);

                if (paginationInfo) {
                    const total = data.recordsFiltered || 0;
                    if (total === 0) {
                        paginationInfo.innerHTML = 'Mostrando <strong>0</strong> resultados';
                    } else {
                        const start = (currentPage - 1) * ROWS_PER_PAGE + 1;
                        const end = Math.min(currentPage * ROWS_PER_PAGE, total);
                        paginationInfo.innerHTML = `Mostrando <strong>${start}-${end}</strong> de <strong>${total}</strong> resultados`;
                    }
                }
            } catch (error) {
                console.error(error);
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4"><i class="bi bi-x-circle me-2"></i>Ocurrió un error al cargar los datos.</td></tr>`;
            }
        };

        const renderTable = (items) => {
            if (items.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-5">No se encontraron ítems con los filtros actuales.</td></tr>`;
                return;
            }

            // SE AGREGÓ ESTA LÍNEA PARA LEER EL TOKEN Y PONERLO EN EL BOTON DE ELIMINAR DINAMICO
            const csrfMetaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            tableBody.innerHTML = items.map(item => {
                const isActivo = item.estado === 1;
                const estadoBadge = isActivo 
                    ? `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" id="badge_status_item_${item.id}">Activo</span>` 
                    : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill" id="badge_status_item_${item.id}">Inactivo</span>`;
                
                const tipoLabel = tipoLabels[item.tipo_item] || item.tipo_item;
                const catNombre = getCategoriaNombre(item.id_categoria);
                const bomIcon = item.bom_pendiente === 1 
                    ? `<i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Falta agregar una receta"></i>` 
                    : '';

const imgHtml = item.imagen_principal
    ? `<img src="/${escapeHtml(item.imagen_principal)}" alt="Foto" class="rounded object-fit-cover border shadow-sm" style="width: 40px; height: 40px; background: #fff;">`
    : `<div class="bg-secondary-subtle rounded border d-flex align-items-center justify-content-center text-secondary shadow-sm" style="width: 40px; height: 40px;"><i class="bi bi-box-seam"></i></div>`;
                const btnEliminar = item.puede_eliminar === 1
                    ? `<button type="submit" class="btn btn-sm border-0 bg-transparent btn-light text-danger" title="Eliminar"><i class="bi bi-trash fs-5"></i></button>`
                    : `<button type="button" class="btn btn-sm border-0 bg-transparent btn-light text-muted opacity-50" title="${escapeHtml(item.motivo_no_eliminar)}" disabled aria-disabled="true"><i class="bi bi-trash fs-5"></i></button>`;

                return `
                    <tr data-id="${item.id}">
                        <td class="ps-4 align-middle text-center">${imgHtml}</td>
                        <td class="fw-semibold text-secondary">${escapeHtml(item.sku)}</td>
                        <td>
                            <div class="fw-bold text-dark d-inline-flex align-items-center gap-1">
                                <span>${escapeHtml(item.nombre)}</span>
                                ${bomIcon}
                            </div>
                            <div class="small text-muted">${escapeHtml(item.descripcion)}</div>
                        </td>
                        <td><span class="badge bg-light text-dark border">${escapeHtml(tipoLabel)}</span></td>
                        <td>${escapeHtml(catNombre)}</td>
                        <td class="text-center">${estadoBadge}</td>
                        <td class="text-end pe-4">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <div class="form-check form-switch pt-1" title="Cambiar estado">
                                    <input class="form-check-input switch-estado-item-dynamic" type="checkbox" role="switch" style="cursor: pointer; width: 2.5em; height: 1.25em;" data-id="${item.id}" ${isActivo ? 'checked' : ''}>
                                </div>
                                <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>
                                
                                <a href="?ruta=items/perfil&id=${item.id}" class="btn btn-sm btn-light text-info border-0 bg-transparent" title="Ver perfil y documentos">
                                    <i class="bi bi-person-badge fs-5"></i>
                                </a>

                                <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarItem"
                                    data-id="${item.id}"
                                    data-sku="${escapeHtml(item.sku)}"
                                    data-nombre="${escapeHtml(item.nombre)}"
                                    data-descripcion="${escapeHtml(item.descripcion)}"
                                    data-tipo="${escapeHtml(item.tipo_item)}"
                                    data-marca="${item.id_marca || ''}"
                                    data-unidad="${escapeHtml(item.unidad_base)}"
                                    data-moneda="${escapeHtml(item.moneda)}"
                                    data-impuesto="${item.impuesto}"
                                    data-precio="${item.precio_venta}"
                                    data-stock-minimo="${item.stock_minimo}"
                                    data-costo="${item.costo_referencial}"
                                    data-peso-kg="${item.peso_kg}"
                                    data-controla-stock="${item.controla_stock}"
                                    data-permite-decimales="${item.permite_decimales}"
                                    data-requiere-lote="${item.requiere_lote}"
                                    data-requiere-vencimiento="${item.requiere_vencimiento}"
                                    data-requiere-formula-bom="${item.requiere_formula_bom}"
                                    data-requiere-factor-conversion="${item.requiere_factor_conversion}"
                                    data-es-envase-retornable="${item.es_envase_retornable}"
                                    data-dias-alerta-vencimiento="${item.dias_alerta_vencimiento || ''}"
                                    data-rubro="${item.id_rubro || ''}"
                                    data-categoria="${item.id_categoria || ''}"
                                    data-sabor="${item.id_sabor || ''}"
                                    data-presentacion="${item.id_presentacion || ''}"
                                    data-estado="${item.estado}">
                                    <i class="bi bi-pencil-square fs-5"></i>
                                </button>

                                <form method="post" class="d-inline m-0 form-eliminar-dinamico">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="${item.id}">
                                    <input type="hidden" name="csrf_token" value="${csrfMetaToken}">
                                    ${btnEliminar}
                                </form>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            bindDynamicEvents();
        };

        const renderPagination = (totalItems) => {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';
            
            const totalPages = Math.max(1, Math.ceil(totalItems / ROWS_PER_PAGE));
            if (currentPage > totalPages) currentPage = totalPages || 1;

            const addBtn = (label, page, active = false, disabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.innerHTML = label;
                a.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    if (disabled || active || page == null) return;
                    currentPage = page;
                    loadTableData();
                });
                li.appendChild(a);
                paginationControls.appendChild(li);
            };

            const addDots = () => {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">...</span>';
                paginationControls.appendChild(li);
            };

            const buildPages = () => {
                const pages = new Set([1, totalPages]);
                for (let i = currentPage - 1; i <= currentPage + 1; i += 1) {
                    if (i > 1 && i < totalPages) pages.add(i);
                }
                const ordered = Array.from(pages).sort((a, b) => a - b);
                const tokens = [];
                ordered.forEach((page, idx) => {
                    if (idx > 0 && page - ordered[idx - 1] > 1) tokens.push('dots');
                    tokens.push(page);
                });
                return tokens;
            };

            addBtn('&laquo; Anterior', currentPage - 1, false, currentPage <= 1);
            buildPages().forEach((token) => {
                if (token === 'dots') addDots();
                else addBtn(String(token), token, token === currentPage, false);
            });
            addBtn('Siguiente &raquo;', currentPage + 1, false, currentPage >= totalPages || totalPages === 0);
        };

        const bindDynamicEvents = () => {
            tableBody.querySelectorAll('.switch-estado-item-dynamic').forEach((switchInput) => {
                switchInput.addEventListener('change', async function () {
                    const id = Number(this.getAttribute('data-id') || 0);
                    if (id <= 0) return;

                    const nuevoEstado = this.checked ? 1 : 0;
                    this.disabled = true;

                    try {
                        await postAction({ accion: 'toggle_estado_item', id: String(id), estado: String(nuevoEstado) });
                        const badge = document.getElementById(`badge_status_item_${id}`);
                        if (badge) {
                            badge.textContent = nuevoEstado === 1 ? 'Activo' : 'Inactivo';
                            badge.className = nuevoEstado === 1 
                                ? 'badge bg-success-subtle text-success border border-success-subtle rounded-pill'
                                : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill';
                        }
                    } catch (error) {
                        this.checked = !this.checked;
                        showError(error?.message || 'No se pudo actualizar el estado.');
                    } finally {
                        this.disabled = false;
                    }
                });
            });

            tableBody.querySelectorAll('.form-eliminar-dinamico').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const confirmed = await confirmAction({
                        title: '¿Eliminar ítem?',
                        text: 'Esta acción no se puede deshacer.'
                    });
                    if (confirmed) form.submit();
                });
            });
        };

        const onFilterChange = () => {
            currentPage = 1;
            loadTableData();
        };

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(onFilterChange, 350); 
            });
        }
        
        [filtroCategoria, filtroTipo, filtroEstado].forEach(el => {
            if (el) el.addEventListener('change', onFilterChange);
        });

        loadTableData();
    }

    function initEstadoSwitches() {
        document.querySelectorAll('.switch-estado-item').forEach((switchInput) => {
            if (switchInput.dataset.boundEstado === '1') return;
            switchInput.dataset.boundEstado = '1';

            switchInput.addEventListener('change', async function () {
                const id = Number(this.getAttribute('data-id') || 0);
                if (id <= 0) return;

                const nuevoEstado = this.checked ? 1 : 0;
                this.disabled = true;

                try {
                    await postAction({ accion: 'toggle_estado_item', id: String(id), estado: String(nuevoEstado) });

                    const row = this.closest('tr');
                    if (row) row.setAttribute('data-estado', String(nuevoEstado));

                    const badge = document.getElementById(`badge_status_item_${id}`);
                    if (badge) {
                        badge.textContent = nuevoEstado === 1 ? 'Activo' : 'Inactivo';
                        badge.classList.toggle('status-active', nuevoEstado === 1);
                        badge.classList.toggle('status-inactive', nuevoEstado !== 1);
                    }
                } catch (error) {
                    this.checked = !this.checked;
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error?.message || 'No se pudo actualizar el estado.'
                    });
                } finally {
                    this.disabled = false;
                }
            });
        });
    }


    window.ItemsShared = window.ItemsShared || {};
    window.ItemsShared.getItemsEndpoint = getItemsEndpoint;
    window.ItemsShared.showError = showError;
    window.ItemsShared.confirmAction = confirmAction;
    window.ItemsShared.postAction = postAction;
    window.ItemsShared.refreshAtributosSelectores = refreshAtributosSelectores;

    document.addEventListener('DOMContentLoaded', () => {
        // --- SE AGREGÓ ESTE BLOQUE PARA PROTEGER LOS FORMULARIOS ESTÁTICOS ---
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            document.querySelectorAll('form[method="post"]').forEach(form => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = token;
                form.appendChild(input);
            });
        }
        // ---------------------------------------------------------------------

        initCreateModal();
        initEditModal();
        initTableManager();
        initEstadoSwitches();

        if (window.ItemsCategoriasRubros?.init) window.ItemsCategoriasRubros.init();
        if (window.ItemsAtributos?.init) window.ItemsAtributos.init();
        if (window.ItemsUnidadesConversion?.init) window.ItemsUnidadesConversion.init();
    });
})();
