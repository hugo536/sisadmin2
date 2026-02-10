(function () {
    if (window.__itemsModuleInitialized) return;
    window.__itemsModuleInitialized = true;
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    // --- NUEVA FUNCIÓN AGREGADA (PARCHE) ---
    function getItemsEndpoint(extraParams = {}) {
        const url = new URL(window.location.href);

        Object.entries(extraParams).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });

        return `${url.pathname}${url.search}`;
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
        // MODIFICADO: Usa getItemsEndpoint con parámetros
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

    async function refreshAtributosSelectores() {
        const data = await fetchOpcionesAtributos();
        fillSelect('newSabor', data.sabores || [], 'Seleccionar sabor...');
        fillSelect('editSabor', data.sabores || [], 'Seleccionar sabor...');
        fillSelect('newPresentacion', data.presentaciones || [], 'Seleccionar presentación...');
        fillSelect('editPresentacion', data.presentaciones || [], 'Seleccionar presentación...');
    }

    function toggleAlertaVencimiento(inputId, containerId, diasInputId) {
        const trigger = document.getElementById(inputId);
        const container = document.getElementById(containerId);
        const diasInput = document.getElementById(diasInputId);
        if (!trigger || !container || !diasInput) return;

        const applyVisibility = () => {
            const visible = trigger.checked;
            container.classList.toggle('d-none', !visible);
            diasInput.disabled = !visible;
            if (!visible) {
                diasInput.value = '';
            } else if (diasInput.value === '') {
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
        const stockContainer = document.getElementById(stockContainerId);
        const stockInput = document.getElementById(stockInputId);
        if (!controlaStock || !stockContainer || !stockInput) return;

        const applyVisibility = () => {
            const visible = controlaStock.checked;
            stockContainer.classList.toggle('d-none', !visible);
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
            const isProducto = value === 'producto';
            const isInsumo = value === 'insumo';
            const isServicio = value === 'servicio';

            marcaContainer?.classList.toggle('d-none', isServicio);
            saborContainer?.classList.toggle('d-none', !(isProducto));
            presentacionContainer?.classList.toggle('d-none', !(isProducto));

            if (saborSelect) {
                saborSelect.required = isProducto;
                if (!isProducto) saborSelect.value = '';
            }
            if (presentacionSelect) {
                presentacionSelect.required = isProducto;
                if (!isProducto) presentacionSelect.value = '';
            }

            if (isServicio) {
                controlaStock.checked = false;
                stockInput.value = '0.0000';
                permiteDecimales.checked = false;
                requiereLote.checked = false;
                requiereVencimiento.checked = false;
            }

            stockContainer?.classList.toggle('d-none', isServicio);
            permiteDecimalesContainer?.classList.toggle('d-none', isServicio);
            requiereLoteContainer?.classList.toggle('d-none', isServicio);
            requiereVencimientoContainer?.classList.toggle('d-none', isServicio);

            if (isInsumo || isServicio || value === '' || value === 'activo' || value === 'gasto') {
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
        });

        document.getElementById('formCrearItem')?.addEventListener('submit', () => {
            const tipo = document.getElementById('newTipo')?.value;
            if (tipo !== 'producto') {
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
        });

        document.getElementById('formEditarItem')?.addEventListener('submit', () => {
            const tipo = document.getElementById('editTipo')?.value;
            if (tipo !== 'producto') {
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
                    alert(error.message);
                }
            });
        };

        bindCreateForm('formAgregarSabor');
        bindCreateForm('formAgregarPresentacion');

        document.querySelectorAll('.js-editar-atributo').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('editarAtributoAccion').value = btn.dataset.target === 'sabor' ? 'editar_sabor' : 'editar_presentacion';
                document.getElementById('editarAtributoId').value = btn.dataset.id || '';
                document.getElementById('editarAtributoNombre').value = btn.dataset.nombre || '';
                document.getElementById('editarAtributoEstado').checked = (btn.dataset.estado || '1') === '1';
                document.getElementById('tituloEditarAtributo').textContent = btn.dataset.target === 'sabor' ? 'Editar sabor' : 'Editar presentación';
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
                alert(error.message);
            }
        });

        document.querySelectorAll('.js-eliminar-atributo').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm('¿Eliminar este registro?')) return;
                try {
                    await postAction({ accion: btn.dataset.accion, id: btn.dataset.id });
                    await refreshAtributosSelectores();
                    window.location.reload();
                } catch (error) {
                    alert(error.message);
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
                    alert(error.message);
                }
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

        bindSearch('buscarSabores', 'tablaSaboresGestion');
        bindSearch('buscarPresentaciones', 'tablaPresentacionesGestion');

        modalEl.addEventListener('hidden.bs.modal', async () => {
            try {
                await refreshAtributosSelectores();
            } catch (_) {
                // no-op
            }
        });
    }

    

        function initTableManager() {
        const searchInput = document.getElementById('itemSearch');
        const filtroTipo = document.getElementById('itemFiltroTipo');
        const filtroEstado = document.getElementById('itemFiltroEstado');
        const paginationControls = document.getElementById('itemsPaginationControls');
        const paginationInfo = document.getElementById('itemsPaginationInfo');

        const table = document.getElementById('itemsTable');
        if (!table) return;

        const allRows = Array.from(table.querySelectorAll('tbody tr'));

        const updateTable = function () {
            const texto = (searchInput?.value || '').toLowerCase().trim();
            const tipo = filtroTipo?.value || '';
            const estado = filtroEstado?.value || '';

            const visibleRows = allRows.filter((row) => {
                const rowSearch = row.getAttribute('data-search') || '';
                const rowTipo = row.getAttribute('data-tipo') || '';
                const rowEstado = row.getAttribute('data-estado') || '';
                return rowSearch.includes(texto) && (tipo === '' || rowTipo === tipo) && (estado === '' || rowEstado === estado);
            });

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE) || 1;
            if (currentPage > totalPages) currentPage = 1;

            allRows.forEach((row) => {
                row.style.display = 'none';
            });
            const start = (currentPage - 1) * ROWS_PER_PAGE;
            visibleRows.slice(start, start + ROWS_PER_PAGE).forEach((row) => {
                row.style.display = '';
            });

            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const end = Math.min(start + ROWS_PER_PAGE, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${end} de ${totalRows} ítems`;
                }
            }
            renderPagination(totalPages);
        };

        function renderPagination(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return;

            const addBtn = (label, page, active = false, disabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
                li.onclick = (e) => {
                    e.preventDefault();
                    if (!active && !disabled) {
                        currentPage = page;
                        updateTable();
                    }
                };
                paginationControls.appendChild(li);
            };

            addBtn('«', currentPage - 1, false, currentPage === 1);
            for (let i = 1; i <= totalPages; i += 1) addBtn(i, i, i === currentPage);
            addBtn('»', currentPage + 1, false, currentPage === totalPages);
        }

        [searchInput, filtroTipo, filtroEstado].forEach((el) => el?.addEventListener('input', () => {
            currentPage = 1;
            updateTable();
        }));
        updateTable();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCreateModal();
        initEditModal();
        initTableManager();
        initCategoriasModal();
        initGestionItemsModal();
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
