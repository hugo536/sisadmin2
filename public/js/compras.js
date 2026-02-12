(() => {
    const app = document.getElementById('comprasApp');
    if (!app) return;

    const urls = {
        index: app.dataset.urlIndex,
        guardar: app.dataset.urlGuardar,
        aprobar: app.dataset.urlAprobar,
        anular: app.dataset.urlAnular,
        recepcionar: app.dataset.urlRecepcionar,
    };

    const modalOrden = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
    const modalRecepcion = new bootstrap.Modal(document.getElementById('modalRecepcionCompra'));
    const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
    const templateFila = document.getElementById('templateFilaDetalle');

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    const ordenId = document.getElementById('ordenId');
    const idProveedor = document.getElementById('idProveedor');
    const fechaEntrega = document.getElementById('fechaEntrega');
    const observaciones = document.getElementById('observaciones');
    const ordenTotal = document.getElementById('ordenTotal');

    const recepcionOrdenId = document.getElementById('recepcionOrdenId');
    const recepcionAlmacen = document.getElementById('recepcionAlmacen');

    function filaToPayload(fila) {
        return {
            id_item: Number(fila.querySelector('.detalle-item').value || 0),
            cantidad: Number(fila.querySelector('.detalle-cantidad').value || 0),
            costo_unitario: Number(fila.querySelector('.detalle-costo').value || 0),
        };
    }

    function recalcularFila(fila) {
        const { cantidad, costo_unitario: costo } = filaToPayload(fila);
        const subtotal = cantidad * costo;
        fila.querySelector('.detalle-subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
        recalcularTotal();
    }

    function recalcularTotal() {
        let total = 0;
        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            const item = filaToPayload(fila);
            total += item.cantidad * item.costo_unitario;
        });
        ordenTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    function agregarFila(item = null) {
        const fragment = templateFila.content.cloneNode(true);
        const fila = fragment.querySelector('tr');

        if (item) {
            fila.querySelector('.detalle-item').value = item.id_item;
            fila.querySelector('.detalle-cantidad').value = Number(item.cantidad || 0).toFixed(2);
            fila.querySelector('.detalle-costo').value = Number(item.costo_unitario || 0).toFixed(2);
        }

        fila.querySelector('.detalle-cantidad').addEventListener('input', () => recalcularFila(fila));
        fila.querySelector('.detalle-costo').addEventListener('input', () => recalcularFila(fila));
        fila.querySelector('.btn-quitar-fila').addEventListener('click', () => {
            fila.remove();
            recalcularTotal();
        });

        tbodyDetalle.appendChild(fragment);
        recalcularFila(tbodyDetalle.lastElementChild);
    }

    function limpiarModal() {
        ordenId.value = 0;
        idProveedor.value = '';
        fechaEntrega.value = '';
        observaciones.value = '';
        tbodyDetalle.innerHTML = '';
        agregarFila();
    }

    async function postJson(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.mensaje || 'Error al procesar la solicitud');
        }
        return payload;
    }

    function filtrosQuery() {
        const params = new URLSearchParams({ accion: 'listar' });
        if (filtroBusqueda.value.trim() !== '') params.set('q', filtroBusqueda.value.trim());
        if (filtroEstado.value !== '') params.set('estado', filtroEstado.value);
        if (filtroFechaDesde.value !== '') params.set('fecha_desde', filtroFechaDesde.value);
        if (filtroFechaHasta.value !== '') params.set('fecha_hasta', filtroFechaHasta.value);
        return params.toString();
    }

    function bindTabla() {
        document.querySelectorAll('.btn-editar').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                const id = Number(e.currentTarget.closest('tr').dataset.id);
                const res = await fetch(`${urls.index}&accion=ver&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const payload = await res.json();
                if (!payload.ok || !payload.data) return;

                const orden = payload.data;
                ordenId.value = orden.id;
                idProveedor.value = orden.id_proveedor;
                fechaEntrega.value = orden.fecha_entrega || '';
                observaciones.value = orden.observaciones || '';
                tbodyDetalle.innerHTML = '';
                (orden.detalle || []).forEach((item) => agregarFila(item));
                if ((orden.detalle || []).length === 0) agregarFila();
                recalcularTotal();
                modalOrden.show();
            });
        });

        document.querySelectorAll('.btn-anular').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                const id = Number(e.currentTarget.closest('tr').dataset.id);
                const ok = await Swal.fire({
                    icon: 'warning',
                    title: '¿Estás seguro de anular esta orden?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, anular',
                    cancelButtonText: 'Cancelar',
                });
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
                const id = Number(e.currentTarget.closest('tr').dataset.id);
                const ok = await Swal.fire({
                    icon: 'question',
                    title: '¿Aprobar orden? Ya no podrá editarse',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, aprobar',
                    cancelButtonText: 'Cancelar',
                });
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

        document.querySelectorAll('.btn-recepcionar').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                recepcionOrdenId.value = Number(e.currentTarget.closest('tr').dataset.id);
                recepcionAlmacen.value = '';
                modalRecepcion.show();
            });
        });
    }

    document.getElementById('btnNuevaOrden').addEventListener('click', () => {
        limpiarModal();
        modalOrden.show();
    });

    document.getElementById('btnAgregarFila').addEventListener('click', () => agregarFila());

    document.getElementById('btnGuardarOrden').addEventListener('click', async () => {
        const detalle = [...tbodyDetalle.querySelectorAll('tr')].map(filaToPayload);
        if (!idProveedor.value) {
            Swal.fire('Validación', 'Debe seleccionar proveedor.', 'warning');
            return;
        }
        if (detalle.some((x) => x.id_item <= 0 || x.cantidad <= 0 || x.costo_unitario < 0)) {
            Swal.fire('Validación', 'Revise el detalle de ítems.', 'warning');
            return;
        }

        try {
            const payload = await postJson(urls.guardar, {
                id: Number(ordenId.value || 0),
                id_proveedor: Number(idProveedor.value),
                fecha_entrega: fechaEntrega.value,
                observaciones: observaciones.value,
                detalle,
            });
            await Swal.fire('Éxito', payload.mensaje, 'success');
            modalOrden.hide();
            window.location.reload();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btnConfirmarRecepcion').addEventListener('click', async () => {
        const idOrden = Number(recepcionOrdenId.value || 0);
        const idAlmacen = Number(recepcionAlmacen.value || 0);
        if (idAlmacen <= 0) {
            Swal.fire('Validación', 'Seleccione un almacén.', 'warning');
            return;
        }

        const proveedorOption = document.querySelector(`tr[data-id="${idOrden}"] td:nth-child(2)`);
        if (proveedorOption && proveedorOption.textContent.toLowerCase().includes('inactivo')) {
            Swal.fire('Validación', 'El proveedor está inactivo, no puede recepcionar.', 'warning');
            return;
        }

        const ok = await Swal.fire({
            icon: 'question',
            title: '¿Confirmar ingreso al stock?',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
        });
        if (!ok.isConfirmed) return;

        try {
            const payload = await postJson(urls.recepcionar, { id_orden: idOrden, id_almacen: idAlmacen });
            await Swal.fire('Éxito', payload.mensaje, 'success');
            modalRecepcion.hide();
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
    if (!tbodyDetalle.children.length) agregarFila();
})();
