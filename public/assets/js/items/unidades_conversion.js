(function () {
    function initUnidadesConversionModal() {
        const { getItemsEndpoint, postAction, showError, confirmAction } = window.ItemsShared || {};
        if (!getItemsEndpoint || !postAction || !showError || !confirmAction) return;

        const modal = document.getElementById('modalUnidadesConversion');
        const tbodyResumen = document.querySelector('#tablaUnidadesConversion tbody');
        const tbodyDetalle = document.querySelector('#tablaDetalleUnidadesConversion tbody');
        const tituloSeleccion = document.getElementById('ucTituloSeleccion');
        const btnAgregar = document.getElementById('btnAgregarUnidadConversion');
        const form = document.getElementById('formUnidadConversion');
        const btnCancelar = document.getElementById('btnCancelarUnidadConversion');
        const alertPendientes = document.getElementById('ucPendientesAlert');
        const btnHeaderConversion = document.querySelector('[data-bs-target="#modalUnidadesConversion"]');

        const inputAccion = document.getElementById('ucAccion');
        const inputId = document.getElementById('ucId');
        const inputIdItem = document.getElementById('ucIdItem');
        const inputNombre = document.getElementById('ucNombre');
        const inputCodigo = document.getElementById('ucCodigoUnidad');
        const inputFactor = document.getElementById('ucFactorConversion');
        const inputPeso = document.getElementById('ucPesoKg');
        const inputEstado = document.getElementById('ucEstado');
        const resumenFormula = document.getElementById('ucResumenFormula');

        if (!modal || !tbodyResumen || !tbodyDetalle || !form) return;

        let itemsResumen = [];
        let itemActivo = null;

        const renderFormula = () => {
            const nombre = (inputNombre.value || 'Unidad').trim() || 'Unidad';
            const factor = Number(inputFactor.value || 0);
            const unidadBase = itemActivo?.unidad_base || 'UND';
            resumenFormula.textContent = `1 ${nombre} = ${factor.toFixed(4)} ${unidadBase}`;
        };

        const resetFormulario = () => {
            inputAccion.value = 'crear_item_unidad_conversion';
            inputId.value = '0';
            inputNombre.value = '';
            inputCodigo.value = '';
            inputFactor.value = '';
            inputPeso.value = '';
            inputEstado.checked = true;
            form.classList.add('d-none');
            renderFormula();
        };

        const abrirFormularioNuevo = () => {
            if (!itemActivo) return;
            inputAccion.value = 'crear_item_unidad_conversion';
            inputId.value = '0';
            inputIdItem.value = String(itemActivo.id || 0);
            inputNombre.value = '';
            inputCodigo.value = '';
            inputFactor.value = '';
            inputPeso.value = '0.000';
            inputEstado.checked = true;
            form.classList.remove('d-none');
            renderFormula();
            inputNombre.focus();
        };

        const abrirFormularioEdicion = (registro) => {
            inputAccion.value = 'editar_item_unidad_conversion';
            inputId.value = String(registro.id || 0);
            inputIdItem.value = String(itemActivo?.id || 0);
            inputNombre.value = registro.nombre || '';
            inputCodigo.value = registro.codigo_unidad || '';
            inputFactor.value = Number(registro.factor_conversion || 0).toFixed(4);
            inputPeso.value = Number(registro.peso_kg || 0).toFixed(3);
            inputEstado.checked = Number(registro.estado || 0) === 1;
            form.classList.remove('d-none');
            renderFormula();
            inputNombre.focus();
        };

        const renderDetalle = (items = []) => {
            if (!Array.isArray(items) || items.length === 0) {
                tbodyDetalle.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay unidades registradas para este ítem.</td></tr>';
                return;
            }

            tbodyDetalle.innerHTML = items.map((item) => {
                const activo = Number(item.estado || 0) === 1;
                const badge = activo
                    ? '<span class="badge bg-success-subtle text-success">Activo</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">Inactivo</span>';

                return `
                    <tr>
                        <td class="fw-semibold">${item.nombre || ''}</td>
                        <td>${item.codigo_unidad || ''}</td>
                        <td class="text-end">${Number(item.factor_conversion || 0).toFixed(4)}</td>
                        <td class="text-end">${Number(item.peso_kg || 0).toFixed(3)}</td>
                        <td class="text-center">${badge}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 js-uc-editar" data-id="${item.id}">Editar</button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-uc-eliminar" data-id="${item.id}">Eliminar</button>
                        </td>
                    </tr>
                `;
            }).join('');

            tbodyDetalle.querySelectorAll('.js-uc-editar').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = Number(btn.dataset.id || 0);
                    const registro = items.find((r) => Number(r.id) === id);
                    if (registro) abrirFormularioEdicion(registro);
                });
            });

            tbodyDetalle.querySelectorAll('.js-uc-eliminar').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const id = Number(btn.dataset.id || 0);
                    if (!itemActivo || id <= 0) return;
                    const confirm = await confirmAction({
                        title: '¿Eliminar unidad?',
                        text: 'Se realizará borrado lógico y quedará en historial.'
                    });
                    if (!confirm) return;

                    try {
                        await postAction({
                            accion: 'eliminar_item_unidad_conversion',
                            id: String(id),
                            id_item: String(itemActivo.id)
                        });
                        await cargarDetalle(itemActivo.id);
                        await cargarResumen();
                    } catch (error) {
                        showError(error.message);
                    }
                });
            });
        };

        const actualizarPendientesUi = (items = []) => {
            const pendientes = Array.isArray(items)
                ? items.filter((item) => Number(item.total_unidades || 0) <= 0).length
                : 0;

            if (alertPendientes) {
                alertPendientes.classList.toggle('d-none', pendientes === 0);
            }

            if (!btnHeaderConversion) return;

            let badge = document.getElementById('ucPendientesBadge');
            if (pendientes > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.id = 'ucPendientesBadge';
                    badge.className = 'badge rounded-pill bg-warning text-dark ms-2';
                    btnHeaderConversion.appendChild(badge);
                }
                badge.textContent = String(pendientes);
            } else if (badge) {
                badge.remove();
            }
        };

        const renderResumen = (items = []) => {
            if (!Array.isArray(items) || items.length === 0) {
                tbodyResumen.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay ítems con factor de conversión activo.</td></tr>';
                return;
            }

            tbodyResumen.innerHTML = items.map((item) => {
                const total = Number(item.total_unidades || 0);
                const estado = total <= 0
                    ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-exclamation-triangle-fill me-1"></i>Pendiente</span>'
                    : '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Configurado</span>';

                return `
                    <tr>
                        <td><div class="fw-semibold">${item.nombre || ''}</div><div class="small text-muted">${item.sku || ''}</div></td>
                        <td>${item.unidad_base || 'UND'}</td>
                        <td class="text-center">${total}</td>
                        <td class="text-center">${estado}</td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary js-uc-seleccionar" data-id="${item.id}">Gestionar</button></td>
                    </tr>
                `;
            }).join('');

            tbodyResumen.querySelectorAll('.js-uc-seleccionar').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = Number(btn.dataset.id || 0);
                    const item = items.find((r) => Number(r.id) === id);
                    if (!item) return;
                    itemActivo = item;
                    inputIdItem.value = String(item.id || 0);
                    tituloSeleccion.textContent = `Gestionando: ${item.nombre || ''} (${item.unidad_base || 'UND'})`;
                    btnAgregar.disabled = false;
                    resetFormulario();
                    cargarDetalle(item.id);
                });
            });
        };

        async function cargarResumen() {
            const response = await fetch(getItemsEndpoint({ accion: 'listar_unidades_conversion' }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('No se pudo cargar el resumen de conversiones.');
            const data = await response.json();
            if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar el resumen de conversiones.');
            itemsResumen = data.items || [];
            actualizarPendientesUi(itemsResumen);
            renderResumen(itemsResumen);
        }

        async function cargarDetalle(idItem) {
            const response = await fetch(getItemsEndpoint({ accion: 'listar_detalle_unidades_conversion', id_item: String(idItem) }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('No se pudo cargar el detalle de conversiones.');
            const data = await response.json();
            if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar el detalle de conversiones.');
            renderDetalle(data.items || []);
        }

        [inputNombre, inputFactor].forEach((el) => el.addEventListener('input', renderFormula));

        btnAgregar?.addEventListener('click', abrirFormularioNuevo);
        btnCancelar?.addEventListener('click', resetFormulario);

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            if (!itemActivo) {
                showError('Debe seleccionar un ítem antes de guardar.');
                return;
            }

            const factor = Number(inputFactor.value || 0);
            if (factor <= 0) {
                showError('El factor de conversión debe ser mayor a 0.');
                return;
            }

            try {
                await postAction({
                    accion: inputAccion.value,
                    id: inputId.value,
                    id_item: inputIdItem.value,
                    nombre: inputNombre.value,
                    codigo_unidad: inputCodigo.value,
                    factor_conversion: inputFactor.value,
                    peso_kg: inputPeso.value || '0',
                    estado: inputEstado.checked ? '1' : '0'
                });
                resetFormulario();
                await cargarDetalle(itemActivo.id);
                await cargarResumen();
            } catch (error) {
                showError(error.message);
            }
        });

        modal.addEventListener('show.bs.modal', async () => {
            try {
                itemActivo = null;
                btnAgregar.disabled = true;
                tituloSeleccion.textContent = 'Selecciona un ítem para gestionar sus conversiones';
                tbodyDetalle.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Selecciona un ítem para ver sus unidades de conversión.</td></tr>';
                resetFormulario();
                await cargarResumen();
            } catch (error) {
                showError(error.message);
            }
        });
    }

    window.ItemsUnidadesConversion = window.ItemsUnidadesConversion || {
        init: function () {
            initUnidadesConversionModal();
        }
    };
})();
