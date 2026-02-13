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

    // Referencias a Modales
    const modalVentaEl = document.getElementById('modalVenta');
    const modalVenta = new bootstrap.Modal(modalVentaEl);
    
    const modalDespachoEl = document.getElementById('modalDespacho');
    const modalDespacho = new bootstrap.Modal(modalDespachoEl);

    // Referencias DOM - Venta
    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    const templateFilaVenta = document.getElementById('templateFilaVenta');
    const ventaId = document.getElementById('ventaId');
    const idCliente = document.getElementById('idCliente');
    const buscarCliente = document.getElementById('buscarCliente');
    const fechaEmision = document.getElementById('fechaEmision'); // NUEVO
    const ventaObservaciones = document.getElementById('ventaObservaciones');
    const ventaTotal = document.getElementById('ventaTotal');

    // Referencias DOM - Despacho
    const tbodyDespacho = document.querySelector('#tablaDetalleDespacho tbody');
    const despachoDocumentoId = document.getElementById('despachoDocumentoId');
    const despachoAlmacen = document.getElementById('despachoAlmacen');
    const despachoObservaciones = document.getElementById('despachoObservaciones');
    const cerrarForzado = document.getElementById('cerrarForzado');

    // Filtros
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    // --- Helpers de Red ---
    async function getJson(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
            throw new Error(payload.mensaje || 'Error de comunicación con el servidor');
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
            throw new Error(payload.mensaje || 'Error al procesar la solicitud');
        }
        return payload;
    }

    // --- Lógica de Venta ---

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

    async function buscarClientes(term = '') {
        const payload = await getJson(`${urls.index}&accion=buscar_clientes&q=${encodeURIComponent(term)}`);
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
        
        // Guardar valor actual si existe para no perderlo al refrescar
        const valorActual = select.value;
        
        select.innerHTML = '<option value="">Seleccione...</option>';
        (payload.data || []).forEach((item) => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.dataset.stock = Number(item.stock_actual || 0);
            opt.dataset.precio = Number(item.precio_venta || 0); // Asumiendo que el back devuelve precio sugerido
            opt.textContent = `${item.sku || ''} - ${item.nombre}`;
            select.appendChild(opt);
        });

        if (valorActual) select.value = valorActual;
    }

    async function agregarFilaVenta(item = null) {
        const fragment = templateFilaVenta.content.cloneNode(true);
        const fila = fragment.querySelector('tr');
        tbodyVenta.appendChild(fragment); // Añadir al DOM primero para tener contexto
        const filaReal = tbodyVenta.lastElementChild;

        // Eventos de la fila
        filaReal.querySelector('.detalle-cantidad').addEventListener('input', recalcularTotalVenta);
        filaReal.querySelector('.detalle-precio').addEventListener('input', recalcularTotalVenta);
        
        filaReal.querySelector('.btn-quitar-fila').addEventListener('click', () => {
            filaReal.remove();
            recalcularTotalVenta();
        });

        // Buscador de items dentro de la fila
        const buscador = filaReal.querySelector('.detalle-item-search');
        buscador.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                await cargarItemsFila(filaReal, buscador.value.trim());
            }
        });
        // También cargar al perder el foco si hubo cambios
        buscador.addEventListener('change', () => cargarItemsFila(filaReal, buscador.value.trim()));

        // Selección de item
        const selectItem = filaReal.querySelector('.detalle-item');
        selectItem.addEventListener('change', () => {
            const opt = selectItem.selectedOptions[0];
            const stock = Number(opt?.dataset?.stock || 0);
            const precio = Number(opt?.dataset?.precio || 0);
            
            filaReal.querySelector('.detalle-stock').textContent = stock.toFixed(2);
            
            // Si es nuevo item y precio está en 0, sugerir precio
            const inputPrecio = filaReal.querySelector('.detalle-precio');
            if (Number(inputPrecio.value) === 0) {
                inputPrecio.value = precio.toFixed(2);
            }
            recalcularTotalVenta();
        });

        // Carga inicial de items (vacío o predefinido)
        await cargarItemsFila(filaReal);

        // Si estamos editando y viene data
        if (item) {
            selectItem.value = item.id_item;
            filaReal.querySelector('.detalle-cantidad').value = Number(item.cantidad || 0).toFixed(2);
            filaReal.querySelector('.detalle-precio').value = Number(item.precio_unitario || 0).toFixed(2);
            
            // Forzar evento change para actualizar stock visual
            selectItem.dispatchEvent(new Event('change'));
        }

        recalcularTotalVenta();
    }

    function limpiarModalVenta() {
        ventaId.value = 0;
        buscarCliente.value = '';
        idCliente.innerHTML = '<option value="">Buscar y seleccionar cliente...</option>';
        fechaEmision.value = new Date().toISOString().split('T')[0]; // Fecha de hoy
        ventaObservaciones.value = '';
        tbodyVenta.innerHTML = '';
        ventaTotal.textContent = 'S/ 0.00';
    }

    // --- Lógica de Despacho ---

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

            // Solo mostramos lo que falta por despachar
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

        // Traemos items de este almacén para ver stocks reales
        const payload = await getJson(`${urls.index}&accion=buscar_items&id_almacen=${idAlmacen}`);
        const stockMap = new Map((payload.data || []).map((item) => [Number(item.id), Number(item.stock_actual || 0)]));

        tbodyDespacho.querySelectorAll('tr').forEach((fila) => {
            const idItem = Number(fila.dataset.idItem || 0);
            const pendiente = Number(fila.dataset.pendiente || 0);
            const stock = stockMap.get(idItem) || 0;
            
            // Visualizar Stock
            const celdaStock = fila.querySelector('.despacho-stock');
            celdaStock.textContent = stock.toFixed(2);
            celdaStock.classList.toggle('text-danger', stock < pendiente);
            celdaStock.classList.toggle('text-success', stock >= pendiente);
            celdaStock.classList.remove('text-muted');

            // Sugerir cantidad (Máximo entre Pendiente y Stock)
            const sugerida = Math.max(0, Math.min(pendiente, stock));
            const input = fila.querySelector('.despacho-cantidad');
            input.value = sugerida.toFixed(2);

            // Validaciones visuales
            if (sugerida < pendiente) {
                input.classList.add('border-warning', 'text-warning');
            } else {
                input.classList.remove('border-warning', 'text-warning');
            }
        });
    }

    // --- Listeners Principales ---

    document.getElementById('btnNuevaVenta').addEventListener('click', async () => {
        limpiarModalVenta();
        // Cargar todos los clientes por defecto (opcional, o esperar búsqueda)
        // await buscarClientes(); 
        await agregarFilaVenta(); // Una fila vacía
        modalVenta.show();
    });

    document.getElementById('btnBuscarCliente').addEventListener('click', () => buscarClientes(buscarCliente.value.trim()));
    // Buscar cliente al dar enter
    buscarCliente.addEventListener('keydown', (e) => {
        if(e.key === 'Enter') { e.preventDefault(); buscarClientes(buscarCliente.value.trim()); }
    });

    document.getElementById('btnAgregarFilaVenta').addEventListener('click', () => agregarFilaVenta());

    document.getElementById('btnGuardarVenta').addEventListener('click', async () => {
        try {
            const detalle = [...tbodyVenta.querySelectorAll('tr')].map(filaVentaPayload);
            const idCli = Number(idCliente.value);

            if (!idCli) throw new Error('Debe buscar y seleccionar un cliente.');
            if (detalle.length === 0) throw new Error('Debe haber al menos un ítem.');
            if (detalle.some((l) => l.id_item <= 0 || l.cantidad <= 0 || l.precio_unitario < 0)) {
                throw new Error('Revise el detalle (producto, cantidad > 0, precio >= 0).');
            }

            const payload = await postJson(urls.guardar, {
                id: Number(ventaId.value || 0),
                id_cliente: idCli,
                fecha_emision: fechaEmision.value, // Se envía la fecha seleccionada
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

    // Despacho
    despachoAlmacen.addEventListener('change', actualizarStockDespacho);

    document.getElementById('btnGuardarDespacho').addEventListener('click', async () => {
        try {
            const idAlmacen = Number(despachoAlmacen.value || 0);
            if (idAlmacen <= 0) throw new Error('Seleccione un almacén de salida.');

            const detalle = [...tbodyDespacho.querySelectorAll('tr')].map((fila) => ({
                id_documento_detalle: Number(fila.dataset.idDetalle || 0),
                cantidad: parseFloat(fila.querySelector('.despacho-cantidad').value || 0),
            })).filter((linea) => linea.cantidad > 0); // Solo enviar lo que tenga cantidad > 0

            if (detalle.length === 0) throw new Error('Ingrese cantidades a despachar.');

            // Validar despacho parcial
            const esParcial = [...tbodyDespacho.querySelectorAll('tr')].some((fila) => {
                const pend = Number(fila.dataset.pendiente || 0);
                const cant = Number(fila.querySelector('.despacho-cantidad').value || 0);
                return cant < pend; // Si despacha menos de lo pendiente
            });

            if (esParcial && !cerrarForzado.checked) {
                const resp = await Swal.fire({
                    icon: 'warning',
                    title: 'Despacho Parcial',
                    text: 'Algunos ítems no se despacharán completos. El pedido quedará "Aprobado" (con saldo pendiente). ¿Continuar?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, despachar parcial'
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

    // Filtros y Recarga
    function filtrosQuery() {
        const params = new URLSearchParams({ accion: 'listar' });
        if (filtroBusqueda.value.trim()) params.set('q', filtroBusqueda.value.trim());
        if (filtroEstado.value !== '') params.set('estado', filtroEstado.value);
        if (filtroFechaDesde.value) params.set('fecha_desde', filtroFechaDesde.value);
        if (filtroFechaHasta.value) params.set('fecha_hasta', filtroFechaHasta.value);
        return params.toString();
    }

    function recargarTabla() {
        window.location.href = `${urls.index}&${filtrosQuery()}`;
    }

    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach((el) => {
        el.addEventListener('change', recargarTabla);
    });

    // Delegación de eventos en tabla principal
    document.querySelector('#tablaVentas tbody').addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const tr = btn.closest('tr');
        const id = Number(tr.dataset.id || 0);

        // Editar / Ver
        if (btn.classList.contains('btn-editar')) {
            try {
                const payload = await getJson(`${urls.index}&accion=ver&id=${id}`);
                const venta = payload.data;
                
                limpiarModalVenta();
                
                // Llenar datos
                ventaId.value = venta.id;
                // Pre-cargar el cliente en el select
                idCliente.innerHTML = `<option value="${venta.id_cliente}" selected>Cargando cliente...</option>`;
                // Hacemos una búsqueda dummy para llenar el select correctamente con el nombre
                await buscarClientes(''); // Carga genérica o específica si tuvieras el nombre
                // Ajuste rápido: poner el nombre si viene del backend en 'cliente'
                // Mejor aproximación: Crear la opción manualmente
                /* Nota: Como buscarClientes limpia el select, lo ideal es crear la option manualmente 
                   con los datos que ya tenemos en la tabla o payload. 
                */
                const optCliente = document.createElement('option');
                optCliente.value = venta.id_cliente;
                optCliente.textContent = '(Cliente Seleccionado)'; // O el nombre real si viene en payload
                optCliente.selected = true;
                idCliente.appendChild(optCliente);
                
                fechaEmision.value = venta.fecha_emision || venta.fecha_documento || '';
                ventaObservaciones.value = venta.observaciones || '';

                // Llenar detalle
                if (venta.detalle && venta.detalle.length) {
                    for (const linea of venta.detalle) {
                        await agregarFilaVenta(linea);
                    }
                } else {
                    await agregarFilaVenta();
                }

                // Control de solo lectura si no es borrador (Estado 0)
                const esBorrador = Number(venta.estado) === 0;
                document.getElementById('btnGuardarVenta').style.display = esBorrador ? 'block' : 'none';
                
                modalVenta.show();
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'No se pudo cargar el pedido', 'error');
            }
        }

        // Anular
        if (btn.classList.contains('btn-anular')) {
            const ok = await Swal.fire({ icon: 'warning', title: '¿Anular pedido?', text: 'Esta acción es irreversible.', showCancelButton: true, confirmButtonText: 'Sí, anular', confirmButtonColor: '#d33' });
            if (ok.isConfirmed) {
                try {
                    const res = await postJson(urls.anular, { id });
                    await Swal.fire('Anulado', res.mensaje, 'success');
                    recargarTabla();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
            }
        }

        // Aprobar
        if (btn.classList.contains('btn-aprobar')) {
            const ok = await Swal.fire({ icon: 'question', title: '¿Aprobar pedido?', text: 'El pedido pasará a estado pendiente de despacho.', showCancelButton: true, confirmButtonText: 'Sí, aprobar' });
            if (ok.isConfirmed) {
                try {
                    const res = await postJson(urls.aprobar, { id });
                    await Swal.fire('Aprobado', res.mensaje, 'success');
                    recargarTabla();
                } catch (err) { Swal.fire('Error', err.message, 'error'); }
            }
        }

        // Despachar
        if (btn.classList.contains('btn-despachar')) {
            try {
                await abrirModalDespacho(id);
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }
    });

});