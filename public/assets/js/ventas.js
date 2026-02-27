document.addEventListener('DOMContentLoaded', () => {
    // --- EVENTO NUEVA VENTA ---
    const btnNuevaVenta = document.getElementById('btnNuevaVenta');
    if (btnNuevaVenta) {
        btnNuevaVenta.addEventListener('click', async () => {
            limpiarModalVenta();
            await agregarFilaVenta(); // Agrega una fila vacía inicial
            document.getElementById('btnGuardarVenta').style.display = 'block';
            modalVenta.show();
        });
    }

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
                
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
    // Nota: despachoAlmacen ya no se usa como global, pero lo usamos para sacar las opciones
    const despachoAlmacenGlobal = document.getElementById('despachoAlmacen'); 
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

    // --- LÓGICA VENTA (CALCULOS) ---
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

    function obtenerItemsSeleccionados(excluirFila = null) {
        const seleccionados = new Set();
        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            if (fila === excluirFila) return;
            const idItem = Number(fila.querySelector('.detalle-item')?.value || 0);
            if (idItem > 0) seleccionados.add(idItem);
        });
        return seleccionados;
    }

    function validarCantidadVsStock(fila) {
        const inputCantidad = fila.querySelector('.detalle-cantidad');
        const stock = Number(fila.querySelector('.detalle-stock').textContent || 0);
        const cantidad = Number(inputCantidad.value || 0);

        if (cantidad > stock) {
            inputCantidad.classList.add('is-invalid');
            inputCantidad.title = `Stock disponible: ${stock.toFixed(2)}`;
            return false;
        }

        inputCantidad.classList.remove('is-invalid');
        inputCantidad.title = '';
        return true;
    }

    // --- 3. LÓGICA DE FILAS VENTA (PRODUCTOS) ---
    async function agregarFilaVenta(item = null) {
        const fragment = templateFilaVenta.content.cloneNode(true);
        const fila = fragment.querySelector('tr');
        tbodyVenta.appendChild(fragment);
        const filaReal = tbodyVenta.lastElementChild;

        const inputCantidad = filaReal.querySelector('.detalle-cantidad');
        const inputPrecio = filaReal.querySelector('.detalle-precio');
        const selectItem = filaReal.querySelector('.detalle-item');

        inputCantidad.addEventListener('input', () => {
            validarCantidadVsStock(filaReal);
            recalcularTotalVenta();
        });
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
                    const idSeleccionado = Number(value || 0);
                    const repetido = idSeleccionado > 0 && obtenerItemsSeleccionados(filaReal).has(idSeleccionado);
                    if (repetido) {
                        this.clear(true);
                        filaReal.querySelector('.detalle-stock').textContent = '0.00';
                        Swal.fire('Producto repetido', 'No se permiten productos repetidos en el pedido.', 'warning');
                        recalcularTotalVenta();
                        return;
                    }

                    filaReal.querySelector('.detalle-stock').textContent = selectedOption.stock.toFixed(2);
                    if (Number(inputPrecio.value) === 0) {
                        inputPrecio.value = selectedOption.precio.toFixed(2);
                    }
                }
                validarCantidadVsStock(filaReal);
                recalcularTotalVenta();
            },
            render: {
                no_results: (data, escape) => '<div class="no-results">No se encontraron productos disponibles</div>',
                loading: (data, escape) => '<div class="spinner-border spinner-border-sm text-primary m-2"></div> buscando...',
                option: function(data, escape) {
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


    document.getElementById('btnAgregarFilaVenta')?.addEventListener('click', async () => {
        await agregarFilaVenta();
    });

    document.getElementById('btnGuardarVenta')?.addEventListener('click', async () => {
        try {
            const detalle = [...tbodyVenta.querySelectorAll('tr')].map((fila) => filaVentaPayload(fila));
            if (!detalle.length) throw new Error('Debe agregar al menos un producto.');

            const ids = new Set();
            for (const fila of tbodyVenta.querySelectorAll('tr')) {
                const data = filaVentaPayload(fila);
                if (data.id_item <= 0) throw new Error('Seleccione un producto en todas las filas.');
                if (ids.has(data.id_item)) throw new Error('No se permiten productos repetidos en el pedido.');
                ids.add(data.id_item);
                if (!validarCantidadVsStock(fila)) throw new Error('No puede ingresar una cantidad mayor al stock.');
            }

            const payload = await postJson(urls.guardar, {
                id: Number(ventaId.value || 0),
                id_cliente: Number(tomSelectCliente ? tomSelectCliente.getValue() : idCliente.value || 0),
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

    // ==========================================
    // --- 4. LÓGICA DESPACHO MULTI-ALMACÉN ---
    // ==========================================
    
    // Obtener las opciones de almacenes del select oculto para reusarlas en las filas
    function obtenerOpcionesAlmacenHTML() {
        return despachoAlmacenGlobal ? despachoAlmacenGlobal.innerHTML : '<option value="">Sin almacenes</option>';
    }

    async function abrirModalDespacho(idDocumento) {
        const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
        const venta = payload.data;
        const opcionesAlmacen = obtenerOpcionesAlmacenHTML();

        despachoDocumentoId.value = venta.id;
        despachoObservaciones.value = '';
        cerrarForzado.checked = false;
        tbodyDespacho.innerHTML = '';

        // Generar filas iniciales
        (venta.detalle || []).forEach((linea) => {
            // Solo mostrar lo que tiene pendiente
            if (Number(linea.cantidad_pendiente) > 0.0001) {
                agregarFilaDespacho(linea, opcionesAlmacen);
            }
        });
        modalDespacho.show();
    }

    // Función para agregar filas al despacho (Soporta inserción adyacente)
    function agregarFilaDespacho(linea, opcionesAlmacen, filaReferencia = null) {
        const tr = document.createElement('tr');
        tr.dataset.idDetalle = linea.id;
        tr.dataset.idItem = linea.id_item;
        tr.dataset.sku = linea.sku || ''; 
        tr.dataset.pendienteTotal = linea.cantidad_pendiente; 

        tr.innerHTML = `
            <td>
                <div class="fw-bold text-dark">${linea.sku || ''} - ${linea.item_nombre || ''}</div>
                <div class="small text-muted">Pendiente Global: <span class="badge bg-warning text-dark badge-pendiente">${Number(linea.cantidad_pendiente)}</span></div>
            </td>
            <td>
                <select class="form-select form-select-sm fila-almacen">
                    ${opcionesAlmacen}
                </select>
            </td>
            <td class="text-center fw-bold despacho-stock text-muted">-</td>
            <td class="d-flex align-items-center">
                <input type="number" class="form-control form-control-sm text-end despacho-cantidad me-2" 
                       min="0" step="1" value="0" title="Solo números enteros (paquetes)">
                
                <button type="button" class="btn btn-sm btn-outline-primary btn-split me-1" title="Añadir otro almacén">
                    <i class="bi bi-plus-lg"></i>
                </button>
                
                <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-despacho d-none" title="Quitar fila">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        if (filaReferencia) {
            filaReferencia.insertAdjacentElement('afterend', tr);
            tr.querySelector('.btn-quitar-despacho').classList.remove('d-none');
        } else {
            tbodyDespacho.appendChild(tr);
        }

        const selectAlmacen = tr.querySelector('.fila-almacen');
        const inputCant = tr.querySelector('.despacho-cantidad');
        const spanStock = tr.querySelector('.despacho-stock');
        const btnSplit = tr.querySelector('.btn-split');
        const btnQuitar = tr.querySelector('.btn-quitar-despacho');

        // --- 1. LÓGICA DE ALMACÉN Y STOCK ---
        selectAlmacen.addEventListener('change', async () => {
            const idAlmacen = selectAlmacen.value;
            if (!idAlmacen) return;

            // Evitar duplicados de Almacén para el mismo ítem
            const yaExiste = [...tbodyDespacho.querySelectorAll(`tr[data-id-detalle="${linea.id}"]`)]
                .some(f => f !== tr && f.querySelector('.fila-almacen').value === idAlmacen);

            if (yaExiste) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Almacén ya seleccionado', showConfirmButton: false, timer: 2000 });
                selectAlmacen.value = '';
                return;
            }

            spanStock.innerHTML = '<div class="spinner-border spinner-border-sm text-secondary"></div>';
            
            try {
                const res = await getJson(`${urls.index}&accion=buscar_items&q=${encodeURIComponent(linea.sku)}&id_almacen=${idAlmacen}`);
                const itemData = (res.data || []).find(i => i.id == linea.id_item);
                const stock = itemData ? Math.floor(parseFloat(itemData.stock_actual)) : 0; // Forzamos entero en stock

                spanStock.textContent = stock;
                spanStock.className = `text-center fw-bold despacho-stock ${stock <= 0 ? 'text-danger' : 'text-success'}`;

                // --- LÓGICA DE AUTO-COMPLETADO (CASCADA) ---
                let despachadoEnOtros = 0;
                tbodyDespacho.querySelectorAll(`tr[data-id-detalle="${linea.id}"]`).forEach(f => {
                    if (f !== tr) despachadoEnOtros += parseInt(f.querySelector('.despacho-cantidad').value || 0);
                });

                const faltaPorAsignar = parseInt(linea.cantidad_pendiente) - despachadoEnOtros;
                
                // El sistema sugiere lo que falta, pero NUNCA más de lo que hay en stock
                const sugerido = Math.max(0, Math.min(faltaPorAsignar, stock));
                inputCant.value = sugerido;
                
                validarGrupoItem(linea.id); // Validar todo el grupo de este producto

            } catch (e) { spanStock.textContent = '0'; }
        });

        // --- 2. LÓGICA DE VALIDACIÓN GRUPAL E ENTEROS ---
        inputCant.addEventListener('input', () => {
            // A. Forzar Enteros al escribir
            if (inputCant.value.includes('.')) {
                inputCant.value = Math.floor(parseFloat(inputCant.value));
            }
            validarGrupoItem(linea.id);
        });

        btnSplit.addEventListener('click', () => agregarFilaDespacho(linea, opcionesAlmacen, tr));
        btnQuitar.addEventListener('click', () => {
            const idDetalle = tr.dataset.idDetalle;
            tr.remove();
            validarGrupoItem(idDetalle);
        });
    }

    // Función auxiliar para validar todas las filas de un mismo producto
    function validarGrupoItem(idDetalle) {
        const filas = [...tbodyDespacho.querySelectorAll(`tr[data-id-detalle="${idDetalle}"]`)];
        if (filas.length === 0) return;

        const pendienteGlobal = parseInt(filas[0].dataset.pendienteTotal);
        let sumaTotalCargada = 0;

        filas.forEach(f => {
            const input = f.querySelector('.despacho-cantidad');
            const cant = parseInt(input.value || 0);
            const stock = parseInt(f.querySelector('.despacho-stock').textContent || 0);
            
            sumaTotalCargada += cant;

            // Validación individual de fila: ¿Excede el stock de SU almacén?
            if (cant > stock) {
                input.classList.add('is-invalid');
                input.title = `Solo hay ${stock} en este almacén`;
            } else {
                input.classList.remove('is-invalid');
                input.title = "";
            }
        });

        // Validación grupal: ¿La suma excede el pedido?
        const badge = filas[0].querySelector('.badge-pendiente');
        if (sumaTotalCargada > pendienteGlobal) {
            filas.forEach(f => f.querySelector('.despacho-cantidad').classList.add('is-invalid'));
            badge.className = "badge bg-danger text-white badge-pendiente";
            badge.textContent = `${pendienteGlobal} (Excedido en ${sumaTotalCargada - pendienteGlobal})`;
        } else if (sumaTotalCargada === pendienteGlobal) {
            badge.className = "badge bg-success text-white badge-pendiente";
            badge.textContent = `COMPLETO`;
        } else {
            badge.className = "badge bg-warning text-dark badge-pendiente";
            badge.textContent = `${pendienteGlobal} (Faltan ${pendienteGlobal - sumaTotalCargada})`;
        }
    }

    // --- GUARDAR DESPACHO MULTI-FILA ---
    document.getElementById('btnGuardarDespacho').addEventListener('click', async () => {
        try {
            // Recolectamos todas las filas
            const filas = [...tbodyDespacho.querySelectorAll('tr')];
            
            // Construimos el detalle agrupando
            const detalle = filas.map(fila => {
                const idAlmacen = fila.querySelector('.fila-almacen').value;
                const cantidad = parseFloat(fila.querySelector('.despacho-cantidad').value || 0);
                
                return {
                    id_documento_detalle: Number(fila.dataset.idDetalle),
                    id_almacen: Number(idAlmacen),
                    cantidad: cantidad
                };
            }).filter(d => d.cantidad > 0); // Solo enviamos lo que tenga cantidad > 0

            // Validaciones
            if (detalle.length === 0) throw new Error('Ingrese cantidades a despachar.');
            if (detalle.some(d => !d.id_almacen)) throw new Error('Seleccione almacén para todas las filas con cantidad.');
            if (tbodyDespacho.querySelector('.is-invalid')) throw new Error('Corrija las cantidades marcadas en rojo (exceden stock o pendiente).');

            // Chequeo de Cierre Forzado
            // Calculamos cuánto se está despachando en total por ítem vs lo pendiente
            const resumenPorItem = {}; // Mapa id_detalle -> cantidad_total_despachando
            filas.forEach(f => {
                const id = f.dataset.idDetalle;
                const cant = parseFloat(f.querySelector('.despacho-cantidad').value || 0);
                resumenPorItem[id] = (resumenPorItem[id] || 0) + cant;
            });

            // Ver si hay algo parcial
            let esParcial = false;
            filas.forEach(f => {
                const id = f.dataset.idDetalle;
                const pendiente = parseFloat(f.dataset.pendienteTotal);
                const despachando = resumenPorItem[id] || 0;
                if (despachando < pendiente - 0.01) esParcial = true;
            });

            if (esParcial && !cerrarForzado.checked) {
                const resp = await Swal.fire({
                    icon: 'warning', title: 'Despacho Parcial', 
                    text: 'No se está cubriendo todo el pendiente. ¿Continuar sin cerrar pedido?', 
                    showCancelButton: true, confirmButtonText: 'Sí, despachar parcial'
                });
                if (!resp.isConfirmed) return;
            }

            // Enviar (Nota: El backend debe estar listo para recibir un array que puede tener varias veces el mismo id_documento_detalle pero con diferente almacén)
            const payload = await postJson(urls.despachar, {
                id_documento: Number(despachoDocumentoId.value || 0),
                observaciones: despachoObservaciones.value,
                cerrar_forzado: cerrarForzado.checked,
                detalle: detalle // Enviamos el detalle multi-almacén
            });

            await Swal.fire('Despachado', payload.mensaje, 'success');
            modalDespacho.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // --- EVENTOS GENERALES ---
    
    // Función para recargar tabla principal
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