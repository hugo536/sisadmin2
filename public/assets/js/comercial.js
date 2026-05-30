/**
 * GESTIÓN COMERCIAL
 * - Acuerdos comerciales (matriz de tarifas)
 * - Tarifa General por Volumen
 */

// Función global para alertas de éxito centrales
const showSuccessAlert = (title, text = '') => {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: text,
        timer: 2500,
        timerProgressBar: true,
        showConfirmButton: true,
        confirmButtonText: 'Aceptar'
    });
};

// Helper clave: Evita que los eventos se dupliquen al recargar el DOM en arquitecturas SPA
const bindEvent = (element, eventType, handler, useCapture = false) => {
    if (element && !element.dataset[`bound${eventType}`]) {
        element.addEventListener(eventType, handler, useCapture);
        element.dataset[`bound${eventType}`] = 'true';
    }
};

function initComercialApp() {
    const appAcuerdos = document.getElementById('acuerdosComercialesApp');
    if (!appAcuerdos) return;

    const urls = {
        clientesDisponibles: appAcuerdos.dataset.urlClientesDisponibles,
        crearAcuerdo: appAcuerdos.dataset.urlCrearAcuerdo,
        obtenerMatriz: appAcuerdos.dataset.urlObtenerMatriz,
        presentacionesDisponibles: appAcuerdos.dataset.urlPresentacionesDisponibles,
        agregarProducto: appAcuerdos.dataset.urlAgregarProducto,
        actualizarPrecio: appAcuerdos.dataset.urlActualizarPrecio,
        togglePrecio: appAcuerdos.dataset.urlTogglePrecio,
        eliminarProducto: appAcuerdos.dataset.urlEliminarProducto,
        suspenderAcuerdo: appAcuerdos.dataset.urlSuspenderAcuerdo,
        activarAcuerdo: appAcuerdos.dataset.urlActivarAcuerdo,
        eliminarAcuerdo: appAcuerdos.dataset.urlEliminarAcuerdo,
        itemsVolumen: appAcuerdos.dataset.urlItemsVolumen,
        agregarVolumen: appAcuerdos.dataset.urlAgregarVolumen,
        actualizarVolumen: appAcuerdos.dataset.urlActualizarVolumen,
        eliminarVolumen: appAcuerdos.dataset.urlEliminarVolumen,
    };

    const sidebarList = document.getElementById('acuerdosSidebarList');
    const filtroClientes = document.getElementById('filtroClientesAcuerdo');
    const filtroMatriz = document.getElementById('filtroMatrizAcuerdo');
    const tabla = document.getElementById('tablaMatrizAcuerdo');
    const tbody = document.getElementById('matrizBodyRows');
    const tituloCliente = document.getElementById('acuerdoTituloCliente');
    const resumenTarifas = document.getElementById('acuerdoResumenTarifas');

    const modalVincularEl = document.getElementById('modalVincularCliente');
    const formVincular = document.getElementById('formVincularCliente');
    const selectCliente = document.getElementById('selectClienteVincular');
    const inputObs = document.getElementById('inputObservacionesAcuerdo');

    const modalAgregarEl = document.getElementById('modalAgregarProducto');
    const formAgregar = document.getElementById('formAgregarProductoAcuerdo');
    const selectPresentacion = document.getElementById('selectPresentacionAcuerdo');
    const inputPrecioInicial = document.getElementById('inputPrecioInicialAcuerdo');

    const modalVolumenEl = document.getElementById('modalAgregarEscalaVolumen');
    const formVolumen = document.getElementById('formAgregarEscalaVolumen');
    const selectItemVolumen = document.getElementById('selectItemVolumen');
    const inputCantidadMinimaVolumen = document.getElementById('inputCantidadMinimaVolumen');
    const inputPrecioUnitarioVolumen = document.getElementById('inputPrecioUnitarioVolumen');

    const btnAgregarProducto = document.getElementById('btnAgregarProducto');
    const btnSuspender = document.getElementById('btnSuspenderAcuerdo');
    const btnActivar = document.getElementById('btnActivarAcuerdo');
    const btnEliminar = document.getElementById('btnEliminarAcuerdo');
    const btnImprimirCliente = document.getElementById('btnImprimirAcuerdoCliente');

    // Usamos window. bootstrap instances para no perderlas entre recargas parciales
    const modalVincular = modalVincularEl ? bootstrap.Modal.getOrCreateInstance(modalVincularEl) : null;
    const modalAgregar = modalAgregarEl ? bootstrap.Modal.getOrCreateInstance(modalAgregarEl) : null;
    const modalVolumen = modalVolumenEl ? bootstrap.Modal.getOrCreateInstance(modalVolumenEl) : null;

    let tsCliente = null;
    let tsPresentacion = null;
    let tsItemVolumen = null;

    const softReloadSPA = (newUrlString = null) => {
        const urlTarget = newUrlString ? new URL(newUrlString, window.location.href) : window.location.href;
        window.location.href = urlTarget.toString();
    };

    const postForm = async (url, payload) => {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => fd.append(k, String(v)));
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const json = await res.json().catch(() => ({ success: false, message: 'Respuesta inválida' }));
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'No se pudo completar la operación.');
        }
        return json;
    };

    const getAcuerdoId = () => (tabla ? parseInt(tabla.dataset.idAcuerdo || '0', 10) || 0 : 0);
    const getModo = () => (tabla ? (tabla.dataset.modo || 'acuerdo') : 'acuerdo');

    const renderEmptyRow = (modo) => {
        if (!tbody) return;
        const msg = modo === 'volumen'
            ? 'Aún no hay escalas por volumen configuradas.'
            : 'Este acuerdo aún no tiene productos tarifados.';
        tbody.innerHTML = `
            <tr id="emptyMatrizRow">
                <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-exclamation-circle text-warning fs-1 d-block mb-2"></i>
                    ${msg}
                </td>
            </tr>
        `;
    };

    const rowTemplate = (item, modo) => {
        if (modo === 'volumen') {
            return `
                <tr data-id-detalle="${item.id}" class="mobile-expandable-row">
                    <td class="ps-4 col-mobile-hide"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                    <td class="fw-semibold text-dark">${item.producto_nombre || ''}</td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width: 120px;">
                            <span class="input-group-text bg-light border-end-0">≥</span>
                            <input type="number" min="0.01" step="0.01" class="form-control border-start-0 px-1 js-cantidad-minima" value="${parseFloat(item.cantidad_minima).toFixed(2)}" data-original="${parseFloat(item.cantidad_minima).toFixed(2)}">
                        </div>
                    </td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width: 130px;">
                            <span class="input-group-text bg-light border-end-0">S/</span>
                            <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-volumen" value="${parseFloat(item.precio_pactado || item.precio_unitario).toFixed(4)}" data-original="${parseFloat(item.precio_pactado || item.precio_unitario).toFixed(4)}">
                        </div>
                    </td>
                    <td class="text-end pe-4 col-mobile-hide">
                        <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-volumen" type="button" title="Eliminar escala">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }

        return `
            <tr data-id-detalle="${item.id}" class="mobile-expandable-row">
                <td class="ps-4 col-mobile-hide"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                <td class="fw-semibold text-dark"><span>${item.producto_nombre}</span></td>
                <td>
                    <div class="input-group input-group-sm" style="max-width: 130px;">
                        <span class="input-group-text bg-light border-end-0">S/</span>
                        <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-pactado" value="${parseFloat(item.precio_pactado).toFixed(4)}" data-original="${parseFloat(item.precio_pactado).toFixed(4)}">
                    </div>
                </td>
                <td class="text-center col-mobile-hide">
                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                        <input class="form-check-input js-estado-precio" type="checkbox" ${parseInt(item.estado, 10) === 1 ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-end pe-4 col-mobile-hide">
                    <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-producto" type="button" title="Eliminar producto">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    };

    const aplicarFiltroMatriz = () => {
        if (!tbody || !filtroMatriz) return;
        const terminosBusqueda = normalizarTexto(filtroMatriz.value).split(' ').filter(t => t.length > 0);
        const filas = Array.from(tbody.querySelectorAll('tr[data-id-detalle]'));
        let visibles = 0;

        filas.forEach((fila) => {
            const textoFila = normalizarTexto(fila.textContent || '');
            const coincide = terminosBusqueda.every(termino => textoFila.includes(termino));
            const mostrar = terminosBusqueda.length === 0 || coincide;
            fila.classList.toggle('d-none', !mostrar);
            if (mostrar) visibles += 1;
        });

        const filaVacia = document.getElementById('emptyMatrizRow');
        if (filaVacia) {
            const sinDatosOriginales = filas.length === 0;
            filaVacia.classList.toggle('d-none', !sinDatosOriginales && visibles > 0);
            if (!sinDatosOriginales) {
                filaVacia.querySelector('td').innerHTML = `
                    <i class="bi bi-search text-muted fs-1 d-block mb-2"></i>
                    No hay coincidencias para la búsqueda.
                `;
            }
        } else if (filas.length > 0 && visibles === 0) {
            const nuevaFilaVacia = document.createElement('tr');
            nuevaFilaVacia.id = 'emptyMatrizRow';
            nuevaFilaVacia.innerHTML = `
                <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-search text-muted fs-1 d-block mb-2"></i>
                    No hay coincidencias para la búsqueda.
                </td>
            `;
            tbody.appendChild(nuevaFilaVacia);
        }
    };

    const cargarMatriz = async (idAcuerdo) => {
        if(tbody) tbody.style.opacity = '0.5';

        const url = `${urls.obtenerMatriz}&id_acuerdo=${idAcuerdo}`;
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        
        if(tbody) tbody.style.opacity = '1';
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudo cargar la matriz.');

        const modo = json.modo || 'acuerdo';
        
        const thead = tabla.querySelector('thead tr');
        if (thead) {
            if (modo === 'volumen') {
                thead.innerHTML = `
                    <th class="ps-4" style="width: 120px;">Código</th>
                    <th>Producto</th>
                    <th style="width: 180px;">Cantidad Mínima</th>
                    <th style="width: 220px;">Precio Unitario</th>
                    <th class="text-end pe-4" style="width: 90px;">Acciones</th>
                `;
            } else {
                thead.innerHTML = `
                    <th class="ps-4" style="width: 120px;">Código</th>
                    <th>Producto</th>
                    <th style="width: 220px;">Precio Pactado</th>
                    <th class="text-center" style="width: 130px;">Estado</th>
                    <th class="text-end pe-4" style="width: 90px;">Acciones</th>
                `;
            }
        }

        if (tabla) {
            tabla.dataset.idAcuerdo = String(idAcuerdo);
            tabla.dataset.modo = modo;
        }

        if (tituloCliente) tituloCliente.textContent = json.acuerdo.cliente_nombre;

        if (btnImprimirCliente) {
            btnImprimirCliente.href = `?ruta=comercial/imprimirAcuerdoPdf&tipo=clientes&id=${encodeURIComponent(String(idAcuerdo))}`;
        }
        
        let countProductos = 0;

        if (resumenTarifas) {
            if (modo === 'volumen') {
                countProductos = new Set(json.matriz.map(item => item.id_item)).size;
                resumenTarifas.textContent = `${countProductos} productos configurados`;
            } else {
                countProductos = json.matriz.length;
                resumenTarifas.textContent = `${countProductos} tarifas configuradas`;
            }
        }

        if (btnAgregarProducto) btnAgregarProducto.innerHTML = `<i class="bi bi-plus-lg me-1"></i>${modo === 'volumen' ? 'Agregar Escala' : 'Agregar Producto'}`;

        const dropdownOpciones = document.querySelector('.dropdown .btn-outline-secondary');
        if (dropdownOpciones) dropdownOpciones.closest('.dropdown').style.display = modo === 'volumen' ? 'none' : 'block';

        if (tbody) {
            if (!Array.isArray(json.matriz) || json.matriz.length === 0) {
                renderEmptyRow(modo);
            } else {
                tbody.innerHTML = json.matriz.map(r => rowTemplate(r, modo)).join('');
            }
        }
        aplicarFiltroMatriz();

        const sidebarItem = document.querySelector(`.acuerdo-sidebar-item[data-id-acuerdo="${idAcuerdo}"]`);
        if (sidebarItem) {
            const counterText = sidebarItem.querySelector('small.text-muted');
            if (counterText) {
                counterText.textContent = `${countProductos} productos`;
            }
            
            const dotEl = sidebarItem.querySelector('.rounded-circle');
            if (dotEl && json.acuerdo) {
                const isActive = parseInt(json.acuerdo.estado, 10) === 1;
                dotEl.style.background = isActive ? '#22c55e' : '#9ca3af';
            }
        }
    };

    const normalizarTexto = (texto) => {
        return (texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    };

    bindEvent(filtroClientes, 'input', () => {
        const terminosBusqueda = normalizarTexto(filtroClientes.value).split(' ').filter(t => t.length > 0);
        const items = sidebarList.querySelectorAll('.acuerdo-sidebar-item');
        let visibles = 0;

        items.forEach(item => {
            const textoItem = normalizarTexto(item.dataset.search || '');
            const coincide = terminosBusqueda.every(termino => textoItem.includes(termino));
            const mostrar = terminosBusqueda.length === 0 || coincide;

            item.removeAttribute('style'); 

            if (mostrar) {
                item.classList.remove('d-none');
                item.classList.add('d-flex');
                visibles += 1;
            } else {
                item.classList.remove('d-flex');
                item.classList.add('d-none');
            }
        });

        const empty = document.getElementById('sidebarNoResults');
        if (empty) {
            if (visibles === 0) {
                empty.classList.remove('d-none');
                empty.classList.add('d-block');
            } else {
                empty.classList.remove('d-block');
                empty.classList.add('d-none');
            }
        }
    });

    bindEvent(sidebarList, 'click', (e) => {
        const btnDeleteSidebar = e.target.closest('.js-eliminar-acuerdo-sidebar');
        if (btnDeleteSidebar) {
            e.preventDefault();
            e.stopPropagation();

            const itemDelete = btnDeleteSidebar.closest('.acuerdo-sidebar-item');
            const idAcuerdoEliminar = parseInt(itemDelete?.dataset.idAcuerdo || '0', 10);
            if (!idAcuerdoEliminar) return;

            Swal.fire({
                icon: 'warning',
                title: 'Romper acuerdo',
                text: 'Esta acción eliminará el acuerdo y su matriz permanentemente.',
                showCancelButton: true,
                confirmButtonText: 'Eliminar acuerdo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then(async (confirm) => {
                if (!confirm.isConfirmed) return;
                try {
                    await postForm(urls.eliminarAcuerdo, { id_acuerdo: idAcuerdoEliminar });
                    showSuccessAlert('Acuerdo eliminado correctamente');

                    const acuerdoActual = getAcuerdoId();
                    if (acuerdoActual === idAcuerdoEliminar) {
                        softReloadSPA('?ruta=comercial/listas');
                        return;
                    }
                    itemDelete.remove();
                    const empty = document.getElementById('sidebarNoResults');
                    const totalItems = sidebarList.querySelectorAll('.acuerdo-sidebar-item').length;
                    if (empty && totalItems === 0) {
                        empty.textContent = 'No hay clientes vinculados.';
                        empty.classList.remove('d-none');
                        empty.classList.add('d-block');
                    }
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
            return;
        }

        const item = e.target.closest('.acuerdo-sidebar-item');
        if (!item) return;
        e.preventDefault();
        
        const idAcuerdo = parseInt(item.dataset.idAcuerdo || '0', 10);
        
        document.querySelectorAll('.acuerdo-sidebar-item').forEach(el => el.classList.remove('active'));
        item.classList.add('active');

        const url = new URL(window.location.href);
        url.searchParams.set('id', idAcuerdo);
        window.history.pushState({ sisadminPartial: true }, '', url.toString());

        cargarMatriz(idAcuerdo);

        const offcanvasEl = document.getElementById('sidebarClientesMenu');
        if (offcanvasEl) {
            const offcanvasInst = bootstrap.Offcanvas.getInstance(offcanvasEl);
            if (offcanvasInst) offcanvasInst.hide();
        }
    });

    bindEvent(filtroMatriz, 'input', aplicarFiltroMatriz);

    const loadClientesDisponibles = async () => {
        const res = await fetch(urls.clientesDisponibles, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar clientes.');

        const options = (json.data || []).map(c => ({ value: String(c.id), text: `${c.numero_documento ? `${c.numero_documento} - ` : ''}${c.cliente_nombre}` }));
        if (tsCliente) tsCliente.destroy();
        if (selectCliente) {
            selectCliente.innerHTML = '';
            tsCliente = new TomSelect(selectCliente, {
                options, maxItems: 1, create: false, valueField: 'value', labelField: 'text', searchField: ['text'],
                placeholder: options.length ? 'Seleccione un cliente...' : 'Sin clientes disponibles'
            });
        }
    };

    const loadPresentacionesDisponibles = async () => {
        const idAcuerdo = getAcuerdoId();
        const res = await fetch(`${urls.presentacionesDisponibles}&id_acuerdo=${idAcuerdo}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar presentaciones.');

        const options = (json.data || []).map(p => ({ value: String(p.id), text: `${p.codigo_presentacion || 'N/A'} - ${p.producto_nombre}` }));
        if (tsPresentacion) tsPresentacion.destroy();
        if (selectPresentacion) {
            selectPresentacion.innerHTML = '';
            tsPresentacion = new TomSelect(selectPresentacion, {
                options, maxItems: 1, create: false, valueField: 'value', labelField: 'text', searchField: ['text'],
                placeholder: options.length ? 'Seleccione un producto...' : 'Sin productos disponibles'
            });
        }
    };

    const loadItemsVolumen = async () => {
        const res = await fetch(urls.itemsVolumen, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar productos.');

        const options = (json.data || []).map(p => ({ value: String(p.id), text: `${p.codigo_presentacion || 'N/A'} - ${p.producto_nombre}` }));
        if (tsItemVolumen) tsItemVolumen.destroy();
        if (selectItemVolumen) {
            selectItemVolumen.innerHTML = '';
            tsItemVolumen = new TomSelect(selectItemVolumen, {
                options, maxItems: 1, create: false, valueField: 'value', labelField: 'text', searchField: ['text'],
                placeholder: options.length ? 'Seleccione un producto...' : 'Sin productos disponibles'
            });
        }
    };

    bindEvent(modalVincularEl, 'show.bs.modal', async () => {
        try { await loadClientesDisponibles(); } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(formVincular, 'submit', async (e) => {
        e.preventDefault();
        const idTercero = tsCliente ? tsCliente.getValue() : '';
        if (!idTercero) return Swal.fire({ icon: 'warning', title: 'Seleccione un cliente' });
        try {
            const resp = await postForm(urls.crearAcuerdo, { id_tercero: idTercero, observaciones: inputObs ? inputObs.value : '' });
            if (modalVincular) modalVincular.hide();
            await showSuccessAlert('¡Acuerdo Creado!', 'El cliente fue vinculado correctamente.');
            softReloadSPA(`?ruta=comercial/listas&id=${resp.id}`);
        } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(btnAgregarProducto, 'click', async () => {
        try {
            if (getModo() === 'volumen') {
                await loadItemsVolumen();
                if (inputCantidadMinimaVolumen) inputCantidadMinimaVolumen.value = '';
                if (inputPrecioUnitarioVolumen) inputPrecioUnitarioVolumen.value = '';
                if (modalVolumen) modalVolumen.show();
            } else {
                await loadPresentacionesDisponibles();
                if (inputPrecioInicial) inputPrecioInicial.value = '';
                if (modalAgregar) modalAgregar.show();
            }
        } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(formAgregar, 'submit', async (e) => {
        e.preventDefault();
        const idAcuerdo = getAcuerdoId();
        const idPresentacion = tsPresentacion ? tsPresentacion.getValue() : '';
        const precio = inputPrecioInicial ? parseFloat(inputPrecioInicial.value || '0') : 0;
        if (!idAcuerdo || !idPresentacion || precio <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos' });
        try {
            await postForm(urls.agregarProducto, { id_acuerdo: idAcuerdo, id_presentacion: idPresentacion, precio_pactado: precio });
            if (modalAgregar) modalAgregar.hide();
            showSuccessAlert('Producto agregado a la lista');
            cargarMatriz(idAcuerdo); 
        } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(formVolumen, 'submit', async (e) => {
        e.preventDefault();
        const idItem = tsItemVolumen ? tsItemVolumen.getValue() : '';
        const cantidad = inputCantidadMinimaVolumen ? parseFloat(inputCantidadMinimaVolumen.value || '0') : 0;
        const precio = inputPrecioUnitarioVolumen ? parseFloat(inputPrecioUnitarioVolumen.value || '0') : 0;
        if (!idItem || cantidad <= 0 || precio <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos' });
        try {
            await postForm(urls.agregarVolumen, { id_item: idItem, cantidad_minima: cantidad, precio_unitario: precio });
            if (modalVolumen) modalVolumen.hide();
            showSuccessAlert('Escala por volumen agregada');
            cargarMatriz(getAcuerdoId()); 
        } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(tbody, 'blur', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        const idDetalle = parseInt(tr.dataset.idDetalle || '0', 10);
        if (!idDetalle) return;

        try {
            let actualizado = false;

            if (e.target.classList.contains('js-precio-pactado')) {
                const precio = parseFloat(e.target.value || '0');
                if (precio < 0) return;
                await postForm(urls.actualizarPrecio, { id_detalle: idDetalle, precio_pactado: precio });
                e.target.value = precio.toFixed(4); 
                e.target.setAttribute('data-original', e.target.value);
                actualizado = true;
            }
            
            if (e.target.classList.contains('js-cantidad-minima') || e.target.classList.contains('js-precio-volumen')) {
                const cantInput = tr.querySelector('.js-cantidad-minima');
                const precInput = tr.querySelector('.js-precio-volumen');
                
                const cantidad = parseFloat(cantInput.value || '0');
                const precioVol = parseFloat(precInput.value || '0');
                
                if (cantidad <= 0 || precioVol < 0) return;
                
                await postForm(urls.actualizarVolumen, { id_detalle: idDetalle, cantidad_minima: cantidad, precio_unitario: precioVol });
                
                cantInput.value = cantidad.toFixed(2);
                precInput.value = precioVol.toFixed(4);
                cantInput.setAttribute('data-original', cantInput.value);
                precInput.setAttribute('data-original', precInput.value);
                actualizado = true;
            }
            
            if(actualizado) showSuccessAlert('Registro actualizado');
            
            e.target.classList.add('is-valid');
            setTimeout(() => e.target.classList.remove('is-valid'), 900);
        } catch (_err) {
            e.target.value = e.target.getAttribute('data-original');
            e.target.classList.add('is-invalid');
            setTimeout(() => e.target.classList.remove('is-invalid'), 1400);
        }
    }, true);

    bindEvent(tbody, 'change', async (e) => {
        if (!e.target.classList.contains('js-estado-precio')) return;
        const tr = e.target.closest('tr');
        const idDetalle = parseInt((tr?.dataset.idDetalle) || '0', 10);
        const estadoNuevo = e.target.checked ? 1 : 0;
        try { 
            await postForm(urls.togglePrecio, { id_detalle: idDetalle, estado: estadoNuevo }); 
            showSuccessAlert(estadoNuevo ? 'Precio activado' : 'Precio desactivado');
        }
        catch (err) { e.target.checked = !e.target.checked; Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(tbody, 'click', async (e) => {
        const btnDeleteAcuerdo = e.target.closest('.js-eliminar-producto');
        const btnDeleteVolumen = e.target.closest('.js-eliminar-volumen');
        if (!btnDeleteAcuerdo && !btnDeleteVolumen) return;
        
        const tr = e.target.closest('tr');
        const idDetalle = parseInt((tr?.dataset.idDetalle) || '0', 10);
        const idAcuerdo = getAcuerdoId();

        const confirm = await Swal.fire({
            icon: 'warning', title: 'Eliminar registro', text: '¿Deseas retirar esta tarifa de la matriz?',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!confirm.isConfirmed) return;

        try {
            if (btnDeleteVolumen) await postForm(urls.eliminarVolumen, { id_detalle: idDetalle });
            else await postForm(urls.eliminarProducto, { id_detalle: idDetalle });
            showSuccessAlert('Tarifa eliminada correctamente');
            cargarMatriz(idAcuerdo); 
        } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(btnSuspender, 'click', async () => {
        const idAcuerdo = getAcuerdoId();
        if (!idAcuerdo) return;
        const confirm = await Swal.fire({ icon: 'question', title: 'Suspender acuerdo', text: 'Se dejarán de aplicar estas tarifas temporalmente.', showCancelButton: true, confirmButtonText: 'Suspender' });
        if (!confirm.isConfirmed) return;
        try { 
            await postForm(urls.suspenderAcuerdo, { id_acuerdo: idAcuerdo }); 
            showSuccessAlert('Acuerdo suspendido');
            cargarMatriz(idAcuerdo); 
        }
        catch (err) { Swal.fire({ icon: 'warning', title: 'No se pudo suspender', text: err.message }); }
    });

    bindEvent(btnActivar, 'click', async () => {
        const idAcuerdo = getAcuerdoId();
        if (!idAcuerdo) return;
        const confirm = await Swal.fire({ icon: 'question', title: 'Activar acuerdo', text: 'Las tarifas volverán a aplicarse a este cliente.', showCancelButton: true, confirmButtonText: 'Activar' });
        if (!confirm.isConfirmed) return;
        try { 
            await postForm(urls.activarAcuerdo, { id_acuerdo: idAcuerdo }); 
            showSuccessAlert('Acuerdo activado');
            cargarMatriz(idAcuerdo); 
        }
        catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });

    bindEvent(btnEliminar, 'click', async () => {
        const idAcuerdo = getAcuerdoId();
        if (!idAcuerdo) return;
        const confirm = await Swal.fire({ icon: 'warning', title: 'Romper acuerdo', text: 'Esta acción eliminará el acuerdo y su matriz permanentemente.', showCancelButton: true, confirmButtonText: 'Eliminar acuerdo', confirmButtonColor: '#dc3545' });
        if (!confirm.isConfirmed) return;
        try { 
            await postForm(urls.eliminarAcuerdo, { id_acuerdo: idAcuerdo }); 
            await showSuccessAlert('Acuerdo eliminado');
            softReloadSPA('?ruta=comercial/listas'); 
        }
        catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
    });
}

function initComercialProveedorApp() {
    const app = document.getElementById('acuerdosProveedoresApp');
    if (!app) return;

    const urls = {
        proveedoresDisponibles: app.dataset.urlProveedoresDisponibles,
        crearAcuerdo: app.dataset.urlCrearAcuerdo,
        obtenerMatriz: app.dataset.urlObtenerMatriz,
        itemsDisponibles: app.dataset.urlItemsDisponibles,
        agregarProducto: app.dataset.urlAgregarProducto,
        actualizarPrecio: app.dataset.urlActualizarPrecio,
        eliminarPrecio: app.dataset.urlEliminarPrecio,
        unidadesItem: app.dataset.urlUnidadesItem,
    };

    const tabla = document.getElementById('tablaMatrizProveedor');
    const tbody = document.getElementById('matrizProveedorBodyRows');
    const titulo = document.getElementById('acuerdoProveedorTitulo');
    const resumen = document.getElementById('acuerdoProveedorResumen');
    const sidebarList = document.getElementById('acuerdosProveedorSidebarList');
    const filtro = document.getElementById('filtroProveedoresAcuerdo');
    const filtroMatriz = document.getElementById('filtroMatrizProveedor');

    const modalVincularEl = document.getElementById('modalVincularProveedor');
    const formVincular = document.getElementById('formVincularProveedor');
    const selectProveedor = document.getElementById('selectProveedorVincular');

    const modalAgregarEl = document.getElementById('modalAgregarProductoProveedor');
    const formAgregar = document.getElementById('formAgregarProductoProveedor');
    const selectProducto = document.getElementById('selectProductoProveedor');
    const inputPrecio = document.getElementById('inputPrecioProveedor');
    const btnAgregar = document.getElementById('btnAgregarProductoProveedor');
    const btnImprimirProveedor = document.getElementById('btnImprimirAcuerdoProveedor');
    const selectUnidad = document.getElementById('selectUnidadProveedor');

    const modalVincular = modalVincularEl ? bootstrap.Modal.getOrCreateInstance(modalVincularEl) : null;
    const modalAgregar = modalAgregarEl ? bootstrap.Modal.getOrCreateInstance(modalAgregarEl) : null;

    const cacheUnidades = new Map();
    const getAcuerdoId = () => (tabla ? parseInt(tabla.dataset.idAcuerdo || '0', 10) || 0 : 0);
    const withParam = (url, key, value) => `${url}${url.includes('?') ? '&' : '?'}${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`;

    const postForm = async (url, payload) => {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => fd.append(k, String(v)));
        const res = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        const json = await res.json().catch(() => ({ success: false, message: 'Respuesta inválida' }));
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudo completar la operación.');
        return json;
    };

    const softReloadSPA = (newUrlString = null) => {
        const urlTarget = newUrlString ? new URL(newUrlString, window.location.href) : window.location.href;
        window.location.href = urlTarget.toString();
    };

    const obtenerUnidadesItem = async (idItem) => {
        if (!idItem) return [];
        if (cacheUnidades.has(idItem)) return cacheUnidades.get(idItem);
        const res = await fetch(withParam(urls.unidadesItem, 'id_item', idItem), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        const data = (json.success && Array.isArray(json.data)) ? json.data : [];
        cacheUnidades.set(idItem, data);
        return data;
    };

    const renderRows = (matriz) => {
        if (!tbody) return;
        if (!Array.isArray(matriz) || matriz.length === 0) {
            tbody.innerHTML = `<tr id="emptyMatrizProveedorRow"><td colspan="5" class="text-center text-muted py-5">
                <i class="bi bi-exclamation-circle text-warning fs-1 d-block mb-2"></i>Este proveedor aún no tiene productos recomendados.
            </td></tr>`;
            return;
        }
        tbody.innerHTML = matriz.map((item) => `
            <tr data-id-detalle="${item.id}" data-id-item="${item.id_item}">
                <td class="ps-4"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                <td class="fw-semibold text-dark">${item.producto_nombre || ''}</td>
                <td>
                    <select class="form-select form-select-sm js-unidad-proveedor" data-original="${parseInt(item.id_unidad_conversion || 0, 10)}">
                        <option value="">Unidad Base (x 1)</option>
                    </select>
                </td>
                <td><div class="input-group input-group-sm" style="max-width: 140px;">
                    <span class="input-group-text bg-light border-end-0">S/</span>
                    <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-proveedor" value="${parseFloat(item.precio_recomendado).toFixed(4)}" data-original="${parseFloat(item.precio_recomendado).toFixed(4)}">
                </div></td>
                <td class="text-end pe-4"><button class="btn btn-sm btn-outline-danger border-0 js-eliminar-precio-proveedor" type="button"><i class="bi bi-trash"></i></button></td>
            </tr>`).join('');
    };

    const aplicarFiltroMatriz = () => {
        if (!tbody || !filtroMatriz) return;
        const terminosBusqueda = (filtroMatriz.value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().split(' ').filter(Boolean);
        const filas = Array.from(tbody.querySelectorAll('tr[data-id-detalle]'));
        let visibles = 0;

        filas.forEach((fila) => {
            const textoFila = (fila.textContent || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            const coincide = terminosBusqueda.every((termino) => textoFila.includes(termino));
            const mostrar = terminosBusqueda.length === 0 || coincide;
            fila.classList.toggle('d-none', !mostrar);
            if (mostrar) visibles++;
        });

        const filaVacia = document.getElementById('emptyMatrizProveedorRow');
        if (filaVacia) {
            const sinDatosOriginales = filas.length === 0;
            filaVacia.classList.toggle('d-none', !sinDatosOriginales && visibles > 0);
        } else if (filas.length > 0 && visibles === 0) {
            const nuevaFilaVacia = document.createElement('tr');
            nuevaFilaVacia.id = 'emptyMatrizProveedorRow';
            nuevaFilaVacia.innerHTML = `<td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search text-muted fs-1 d-block mb-2"></i>No hay coincidencias para la búsqueda.</td>`;
            tbody.appendChild(nuevaFilaVacia);
        }
    };

    const hidratarUnidadesTabla = async () => {
        if (!tbody) return;
        const filas = Array.from(tbody.querySelectorAll('tr[data-id-item]'));
        await Promise.all(filas.map(async (fila) => {
            const select = fila.querySelector('.js-unidad-proveedor');
            const idItem = parseInt(fila.dataset.idItem || '0', 10);
            if (!select || !idItem) return;
            const unidades = await obtenerUnidadesItem(idItem);
            const opciones = ['<option value="">Unidad Base (x 1)</option>'].concat(unidades.map((u) => `<option value="${u.id}">${u.nombre} (x ${parseFloat(u.factor_conversion || 1)})</option>`)).join('');
            select.innerHTML = opciones;
            select.value = select.dataset.original === '0' ? '' : (select.dataset.original || '');
        }));
    };

    const cargarMatriz = async (idAcuerdo) => {
        const res = await fetch(withParam(urls.obtenerMatriz, 'id_acuerdo', idAcuerdo), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudo cargar la matriz.');
        const totalProductos = (json.matriz || []).length;
        if (tabla) tabla.dataset.idAcuerdo = String(idAcuerdo);
        if (titulo) titulo.textContent = json.acuerdo.proveedor_nombre;
        if (btnImprimirProveedor) btnImprimirProveedor.href = `?ruta=comercial/imprimirAcuerdoPdf&tipo=proveedores&id=${encodeURIComponent(String(idAcuerdo))}`;
        if (resumen) resumen.textContent = `${totalProductos} productos configurados`;
        renderRows(json.matriz || []);
        await hidratarUnidadesTabla();
        aplicarFiltroMatriz();

        const sidebarItem = document.querySelector(`.proveedor-sidebar-item[data-id-acuerdo="${idAcuerdo}"]`);
        if (sidebarItem) {
            const counterText = sidebarItem.querySelector('small');
            if (counterText) counterText.textContent = `${totalProductos} productos`;
            const dotEl = sidebarItem.querySelector('.rounded-circle');
            if (dotEl && json.acuerdo) {
                dotEl.style.background = parseInt(json.acuerdo.estado, 10) === 1 ? '#22c55e' : '#9ca3af';
            }
        }
    };

    bindEvent(sidebarList, 'click', async (e) => {
        const item = e.target.closest('.proveedor-sidebar-item');
        if (!item) return;
        const idAcuerdo = parseInt(item.dataset.idAcuerdo || '0', 10);
        document.querySelectorAll('.proveedor-sidebar-item').forEach(el => el.classList.remove('active'));
        item.classList.add('active');
        try { await cargarMatriz(idAcuerdo); } catch (err) { Swal.fire('Error', err.message, 'error'); }
    });

    bindEvent(filtro, 'input', () => {
        const term = (filtro.value || '').trim().toLowerCase();
        let visible = 0;
        sidebarList.querySelectorAll('.proveedor-sidebar-item').forEach((row) => {
            const ok = row.dataset.search.includes(term);
            row.classList.toggle('d-none', !ok);
            if (ok) visible++;
        });
        const noResults = document.getElementById('sidebarProveedorNoResults');
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
    });

    bindEvent(filtroMatriz, 'input', aplicarFiltroMatriz);

    bindEvent(modalVincularEl, 'show.bs.modal', async () => {
        const res = await fetch(urls.proveedoresDisponibles, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        const opciones = (json.data || []).map(p => `<option value="${p.id}">${p.proveedor_nombre}</option>`).join('');
        selectProveedor.innerHTML = `<option value="">Seleccione...</option>${opciones}`;
    });

    bindEvent(formVincular, 'submit', async (e) => {
        e.preventDefault();
        try {
            const resp = await postForm(urls.crearAcuerdo, { id_tercero: selectProveedor.value });
            modalVincular?.hide();
            await showSuccessAlert('Proveedor Vinculado', 'Se ha creado la configuración de matriz.');
            softReloadSPA(`?ruta=comercial/proveedores&id=${resp.id}`);
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
    });

    bindEvent(btnAgregar, 'click', async () => {
        const idAcuerdo = getAcuerdoId();
        if (!idAcuerdo) return;
        try {
            const res = await fetch(withParam(urls.itemsDisponibles, 'id_acuerdo', idAcuerdo), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            selectProducto.innerHTML = `<option value="">Seleccione...</option>${(json.data || []).map(i => `<option value="${i.id}">${i.producto_nombre}</option>`).join('')}`;
            inputPrecio.value = '';
            selectUnidad.innerHTML = '<option value="">Unidad Base (x 1)</option>';
            modalAgregar?.show();
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
    });

    bindEvent(selectProducto, 'change', async () => {
        const idItem = parseInt(selectProducto.value || '0', 10);
        selectUnidad.innerHTML = '<option value="">Unidad Base (x 1)</option>';
        if (!idItem) return;
        try {
            const unidades = await obtenerUnidadesItem(idItem);
            if (unidades.length > 0) {
                selectUnidad.innerHTML += unidades.map((u) => `<option value="${u.id}">${u.nombre} (x ${parseFloat(u.factor_conversion || 1)})</option>`).join('');
            }
        } catch (err) { console.error('Error buscando unidades:', err); }
    });

    bindEvent(formAgregar, 'submit', async (e) => {
        e.preventDefault();
        const idAcuerdo = getAcuerdoId();
        try {
            await postForm(urls.agregarProducto, {
                id_acuerdo: idAcuerdo, id_item: selectProducto.value, id_unidad: selectUnidad?.value || '', precio_recomendado: inputPrecio.value,
            });
            modalAgregar?.hide();
            showSuccessAlert('Recomendación guardada');
            await cargarMatriz(idAcuerdo);
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
    });

    bindEvent(tbody, 'change', async (e) => {
        const fila = e.target.closest('tr');
        const idDetalle = parseInt(fila?.dataset.idDetalle || '0', 10);
        if (!idDetalle) return;

        if (e.target.classList.contains('js-precio-proveedor')) {
            try {
                await postForm(urls.actualizarPrecio, { id_detalle: idDetalle, precio_recomendado: e.target.value });
                e.target.dataset.original = e.target.value;
                showSuccessAlert('Precio actualizado');
            } catch (err) {
                e.target.value = e.target.dataset.original || '0.0000';
                Swal.fire('Error', err.message, 'error');
            }
            return;
        }

        if (e.target.classList.contains('js-unidad-proveedor')) {
            try {
                await postForm(urls.actualizarPrecio, { id_detalle: idDetalle, id_unidad: e.target.value });
                e.target.dataset.original = e.target.value || '0';
                showSuccessAlert('Unidad de compra actualizada');
            } catch (err) {
                e.target.value = e.target.dataset.original === '0' ? '' : e.target.dataset.original;
                Swal.fire('Error', err.message, 'error');
            }
        }
    });

    bindEvent(tbody, 'click', async (e) => {
        const btn = e.target.closest('.js-eliminar-precio-proveedor');
        if (!btn) return;
        const fila = btn.closest('tr');
        const idDetalle = parseInt(fila?.dataset.idDetalle || '0', 10);
        if (!idDetalle) return;
        const conf = await Swal.fire({ icon: 'warning', title: 'Eliminar producto', text: 'Se quitará la recomendación de precio.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', confirmButtonColor: '#dc3545' });
        if (!conf.isConfirmed) return;
        try {
            await postForm(urls.eliminarPrecio, { id_detalle: idDetalle });
            showSuccessAlert('Recomendación eliminada');
            await cargarMatriz(getAcuerdoId());
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
    });

    hidratarUnidadesTabla().catch(() => null);
}

// ---------------------------------------------------------
// INICIALIZADOR GLOBAL (LA CLAVE DE LA SOLUCIÓN)
// ---------------------------------------------------------
const arrancarModulosComerciales = () => {
    initComercialApp();
    initComercialProveedorApp();
};

// 1. Carga inicial tradicional
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', arrancarModulosComerciales);
} else {
    arrancarModulosComerciales();
}

// 2. Integración silenciosa para navegaciones SPA (Livewire / Filament / Turbo)
document.addEventListener('livewire:navigated', arrancarModulosComerciales);
document.addEventListener('livewire:load', arrancarModulosComerciales);
document.addEventListener('turbo:load', arrancarModulosComerciales);