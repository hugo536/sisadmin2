/**
 * GESTIÓN COMERCIAL
 * - Acuerdos comerciales (matriz de tarifas)
 */

document.addEventListener('DOMContentLoaded', () => {

    // 2) ACUERDOS COMERCIALES
    // =========================================================================
    const appAcuerdos = document.getElementById('acuerdosComercialesApp');
    if (appAcuerdos) {
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

        const btnAgregarProducto = document.getElementById('btnAgregarProducto');
        const btnSuspender = document.getElementById('btnSuspenderAcuerdo');
        const btnActivar = document.getElementById('btnActivarAcuerdo');
        const btnEliminar = document.getElementById('btnEliminarAcuerdo');

        const modalVincular = modalVincularEl ? new bootstrap.Modal(modalVincularEl) : null;
        const modalAgregar = modalAgregarEl ? new bootstrap.Modal(modalAgregarEl) : null;

        let tsCliente = null;
        let tsPresentacion = null;

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

        const getAcuerdoId = () => {
            if (!tabla) return 0;
            return parseInt(tabla.dataset.idAcuerdo || '0', 10) || 0;
        };

        const renderEmptyRow = () => {
            if (!tbody) return;
            tbody.innerHTML = `
                <tr id="emptyMatrizRow">
                    <td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-exclamation-circle text-warning me-1"></i>
                        Este acuerdo aún no tiene productos tarifados.
                    </td>
                </tr>
            `;
        };

        const rowTemplate = (item) => `
            <tr data-id-detalle="${item.id}">
                <td class="ps-4"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                <td>${item.producto_nombre}</td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">S/</span>
                        <input type="number" min="0" step="0.0001" class="form-control text-end js-precio-pactado" value="${item.precio_pactado}">
                    </div>
                </td>
                <td>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input js-estado-precio" type="checkbox" ${parseInt(item.estado, 10) === 1 ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-danger js-eliminar-producto" type="button" title="Eliminar producto">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        const cargarMatriz = async (idAcuerdo) => {
            const url = `${urls.obtenerMatriz}&id_acuerdo=${idAcuerdo}`;
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'No se pudo cargar la matriz.');
            }

            if (tabla) tabla.dataset.idAcuerdo = String(idAcuerdo);
            if (tituloCliente) tituloCliente.textContent = json.acuerdo.cliente_nombre;
            if (resumenTarifas) resumenTarifas.textContent = `${json.matriz.length} tarifas configuradas`;

            if (!tbody) return;
            if (!json.matriz.length) {
                renderEmptyRow();
                return;
            }
            tbody.innerHTML = json.matriz.map(rowTemplate).join('');
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
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'No se pudo cargar clientes.');
            }

            const options = json.data.map(c => ({
                value: String(c.id),
                text: `${c.cliente_nombre}${c.documento_numero ? ` · ${c.documento_numero}` : ''}`,
            }));

            if (tsCliente) tsCliente.destroy();
            if (selectCliente) {
                selectCliente.innerHTML = '';
                tsCliente = new TomSelect(selectCliente, {
                    options,
                    create: false,
                    valueField: 'value',
                    labelField: 'text',
                    searchField: ['text'],
                    placeholder: 'Seleccione un cliente...'
                });
            }
        };

        const loadPresentacionesDisponibles = async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) throw new Error('Debe seleccionar un acuerdo.');

            const res = await fetch(`${urls.presentacionesDisponibles}&id_acuerdo=${idAcuerdo}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'No se pudo cargar presentaciones.');
            }

            const options = json.data.map(p => ({
                value: String(p.id),
                text: `${p.codigo_presentacion || 'N/A'} · ${p.producto_nombre}`,
            }));

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

        if (filtroClientes && sidebarList) {
            filtroClientes.addEventListener('input', () => {
                const term = filtroClientes.value.toLowerCase().trim();
                const items = sidebarList.querySelectorAll('.acuerdo-sidebar-item');
                let visibles = 0;
                items.forEach(item => {
                    const s = item.dataset.search || '';
                    const show = s.includes(term);
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
                if (!idAcuerdo) return;

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
                try {
                    await loadClientesDisponibles();
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }

        if (formVincular) {
            formVincular.addEventListener('submit', async (e) => {
                e.preventDefault();
                const idTercero = tsCliente ? tsCliente.getValue() : '';
                if (!idTercero) {
                    Swal.fire({ icon: 'warning', title: 'Seleccione un cliente' });
                    return;
                }

                try {
                    const resp = await postForm(urls.crearAcuerdo, {
                        id_tercero: idTercero,
                        observaciones: inputObs ? inputObs.value : ''
                    });
                    if (modalVincular) modalVincular.hide();
                    refrescarPaginaConAcuerdo(resp.id);
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }

        if (btnAgregarProducto) {
            btnAgregarProducto.addEventListener('click', async () => {
                try {
                    await loadPresentacionesDisponibles();
                    if (inputPrecioInicial) inputPrecioInicial.value = '';
                    if (modalAgregar) modalAgregar.show();
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

                if (!idAcuerdo || !idPresentacion || precio <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Seleccione presentación y precio válido.' });
                    return;
                }

                try {
                    await postForm(urls.agregarProducto, {
                        id_acuerdo: idAcuerdo,
                        id_presentacion: idPresentacion,
                        precio_pactado: precio
                    });
                    if (modalAgregar) modalAgregar.hide();
                    await cargarMatriz(idAcuerdo);
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }

        if (tbody) {
            tbody.addEventListener('keydown', async (e) => {
                if (!e.target.classList.contains('js-precio-pactado')) return;
                if (e.key !== 'Enter') return;
                e.preventDefault();
                e.target.blur();
            });

            tbody.addEventListener('blur', async (e) => {
                if (!e.target.classList.contains('js-precio-pactado')) return;
                const input = e.target;
                const tr = input.closest('tr');
                if (!tr) return;

                const idDetalle = parseInt(tr.dataset.idDetalle || '0', 10);
                const precio = parseFloat(input.value || '0');
                if (!idDetalle || precio < 0) return;

                try {
                    await postForm(urls.actualizarPrecio, {
                        id_detalle: idDetalle,
                        precio_pactado: precio
                    });
                    input.classList.add('is-valid');
                    setTimeout(() => input.classList.remove('is-valid'), 900);
                } catch (err) {
                    input.classList.add('is-invalid');
                    setTimeout(() => input.classList.remove('is-invalid'), 1400);
                }
            }, true);

            tbody.addEventListener('change', async (e) => {
                if (!e.target.classList.contains('js-estado-precio')) return;
                const sw = e.target;
                const tr = sw.closest('tr');
                if (!tr) return;

                const idDetalle = parseInt(tr.dataset.idDetalle || '0', 10);
                const estado = sw.checked ? 1 : 0;

                try {
                    await postForm(urls.togglePrecio, { id_detalle: idDetalle, estado });
                } catch (err) {
                    sw.checked = !sw.checked;
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });

            tbody.addEventListener('click', async (e) => {
                const btn = e.target.closest('.js-eliminar-producto');
                if (!btn) return;

                const tr = btn.closest('tr');
                if (!tr) return;
                const idDetalle = parseInt(tr.dataset.idDetalle || '0', 10);
                const idAcuerdo = getAcuerdoId();

                const confirm = await Swal.fire({
                    icon: 'warning',
                    title: 'Eliminar producto',
                    text: '¿Deseas retirar este producto de la matriz?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                });

                if (!confirm.isConfirmed) return;

                try {
                    await postForm(urls.eliminarProducto, { id_detalle: idDetalle });
                    await cargarMatriz(idAcuerdo);
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }

        if (btnSuspender) {
            btnSuspender.addEventListener('click', async () => {
                const idAcuerdo = getAcuerdoId();
                if (!idAcuerdo) return;

                const confirm = await Swal.fire({
                    icon: 'question',
                    title: 'Suspender acuerdo',
                    text: 'Se dejarán de aplicar estas tarifas y se usarán precios normales.',
                    showCancelButton: true,
                    confirmButtonText: 'Suspender',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f59e0b'
                });
                if (!confirm.isConfirmed) return;

                try {
                    await postForm(urls.suspenderAcuerdo, { id_acuerdo: idAcuerdo });
                    Swal.fire({ icon: 'success', title: 'Acuerdo suspendido', timer: 1200, showConfirmButton: false });
                    window.location.reload();
                } catch (err) {
                    Swal.fire({ icon: 'warning', title: 'No se pudo suspender', text: err.message });
                }
            });
        }

        if (btnActivar) {
            btnActivar.addEventListener('click', async () => {
                const idAcuerdo = getAcuerdoId();
                if (!idAcuerdo) return;

                try {
                    await postForm(urls.activarAcuerdo, { id_acuerdo: idAcuerdo });
                    Swal.fire({ icon: 'success', title: 'Acuerdo activado', timer: 1000, showConfirmButton: false });
                    window.location.reload();
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }

        if (btnEliminar) {
            btnEliminar.addEventListener('click', async () => {
                const idAcuerdo = getAcuerdoId();
                if (!idAcuerdo) return;

                const confirm = await Swal.fire({
                    icon: 'warning',
                    title: 'Romper acuerdo',
                    text: 'Esta acción eliminará el acuerdo y su matriz de tarifas.',
                    showCancelButton: true,
                    confirmButtonText: 'Eliminar acuerdo',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                });
                if (!confirm.isConfirmed) return;

                try {
                    await postForm(urls.eliminarAcuerdo, { id_acuerdo: idAcuerdo });
                    Swal.fire({ icon: 'success', title: 'Acuerdo eliminado', timer: 1000, showConfirmButton: false });
                    window.location.href = '?ruta=comercial/listas';
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }
    }

});
