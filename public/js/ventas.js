(() => {
    const app = document.getElementById('ventasApp');
    if (!app) return;

    const urls = {
        index: app.dataset.urlIndex,
        guardar: app.dataset.urlGuardar,
        aprobar: app.dataset.urlAprobar,
        anular: app.dataset.urlAnular,
        despachar: app.dataset.urlDespachar,
    };

    const modalVenta = new bootstrap.Modal(document.getElementById('modalVenta'));
    const modalDespacho = new bootstrap.Modal(document.getElementById('modalDespacho'));
    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    const tbodyDespacho = document.querySelector('#tablaDetalleDespacho tbody');
    const templateFilaVenta = document.getElementById('templateFilaVenta');

    const ventaId = document.getElementById('ventaId');
    const idCliente = document.getElementById('idCliente');
    const buscarCliente = document.getElementById('buscarCliente');
    const ventaObservaciones = document.getElementById('ventaObservaciones');
    const ventaTotal = document.getElementById('ventaTotal');

    const despachoDocumentoId = document.getElementById('despachoDocumentoId');
    const despachoAlmacen = document.getElementById('despachoAlmacen');
    const despachoObservaciones = document.getElementById('despachoObservaciones');
    const cerrarForzado = document.getElementById('cerrarForzado');

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    async function getJson(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
            throw new Error(payload.mensaje || 'No se pudo completar la operación');
        }
        return payload;
    }

    async function postJson(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
            throw new Error(payload.mensaje || 'No se pudo completar la operación');
        }
        return payload;
    }

    function filaVentaPayload(fila) {
        return {
            id_item: Number(fila.querySelector('.detalle-item').value || 0),
            cantidad: Number(fila.querySelector('.detalle-cantidad').value || 0),
            precio_unitario: Number(fila.querySelector('.detalle-precio').value || 0),
        };
    }

    function recalcularTotalVenta() {
        let total = 0;
        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            const data = filaVentaPayload(fila);
            total += data.cantidad * data.precio_unitario;
            fila.querySelector('.detalle-subtotal').textContent = `S/ ${(data.cantidad * data.precio_unitario).toFixed(2)}`;
        });
        ventaTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    async function buscarClientes() {
        const q = buscarCliente.value.trim();
        const payload = await getJson(`${urls.index}&accion=buscar_clientes&q=${encodeURIComponent(q)}`);
        idCliente.innerHTML = '<option value="">Seleccione cliente...</option>';
        (payload.data || []).forEach((cliente) => {
            const opt = document.createElement('option');
            opt.value = cliente.id;
            opt.textContent = `${cliente.nombre_completo}${cliente.num_doc ? ` (${cliente.num_doc})` : ''}`;
            idCliente.appendChild(opt);
        });
    }

    async function cargarItemsFila(fila, termino = '') {
        const payload = await getJson(`${urls.index}&accion=buscar_items&q=${encodeURIComponent(termino)}`);
        const select = fila.querySelector('.detalle-item');
        select.innerHTML = '<option value="">Seleccione...</option>';
        (payload.data || []).forEach((item) => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.dataset.stock = Number(item.stock_actual || 0);
            opt.textContent = `${item.sku || ''} - ${item.nombre}`;
            select.appendChild(opt);
        });
    }

    async function agregarFilaVenta(item = null) {
        const fragment = templateFilaVenta.content.cloneNode(true);
        const fila = fragment.querySelector('tr');
        tbodyVenta.appendChild(fragment);
        const filaReal = tbodyVenta.lastElementChild;

        filaReal.querySelector('.detalle-cantidad').addEventListener('input', recalcularTotalVenta);
        filaReal.querySelector('.detalle-precio').addEventListener('input', recalcularTotalVenta);
        filaReal.querySelector('.btn-quitar-fila').addEventListener('click', () => {
            filaReal.remove();
            recalcularTotalVenta();
        });

        const buscador = filaReal.querySelector('.detalle-item-search');
        buscador.addEventListener('change', () => cargarItemsFila(filaReal, buscador.value.trim()));

        const selectItem = filaReal.querySelector('.detalle-item');
        selectItem.addEventListener('change', () => {
            const opt = selectItem.selectedOptions[0];
            filaReal.querySelector('.detalle-stock').textContent = Number(opt?.dataset?.stock || 0).toFixed(2);
        });

        await cargarItemsFila(filaReal);

        if (item) {
            selectItem.value = item.id_item;
            filaReal.querySelector('.detalle-cantidad').value = Number(item.cantidad || 0).toFixed(2);
            filaReal.querySelector('.detalle-precio').value = Number(item.precio_unitario || 0).toFixed(2);
            const selected = selectItem.selectedOptions[0];
            filaReal.querySelector('.detalle-stock').textContent = Number(selected?.dataset?.stock || 0).toFixed(2);
        }

        recalcularTotalVenta();
    }

    function limpiarModalVenta() {
        ventaId.value = 0;
        buscarCliente.value = '';
        idCliente.innerHTML = '<option value="">Seleccione cliente...</option>';
        ventaObservaciones.value = '';
        tbodyVenta.innerHTML = '';
        ventaTotal.textContent = 'S/ 0.00';
    }

    function filtrosQuery() {
        const params = new URLSearchParams({ accion: 'listar' });
        if (filtroBusqueda.value.trim()) params.set('q', filtroBusqueda.value.trim());
        if (filtroEstado.value !== '') params.set('estado', filtroEstado.value);
        if (filtroFechaDesde.value) params.set('fecha_desde', filtroFechaDesde.value);
        if (filtroFechaHasta.value) params.set('fecha_hasta', filtroFechaHasta.value);
        return params.toString();
    }

    async function abrirModalDespacho(idDocumento) {
        const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
        const venta = payload.data;

        despachoDocumentoId.value = venta.id;
        despachoAlmacen.value = '';
        despachoObservaciones.value = '';
        cerrarForzado.checked = false;
        tbodyDespacho.innerHTML = '';

        (venta.detalle || []).forEach((linea) => {
            const solicitado = Number(linea.cantidad || 0);
            const despachado = Number(linea.cantidad_despachada || 0);
            const pendiente = Number(linea.cantidad_pendiente || 0);
            if (pendiente <= 0) return;

            const tr = document.createElement('tr');
            tr.dataset.idDetalle = linea.id;
            tr.dataset.idItem = linea.id_item;
            tr.dataset.pendiente = pendiente.toString();
            tr.innerHTML = `
                <td>${linea.sku || ''} - ${linea.item_nombre || ''}</td>
                <td class="text-end">${solicitado.toFixed(2)}</td>
                <td class="text-end">${despachado.toFixed(2)}</td>
                <td class="text-end text-warning fw-semibold">${pendiente.toFixed(2)}</td>
                <td class="text-end despacho-stock">0.00</td>
                <td><input type="number" class="form-control form-control-sm despacho-cantidad" min="0" step="0.01" value="0"></td>
            `;
            tbodyDespacho.appendChild(tr);
        });

        modalDespacho.show();
    }

    async function actualizarStockDespacho() {
        const idAlmacen = Number(despachoAlmacen.value || 0);
        if (idAlmacen <= 0) return;

        const payload = await getJson(`${urls.index}&accion=buscar_items&id_almacen=${idAlmacen}`);
        const stockMap = new Map((payload.data || []).map((item) => [Number(item.id), Number(item.stock_actual || 0)]));

        tbodyDespacho.querySelectorAll('tr').forEach((fila) => {
            const idItem = Number(fila.dataset.idItem || 0);
            const pendiente = Number(fila.dataset.pendiente || 0);
            const stock = stockMap.get(idItem) || 0;
            fila.querySelector('.despacho-stock').textContent = stock.toFixed(2);

            const sugerida = Math.min(pendiente, stock);
            const input = fila.querySelector('.despacho-cantidad');
            input.value = sugerida.toFixed(2);

            if (sugerida < pendiente) {
                input.classList.add('border-warning');
            } else {
                input.classList.remove('border-warning');
            }
        });

        if ([...tbodyDespacho.querySelectorAll('tr')].some((fila) => Number(fila.querySelector('.despacho-cantidad').value || 0) < Number(fila.dataset.pendiente || 0))) {
            Swal.fire({
                icon: 'info',
                title: 'Despacho parcial detectado',
                text: 'Hay ítems con despacho menor al pendiente. Puedes marcar "Cerrar pedido tras este despacho" para cancelar saldos.',
                timer: 3200,
                showConfirmButton: false,
            });
        }
    }

    function bindTabla() {
        document.querySelectorAll('.btn-editar').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                try {
                    const id = Number(e.currentTarget.closest('tr').dataset.id || 0);
                    const payload = await getJson(`${urls.index}&accion=ver&id=${id}`);
                    const venta = payload.data;

                    limpiarModalVenta();
                    await buscarClientes();
                    ventaId.value = venta.id;
                    idCliente.value = venta.id_cliente;
                    ventaObservaciones.value = venta.observaciones || '';
                    for (const linea of (venta.detalle || [])) {
                        await agregarFilaVenta(linea);
                    }
                    modalVenta.show();
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            });
        });

        document.querySelectorAll('.btn-anular').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                const id = Number(e.currentTarget.closest('tr').dataset.id || 0);
                const ok = await Swal.fire({ icon: 'warning', title: '¿Anular pedido?', showCancelButton: true, confirmButtonText: 'Sí, anular' });
                if (!ok.isConfirmed) return;
                try {
                    const payload = await postJson(urls.anular, { id });
                    await Swal.fire('Éxito', payload.mensaje, 'success');
                    window.location.reload();
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            });
        });

        document.querySelectorAll('.btn-aprobar').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                const id = Number(e.currentTarget.closest('tr').dataset.id || 0);
                const ok = await Swal.fire({ icon: 'question', title: '¿Aprobar pedido?', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
                if (!ok.isConfirmed) return;
                try {
                    const payload = await postJson(urls.aprobar, { id });
                    await Swal.fire('Éxito', payload.mensaje, 'success');
                    window.location.reload();
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            });
        });

        document.querySelectorAll('.btn-despachar').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                try {
                    const id = Number(e.currentTarget.closest('tr').dataset.id || 0);
                    await abrirModalDespacho(id);
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            });
        });
    }

    document.getElementById('btnNuevaVenta').addEventListener('click', async () => {
        limpiarModalVenta();
        await buscarClientes();
        await agregarFilaVenta();
        modalVenta.show();
    });

    document.getElementById('btnBuscarCliente').addEventListener('click', async () => {
        try {
            await buscarClientes();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btnAgregarFilaVenta').addEventListener('click', async () => {
        try {
            await agregarFilaVenta();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btnGuardarVenta').addEventListener('click', async () => {
        try {
            const detalle = [...tbodyVenta.querySelectorAll('tr')].map(filaVentaPayload);
            if (!idCliente.value) throw new Error('Debe seleccionar un cliente.');
            if (!detalle.length || detalle.some((l) => l.id_item <= 0 || l.cantidad <= 0 || l.precio_unitario < 0)) {
                throw new Error('Revise el detalle del pedido.');
            }

            const payload = await postJson(urls.guardar, {
                id: Number(ventaId.value || 0),
                id_cliente: Number(idCliente.value),
                observaciones: ventaObservaciones.value,
                detalle,
            });

            await Swal.fire('Éxito', payload.mensaje, 'success');
            modalVenta.hide();
            window.location.reload();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    despachoAlmacen.addEventListener('change', async () => {
        try {
            await actualizarStockDespacho();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btnGuardarDespacho').addEventListener('click', async () => {
        try {
            const idAlmacen = Number(despachoAlmacen.value || 0);
            if (idAlmacen <= 0) throw new Error('Seleccione un almacén para despachar.');

            const detalle = [...tbodyDespacho.querySelectorAll('tr')].map((fila) => ({
                id_documento_detalle: Number(fila.dataset.idDetalle || 0),
                cantidad: Number(fila.querySelector('.despacho-cantidad').value || 0),
            })).filter((linea) => linea.cantidad > 0);

            if (!detalle.length) throw new Error('Indique cantidades a despachar.');

            const parcial = [...tbodyDespacho.querySelectorAll('tr')].some((fila) => {
                const pendiente = Number(fila.dataset.pendiente || 0);
                const cantidad = Number(fila.querySelector('.despacho-cantidad').value || 0);
                return cantidad > 0 && cantidad < pendiente;
            });

            if (parcial && !cerrarForzado.checked) {
                const resp = await Swal.fire({
                    icon: 'warning',
                    title: 'Despacho parcial',
                    text: 'Hay saldos pendientes. ¿Deseas continuar sin cerrar pedido?',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                });
                if (!resp.isConfirmed) return;
            }

            const payload = await postJson(urls.despachar, {
                id_documento: Number(despachoDocumentoId.value || 0),
                id_almacen: idAlmacen,
                observaciones: despachoObservaciones.value,
                cerrar_forzado: cerrarForzado.checked,
                detalle,
            });

            await Swal.fire('Éxito', payload.mensaje, 'success');
            modalDespacho.hide();
            window.location.reload();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach((el) => {
        el.addEventListener('change', () => {
            window.location.href = `${urls.index}&${filtrosQuery()}`;
        });
    });

    bindTabla();
})();
