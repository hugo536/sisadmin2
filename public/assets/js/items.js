(function () {
    if (window.__itemsModuleInitialized) return;
    window.__itemsModuleInitialized = true;
    const ROWS_PER_PAGE = 20;
    let currentPage = 1;

    // --- NUEVA FUNCIÓN AGREGADA (PARCHE) ---
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
        return (select.selectedOptions[0].textContent || '').trim();
    }

    function saborDebeOmitirse(nombreSabor = '') {
        const base = normalizarTextoSku(nombreSabor).toLowerCase();
        return base === '' || base === 'ninguno' || base === 'sin sabor';
    }

    function generarSkuProducto({ categoria, marca, sabor, presentacion }) {
        const bloques = [];
        const prefCategoria = obtenerPrefijo(categoria);
        const prefMarca = obtenerPrefijo(marca);
        const prefSabor = obtenerPrefijo(sabor);
        const bloquePresentacion = normalizarTextoSku(presentacion);

        if (prefCategoria) bloques.push(prefCategoria);
        if (prefMarca) bloques.push(prefMarca);
        if (!saborDebeOmitirse(sabor) && prefSabor) bloques.push(prefSabor);
        if (bloquePresentacion) bloques.push(bloquePresentacion);

        return bloques.join('-');
    }

    function bindSkuAuto(config) {
        const tipo = document.getElementById(config.tipoId);
        const sku = document.getElementById(config.skuId);
        const categoria = document.getElementById(config.categoriaId);
        const marca = document.getElementById(config.marcaId);
        const sabor = document.getElementById(config.saborId);
        const presentacion = document.getElementById(config.presentacionId);
        const autoGenerate = config.autoGenerate !== false;
        const forceDisabled = config.forceDisabled === true;
        if (!tipo || !sku) return;

        const apply = () => {
            const value = tipo.value;
            const hasTipo = value.trim() !== '';
            const isItemDetallado = value === 'producto_terminado' || value === 'semielaborado';

            // El SKU se muestra siempre bloqueado para evitar edición manual.
            sku.readOnly = true;
            sku.disabled = true;

            if (!hasTipo) {
                sku.value = '';
                return;
            }

            if (!isItemDetallado) {
                sku.value = '';
                return;
            }

            if (!autoGenerate) {
                return;
            }

            const generado = generarSkuProducto({
                categoria: obtenerTextoSeleccionado(categoria),
                marca: obtenerTextoSeleccionado(marca),
                sabor: obtenerTextoSeleccionado(sabor),
                presentacion: obtenerTextoSeleccionado(presentacion)
            });

            sku.value = generado;
        };

        if (!tipo.dataset.skuAutoBound) {
            [tipo, categoria, marca, sabor, presentacion].forEach((el) => {
                el?.addEventListener('change', apply);
            });
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

        const apply = () => {
            const value = tipo.value;
            const isItemDetallado = value === 'producto_terminado' || value === 'semielaborado';
            const isMateriaPrima = value === 'materia_prima';
            const isMaterialEmpaque = value === 'material_empaque';
            const isServicio = value === 'servicio';

            marcaContainer?.classList.toggle('d-none', isServicio);
            saborContainer?.classList.toggle('d-none', !(isItemDetallado));
            presentacionContainer?.classList.toggle('d-none', !(isItemDetallado));

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

            if (isItemDetallado) {
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

            if (isMateriaPrima || isMaterialEmpaque || isServicio || value === '') {
                if (saborSelect) saborSelect.value = '';
                if (presentacionSelect) presentacionSelect.value = '';
            }

            toggleStockMinimo(config.controlaStockId, config.stockContainerId, config.stockInputId);
            toggleAlertaVencimiento(config.requiereVencimientoId, config.diasAlertaContainerId, config.diasAlertaId);
        };

        if (!tipo.dataset.tipoRulesBound) {
            tipo.addEventListener('change', apply);
            tipo.dataset.tipoRulesBound = '1';
        }
        apply();
    }


    function serializeFormState(form) {
        if (!(form instanceof HTMLFormElement)) return '';

        const entries = [];
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach((field) => {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
                return;
            }

            const name = field.name || field.id;
            if (!name) return;

            if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                entries.push(`${name}:${field.checked ? '1' : '0'}`);
                return;
            }

            entries.push(`${name}:${field.value ?? ''}`);
        });

        return entries.join('|');
    }

    function pulseModalDialog(modalEl) {
        const dialog = modalEl?.querySelector('.modal-dialog');
        if (!dialog) return;

        dialog.classList.remove('modal-dialog-pulse');
        void dialog.offsetWidth;
        dialog.classList.add('modal-dialog-pulse');

        window.setTimeout(() => {
            dialog.classList.remove('modal-dialog-pulse');
        }, 450);
    }

    function bindDirtyCloseGuard(modalId, formId) {
        const modalEl = document.getElementById(modalId);
        const form = document.getElementById(formId);
        if (!modalEl || !form || modalEl.dataset.dirtyCloseGuardBound === '1') return;

        let initialState = '';
        let allowDismiss = false;

        modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                allowDismiss = true;
            });
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            initialState = serializeFormState(form);
            allowDismiss = false;
        });

        modalEl.addEventListener('hide.bs.modal', (event) => {
            if (allowDismiss) {
                allowDismiss = false;
                return;
            }

            if (serializeFormState(form) === initialState) {
                return;
            }

            event.preventDefault();
            pulseModalDialog(modalEl);
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            allowDismiss = false;
        });

        modalEl.dataset.dirtyCloseGuardBound = '1';
    }

    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearItem');
        if (!modalCreate) return;

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearItem');
            if (form) form.reset();

            applyTipoItemRules({
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
                diasAlertaId: 'newDiasAlerta'
            });

            bindSkuAuto({
                tipoId: 'newTipo',
                skuId: 'newSku',
                categoriaId: 'newCategoria',
                marcaId: 'newMarca',
                saborId: 'newSabor',
                presentacionId: 'newPresentacion',
                autoGenerate: true
            });

            bindComercialVisibility({
                tipoId: 'newTipo',
                cardId: 'newComercialCard',
                costoId: 'newCosto'
            });
        });

        bindDirtyCloseGuard('modalCrearItem', 'formCrearItem');
        document.getElementById('formCrearItem')?.addEventListener('submit', (event) => {
            const tipo = document.getElementById('newTipo')?.value;
            if (tipo !== 'producto_terminado' && tipo !== 'semielaborado') {
                const sabor = document.getElementById('newSabor');
                const presentacion = document.getElementById('newPresentacion');
                if (sabor) sabor.value = '';
                if (presentacion) presentacion.value = '';
            }
        });
    }

    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarItem');
        if (!modalEdit) return;

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const form = document.getElementById('formEditarItem');
            form?.reset();

            ['editControlaStock', 'editPermiteDecimales', 'editRequiereLote', 'editRequiereVencimiento'].forEach((id) => {
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
                editMoneda: 'data-moneda',
                editImpuesto: 'data-impuesto',
                editPrecio: 'data-precio',
                editStockMinimo: 'data-stock-minimo',
                editCosto: 'data-costo',
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
                editRequiereVencimiento: 'data-requiere-vencimiento'
            };

            Object.keys(checks).forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.checked = btn.getAttribute(checks[id]) === '1';
            });

            applyTipoItemRules({
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
                diasAlertaId: 'editDiasAlerta'
            });

            bindSkuAuto({
                tipoId: 'editTipo',
                skuId: 'editSku',
                categoriaId: 'editCategoria',
                marcaId: 'editMarca',
                saborId: 'editSabor',
                presentacionId: 'editPresentacion',
                autoGenerate: false,
                forceDisabled: true
            });

            bindComercialVisibility({
                tipoId: 'editTipo',
                cardId: 'editComercialCard',
                costoId: 'editCosto'
            });
        });

        bindDirtyCloseGuard('modalEditarItem', 'formEditarItem');
        document.getElementById('formEditarItem')?.addEventListener('submit', (event) => {
            const tipo = document.getElementById('editTipo')?.value;
            if (tipo !== 'producto_terminado' && tipo !== 'semielaborado') {
                const sabor = document.getElementById('editSabor');
                const presentacion = document.getElementById('editPresentacion');
                if (sabor) sabor.value = '';
                if (presentacion) presentacion.value = '';
            }
        });
    }

    function initCategoriasModal() {
        const form = document.getElementById('formGestionCategoria');
        if (!form) return;

        const accion = document.getElementById('categoriaAccion');
        const idInput = document.getElementById('categoriaId');
        const nombre = document.getElementById('categoriaNombre');
        const descripcion = document.getElementById('categoriaDescripcion');
        const estado = document.getElementById('categoriaEstado');
        const btnGuardar = document.getElementById('btnGuardarCategoria');
        const btnReset = document.getElementById('btnResetCategoria');

        const resetForm = () => {
            if (accion) accion.value = 'crear_categoria';
            if (idInput) idInput.value = '';
            if (nombre) nombre.value = '';
            if (descripcion) descripcion.value = '';
            if (estado) estado.value = '1';
            if (btnGuardar) btnGuardar.textContent = 'Guardar categoría';
        };

        document.querySelectorAll('.btn-editar-categoria').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (accion) accion.value = 'editar_categoria';
                if (idInput) idInput.value = btn.getAttribute('data-id') || '';
                if (nombre) nombre.value = btn.getAttribute('data-nombre') || '';
                if (descripcion) descripcion.value = btn.getAttribute('data-descripcion') || '';
                if (estado) estado.value = btn.getAttribute('data-estado') || '1';
                if (btnGuardar) btnGuardar.textContent = 'Actualizar categoría';
                nombre?.focus();
            });
        });

        btnReset?.addEventListener('click', resetForm);
        document.getElementById('modalGestionCategorias')?.addEventListener('show.bs.modal', resetForm);
    }

    function initGestionItemsModal() {
        const modalEl = document.getElementById('modalGestionItems');
        if (!modalEl) return;

        const editModalEl = document.getElementById('modalEditarAtributo');
        const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
        const gestionModal = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.js-open-gestion-items').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                const trigger = document.getElementById(`${tab}-tab`);
                if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
                gestionModal.show();
            });
        });

        const bindCreateForm = (formId) => {
            const form = document.getElementById(formId);
            if (!form) return;
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = Object.fromEntries(new FormData(form).entries());
                try {
                    await postAction(payload);
                    form.reset();
                    window.location.reload();
                } catch (error) {
                    showError(error.message);
                }
            });
        };

        bindCreateForm('formAgregarMarca');
        bindCreateForm('formAgregarSabor');
        bindCreateForm('formAgregarPresentacion');

        document.querySelectorAll('.js-editar-atributo').forEach((btn) => {
            btn.addEventListener('click', () => {
                const configByTarget = {
                    marca: { accion: 'editar_marca', titulo: 'Editar marca' },
                    sabor: { accion: 'editar_sabor', titulo: 'Editar sabor' },
                    presentacion: { accion: 'editar_presentacion', titulo: 'Editar presentación' }
                };
                const targetConfig = configByTarget[btn.dataset.target || ''] || configByTarget.presentacion;

                document.getElementById('editarAtributoAccion').value = targetConfig.accion;
                document.getElementById('editarAtributoId').value = btn.dataset.id || '';
                document.getElementById('editarAtributoNombre').value = btn.dataset.nombre || '';
                document.getElementById('editarAtributoEstado').checked = (btn.dataset.estado || '1') === '1';
                document.getElementById('tituloEditarAtributo').textContent = targetConfig.titulo;
                editModal?.show();
            });
        });

        document.getElementById('formEditarAtributo')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            const payload = Object.fromEntries(new FormData(form).entries());
            if (!payload.estado) payload.estado = '0';

            try {
                await postAction(payload);
                editModal?.hide();
                await refreshAtributosSelectores();
                window.location.reload();
            } catch (error) {
                showError(error.message);
            }
        });

        document.querySelectorAll('.js-eliminar-atributo').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const confirmed = await confirmAction({ title: '¿Estás seguro?', text: 'Esta acción no se puede deshacer.' });
                if (!confirmed) return;
                try {
                    const result = await postAction({ accion: btn.dataset.accion, id: btn.dataset.id });
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Eliminado!',
                        text: result?.mensaje || 'Registro eliminado correctamente.'
                    });
                    await refreshAtributosSelectores();
                    window.location.reload();
                } catch (error) {
                    showError(error.message);
                }
            });
        });

        document.querySelectorAll('.js-toggle-atributo').forEach((input) => {
            input.addEventListener('change', async () => {
                try {
                    await postAction({
                        accion: input.dataset.accion,
                        id: input.dataset.id,
                        nombre: input.dataset.nombre,
                        estado: input.checked ? '1' : '0'
                    });
                    await refreshAtributosSelectores();
                } catch (error) {
                    input.checked = !input.checked;
                    showError(error.message);
                }
            });
        });


        document.querySelectorAll('.js-swal-confirm').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '0';
                    return;
                }
                event.preventDefault();
                const confirmed = await confirmAction({
                    title: form.dataset.confirmTitle || '¿Confirmar acción?',
                    text: form.dataset.confirmText || 'Esta acción no se puede deshacer.'
                });
                if (!confirmed) return;
                form.dataset.confirmed = '1';
                form.requestSubmit();
            });
        });

        const bindSearch = (inputId, tableId) => {
            const input = document.getElementById(inputId);
            const rows = Array.from(document.querySelectorAll(`#${tableId} tbody tr`));
            if (!input || rows.length === 0) return;
            input.addEventListener('input', () => {
                const term = input.value.toLowerCase().trim();
                rows.forEach((row) => {
                    row.classList.toggle('d-none', !(row.getAttribute('data-search') || '').includes(term));
                });
            });
        };

        bindSearch('buscarMarcas', 'tablaMarcasGestion');
        bindSearch('buscarSabores', 'tablaSaboresGestion');
        bindSearch('buscarPresentaciones', 'tablaPresentacionesGestion');

        modalEl.addEventListener('hidden.bs.modal', async () => {
            try {
                await refreshAtributosSelectores();
            } catch (_) {
            }
        });
    }

    // --- REESCRITO: GESTOR DE TABLA PRINCIPAL AL ESTILO INVENTARIO ---
    function initTableManager() {
        const searchInput = document.getElementById('itemSearch');
        const filtroCategoria = document.getElementById('itemFiltroCategoria');
        const filtroTipo = document.getElementById('itemFiltroTipo');
        const filtroEstado = document.getElementById('itemFiltroEstado');
        const paginationControls = document.getElementById('itemsPaginationControls');
        const paginationInfo = document.getElementById('itemsPaginationInfo');
        const table = document.getElementById('itemsTable');
        
        if (!table) return;

        // Estilos automáticos: Aseguramos el thead sticky y el contenedor responsive
        const thead = table.querySelector('thead');
        if (thead && !thead.classList.contains('tabla-global-sticky-thead')) {
            thead.classList.add('tabla-global-sticky-thead');
        }

        const parent = table.parentElement;
        if (parent && !parent.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive tabla-global-scroll-wrapper';
            parent.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        } else if (parent && !parent.classList.contains('inventario-table-wrapper')) {
            parent.classList.add('tabla-global-scroll-wrapper');
        }

        const allRows = Array.from(table.querySelectorAll('tbody tr'));

        function normalizarTexto(valor) {
            return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        const updateTable = function () {
            const texto = normalizarTexto(searchInput?.value || '');
            const categoria = filtroCategoria?.value || '';
            const tipo = filtroTipo?.value || '';
            const estado = filtroEstado?.value || '';

            const visibleRows = allRows.filter((row) => {
                const rowSearch = normalizarTexto(row.getAttribute('data-search') || '');
                const rowCategoria = row.getAttribute('data-categoria') || '';
                const rowTipo = row.getAttribute('data-tipo') || '';
                const rowEstado = row.getAttribute('data-estado') || '';
                
                const okTexto = texto === '' || rowSearch.includes(texto);
                const okCategoria = categoria === '' || rowCategoria === categoria;
                const okTipo = tipo === '' || rowTipo === tipo;
                const okEstado = estado === '' || rowEstado === estado;

                return okTexto && okCategoria && okTipo && okEstado;
            });

            const totalItems = visibleRows.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / ROWS_PER_PAGE));
            if (currentPage > totalPages) currentPage = totalPages;

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;

            // Usamos las clases d-none como en inventario
            allRows.forEach((row) => row.classList.add('d-none'));
            visibleRows.slice(start, end).forEach((row) => row.classList.remove('d-none'));

            if (paginationInfo) {
                if (totalItems === 0) {
                    paginationInfo.textContent = 'Mostrando 0-0 de 0 resultados';
                } else {
                    paginationInfo.textContent = `Mostrando ${start + 1}-${Math.min(end, totalItems)} de ${totalItems} resultados`;
                }
            }
            
            renderPagination(totalPages);
        };

        function renderPagination(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';

            const addBtn = (label, page, active = false, disabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = label;
                a.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    if (disabled || active || page == null) return;
                    currentPage = page;
                    updateTable();
                });
                li.appendChild(a);
                paginationControls.appendChild(li);
            };

            const addDots = () => {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                const span = document.createElement('span');
                span.className = 'page-link';
                span.textContent = '...';
                li.appendChild(span);
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

            addBtn('Anterior', currentPage - 1, false, currentPage === 1);
            buildPages().forEach((token) => {
                if (token === 'dots') addDots();
                else addBtn(String(token), token, token === currentPage, false);
            });
            addBtn('Siguiente', currentPage + 1, false, currentPage === totalPages || totalPages === 0);
        }

        [searchInput, filtroCategoria, filtroTipo, filtroEstado].forEach((el) => {
            if (!el) return;
            const onFilterChange = () => {
                currentPage = 1;
                updateTable();
            };
            el.addEventListener('input', onFilterChange);
            el.addEventListener('change', onFilterChange);
        });
        updateTable();
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

    document.addEventListener('DOMContentLoaded', () => {
        initCreateModal();
        initEditModal();
        initTableManager();
        initCategoriasModal();
        initGestionItemsModal();
        initEstadoSwitches();
        applyTipoItemRules({
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
            diasAlertaId: 'newDiasAlerta'
        });
        applyTipoItemRules({
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
            diasAlertaId: 'editDiasAlerta'
        });
    });
})();
