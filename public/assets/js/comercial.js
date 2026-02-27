/**
 * GESTIÓN COMERCIAL
 * - Acuerdos comerciales (matriz de tarifas)
 */

document.addEventListener('DOMContentLoaded', () => {
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

    const modalVincular = modalVincularEl ? new bootstrap.Modal(modalVincularEl) : null;
    const modalAgregar = modalAgregarEl ? new bootstrap.Modal(modalAgregarEl) : null;
    const modalVolumen = modalVolumenEl ? new bootstrap.Modal(modalVolumenEl) : null;

    let tsCliente = null;
    let tsPresentacion = null;
    let tsItemVolumen = null;

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
                    <i class="bi bi-exclamation-circle text-warning me-1"></i>
                    ${msg}
                </td>
            </tr>
        `;
    };

    const rowTemplate = (item, modo) => {
        if (modo === 'volumen') {
            return `
                <tr data-id-detalle="${item.id}">
                    <td class="ps-4"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                    <td>${item.producto_nombre}</td>
                    <td><input type="number" min="0.0001" step="0.0001" class="form-control form-control-sm text-end js-cantidad-minima" value="${item.cantidad_minima}"></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">S/</span>
                            <input type="number" min="0" step="0.0001" class="form-control text-end js-precio-volumen" value="${item.precio_unitario}">
                        </div>
                    </td>
                    <td class="text-end pe-4"><button class="btn btn-sm btn-outline-danger js-eliminar-volumen" type="button"><i class="bi bi-trash"></i></button></td>
                </tr>
            `;
        }

        return `
            <tr data-id-detalle="${item.id}">
                <td class="ps-4"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                <td>${item.producto_nombre}</td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">S/</span>
                        <input type="number" min="0" step="0.0001" class="form-control text-end js-precio-pactado" value="${item.precio_pactado}">
                    </div>
                </td>
                <td><div class="form-check form-switch m-0"><input class="form-check-input js-estado-precio" type="checkbox" ${parseInt(item.estado, 10) === 1 ? 'checked' : ''}></div></td>
                <td class="text-end pe-4"><button class="btn btn-sm btn-outline-danger js-eliminar-producto" type="button"><i class="bi bi-trash"></i></button></td>
            </tr>
        `;
    };

    const cargarMatriz = async (idAcuerdo) => {
        const url = `${urls.obtenerMatriz}&id_acuerdo=${idAcuerdo}`;
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudo cargar la matriz.');

        const modo = json.modo || 'acuerdo';
        if (tabla) {
            tabla.dataset.idAcuerdo = String(idAcuerdo);
            tabla.dataset.modo = modo;
        }

        if (tituloCliente) tituloCliente.textContent = json.acuerdo.cliente_nombre;
        if (resumenTarifas) {
            resumenTarifas.textContent = `${json.matriz.length} ${modo === 'volumen' ? 'escalas configuradas' : 'tarifas configuradas'}`;
        }
        if (btnAgregarProducto) btnAgregarProducto.innerHTML = `<i class="bi bi-plus-lg me-1"></i>${modo === 'volumen' ? 'Agregar Escala' : 'Agregar Producto'}`;

        if (!tbody) return;
        if (!Array.isArray(json.matriz) || json.matriz.length === 0) {
            renderEmptyRow(modo);
            return;
        }
        tbody.innerHTML = json.matriz.map(r => rowTemplate(r, modo)).join('');
    };

    const refrescarPaginaConAcuerdo = (idAcuerdo) => {
        const u = new URL(window.location.href);
        u.searchParams.set('ruta', 'comercial/listas');
        u.searchParams.set('id', String(idAcuerdo));
        window.location.href = u.toString();
    };

    const loadClientesDisponibles = async () => {
        const res = await fetch(urls.clientesDisponibles, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar clientes.');

        const options = (json.data || []).map(c => ({ value: String(c.id), text: `${c.cliente_nombre}${c.numero_documento ? ` · ${c.numero_documento}` : ''}` }));
        if (tsCliente) tsCliente.destroy();
        if (selectCliente) {
            selectCliente.innerHTML = '';
            tsCliente = new TomSelect(selectCliente, {
                options,
                create: false,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                placeholder: options.length ? 'Seleccione un cliente...' : 'Sin clientes disponibles'
            });
        }
    };

    const loadPresentacionesDisponibles = async () => {
        const idAcuerdo = getAcuerdoId();
        const res = await fetch(`${urls.presentacionesDisponibles}&id_acuerdo=${idAcuerdo}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar presentaciones.');

        const options = (json.data || []).map(p => ({ value: String(p.id), text: `${p.codigo_presentacion || 'N/A'} · ${p.producto_nombre}` }));
        if (tsPresentacion) tsPresentacion.destroy();
        if (selectPresentacion) {
            selectPresentacion.innerHTML = '';
            tsPresentacion = new TomSelect(selectPresentacion, {
                options,
                create: false,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                placeholder: options.length ? 'Seleccione una presentación...' : 'Sin presentaciones disponibles'
            });
        }
    };

    const loadItemsVolumen = async () => {
        const res = await fetch(urls.itemsVolumen, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar productos.');

        const options = (json.data || []).map(p => ({ value: String(p.id), text: `${p.codigo_presentacion || 'N/A'} · ${p.producto_nombre}` }));
        if (tsItemVolumen) tsItemVolumen.destroy();
        if (selectItemVolumen) {
            selectItemVolumen.innerHTML = '';
            tsItemVolumen = new TomSelect(selectItemVolumen, {
                options,
                create: false,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                placeholder: options.length ? 'Seleccione un producto...' : 'Sin productos disponibles'
            });
        }
    };

    if (filtroClientes && sidebarList) {
        filtroClientes.addEventListener('input', () => {
            const term = filtroClientes.value.toLowerCase().trim();
            const items = sidebarList.querySelectorAll('.acuerdo-sidebar-item');
            let visibles = 0;
            items.forEach(item => {
                const show = (item.dataset.search || '').includes(term);
                item.style.display = show ? '' : 'none';
                if (show) visibles += 1;
            });
            const empty = document.getElementById('sidebarNoResults');
            if (empty) empty.style.display = visibles ? 'none' : '';
        });
    }

    if (sidebarList) {
        sidebarList.addEventListener('click', async (e) => {
            const item = e.target.closest('.acuerdo-sidebar-item');
            if (!item) return;
            sidebarList.querySelectorAll('.acuerdo-sidebar-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            const idAcuerdo = parseInt(item.dataset.idAcuerdo || '0', 10);
            try {
                await cargarMatriz(idAcuerdo);
                const u = new URL(window.location.href);
                u.searchParams.set('ruta', 'comercial/listas');
                u.searchParams.set('id', String(idAcuerdo));
                window.history.replaceState({}, '', u.toString());
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    if (modalVincularEl) {
        modalVincularEl.addEventListener('show.bs.modal', async () => {
            try { await loadClientesDisponibles(); } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (formVincular) {
        formVincular.addEventListener('submit', async (e) => {
            e.preventDefault();
            const idTercero = tsCliente ? tsCliente.getValue() : '';
            if (!idTercero) return Swal.fire({ icon: 'warning', title: 'Seleccione un cliente' });
            try {
                const resp = await postForm(urls.crearAcuerdo, { id_tercero: idTercero, observaciones: inputObs ? inputObs.value : '' });
                if (modalVincular) modalVincular.hide();
                refrescarPaginaConAcuerdo(resp.id);
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (btnAgregarProducto) {
        btnAgregarProducto.addEventListener('click', async () => {
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
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    if (formAgregar) {
        formAgregar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const idAcuerdo = getAcuerdoId();
            const idPresentacion = tsPresentacion ? tsPresentacion.getValue() : '';
            const precio = inputPrecioInicial ? parseFloat(inputPrecioInicial.value || '0') : 0;
            if (!idAcuerdo || !idPresentacion || precio <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos' });
            try {
                await postForm(urls.agregarProducto, { id_acuerdo: idAcuerdo, id_presentacion: idPresentacion, precio_pactado: precio });
                if (modalAgregar) modalAgregar.hide();
                await cargarMatriz(idAcuerdo);
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (formVolumen) {
        formVolumen.addEventListener('submit', async (e) => {
            e.preventDefault();
            const idItem = tsItemVolumen ? tsItemVolumen.getValue() : '';
            const cantidad = inputCantidadMinimaVolumen ? parseFloat(inputCantidadMinimaVolumen.value || '0') : 0;
            const precio = inputPrecioUnitarioVolumen ? parseFloat(inputPrecioUnitarioVolumen.value || '0') : 0;
            if (!idItem || cantidad <= 0 || precio <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos' });
            try {
                await postForm(urls.agregarVolumen, { id_item: idItem, cantidad_minima: cantidad, precio_unitario: precio });
                if (modalVolumen) modalVolumen.hide();
                await cargarMatriz(0);
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (tbody) {
        tbody.addEventListener('blur', async (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;
            const idDetalle = parseInt(tr.dataset.idDetalle || '0', 10);
            if (!idDetalle) return;

            try {
                if (e.target.classList.contains('js-precio-pactado')) {
                    const precio = parseFloat(e.target.value || '0');
                    if (precio < 0) return;
                    await postForm(urls.actualizarPrecio, { id_detalle: idDetalle, precio_pactado: precio });
                }
                if (e.target.classList.contains('js-cantidad-minima') || e.target.classList.contains('js-precio-volumen')) {
                    const cantidad = parseFloat((tr.querySelector('.js-cantidad-minima') || {}).value || '0');
                    const precioVol = parseFloat((tr.querySelector('.js-precio-volumen') || {}).value || '0');
                    if (cantidad <= 0 || precioVol < 0) return;
                    await postForm(urls.actualizarVolumen, { id_detalle: idDetalle, cantidad_minima: cantidad, precio_unitario: precioVol });
                }
                e.target.classList.add('is-valid');
                setTimeout(() => e.target.classList.remove('is-valid'), 900);
            } catch (_err) {
                e.target.classList.add('is-invalid');
                setTimeout(() => e.target.classList.remove('is-invalid'), 1400);
            }
        }, true);

        tbody.addEventListener('change', async (e) => {
            if (!e.target.classList.contains('js-estado-precio')) return;
            const tr = e.target.closest('tr');
            const idDetalle = parseInt((tr?.dataset.idDetalle) || '0', 10);
            try { await postForm(urls.togglePrecio, { id_detalle: idDetalle, estado: e.target.checked ? 1 : 0 }); }
            catch (err) { e.target.checked = !e.target.checked; Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });

        tbody.addEventListener('click', async (e) => {
            const btnDeleteAcuerdo = e.target.closest('.js-eliminar-producto');
            const btnDeleteVolumen = e.target.closest('.js-eliminar-volumen');
            if (!btnDeleteAcuerdo && !btnDeleteVolumen) return;
            const tr = e.target.closest('tr');
            const idDetalle = parseInt((tr?.dataset.idDetalle) || '0', 10);
            const idAcuerdo = getAcuerdoId();

            const confirm = await Swal.fire({
                icon: 'warning',
                title: 'Eliminar registro',
                text: '¿Deseas retirar este registro de la matriz?',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!confirm.isConfirmed) return;

            try {
                if (btnDeleteVolumen) await postForm(urls.eliminarVolumen, { id_detalle: idDetalle });
                else await postForm(urls.eliminarProducto, { id_detalle: idDetalle });
                await cargarMatriz(idAcuerdo);
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (btnSuspender) {
        btnSuspender.addEventListener('click', async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) return;
            const confirm = await Swal.fire({ icon: 'question', title: 'Suspender acuerdo', text: 'Se dejarán de aplicar estas tarifas.', showCancelButton: true, confirmButtonText: 'Suspender' });
            if (!confirm.isConfirmed) return;
            try { await postForm(urls.suspenderAcuerdo, { id_acuerdo: idAcuerdo }); window.location.reload(); }
            catch (err) { Swal.fire({ icon: 'warning', title: 'No se pudo suspender', text: err.message }); }
        });
    }

    if (btnActivar) {
        btnActivar.addEventListener('click', async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) return;
            try { await postForm(urls.activarAcuerdo, { id_acuerdo: idAcuerdo }); window.location.reload(); }
            catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (btnEliminar) {
        btnEliminar.addEventListener('click', async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) return;
            const confirm = await Swal.fire({ icon: 'warning', title: 'Romper acuerdo', text: 'Esta acción eliminará el acuerdo y su matriz.', showCancelButton: true, confirmButtonText: 'Eliminar acuerdo', confirmButtonColor: '#dc3545' });
            if (!confirm.isConfirmed) return;
            try { await postForm(urls.eliminarAcuerdo, { id_acuerdo: idAcuerdo }); window.location.href = '?ruta=comercial/listas'; }
            catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }
});
