document.addEventListener('DOMContentLoaded', async () => {
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

    async function esperarTomSelect(maxIntentos = 20, esperaMs = 150) {
        for (let i = 0; i < maxIntentos; i++) {
            if (typeof TomSelect !== 'undefined') return true;
            await new Promise((resolve) => setTimeout(resolve, esperaMs));
        }
        return false;
    }

    // --- 1. CLIENTES (Tom Select con AJAX - robusto ante carga tardía CDN/fallback) ---
    let tomSelectCliente = null;
    const idClienteEl = document.getElementById('idCliente');
    const tomSelectListo = await esperarTomSelect();

    if (!tomSelectListo) {
        console.warn('TomSelect no se pudo cargar en Ventas. Revise conectividad o CDN.');
    }

    if (idClienteEl && tomSelectListo) {
        tomSelectCliente = new TomSelect("#idCliente", {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            allowEmptyOption: true,
            plugins: ['clear_button'],
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
    const despachoObservaciones = document.getElementById('despachoObservaciones');
    const cerrarForzado = document.getElementById('cerrarForzado');

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    const estadoBusquedaItems = { tieneAcuerdo: false, listaVacia: false };

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

    async function obtenerPrecioItem(idItem, cantidad) {
        const idClienteActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idCliente.value || 0);
        if (!idItem || !idClienteActual) return null;

        const url = `${urls.index}&accion=precio_item&id_cliente=${idClienteActual}&id_item=${idItem}&cantidad=${encodeURIComponent(cantidad || 1)}`;
        const json = await getJson(url);
        return Number(json.data?.precio || 0);
    }

    async function refrescarPrecioFila(fila) {
        const idItem = Number(fila.querySelector('.detalle-item').value || 0);
        if (!idItem) return;
        const cantidad = Number(fila.querySelector('.detalle-cantidad').value || 0);
        const precio = await obtenerPrecioItem(idItem, cantidad > 0 ? cantidad : 1);
        if (precio === null) return;
        fila.querySelector('.detalle-precio').value = precio.toFixed(2);
        recalcularTotalVenta();
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

        inputCantidad.addEventListener('input', async () => {
            validarCantidadVsStock(filaReal);
            await refrescarPrecioFila(filaReal);
            recalcularTotalVenta();
        });
        inputPrecio.addEventListener('input', recalcularTotalVenta);
        
        filaReal.querySelector('.btn-quitar-fila').addEventListener('click', () => {
            if (selectItem.tomselect) selectItem.tomselect.destroy();
            filaReal.remove();
            recalcularTotalVenta();
        });

        if (!tomSelectListo) {
            selectItem.innerHTML = '<option value="">Tom Select no disponible</option>';
            selectItem.disabled = true;
            return;
        }

        const tom = new TomSelect(selectItem, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            placeholder: "Buscar producto...",
            dropdownParent: 'body',
            load: function(query, callback) {
                const idClienteActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idCliente.value || 0);
                const cantidadActual = Number(inputCantidad.value || 1) || 1;
                const url = `${urls.index}&accion=buscar_items&q=${encodeURIComponent(query)}&id_cliente=${idClienteActual}&cantidad=${encodeURIComponent(cantidadActual)}`;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.json())
                    .then(json => {
                        estadoBusquedaItems.tieneAcuerdo = !!json.meta?.tiene_acuerdo;
                        estadoBusquedaItems.listaVacia = !!json.meta?.lista_vacia;

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
                    inputPrecio.value = selectedOption.precio.toFixed(2);
                }
                validarCantidadVsStock(filaReal);
                recalcularTotalVenta();
            },
            render: {
                no_results: () => {
                    if (estadoBusquedaItems.tieneAcuerdo && estadoBusquedaItems.listaVacia) {
                        return '<div class="no-results">Lista de productos vacía para este cliente/distribuidor</div>';
                    }
                    return '<div class="no-results">No se encontraron productos disponibles</div>';
                },
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

    idCliente?.addEventListener('change', () => {
        tbodyVenta.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) {
                select.tomselect.clear(true);
                select.tomselect.clearOptions();
            }
        });
    });

    if (tomSelectCliente) {
        tomSelectCliente.on('change', () => {
            tbodyVenta.querySelectorAll('.detalle-item').forEach((select) => {
                if (select.tomselect) {
                    select.tomselect.clear(true);
                    select.tomselect.clearOptions();
                }
            });
        });
    }

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
    
    async function abrirModalDespacho(idDocumento) {
        const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
        const venta = payload.data;

        despachoDocumentoId.value = venta.id;
        despachoObservaciones.value = '';
        cerrarForzado.checked = false;
        tbodyDespacho.innerHTML = '';

        // Generar filas iniciales
        (venta.detalle || []).forEach((linea) => {
            // Solo mostrar lo que tiene pendiente
            if (Number(linea.cantidad_pendiente) > 0.0001) {
                agregarFilaDespacho(linea, null);
            }
        });
        modalDespacho.show();
    }

    // Función para agregar filas al despacho (Soporta inserción adyacente)
    function agregarFilaDespacho(linea, filaReferencia = null) {
        let opcionesHTML = '<option value="">Seleccione...</option>';
        let disabledState = '';
        const almacenesDisp = linea.almacenes_disponibles || [];

        if (almacenesDisp.length === 0) {
            opcionesHTML = '<option value="">Sin stock en ningún almacén</option>';
            disabledState = 'disabled';
        } else {
            almacenesDisp.forEach(alm => {
                opcionesHTML += `<option value="${alm.id}">${alm.nombre} (Dispo: ${parseFloat(alm.stock_actual)})</option>`;
            });
        }

        const tr = document.createElement('tr');
        tr.dataset.idDetalle = linea.id;
        tr.dataset.idItem = linea.id_item;
        tr.dataset.sku = linea.sku || '';
        tr.dataset.pendienteTotal = linea.cantidad_pendiente;

        if (almacenesDisp.length === 0) {
            tr.classList.add('table-danger', 'opacity-75');
        }

        tr.innerHTML = `
            <td class="align-middle py-3">
                <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">${linea.sku || ''} - ${linea.item_nombre || ''}</div>
                <div class="small text-muted d-flex align-items-center gap-2">
                    <span>Pendiente Global:</span> 
                    <span class="badge bg-warning text-dark badge-pendiente rounded-pill px-2 py-1 shadow-sm">${Number(linea.cantidad_pendiente)}</span>
                </div>
                <button type="button" class="btn btn-link btn-sm px-0 mt-2 text-decoration-none fw-semibold btn-split" title="Fraccionar en otro almacén" ${disabledState}>
                    <i class="bi bi-diagram-2 me-1"></i>Agregar otro almacén
                </button>
            </td>
            <td class="align-middle">
                <select class="form-select form-select-sm fila-almacen shadow-none border-secondary-subtle fw-semibold text-secondary" ${disabledState}>
                    ${opcionesHTML}
                </select>
            </td>
            <td class="text-center align-middle">
                <span class="fw-bold despacho-stock text-secondary fs-6">-</span>
            </td>
            <td class="align-middle px-2">
                <input type="number" class="form-control form-control-sm text-center despacho-cantidad fw-bold text-primary shadow-none border-secondary-subtle mx-auto"
                       min="0" step="1" value="0" title="Solo números enteros" ${disabledState} style="max-width: 90px;">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-despacho d-none d-inline-flex align-items-center justify-content-center transition-all hover-lift p-0" title="Quitar almacén" style="width: 34px; height: 34px;">
                    <i class="bi bi-trash-fill fs-6"></i>
                </button>
            </td>
        `;

        if (filaReferencia) {
            filaReferencia.insertAdjacentElement('afterend', tr);
        } else {
            tbodyDespacho.appendChild(tr);
        }

        const selectAlmacen = tr.querySelector('.fila-almacen');
        const inputCant = tr.querySelector('.despacho-cantidad');
        const spanStock = tr.querySelector('.despacho-stock');
        const btnSplit = tr.querySelector('.btn-split');
        const btnQuitar = tr.querySelector('.btn-quitar-despacho');

        const obtenerFilasGrupo = () => [...tbodyDespacho.querySelectorAll(`tr[data-id-detalle="${linea.id}"]`)];

        const actualizarModoGrupo = () => {
            const filas = obtenerFilasGrupo();
            const multiple = filas.length > 1;
            filas.forEach((fila, idx) => {
                const input = fila.querySelector('.despacho-cantidad');
                const btn = fila.querySelector('.btn-quitar-despacho');
                input.readOnly = !multiple;
                input.classList.toggle('bg-light', !multiple);
                if (btn) btn.classList.toggle('d-none', !multiple || idx === 0);
            });
        };

        const sincronizarGrupo = (filaOrigen = null) => {
            const filas = obtenerFilasGrupo();
            if (filas.length !== 2) return;

            const [filaA, filaB] = filas;
            const inputA = filaA.querySelector('.despacho-cantidad');
            const inputB = filaB.querySelector('.despacho-cantidad');
            const stockA = parseInt(filaA.querySelector('.despacho-stock').textContent || 0, 10) || 0;
            const stockB = parseInt(filaB.querySelector('.despacho-stock').textContent || 0, 10) || 0;
            const pendiente = parseInt(linea.cantidad_pendiente || 0, 10) || 0;

            const origenEsA = !filaOrigen || filaOrigen === filaA;
            if (origenEsA) {
                const valorA = Math.max(0, Math.min(pendiente, parseInt(inputA.value || 0, 10) || 0));
                inputA.value = valorA;
                inputB.value = Math.max(0, pendiente - valorA);
                if (stockB > 0 && Number(inputB.value) > stockB) {
                    inputB.value = stockB;
                    inputA.value = Math.max(0, pendiente - stockB);
                }
            } else {
                const valorB = Math.max(0, Math.min(pendiente, parseInt(inputB.value || 0, 10) || 0));
                inputB.value = valorB;
                inputA.value = Math.max(0, pendiente - valorB);
                if (stockA > 0 && Number(inputA.value) > stockA) {
                    inputA.value = stockA;
                    inputB.value = Math.max(0, pendiente - stockA);
                }
            }
        };

        selectAlmacen.addEventListener('change', async () => {
            const idAlmacen = selectAlmacen.value;
            if (!idAlmacen) {
                spanStock.textContent = '-';
                inputCant.value = 0;
                validarGrupoItem(linea.id);
                return;
            }

            const yaExiste = obtenerFilasGrupo().some(f => f !== tr && f.querySelector('.fila-almacen').value === idAlmacen);
            if (yaExiste) {
                Swal.fire('Almacén duplicado', 'No puede seleccionar el mismo almacén para este producto.', 'warning');
                selectAlmacen.value = '';
                spanStock.textContent = '-';
                inputCant.value = 0;
                validarGrupoItem(linea.id);
                return;
            }

            spanStock.innerHTML = '<div class="spinner-border spinner-border-sm text-secondary"></div>';
            try {
                const res = await getJson(`${urls.index}&accion=buscar_items&q=${encodeURIComponent(linea.sku)}&id_almacen=${idAlmacen}`);
                const itemData = (res.data || []).find(i => i.id == linea.id_item);
                const stock = itemData ? Math.floor(parseFloat(itemData.stock_actual)) : 0;

                spanStock.textContent = stock;
                spanStock.className = `text-center fw-bold despacho-stock ${stock <= 0 ? 'text-danger' : 'text-success'}`;

                let despachadoEnOtros = 0;
                obtenerFilasGrupo().forEach(f => {
                    if (f !== tr) despachadoEnOtros += parseInt(f.querySelector('.despacho-cantidad').value || 0, 10) || 0;
                });

                const faltaPorAsignar = parseInt(linea.cantidad_pendiente, 10) - despachadoEnOtros;
                const sugerido = Math.max(0, Math.min(faltaPorAsignar, stock));
                inputCant.value = sugerido;

                sincronizarGrupo(tr);
                validarGrupoItem(linea.id);
            } catch (e) {
                spanStock.textContent = '0';
                spanStock.className = 'text-center fw-bold despacho-stock text-danger';
                inputCant.value = 0;
                validarGrupoItem(linea.id);
            }
        });

        inputCant.addEventListener('input', () => {
            if (inputCant.value.includes('.')) {
                inputCant.value = Math.floor(parseFloat(inputCant.value || 0));
            }
            sincronizarGrupo(tr);
            validarGrupoItem(linea.id);
        });

        btnSplit.addEventListener('click', () => {
            const filas = obtenerFilasGrupo();
            if (filas.length >= 2) {
                Swal.fire('Límite alcanzado', 'Por ahora solo puede despachar este producto desde 2 almacenes.', 'info');
                return;
            }

            agregarFilaDespacho(linea, tr);
            actualizarModoGrupo();
            sincronizarGrupo(tr);
            validarGrupoItem(linea.id);
        });

        btnQuitar.addEventListener('click', () => {
            tr.remove();
            actualizarModoGrupo();
            const filas = obtenerFilasGrupo();
            if (filas.length === 1) {
                const unica = filas[0];
                const stockUnico = parseInt(unica.querySelector('.despacho-stock').textContent || 0, 10) || 0;
                const pendiente = parseInt(unica.dataset.pendienteTotal || 0, 10) || 0;
                unica.querySelector('.despacho-cantidad').value = Math.max(0, Math.min(stockUnico, pendiente));
            }
            validarGrupoItem(linea.id);
        });

        actualizarModoGrupo();
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
            const stockStr = f.querySelector('.despacho-stock').textContent;
            const stock = isNaN(parseInt(stockStr)) ? 0 : parseInt(stockStr);
            
            sumaTotalCargada += cant;

            // Validación individual de fila: ¿Excede el stock de SU almacén?
            if (cant > stock && stockStr !== '-') {
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
            const resumenPorItem = {}; 
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

            const payload = await postJson(urls.despachar, {
                id_documento: Number(despachoDocumentoId.value || 0),
                observaciones: despachoObservaciones.value,
                cerrar_forzado: cerrarForzado.checked,
                detalle: detalle
            });

            await Swal.fire('Despachado', payload.mensaje, 'success');
            modalDespacho.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // --- EVENTOS GENERALES ---
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
