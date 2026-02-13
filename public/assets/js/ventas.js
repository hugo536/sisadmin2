document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('ventasApp');
    if (!app) return;

    const urls = {
        index: app.dataset.urlIndex,
        guardar: app.dataset.urlGuardar,
        aprobar: app.dataset.urlAprobar,
        anular: app.dataset.urlAnular,
        despachar: app.dataset.urlDespachar,
    };

    // --- 1. CLIENTES (Tom Select con AJAX - CORREGIDO) ---
    let tomSelectCliente = null;
    if (document.getElementById('idCliente')) {
        tomSelectCliente = new TomSelect("#idCliente", {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            placeholder: "Buscar cliente por nombre o documento...",
            dropdownParent: 'body',
            load: function(query, callback) {
                if (!query.length) return callback();
                const url = `${urls.index}&accion=buscar_clientes&q=${encodeURIComponent(query)}`;
                
                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(json => {
                    const items = (json.data || []).map(item => ({
                        id: item.id,
                        text: `${item.nombre_completo} (${item.num_doc || 'S/D'})`
                    }));
                    callback(items);
                }).catch(() => callback());
            },
            render: {
                no_results: (data, escape) => '<div class="no-results">No se encontraron coincidencias</div>',
                loading: (data, escape) => '<div class="spinner-border spinner-border-sm text-primary m-2"></div> Buscando...'
            }
        });
    }

    // --- 2. ALMACÉN DESPACHO (Tom Select Simple) ---
    let tomSelectDespacho = null;
    if (document.getElementById('despachoAlmacen')) {
        tomSelectDespacho = new TomSelect("#despachoAlmacen", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "Seleccione almacén...",
            dropdownParent: 'body'
        });
    }

    // --- REFERENCIAS DOM ---
    const modalVentaEl = document.getElementById('modalVenta');
    const modalVenta = new bootstrap.Modal(modalVentaEl);
    const modalDespachoEl = document.getElementById('modalDespacho');
    const modalDespacho = new bootstrap.Modal(modalDespachoEl);

    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    const templateFilaVenta = document.getElementById('templateFilaVenta');
    
    const ventaId = document.getElementById('ventaId');
    const idCliente = document.getElementById('idCliente');
    const fechaEmision = document.getElementById('fechaEmision');
    const ventaObservaciones = document.getElementById('ventaObservaciones');
    const ventaTotal = document.getElementById('ventaTotal');

    const tbodyDespacho = document.querySelector('#tablaDetalleDespacho tbody');
    const despachoDocumentoId = document.getElementById('despachoDocumentoId');
    const despachoAlmacen = document.getElementById('despachoAlmacen');
    const despachoObservaciones = document.getElementById('despachoObservaciones');
    const cerrarForzado = document.getElementById('cerrarForzado');

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    // --- HELPERS DE RED ---
    async function getJson(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await res.json();
        if (!res.ok || !payload.ok) throw new Error(payload.mensaje || 'Error del servidor');
        return payload;
    }

    async function postJson(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
        const payload = await res.json();
        if (!res.ok || !payload.ok) throw new Error(payload.mensaje || 'Error al procesar');
        return payload;
    }

    function filaVentaPayload(fila) {
        return {
            id_item: Number(fila.querySelector('.detalle-item').value || 0),
            cantidad: parseFloat(fila.querySelector('.detalle-cantidad').value || 0),
            precio_unitario: parseFloat(fila.querySelector('.detalle-precio').value || 0),
        };
    }

    function recalcularTotalVenta() {
        let total = 0;
        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            const data = filaVentaPayload(fila);
            const subtotal = data.cantidad * data.precio_unitario;
            total += subtotal;
            fila.querySelector('.detalle-subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
        });
        ventaTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    // --- 3. LÓGICA DE FILAS (PRODUCTOS) CON AJAX ---
    async function agregarFilaVenta(item = null) {
        const fragment = templateFilaVenta.content.cloneNode(true);
        const fila = fragment.querySelector('tr');
        tbodyVenta.appendChild(fragment);
        const filaReal = tbodyVenta.lastElementChild;

        const inputCantidad = filaReal.querySelector('.detalle-cantidad');
        const inputPrecio = filaReal.querySelector('.detalle-precio');
        const selectItem = filaReal.querySelector('.detalle-item');

        inputCantidad.addEventListener('input', recalcularTotalVenta);
        inputPrecio.addEventListener('input', recalcularTotalVenta);
        
        filaReal.querySelector('.btn-quitar-fila').addEventListener('click', () => {
            if (selectItem.tomselect) selectItem.tomselect.destroy();
            filaReal.remove();
            recalcularTotalVenta();
        });

        const tom = new TomSelect(selectItem, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            placeholder: "Buscar producto...",
            dropdownParent: 'body',
            load: function(query, callback) {
                const url = `${urls.index}&accion=buscar_items&q=${encodeURIComponent(query)}`;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.json())
                    .then(json => {
                        const items = (json.data || []).map(prod => ({
                            id: prod.id,
                            text: `${prod.sku || ''} - ${prod.nombre}`,
                            stock: parseFloat(prod.stock_actual || 0),
                            precio: parseFloat(prod.precio_venta || 0)
                        }));
                        callback(items);
                    }).catch(() => callback());
            },
            onChange: function(value) {
                const selectedOption = this.options[value];
                if (selectedOption) {
                    filaReal.querySelector('.detalle-stock').textContent = selectedOption.stock.toFixed(2);
                    if (Number(inputPrecio.value) === 0) {
                        inputPrecio.value = selectedOption.precio.toFixed(2);
                    }
                }
                recalcularTotalVenta();
            },
            render: {
                no_results: (data, escape) => '<div class="no-results">No se encontró producto terminado con ese nombre</div>',
                loading: (data, escape) => '<div class="spinner-border spinner-border-sm text-primary m-2"></div> buscando...',
                option: function(data, escape) {
                    // Lógica para color de stock
                    const stockColor = data.stock <= 0 ? 'text-danger fw-bold' : 'text-success';
                    const stockLabel = data.stock <= 0 ? 'SIN STOCK' : data.stock;

                    return `<div class="py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark">${escape(data.text)}</div>
                            <div class="small text-muted">${escape(data.sku || 'Sin SKU')}</div>
                        </div>
                        <div class="text-end">
                            <div class="small ${stockColor}">Stock: ${stockLabel}</div>
                            <div class="fw-bold text-primary">S/ ${escape(Number(data.precio).toFixed(2))}</div>
                        </div>
                    </div>`;
                }
            }
        });

        if (item) {
            tom.addOption({
                id: item.id_item,
                text: `${item.sku || ''} - ${item.item_nombre}`,
                stock: 0,
                precio: Number(item.precio_unitario)
            });
            tom.setValue(item.id_item);
            inputCantidad.value = Number(item.cantidad || 0).toFixed(2);
            inputPrecio.value = Number(item.precio_unitario || 0).toFixed(2);
        }
        recalcularTotalVenta();
    }

    function limpiarModalVenta() {
        ventaId.value = 0;
        if (tomSelectCliente) {
            tomSelectCliente.clear();
            tomSelectCliente.clearOptions();
        }
        fechaEmision.value = new Date().toISOString().split('T')[0];
        ventaObservaciones.value = '';
        tbodyVenta.innerHTML = '';
        ventaTotal.textContent = 'S/ 0.00';
    }

    // --- LÓGICA DESPACHO ---
    async function abrirModalDespacho(idDocumento) {
        const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
        const venta = payload.data;

        despachoDocumentoId.value = venta.id;
        if (tomSelectDespacho) tomSelectDespacho.clear();
        else despachoAlmacen.value = '';
        
        despachoObservaciones.value = '';
        cerrarForzado.checked = false;
        tbodyDespacho.innerHTML = '';

        (venta.detalle || []).forEach((linea) => {
            const solicitado = Number(linea.cantidad || 0);
            const despachado = Number(linea.cantidad_despachada || 0);
            const pendiente = Number(linea.cantidad_pendiente || 0);
            if (pendiente <= 0.0001) return;

            const tr = document.createElement('tr');
            tr.dataset.idDetalle = linea.id;
            tr.dataset.idItem = linea.id_item;
            tr.dataset.pendiente = pendiente.toString();
            
            tr.innerHTML = `
                <td>${linea.sku || ''} - ${linea.item_nombre || ''}</td>
                <td class="text-center bg-light border-start">${solicitado.toFixed(2)}</td>
                <td class="text-center bg-light">${despachado.toFixed(2)}</td>
                <td class="text-center bg-warning bg-opacity-10 fw-bold border-end text-dark">${pendiente.toFixed(2)}</td>
                <td class="text-center fw-bold despacho-stock text-muted">-</td>
                <td class="pe-3"><input type="number" class="form-control form-control-sm text-end despacho-cantidad" min="0" step="0.01" value="0"></td>
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
            
            const celdaStock = fila.querySelector('.despacho-stock');
            celdaStock.textContent = stock.toFixed(2);
            celdaStock.classList.toggle('text-danger', stock < pendiente);
            celdaStock.classList.toggle('text-success', stock >= pendiente);
            celdaStock.classList.remove('text-muted');

            const sugerida = Math.max(0, Math.min(pendiente, stock));
            fila.querySelector('.despacho-cantidad').value = sugerida.toFixed(2);
        });
    }

    // --- EVENT LISTENERS ---
    document.getElementById('btnNuevaVenta').addEventListener('click', async () => {
        limpiarModalVenta();
        await agregarFilaVenta();
        document.getElementById('btnGuardarVenta').style.display = 'block';
        modalVenta.show();
    });

    document.getElementById('btnAgregarFilaVenta').addEventListener('click', () => agregarFilaVenta());

    document.getElementById('btnGuardarVenta').addEventListener('click', async () => {
        try {
            const detalle = [...tbodyVenta.querySelectorAll('tr')].map(filaVentaPayload);
            const idCli = Number(idCliente.value);

            if (!idCli) throw new Error('Debe buscar y seleccionar un cliente.');
            if (!detalle.length) throw new Error('Debe haber al menos un ítem.');
            if (detalle.some((l) => l.id_item <= 0 || l.cantidad <= 0 || l.precio_unitario < 0)) {
                throw new Error('Revise el detalle (producto, cantidad > 0, precio >= 0).');
            }

            const payload = await postJson(urls.guardar, {
                id: Number(ventaId.value || 0),
                id_cliente: idCli,
                fecha_emision: fechaEmision.value,
                observaciones: ventaObservaciones.value,
                detalle,
            });

            await Swal.fire('Guardado', payload.mensaje, 'success');
            modalVenta.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    despachoAlmacen.addEventListener('change', actualizarStockDespacho);

    document.getElementById('btnGuardarDespacho').addEventListener('click', async () => {
        try {
            const idAlmacen = Number(despachoAlmacen.value || 0);
            if (idAlmacen <= 0) throw new Error('Seleccione un almacén de salida.');

            const detalle = [...tbodyDespacho.querySelectorAll('tr')].map((fila) => ({
                id_documento_detalle: Number(fila.dataset.idDetalle || 0),
                cantidad: parseFloat(fila.querySelector('.despacho-cantidad').value || 0),
            })).filter((linea) => linea.cantidad > 0);

            if (!detalle.length) throw new Error('Ingrese cantidades a despachar.');

            const esParcial = [...tbodyDespacho.querySelectorAll('tr')].some((fila) => {
                const pend = Number(fila.dataset.pendiente || 0);
                const cant = Number(fila.querySelector('.despacho-cantidad').value || 0);
                return cant < pend;
            });

            if (esParcial && !cerrarForzado.checked) {
                const resp = await Swal.fire({
                    icon: 'warning', title: 'Despacho Parcial', text: '¿Continuar sin cerrar pedido?', 
                    showCancelButton: true, confirmButtonText: 'Sí'
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

            await Swal.fire('Despachado', payload.mensaje, 'success');
            modalDespacho.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    function recargarTabla() {
        const params = new URLSearchParams({ accion: 'listar' });
        if (filtroBusqueda.value.trim()) params.set('q', filtroBusqueda.value.trim());
        if (filtroEstado.value !== '') params.set('estado', filtroEstado.value);
        if (filtroFechaDesde.value) params.set('fecha_desde', filtroFechaDesde.value);
        if (filtroFechaHasta.value) params.set('fecha_hasta', filtroFechaHasta.value);
        window.location.href = `${urls.index}&${params.toString()}`;
    }

    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach(el => {
        el.addEventListener('change', recargarTabla);
    });
    
    document.querySelector('#tablaVentas tbody').addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const tr = btn.closest('tr');
        const id = Number(tr.dataset.id || 0);

        if (btn.classList.contains('btn-editar')) {
            try {
                const payload = await getJson(`${urls.index}&accion=ver&id=${id}`);
                const venta = payload.data;
                limpiarModalVenta();
                ventaId.value = venta.id;
                
                const nombreCliente = tr.querySelector('td:nth-child(2)').textContent;
                if (tomSelectCliente) {
                    tomSelectCliente.addOption({ id: venta.id_cliente, text: nombreCliente });
                    tomSelectCliente.setValue(venta.id_cliente);
                } else {
                    idCliente.innerHTML = `<option value="${venta.id_cliente}">${nombreCliente}</option>`;
                    idCliente.value = venta.id_cliente;
                }
                
                fechaEmision.value = venta.fecha_emision || '';
                ventaObservaciones.value = venta.observaciones || '';

                if (venta.detalle && venta.detalle.length) {
                    for (const linea of venta.detalle) await agregarFilaVenta(linea);
                } else {
                    await agregarFilaVenta();
                }

                const esBorrador = Number(venta.estado) === 0;
                document.getElementById('btnGuardarVenta').style.display = esBorrador ? 'block' : 'none';
                modalVenta.show();
            } catch (err) { Swal.fire('Error', 'No se pudo cargar', 'error'); }
        }

        if (btn.classList.contains('btn-anular')) {
            const ok = await Swal.fire({ icon: 'warning', title: '¿Anular pedido?', showCancelButton: true, confirmButtonText: 'Sí, anular', confirmButtonColor: '#d33' });
            if (ok.isConfirmed) {
                try {
                    const res = await postJson(urls.anular, { id });
                    await Swal.fire('Anulado', res.mensaje, 'success');
                    recargarTabla();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
            }
        }

        if (btn.classList.contains('btn-aprobar')) {
            const ok = await Swal.fire({ icon: 'question', title: '¿Aprobar pedido?', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
            if (ok.isConfirmed) {
                try {
                    const res = await postJson(urls.aprobar, { id });
                    await Swal.fire('Aprobado', res.mensaje, 'success');
                    recargarTabla();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
            }
        }

        if (btn.classList.contains('btn-despachar')) {
            try { await abrirModalDespacho(id); } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }
    });
});