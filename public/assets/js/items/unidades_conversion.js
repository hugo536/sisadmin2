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
        const contenedorFormulario = document.getElementById('contenedorFormulario'); // <--- NUEVA REFERENCIA AÑADIDA
        const btnCancelar = document.getElementById('btnCancelarUnidadConversion');
        const btnGuardar = document.getElementById('btnGuardarUnidadConversion');
        const inputBuscarItem = document.getElementById('ucBuscarItem');
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

        if (!modal || !tbodyResumen || !tbodyDetalle || !form || !contenedorFormulario) return;
        if (modal.dataset.ucInit === '1') return;
        modal.dataset.ucInit = '1';

        let itemsResumen = [];
        let itemActivo = null;
        let terminoBusquedaResumen = '';
        let solicitudDetalleActual = 0;

        // --- FUNCIONES AUXILIARES ---
        const generarCodigoUnidadAuto = () => {
            if (!inputCodigo) return;
            const skuBase = String(itemActivo?.sku || 'UND').trim().toUpperCase() || 'UND';
            const nombreUnidad = String(inputNombre?.value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .replace(/-{2,}/g, '-')
                .slice(0, 24);

            inputCodigo.value = `${skuBase}-${nombreUnidad || 'UNIDAD'}`.slice(0, 40);
        };

        const showSuccess = async (message, title = 'Éxito') => {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                await window.Swal.fire({
                    icon: 'success',         
                    title,                   
                    text: message || 'Operación completada correctamente.', 
                    confirmButtonText: 'OK', 
                    confirmButtonColor: '#198754', 
                    timer: 2000,             
                    showConfirmButton: true  
                });
                return;
            }
            console.info(message || 'Operación completada correctamente.');
        };

        const renderFormula = () => {
            const nombre = (inputNombre.value || 'Unidad').trim() || 'Unidad';
            const factor = Number(inputFactor.value || 0);
            const unidadBase = itemActivo?.unidad_base || 'UND';
            if (resumenFormula) {
                resumenFormula.textContent = `1 ${nombre} = ${factor.toFixed(4)} ${unidadBase}`;
            }
        };

        const actualizarBotonGuardar = (esEdicion = false) => {
            if (!btnGuardar) return;
            btnGuardar.innerHTML = esEdicion
                ? '<i class="bi bi-arrow-repeat me-2"></i>Actualizar Unidad'
                : '<i class="bi bi-save me-2"></i>Guardar Unidad';
        };

        const resetFormulario = () => {
            if (inputAccion) inputAccion.value = 'crear_item_unidad_conversion';
            if (inputId) inputId.value = '0';
            if (inputNombre) inputNombre.value = '';
            if (inputCodigo) inputCodigo.value = '';
            if (inputFactor) inputFactor.value = '';
            if (inputPeso) inputPeso.value = '';
            if (inputEstado) inputEstado.checked = true;
            contenedorFormulario.classList.add('d-none'); // <--- CORRECCIÓN APLICADA AQUÍ
            actualizarBotonGuardar(false);
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
            generarCodigoUnidadAuto();
            contenedorFormulario.classList.remove('d-none'); // <--- CORRECCIÓN APLICADA AQUÍ
            actualizarBotonGuardar(false);
            renderFormula();
            inputNombre?.focus();
        };

        const abrirFormularioEdicion = (registro) => {
            if (inputAccion) inputAccion.value = 'editar_item_unidad_conversion';
            if (inputId) inputId.value = String(registro.id || 0);
            if (inputIdItem) inputIdItem.value = String(itemActivo?.id || 0);
            if (inputNombre) inputNombre.value = registro.nombre || '';
            generarCodigoUnidadAuto();
            if (inputFactor) inputFactor.value = Number(registro.factor_conversion || 0).toFixed(4);
            if (inputPeso) inputPeso.value = Number(registro.peso_kg || 0).toFixed(3);
            if (inputEstado) inputEstado.checked = Number(registro.estado || 0) === 1;
            
            contenedorFormulario.classList.remove('d-none'); // <--- CORRECCIÓN APLICADA AQUÍ
            actualizarBotonGuardar(true);
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

        const normalizarTexto = (valor) => String(valor || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();

        const getItemsResumenFiltrados = () => {
            const termino = normalizarTexto(terminoBusquedaResumen);
            if (!termino) return itemsResumen;

            return itemsResumen.filter((item) => {
                const texto = normalizarTexto(`${item.nombre || ''} ${item.sku || ''}`);
                return texto.includes(termino);
            });
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

                // Usando el catálogo global de iconos para editar
                const btnEditar = IconosAccion.crear('editar', item.id, 'js-uc-editar');

                // Lógica del basurero gris deshabilitado si tiene historial
                const puedeEliminar = item.hasOwnProperty('puede_eliminar') ? Number(item.puede_eliminar) === 1 : true;
                let btnEliminar = '';

                if (puedeEliminar) {
                    // Basurero normal activo
                    btnEliminar = IconosAccion.crear('eliminar', item.id, 'js-uc-eliminar');
                } else {
                    // Basurero gris deshabilitado con mensaje de advertencia
                    const motivo = item.motivo_no_eliminar || 'Tiene historial de movimientos. Puedes desactivarlo usando el interruptor.';
                    btnEliminar = `
                        <button type="button" class="btn-icon text-muted opacity-50 border-0 bg-transparent p-1" 
                                style="cursor: not-allowed;" title="${motivo}" disabled>
                            <i class="bi bi-trash3"></i>
                        </button>
                    `;
                }

                const accionesHTML = IconosAccion.agrupar(btnEditar, btnEliminar);

                const isPredeterminada = Number(item.es_predeterminada || 0) === 1;
                const puedeFijarPredeterminada = Number(item.puede_fijar_predeterminada || 0) === 1;
                const starClass = isPredeterminada ? 'bi-star-fill text-warning' : 'bi-star text-secondary opacity-50';
                const starTitle = puedeFijarPredeterminada
                    ? 'Usar como predeterminada en Inventario'
                    : 'La unidad predeterminada estará disponible después de actualizar la base de datos';
                const starBtn = `
                    <button type="button" class="btn btn-sm btn-light border-0 js-uc-predeterminada p-1 rounded-circle shadow-none" 
                            data-id="${item.id}" data-item="${item.id_item}" 
                            title="${starTitle}" ${puedeFijarPredeterminada ? '' : 'disabled'}>
                        <i class="bi ${starClass} fs-5"></i>
                    </button>
                `;

                return `
                    <tr class="border-bottom">
                        <td class="fw-semibold text-dark">
                            <div class="text-wrap text-break" title="${item.nombre || ''}">${item.nombre || ''}</div>
                        </td>
                        <td class="text-end fw-medium text-primary">${Number(item.factor_conversion || 0).toFixed(4)}</td>
                        <td class="text-end small d-none d-sm-table-cell">${Number(item.peso_kg || 0).toFixed(3)}</td>
                        
                        <td class="text-center bg-light-subtle">${starBtn}</td>
                        
                        <td class="text-center d-none d-md-table-cell">${badge}</td>
                        <td class="text-end pe-3 text-nowrap">${accionesHTML}</td>
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
                        text: 'Se realizará un borrado lógico y quedará en el historial.'
                    });
                    if (!confirm) return;

                    try {
                        const result = await postAction({
                            accion: 'eliminar_item_unidad_conversion',
                            id: String(id),
                            id_item: String(itemActivo.id)
                        });
                        await showSuccess(result?.mensaje || 'Unidad de conversión eliminada correctamente.', 'Eliminado');
                        await cargarDetalle(itemActivo.id);
                        await cargarResumen();
                    } catch (error) {
                        showError(error.message);
                    }
                });
            });

            tbodyDetalle.querySelectorAll('.js-uc-predeterminada').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const idUnidad = Number(btn.dataset.id || 0);
                    const idItem = Number(btn.dataset.item || 0);
                    if (idUnidad <= 0 || idItem <= 0) return;

                    const icon = btn.querySelector('i');

                    // --- NUEVAS REGLAS DE VALIDACIÓN FRONTEND ---
                    // Regla 1: Si ya tiene la estrella llena (es la predeterminada), detenemos el clic
                    if (icon.classList.contains('bi-star-fill')) {
                        return;
                    }

                    // Regla 2: Si el ítem solo tiene 1 unidad en total en la tabla, no dejamos cambiarla
                    if (items.length <= 1) {
                        window.Swal?.fire({
                            icon: 'info',
                            title: 'Acción no permitida',
                            text: 'El ítem debe tener al menos una unidad predeterminada.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        return;
                    }
                    // ---------------------------------------------

                    const originalClass = icon.className;
                    icon.className = 'bi bi-hourglass-split text-info fs-5 spinner-border spinner-border-sm border-0';

                    try {
                        await postAction({
                            accion: 'fijar_predeterminada_unidad',
                            id: String(idUnidad),
                            id_item: String(idItem)
                        });
                        
                        await cargarDetalle(idItem);
                    } catch (error) {
                        icon.className = originalClass;
                        showError(error.message);
                    }
                });
            });
        };

        const renderDetalleCargando = () => {
            tbodyDetalle.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cargando unidades de conversión...</td></tr>';
        };

        const renderDetalleError = () => {
            tbodyDetalle.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">No se pudieron cargar las unidades de conversión del ítem seleccionado.</td></tr>';
        };

        const renderResumen = (items = []) => {
            if (!Array.isArray(items) || items.length === 0) {
                const mensaje = terminoBusquedaResumen
                    ? 'No se encontraron ítems para la búsqueda actual.'
                    : 'No hay ítems con factor de conversión activo.';
                tbodyResumen.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">${mensaje}</td></tr>`;
                return;
            }

            tbodyResumen.innerHTML = items.map((item) => {
                const total = Number(item.total_unidades || 0);
                const estado = total <= 0
                    ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i> Pendiente</span>'
                    : '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> OK</span>';

                const esActivo = itemActivo && itemActivo.id === item.id ? 'table-active' : '';

                const btnGestionar = IconosAccion.crear('gestionar', item.id, 'js-uc-seleccionar');
                const accionesHTML = IconosAccion.agrupar(btnGestionar);

                return `
                    <tr class="${esActivo}">
                        <td class="ps-3">
                            <div class="fw-semibold text-dark text-wrap text-break" title="${item.nombre || ''}">${item.nombre || ''}</div>
                            <div class="small text-muted text-wrap text-break" style="font-size: 0.75rem;">${item.sku || ''}</div>
                        </td>
                        <td class="text-center align-middle fw-medium text-secondary d-none d-xl-table-cell">${item.unidad_base || 'UND'}</td>
                        <td class="text-center align-middle fw-bold text-dark">${total}</td>
                        <td class="text-center align-middle d-none d-md-table-cell">${estado}</td>
                        <td class="text-end pe-3 align-middle text-nowrap">${accionesHTML}</td>
                    </tr>
                `;
            }).join('');

            tbodyResumen.querySelectorAll('.js-uc-seleccionar').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const id = Number(btn.dataset.id || 0);
                    const item = items.find((r) => Number(r.id) === id);
                    if (!item) return;
                    
                    itemActivo = item;
                    if (inputIdItem) inputIdItem.value = String(item.id || 0);
                    if (tituloSeleccion) tituloSeleccion.textContent = `Gestionando: ${item.nombre || ''} (${item.unidad_base || 'UND'})`;
                    if (btnAgregar) btnAgregar.disabled = false;
                    
                    resetFormulario();
                    renderResumenFiltrado(); 

                    try {
                        await cargarDetalle(item.id);
                    } catch (error) {
                        if (Number(itemActivo?.id || 0) === Number(item.id || 0)) {
                            renderDetalleError();
                            showError(error.message);
                        }
                    }
                });
            });
        };

        const renderResumenFiltrado = () => {
            renderResumen(getItemsResumenFiltrados());
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
            renderResumenFiltrado();
        };

        const cargarDetalle = async (idItem) => {
            const id = Number(idItem || 0);
            if (id <= 0) {
                throw new Error('El ítem seleccionado no es válido.');
            }

            const solicitudActual = ++solicitudDetalleActual;
            renderDetalleCargando();

            const response = await fetch(getItemsEndpoint({ accion: 'listar_detalle_unidades_conversion', id_item: String(idItem) }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('No se pudo cargar el detalle de conversiones.');
            
            const data = await response.json();
            if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar el detalle de conversiones.');

            // Si el usuario eligió otro ítem antes de que termine la consulta,
            // no reemplazamos el detalle del ítem que está gestionando ahora.
            if (solicitudActual !== solicitudDetalleActual || Number(itemActivo?.id || 0) !== id) return;

            renderDetalle(data.items || []);
        };

        // --- EVENT LISTENERS PRINCIPALES ---

        [inputNombre, inputFactor].forEach((el) => {
            el?.addEventListener('input', renderFormula);
        });
        inputNombre?.addEventListener('input', generarCodigoUnidadAuto);

        btnAgregar?.addEventListener('click', abrirFormularioNuevo);
        btnCancelar?.addEventListener('click', resetFormulario);
        inputBuscarItem?.addEventListener('input', () => {
            terminoBusquedaResumen = inputBuscarItem.value || '';
            renderResumenFiltrado();
        });

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
                const esEdicion = inputAccion.value === 'editar_item_unidad_conversion';
                const confirm = await confirmAction({
                    title: esEdicion ? '¿Guardar cambios?' : '¿Crear unidad de conversión?',
                    text: esEdicion
                        ? 'Se actualizarán los datos de la unidad seleccionada.'
                        : 'Se registrará una nueva unidad de conversión para este ítem.'
                });
                if (!confirm) return;

                const result = await postAction({
                    accion: inputAccion.value,
                    id: inputId.value,
                    id_item: inputIdItem.value,
                    nombre: inputNombre.value,
                    codigo_unidad: inputCodigo.value,
                    factor_conversion: inputFactor.value,
                    peso_kg: inputPeso.value || '0',
                    estado: inputEstado.checked ? '1' : '0'
                });

                await showSuccess(
                    result?.mensaje || (esEdicion
                        ? 'Unidad de conversión actualizada correctamente.'
                        : 'Unidad de conversión creada correctamente.'),
                    esEdicion ? 'Actualizado' : 'Guardado'
                );
                
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
                terminoBusquedaResumen = '';
                if (inputBuscarItem) inputBuscarItem.value = '';
                if (btnAgregar) btnAgregar.disabled = true;
                if (tituloSeleccion) tituloSeleccion.textContent = 'Selecciona un ítem para gestionar sus conversiones';
                solicitudDetalleActual += 1;
                
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
