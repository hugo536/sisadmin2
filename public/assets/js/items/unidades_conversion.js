(function () {
    "use strict";

    const initUnidadesConversionModal = () => {
        const { getItemsEndpoint, postAction, showError, confirmAction } = window.ItemsShared || {};
        // Se asume que la librería IconosAccion está disponible globalmente
        const { IconosAccion } = window; 

        if (!getItemsEndpoint || !postAction || !showError || !confirmAction || !IconosAccion) {
            console.warn('Faltan dependencias compartidas o la librería IconosAccion para inicializar el modal de conversiones.');
            return;
        }

        // Referencias al DOM
        const modal = document.getElementById('modalUnidadesConversion');
        const tbodyResumen = document.querySelector('#tablaUnidadesConversion tbody');
        const tbodyDetalle = document.querySelector('#tablaDetalleUnidadesConversion tbody');
        const tituloSeleccion = document.getElementById('ucTituloSeleccion');
        const btnAgregar = document.getElementById('btnAgregarUnidadConversion');
        const form = document.getElementById('formUnidadConversion');
        const btnCancelar = document.getElementById('btnCancelarUnidadConversion');
        const alertPendientes = document.getElementById('ucPendientesAlert');
        const btnHeaderConversion = document.querySelector('[data-bs-target="#modalUnidadesConversion"]');

        // Inputs del formulario
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
        if (modal.dataset.ucInit === '1') return;
        modal.dataset.ucInit = '1';

        let itemsResumen = [];
        let itemActivo = null;

        // --- FUNCIONES AUXILIARES ---

        const renderFormula = () => {
            const nombre = (inputNombre.value || 'Unidad').trim() || 'Unidad';
            const factor = Number(inputFactor.value || 0);
            const unidadBase = itemActivo?.unidad_base || 'UND';
            if (resumenFormula) {
                resumenFormula.textContent = `1 ${nombre} = ${factor.toFixed(4)} ${unidadBase}`;
            }
        };

        const resetFormulario = () => {
            if (inputAccion) inputAccion.value = 'crear_item_unidad_conversion';
            if (inputId) inputId.value = '0';
            if (inputNombre) inputNombre.value = '';
            if (inputCodigo) inputCodigo.value = '';
            if (inputFactor) inputFactor.value = '';
            if (inputPeso) inputPeso.value = '';
            if (inputEstado) inputEstado.checked = true;
            form.classList.add('d-none');
            renderFormula();
        };

        const abrirFormularioNuevo = () => {
            if (!itemActivo) return;
            if (inputAccion) inputAccion.value = 'crear_item_unidad_conversion';
            if (inputId) inputId.value = '0';
            if (inputIdItem) inputIdItem.value = String(itemActivo.id || 0);
            if (inputNombre) inputNombre.value = '';
            if (inputCodigo) inputCodigo.value = '';
            if (inputFactor) inputFactor.value = '';
            if (inputPeso) inputPeso.value = '0.000';
            if (inputEstado) inputEstado.checked = true;
            
            form.classList.remove('d-none');
            renderFormula();
            inputNombre?.focus();
        };

        const abrirFormularioEdicion = (registro) => {
            if (inputAccion) inputAccion.value = 'editar_item_unidad_conversion';
            if (inputId) inputId.value = String(registro.id || 0);
            if (inputIdItem) inputIdItem.value = String(itemActivo?.id || 0);
            if (inputNombre) inputNombre.value = registro.nombre || '';
            if (inputCodigo) inputCodigo.value = registro.codigo_unidad || '';
            if (inputFactor) inputFactor.value = Number(registro.factor_conversion || 0).toFixed(4);
            if (inputPeso) inputPeso.value = Number(registro.peso_kg || 0).toFixed(3);
            if (inputEstado) inputEstado.checked = Number(registro.estado || 0) === 1;
            
            form.classList.remove('d-none');
            renderFormula();
            inputNombre?.focus();
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

        // --- RENDERIZADO DE TABLAS ---

        const renderDetalle = (items = []) => {
            if (!Array.isArray(items) || items.length === 0) {
                tbodyDetalle.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay unidades registradas para este ítem.</td></tr>';
                return;
            }

            tbodyDetalle.innerHTML = items.map((item) => {
                const activo = Number(item.estado || 0) === 1;
                const badge = activo
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Activo</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Inactivo</span>';

                // Usando el catálogo global de iconos
                const btnEditar = IconosAccion.crear('editar', item.id, 'js-uc-editar');
                const btnEliminar = IconosAccion.crear('eliminar', item.id, 'js-uc-eliminar');
                const accionesHTML = IconosAccion.agrupar(btnEditar, btnEliminar);

                return `
                    <tr>
                        <td class="fw-semibold text-dark" style="max-width: 150px;">
                            <div class="text-truncate" title="${item.nombre || ''}">${item.nombre || ''}</div>
                        </td>
                        <td class="small text-muted">${item.codigo_unidad || '-'}</td>
                        <td class="text-end fw-medium text-primary">${Number(item.factor_conversion || 0).toFixed(4)}</td>
                        <td class="text-end small">${Number(item.peso_kg || 0).toFixed(3)}</td>
                        <td class="text-center">${badge}</td>
                        <td class="text-end pe-3">${accionesHTML}</td>
                    </tr>
                `;
            }).join('');

            // Bind Eventos de Edición
            tbodyDetalle.querySelectorAll('.js-uc-editar').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = Number(btn.dataset.id || 0);
                    const registro = items.find((r) => Number(r.id) === id);
                    if (registro) abrirFormularioEdicion(registro);
                });
            });

            // Bind Eventos de Eliminación
            tbodyDetalle.querySelectorAll('.js-uc-eliminar').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const id = Number(btn.dataset.id || 0);
                    if (!itemActivo || id <= 0) return;
                    
                    const confirm = await confirmAction({
                        title: '¿Eliminar unidad?',
                        text: 'Se realizará un borrado lógico y quedará en el historial.'
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

        const renderResumen = (items = []) => {
            if (!Array.isArray(items) || items.length === 0) {
                tbodyResumen.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay ítems con factor de conversión activo.</td></tr>';
                return;
            }

            tbodyResumen.innerHTML = items.map((item) => {
                const total = Number(item.total_unidades || 0);
                const estado = total <= 0
                    ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i> Pendiente</span>'
                    : '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> OK</span>';

                const esActivo = itemActivo && itemActivo.id === item.id ? 'table-active' : '';

                // Usando el catálogo global de iconos
                const btnGestionar = IconosAccion.crear('gestionar', item.id, 'js-uc-seleccionar');
                const accionesHTML = IconosAccion.agrupar(btnGestionar);

                return `
                    <tr class="${esActivo}">
                        <td class="ps-3 w-50" style="max-width: 180px;">
                            <div class="fw-semibold text-dark text-truncate" title="${item.nombre || ''}">${item.nombre || ''}</div>
                            <div class="small text-muted text-truncate" style="font-size: 0.75rem;">${item.sku || ''}</div>
                        </td>
                        <td class="text-center align-middle fw-medium text-secondary">${item.unidad_base || 'UND'}</td>
                        <td class="text-center align-middle fw-bold text-dark">${total}</td>
                        <td class="text-center align-middle">${estado}</td>
                        <td class="text-end pe-3 align-middle">${accionesHTML}</td>
                    </tr>
                `;
            }).join('');

            // Bind Evento de Selección
            tbodyResumen.querySelectorAll('.js-uc-seleccionar').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = Number(btn.dataset.id || 0);
                    const item = items.find((r) => Number(r.id) === id);
                    if (!item) return;
                    
                    itemActivo = item;
                    if (inputIdItem) inputIdItem.value = String(item.id || 0);
                    if (tituloSeleccion) tituloSeleccion.textContent = `Gestionando: ${item.nombre || ''} (${item.unidad_base || 'UND'})`;
                    if (btnAgregar) btnAgregar.disabled = false;
                    
                    resetFormulario();
                    cargarDetalle(item.id);
                    renderResumen(itemsResumen); // Re-render para aplicar la clase 'table-active'
                });
            });
        };

        // --- FETCH DATA ---

        const cargarResumen = async () => {
            const response = await fetch(getItemsEndpoint({ accion: 'listar_unidades_conversion' }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('No se pudo cargar el resumen de conversiones.');
            
            const data = await response.json();
            if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar el resumen de conversiones.');
            
            itemsResumen = data.items || [];
            actualizarPendientesUi(itemsResumen);
            renderResumen(itemsResumen);
        };

        const cargarDetalle = async (idItem) => {
            const response = await fetch(getItemsEndpoint({ accion: 'listar_detalle_unidades_conversion', id_item: String(idItem) }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('No se pudo cargar el detalle de conversiones.');
            
            const data = await response.json();
            if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar el detalle de conversiones.');
            
            renderDetalle(data.items || []);
        };

        // --- EVENT LISTENERS PRINCIPALES ---

        [inputNombre, inputFactor].forEach((el) => {
            el?.addEventListener('input', renderFormula);
        });

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
                if (btnAgregar) btnAgregar.disabled = true;
                if (tituloSeleccion) tituloSeleccion.textContent = 'Selecciona un ítem para gestionar sus conversiones';
                
                tbodyDetalle.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Selecciona un ítem para ver sus unidades de conversión.</td></tr>';
                
                resetFormulario();
                await cargarResumen();
            } catch (error) {
                showError(error.message);
            }
        });
    };

    window.ItemsUnidadesConversion = window.ItemsUnidadesConversion || {
        init: () => {
            initUnidadesConversionModal();
        }
    };
})();
