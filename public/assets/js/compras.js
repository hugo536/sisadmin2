(async function initCompras() {

    const modalDevolucionEl = document.getElementById('modalDevolucionCompra');
    const modalDevolucion = modalDevolucionEl ? new bootstrap.Modal(modalDevolucionEl) : null;
    const devolucionOrdenId = document.getElementById('devolucionOrdenId');
    const devolucionMotivo = document.getElementById('devolucionMotivo');
    const devolucionResolucion = document.getElementById('devolucionResolucion');
    const tbodyDevolucion = document.querySelector('#tablaDetalleDevolucion tbody');
    const devolucionTotal = document.getElementById('devolucionTotal');
    const btnConfirmarDevolucion = document.getElementById('btnConfirmarDevolucion');
    
    // 1. Verificación vital: si no estamos en compras, salimos de inmediato
    const app = document.getElementById('comprasApp');
    if (!app) return;

    const urls = {
        index: app.dataset.urlIndex,
        guardar: app.dataset.urlGuardar,
        aprobar: app.dataset.urlAprobar,
        anular: app.dataset.urlAnular,
        recepcionar: app.dataset.urlRecepcionar,
        unidadesItem: app.dataset.urlUnidadesItem,
        precioSugerido: app.dataset.urlPrecioSugerido,
    };

    const cacheUnidades = new Map();

    async function esperarTomSelect(maxIntentos = 20, esperaMs = 150) {
        for (let i = 0; i < maxIntentos; i++) {
            if (typeof TomSelect !== 'undefined') return true;
            await new Promise((resolve) => setTimeout(resolve, esperaMs));
        }
        return false;
    }

    const tomSelectListo = await esperarTomSelect();
    if (!tomSelectListo) {
        console.warn('TomSelect no se pudo cargar en Compras. Se usará selector simple.');
    }

    const obtenerDropdownParentModalCompras = () => {
        const modal = document.getElementById('modalOrdenCompra');
        return modal || document.body;
    };

    let tomSelectProveedor = null;
    if (document.getElementById('idProveedor') && tomSelectListo) {
        tomSelectProveedor = new TomSelect('#idProveedor', {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Escribe para buscar proveedor...',
            dropdownParent: obtenerDropdownParentModalCompras(),
        });
    }

    // --- VARIABLES ORDEN DE COMPRA ---
    const modalOrdenElement = document.getElementById('modalOrdenCompra');
    const modalOrden = new bootstrap.Modal(modalOrdenElement);
    const tablaCompras = document.getElementById('tablaCompras');
    const tbodyTabla = tablaCompras.querySelector('tbody');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');
    const formOrden = document.getElementById('formOrdenCompra');
    const tipoImpuesto = document.getElementById('tipoImpuesto');
    const ordenSubtotal = document.getElementById('ordenSubtotal');
    const ordenIgv = document.getElementById('ordenIgv');
    const ordenId = document.getElementById('ordenId');
    const idProveedor = document.getElementById('idProveedor');
    const fechaEntrega = document.getElementById('fechaEntrega');
    const observaciones = document.getElementById('observaciones');
    const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
    const ordenTotal = document.getElementById('ordenTotal');
    const templateFila = document.getElementById('templateFilaDetalle');
    const btnGuardarOrden = document.getElementById('btnGuardarOrden');
    const tituloModalOrden = document.querySelector('#modalOrdenCompra .modal-title');
    const btnAgregarFila = document.getElementById('btnAgregarFila');

    // --- VARIABLES RECEPCIÓN PARCIAL / MULTI-ALMACÉN ---
    const modalRecepcionEl = document.getElementById('modalRecepcionCompra');
    const modalRecepcion = new bootstrap.Modal(modalRecepcionEl);
    const recepcionOrdenId = document.getElementById('recepcionOrdenId');
    const cerrarForzadoRecepcion = document.getElementById('cerrarForzadoRecepcion');
    const tbodyRecepcion = document.querySelector('#tablaDetalleRecepcion tbody');
    const selectTemplateAlmacen = document.getElementById('recepcionAlmacen');
    const btnConfirmarRecepcion = document.getElementById('btnConfirmarRecepcion');
    const DECIMALES_RECEPCION = 4;
    const EPSILON_RECEPCION = 0.0001;

    function limpiarBloqueoVisualModales() {
        const hayModalesAbiertos = document.querySelectorAll('.modal.show').length > 0;
        if (!hayModalesAbiertos) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
            document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        }
    }

    [modalOrdenElement, modalRecepcionEl, modalDevolucionEl].forEach((modalEl) => {
        modalEl?.addEventListener('hidden.bs.modal', limpiarBloqueoVisualModales);
    });

    let ordenEnEdicionId = 0;
    let modalSoloLecturaActiva = false;

    // --- FUNCIONES GENERALES ---
    function setOrdenEnEdicion(id = 0) {
        const parsedId = Number(id || 0);
        ordenEnEdicionId = Number.isFinite(parsedId) ? parsedId : 0;
        ordenId.value = String(ordenEnEdicionId);
    }

    function formatearCantidadRecepcion(valor) {
        return Number(valor || 0).toFixed(DECIMALES_RECEPCION);
    }

    function recargarPagina() {
        const params = new URLSearchParams(window.location.search);
        params.delete('ruta');
        params.set('q', filtroBusqueda.value.trim());
        params.set('estado', filtroEstado.value);
        params.set('fecha_desde', filtroFechaDesde.value);
        params.set('fecha_hasta', filtroFechaHasta.value);

        const separador = urls.index.includes('?') ? '&' : '?';
        window.location.href = `${urls.index}${separador}${params.toString()}`;
    }

    async function parseJsonSafe(response) {
        const contentType = response.headers.get('content-type') || '';
        const raw = await response.text();

        if (!contentType.includes('application/json')) {
            throw new Error('El servidor devolvió una respuesta no válida. Revise el log del backend.');
        }
        try { return JSON.parse(raw); } 
        catch (_) { throw new Error('No se pudo interpretar la respuesta del servidor.'); }
    }

    async function postJson(url, data, btnElement = null) {
        let originalText = '';
        if (btnElement) {
            originalText = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data),
            });

            const json = await parseJsonSafe(response);
            if (!response.ok || !json.ok) throw new Error(json.mensaje || 'Error en la operación.');
            return json;
        } finally {
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalText;
            }
        }
    }

    // --- LÓGICA ORDEN DE COMPRA ---
    async function obtenerUnidadesItem(idItem) {
        if (!idItem || idItem <= 0) return [];
        if (cacheUnidades.has(idItem)) return cacheUnidades.get(idItem);

        const separador = urls.unidadesItem.includes('?') ? '&' : '?';
        const res = await fetch(`${urls.unidadesItem}${separador}accion=unidades_item&id_item=${idItem}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await parseJsonSafe(res);
        if (!res.ok || !json.ok) {
            throw new Error(json.mensaje || 'No se pudieron cargar unidades de conversión.');
        }

        const items = Array.isArray(json.items) ? json.items : [];
        cacheUnidades.set(idItem, items);
        return items;
    }

    async function aplicarPrecioSugeridoProveedor(fila) {
        if (modalSoloLecturaActiva) return;
        const idProv = Number(idProveedor.value || 0);
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const inputCosto = fila.querySelector('.detalle-costo');
        const idItem = Number(inputItem?.value || 0);
        const idUnidad = inputUnidad && !inputUnidad.classList.contains('d-none')
            ? Number(inputUnidad.value || 0)
            : 0;

        if (idProv <= 0 || idItem <= 0 || !urls.precioSugerido) return;

        const separador = urls.precioSugerido.includes('?') ? '&' : '?';
        const res = await fetch(`${urls.precioSugerido}${separador}accion=precio_sugerido_proveedor&id_proveedor=${idProv}&id_item=${idItem}&id_unidad=${idUnidad}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await parseJsonSafe(res);
        
        if (!res.ok || !json.ok || !json.encontrado) {
            inputCosto.value = "0.0000";
            recalcularFila(fila);
            return;
        }

        inputCosto.value = Number(json.precio_recomendado).toFixed(4);
        recalcularFila(fila);
    }

    function getUnidadBaseDesdeSelect(inputItem) {
        const selected = inputItem.options[inputItem.selectedIndex];
        return selected?.dataset?.unidadBase || 'UND';
    }

    function filaToPayload(fila) {
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const inputCentroCosto = fila.querySelector('.detalle-centro-costo');
        const info = fila.querySelector('.detalle-conversion-info');

        const idItem = Number(inputItem.value || 0);
        const cantidad = parseFloat(fila.querySelector('.detalle-cantidad').value || 0);
        const costoUnitario = parseFloat(fila.querySelector('.detalle-costo').value || 0);

        let factor = parseFloat(inputUnidad.selectedOptions?.[0]?.dataset?.factor || 1);
        if (!Number.isFinite(factor) || factor <= 0) factor = 1;

        const unidadNombre = inputUnidad.classList.contains('d-none')
            ? getUnidadBaseDesdeSelect(inputItem)
            : (inputUnidad.selectedOptions?.[0]?.text.split(' (')[0] || 'UND');

        const cantidadBase = cantidad * factor;

        if (info) {
            if (idItem > 0 && factor > 1) {
                 info.innerHTML = `<small class="text-muted fw-bold">Entrarán al almacén: ${cantidadBase.toFixed(2)} ${getUnidadBaseDesdeSelect(inputItem)}</small>`;
            } else if (idItem > 0) {
                 info.innerHTML = `<small class="text-muted">Unidad base: ${getUnidadBaseDesdeSelect(inputItem)}</small>`;
            } else {
                 info.innerHTML = '';
            }
        }

        return {
            id_item: idItem,
            id_item_unidad: inputUnidad.classList.contains('d-none') || !inputUnidad.value ? null : Number(inputUnidad.value),
            unidad_nombre: unidadNombre,
            factor_conversion_aplicado: factor,
            cantidad,
            cantidad_base: cantidadBase,
            costo_unitario: costoUnitario,
            id_centro_costo: inputCentroCosto?.value ? Number(inputCentroCosto.value) : null,
        };
    }

    function recalcularFila(fila) {
        const { cantidad, costo_unitario } = filaToPayload(fila);
        const subtotal = cantidad * costo_unitario;
        fila.querySelector('.detalle-subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
        recalcularTotalGeneral();
    }

    function recalcularTotalGeneral() {
        let sumaLineas = 0;
        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            const item = filaToPayload(fila);
            sumaLineas += item.cantidad * item.costo_unitario;
        });

        let subtotal = 0;
        let igv = 0;
        let total = 0;
        const tipo = tipoImpuesto ? tipoImpuesto.value : 'incluido';

        if (tipo === 'incluido') {
            total = sumaLineas;
            subtotal = total / 1.18;
            igv = total - subtotal;
        } else if (tipo === 'mas_igv') {
            subtotal = sumaLineas;
            igv = subtotal * 0.18;
            total = subtotal + igv;
        } else { // exonerado
            subtotal = sumaLineas;
            igv = 0;
            total = subtotal;
        }

        if (ordenSubtotal) ordenSubtotal.textContent = `S/ ${subtotal.toFixed(2)}`;
        if (ordenIgv) ordenIgv.textContent = `S/ ${igv.toFixed(2)}`;
        if (ordenTotal) ordenTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    if (tipoImpuesto) {
        tipoImpuesto.addEventListener('change', recalcularTotalGeneral);
    }

    async function actualizarUnidadPorItem(fila, itemGuardado = null) {
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const info = fila.querySelector('.detalle-conversion-info');
        const inputCosto = fila.querySelector('.detalle-costo');

        inputUnidad.innerHTML = '<option value="">Unidad de compra...</option>';
        inputUnidad.classList.add('d-none');
        inputUnidad.disabled = true;

        const selected = inputItem.options[inputItem.selectedIndex];
        if (!selected) return;

        const requiereFactor = Number(selected.dataset.requiereFactorConversion || 0) === 1;
        const unidadBase = selected.dataset.unidadBase || 'UND';
        
        if (!itemGuardado && selected.dataset.costoReferencial) {
             const costoRef = parseFloat(selected.dataset.costoReferencial);
             if (costoRef > 0) inputCosto.value = costoRef.toFixed(4);
        }

        if (!inputItem.value || !requiereFactor) {
            if (info) info.innerHTML = inputItem.value ? `<small class="text-muted">Unidad base: ${unidadBase}</small>` : '';
            recalcularFila(fila);
            return;
        }

        try {
            const unidades = await obtenerUnidadesItem(Number(inputItem.value));
            unidades.forEach((u) => {
                const option = document.createElement('option');
                option.value = String(u.id || '');
                option.dataset.factor = String(u.factor_conversion || '1');
                const factorLimpio = parseFloat(u.factor_conversion).toString();
                const nombreSelect = u.text || u.nombre;
                option.textContent = `${nombreSelect} (Equivale a ${factorLimpio} ${unidadBase})`;
                inputUnidad.appendChild(option);
            });

            inputUnidad.classList.remove('d-none');
            inputUnidad.disabled = false;

            if (itemGuardado?.id_item_unidad) {
                inputUnidad.value = String(itemGuardado.id_item_unidad);
            } else if (inputUnidad.options.length > 1) {
                inputUnidad.selectedIndex = 1;
            }

            if (!itemGuardado) {
                await aplicarPrecioSugeridoProveedor(fila);
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Atención', 'No se pudieron cargar las unidades de este ítem.', 'warning');
        }

        sincronizarBloqueoFilaDetalle(fila);
        recalcularFila(fila);
    }

    function agregarFila(item = null) {
        const clone = templateFila.content.cloneNode(true);
        const fila = clone.querySelector('tr');

        const inputItem = fila.querySelector('.detalle-item');
        const inputCantidad = fila.querySelector('.detalle-cantidad');
        const inputCosto = fila.querySelector('.detalle-costo');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const inputCentroCosto = fila.querySelector('.detalle-centro-costo');
        const btnQuitar = fila.querySelector('.btn-quitar-fila');

        tbodyDetalle.appendChild(fila);

        let tomSelectItem = null;
        if (tomSelectListo) {
            tomSelectItem = new TomSelect(inputItem, {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                placeholder: 'Buscar ítem...',
                dropdownParent: obtenerDropdownParentModalCompras(),
            });
        }

        [inputCantidad, inputCosto, inputUnidad].forEach((input) => {
            input.addEventListener('input', () => recalcularFila(fila));
            input.addEventListener('change', () => recalcularFila(fila));
        });
        inputUnidad.addEventListener('change', async () => {
            await aplicarPrecioSugeridoProveedor(fila);
            recalcularFila(fila);
        });

        if (inputCentroCosto) {
            inputCentroCosto.addEventListener('change', () => {
                if (inputCentroCosto.value) {
                    inputCentroCosto.classList.remove('is-invalid', 'border-danger');
                }
                recalcularFila(fila);
            });
        }

        const onCambioItem = async (value) => {
            if (!value) {
                await actualizarUnidadPorItem(fila, null);
                return;
            }

            let contadorDuplicados = 0;
            tbodyDetalle.querySelectorAll('.detalle-item').forEach((select) => {
                if (select.value === value) contadorDuplicados++;
            });

            if (contadorDuplicados > 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ítem duplicado',
                    text: 'Este producto ya está en la lista.',
                    confirmButtonColor: '#3085d6',
                });
                if (tomSelectItem) tomSelectItem.clear();
                else inputItem.value = '';
                return;
            }

            await actualizarUnidadPorItem(fila, null);
        };

        if (tomSelectItem) {
            tomSelectItem.on('change', onCambioItem);
        } else {
            inputItem.addEventListener('change', (e) => onCambioItem(e.target.value));
        }

        btnQuitar.addEventListener('click', () => {
            if (tomSelectItem) tomSelectItem.destroy();
            fila.remove();
            recalcularTotalGeneral();
        });

        if (item) {
            if (tomSelectItem) tomSelectItem.setValue(item.id_item);
            else inputItem.value = String(item.id_item || '');
            inputCantidad.value = item.cantidad;
            inputCosto.value = item.costo_unitario;
            if (inputCentroCosto) {
                inputCentroCosto.value = item.id_centro_costo ? String(item.id_centro_costo) : '';
            }
            actualizarUnidadPorItem(fila, item);
        } else {
            actualizarUnidadPorItem(fila, null);
        }

        sincronizarBloqueoFilaDetalle(fila);
        recalcularFila(fila);

        // --- AUTOSCROLL Y ENFOQUE AL AGREGAR ---
        if (!item) {
            setTimeout(() => {
                fila.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (tomSelectItem && !modalSoloLecturaActiva) {
                    tomSelectItem.focus();
                }
            }, 100);
        }
    }

    function sincronizarBloqueoFilaDetalle(fila) {
        if (!fila) return;
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');

        if (inputUnidad) inputUnidad.disabled = modalSoloLecturaActiva;
        if (inputItem?.tomselect) {
            if (modalSoloLecturaActiva) inputItem.tomselect.disable();
            else inputItem.tomselect.enable();
        }
    }

    function setModoSoloLectura(esSoloLectura = false, estado = 0) {
        const deshabilitar = Boolean(esSoloLectura);
        modalSoloLecturaActiva = deshabilitar;

        if (modalOrdenElement) {
            modalOrdenElement.classList.toggle('modal-orden-solo-lectura', deshabilitar);
        }

        if (tituloModalOrden) {
            if (deshabilitar && Number(estado) === 3) {
                tituloModalOrden.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Compra finalizada (solo lectura)';
            } else if (deshabilitar) {
                tituloModalOrden.innerHTML = '<i class="bi bi-eye me-2"></i>Orden de Compra (solo lectura)';
            } else {
                tituloModalOrden.innerHTML = '<i class="bi bi-receipt-cutoff me-2"></i>Orden de Compra';
            }
        }

        [idProveedor, fechaEntrega, observaciones].forEach((el) => {
            if (!el) return;
            el.disabled = deshabilitar;
            el.readOnly = deshabilitar;
        });

        if (tomSelectProveedor) {
            if (deshabilitar) {
                tomSelectProveedor.disable();
                tomSelectProveedor.close();
                tomSelectProveedor.blur();
            } else {
                tomSelectProveedor.enable();
            }
        }

        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            fila.querySelectorAll('input, select, button').forEach((control) => {
                if (control.classList.contains('btn-quitar-fila')) {
                    control.style.display = deshabilitar ? 'none' : '';
                    control.disabled = deshabilitar;
                    return;
                }
                if (control.classList.contains('detalle-subtotal')) return;
                control.disabled = deshabilitar;
                if (control.tagName === 'INPUT') control.readOnly = deshabilitar;
            });
            sincronizarBloqueoFilaDetalle(fila);
        });

        if (btnAgregarFila) {
            btnAgregarFila.style.display = deshabilitar ? 'none' : 'inline-block';
            btnAgregarFila.disabled = deshabilitar;
        }

        btnGuardarOrden.style.display = deshabilitar ? 'none' : 'block';
    }

    function limpiarModalOrden() {
        formOrden.reset();
        setOrdenEnEdicion(0);
        if (tomSelectProveedor) tomSelectProveedor.clear();
        else idProveedor.value = '';

        tbodyDetalle.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) select.tomselect.destroy();
        });

        tbodyDetalle.innerHTML = '';
        ordenTotal.textContent = 'S/ 0.00';
        setModoSoloLectura(false, 0);
    }

    // Helper interno para no duplicar código
    async function getJson(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await res.json();
        if (!res.ok || !payload.ok) throw new Error(payload.mensaje || 'Error del servidor');
        return payload;
    }

    // --- LÓGICA RECEPCIÓN PARCIAL / MULTI-ALMACÉN ---
    async function abrirModalRecepcion(idOrden) {
        try {
            const separador = urls.index.includes('?') ? '&' : '?';
            const res = await getJson(`${urls.index}${separador}accion=ver&id=${idOrden}`);
            const orden = res.data;

            recepcionOrdenId.value = orden.id;
            cerrarForzadoRecepcion.checked = false;
            tbodyRecepcion.innerHTML = '';

            const detalle = Array.isArray(orden.detalle) ? orden.detalle : [];
            detalle.forEach((linea) => {
                if (Number(linea.cantidad_pendiente) > 0.0001) {
                    agregarFilaRecepcion(linea, null);
                }
            });
            modalRecepcion.show();
        } catch (error) {
            Swal.fire('Error', error.message || 'No se pudo preparar la recepción.', 'error');
        }
    }

    function agregarFilaRecepcion(linea, filaReferencia = null) {
        const tr = document.createElement('tr');
        tr.dataset.idDetalle = linea.id;
        tr.dataset.pendienteTotal = linea.cantidad_pendiente;

        const factorHtml = Number(linea.factor_conversion_aplicado) > 1 
            ? `<span class="badge bg-info-subtle text-info border border-info-subtle ms-1">x ${linea.factor_conversion_aplicado}</span>` 
            : '';

        tr.innerHTML = `
            <td class="align-middle py-3 ps-3">
                <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">${linea.item_nombre || ''}</div>
                <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                    <span>Pedido: <strong class="text-dark">${Number(linea.cantidad_unidad).toFixed(2)} ${linea.unidad_nombre}</strong> ${factorHtml}</span>
                </div>
                <button type="button" class="btn btn-link btn-sm px-0 mt-2 text-decoration-none fw-semibold btn-split-recepcion" title="Ingresar a otro almacén adicional">
                    <i class="bi bi-diagram-2 me-1"></i>Fraccionar en otro almacén
                </button>
            </td>
            <td class="align-middle px-2">
                <select class="form-select form-select-sm fila-almacen-rec shadow-none border-secondary-subtle fw-semibold text-secondary" required>
                    ${selectTemplateAlmacen.innerHTML}
                </select>
            </td>
            <td class="text-center align-middle">
                <span class="badge bg-warning text-dark badge-pendiente-rec rounded-pill px-3 py-2 shadow-sm">${formatearCantidadRecepcion(linea.cantidad_pendiente)} ${linea.unidad_base}</span>
            </td>
            <td class="align-middle px-2">
                <input type="number" class="form-control form-control-sm text-center recepcion-cantidad fw-bold text-primary shadow-none border-secondary-subtle mx-auto"
                       min="0" step="0.0001" value="${formatearCantidadRecepcion(linea.cantidad_pendiente)}" style="max-width: 110px;">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-recepcion d-none d-inline-flex align-items-center justify-content-center transition-all p-0" title="Quitar línea" style="width: 34px; height: 34px;">
                    <i class="bi bi-trash-fill fs-6"></i>
                </button>
            </td>
        `;

        if (filaReferencia) {
            filaReferencia.insertAdjacentElement('afterend', tr);
        } else {
            tbodyRecepcion.appendChild(tr);
        }

        const selectAlmacen = tr.querySelector('.fila-almacen-rec');
        const inputCant = tr.querySelector('.recepcion-cantidad');
        const btnSplit = tr.querySelector('.btn-split-recepcion');
        const btnQuitar = tr.querySelector('.btn-quitar-recepcion');

        const obtenerFilasGrupo = () => [...tbodyRecepcion.querySelectorAll(`tr[data-id-detalle="${linea.id}"]`)];

        const actualizarModoGrupo = () => {
            const filas = obtenerFilasGrupo();
            const multiple = filas.length > 1;
            filas.forEach((fila, idx) => {
                const btn = fila.querySelector('.btn-quitar-recepcion');
                if (btn) btn.classList.toggle('d-none', !multiple || idx === 0);
            });
        };

        const validarCantidades = () => {
            const filas = obtenerFilasGrupo();
            const pendienteGlobal = parseFloat(linea.cantidad_pendiente);
            let sumaCargada = 0;

            filas.forEach(f => sumaCargada += parseFloat(f.querySelector('.recepcion-cantidad').value || 0));

            const badge = filas[0].querySelector('.badge-pendiente-rec');
            if ((sumaCargada - pendienteGlobal) > EPSILON_RECEPCION) {
                filas.forEach(f => f.querySelector('.recepcion-cantidad').classList.add('is-invalid'));
                badge.className = "badge bg-danger text-white badge-pendiente-rec rounded-pill px-3 py-2";
                badge.textContent = `Excedido (Máx: ${formatearCantidadRecepcion(pendienteGlobal)})`;
            } else {
                filas.forEach(f => f.querySelector('.recepcion-cantidad').classList.remove('is-invalid'));
                badge.className = "badge bg-warning text-dark badge-pendiente-rec rounded-pill px-3 py-2";
                badge.textContent = `${formatearCantidadRecepcion(pendienteGlobal)} ${linea.unidad_base}`;
            }
        };

        inputCant.addEventListener('input', validarCantidades);
        selectAlmacen.addEventListener('change', validarCantidades);

        btnSplit.addEventListener('click', () => {
            agregarFilaRecepcion(linea, tr);
            tr.querySelector('.recepcion-cantidad').value = 0; 
            validarCantidades();
        });

        btnQuitar.addEventListener('click', () => {
            tr.remove();
            actualizarModoGrupo();
            validarCantidades();
        });

        actualizarModoGrupo();
        return tr;
    }

    // --- LÓGICA DEVOLUCIONES ---
    async function abrirModalDevolucion(idOrden) {
        try {
            const separador = urls.index.includes('?') ? '&' : '?';
            const res = await getJson(`${urls.index}${separador}accion=ver&id=${idOrden}`);
            const orden = res.data;

            devolucionOrdenId.value = orden.id;
            devolucionMotivo.value = '';
            tbodyDevolucion.innerHTML = '';
            devolucionTotal.textContent = 'S/ 0.00';

            const detalle = Array.isArray(orden.detalle) ? orden.detalle : [];
            let lineasRecibidas = 0;

            // Almacenamos las promesas para cargar las unidades en paralelo
            const promesasLineas = [];

            detalle.forEach((linea) => {
                const recibido = parseFloat(linea.cantidad_recibida || 0);
                if (recibido > 0.0001) {
                    lineasRecibidas++;
                    promesasLineas.push(agregarFilaDevolucion(linea, recibido));
                }
            });

            if (lineasRecibidas === 0) {
                Swal.fire('Aviso', 'Esta orden no tiene productos recepcionados para devolver.', 'info');
                return;
            }

            await Promise.all(promesasLineas);
            modalDevolucion.show();
        } catch (error) {
            Swal.fire('Error', error.message || 'No se pudo preparar la devolución.', 'error');
        }
    }

    async function agregarFilaDevolucion(linea, cantRecibidaBase) {
        const tr = document.createElement('tr');
        
        // 1. EXTRAER EL COSTO REAL DE LA ORDEN DE COMPRA
        const factorCompra = parseFloat(linea.factor_conversion_aplicado || 1);
        const costoCompra = parseFloat(linea.costo_unitario || 0); 
        // Costo por unidad base real con el que entró al Kardex
        const costoBaseReal = costoCompra / factorCompra;

        tr.dataset.idDetalle = linea.id;
        tr.dataset.idItem = linea.id_item;
        tr.dataset.costoBase = costoBaseReal; // <-- GUARDAMOS EL COSTO BASE REAL
        tr.dataset.maxBase = cantRecibidaBase; 

        tr.innerHTML = `
            <td class="align-middle py-3 ps-3">
                <div class="fw-bold text-dark" style="font-size: 0.95rem;">${linea.item_nombre || ''}</div>
                <small class="text-muted dev-info-conversion">Unidad base: ${linea.unidad_base}</small>
            </td>
            <td class="text-center align-middle">
                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-bold">
                    ${cantRecibidaBase.toFixed(2)} ${linea.unidad_base}
                </span>
            </td>
            <td class="text-center align-middle">
                <div class="fw-semibold text-secondary">S/ ${costoCompra.toFixed(2)}</div>
                <small style="font-size: 0.75em;" class="text-muted">x ${linea.unidad_nombre}</small>
            </td>
            <td class="align-middle px-2">
                <select class="form-select form-select-sm shadow-none dev-select-unidad border-warning-subtle">
                    <option value="" data-factor="1">Unidad Base (${linea.unidad_base})</option>
                </select>
            </td>
            <td class="align-middle px-2">
                <input type="number" class="form-control form-control-sm text-center input-devolver fw-bold text-warning-emphasis border-warning mx-auto shadow-none"
                       min="0" step="0.01" value="0.00" style="max-width: 100px;">
            </td>
            <td class="text-end align-middle pe-4 fw-bold text-dark subtotal-fila-dev">
                S/ 0.00
            </td>
        `;

        tbodyDevolucion.appendChild(tr);

        const selectUnidad = tr.querySelector('.dev-select-unidad');
        const inputCant = tr.querySelector('.input-devolver');
        const tdSubtotal = tr.querySelector('.subtotal-fila-dev');
        const infoConv = tr.querySelector('.dev-info-conversion');

        try {
            const unidades = await obtenerUnidadesItem(Number(linea.id_item));
            unidades.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.dataset.factor = u.factor_conversion;
                opt.textContent = `${u.nombre} (x ${parseFloat(u.factor_conversion)})`;
                selectUnidad.appendChild(opt);
            });
            
            // Si la compró en Caja, le sugerimos devolver en Caja
            if (linea.id_item_unidad) {
                selectUnidad.value = String(linea.id_item_unidad);
            }
        } catch (e) {
            console.warn("No se pudieron cargar unidades para el ítem", linea.id_item);
        }

        const recalcularLinea = () => {
            let cantInput = parseFloat(inputCant.value || 0);
            const factorSeleccionado = parseFloat(selectUnidad.options[selectUnidad.selectedIndex]?.dataset.factor || 1);
            
            let cantBaseCalculada = cantInput * factorSeleccionado;
            
            // Validar que no devuelva más de lo que recibió
            if (cantBaseCalculada > cantRecibidaBase) {
                cantInput = cantRecibidaBase / factorSeleccionado;
                cantBaseCalculada = cantRecibidaBase;
                inputCant.value = cantInput.toFixed(2); 
                inputCant.classList.add('is-invalid', 'border-danger');
            } else {
                inputCant.classList.remove('is-invalid', 'border-danger');
            }

            // Usamos el costo base real que calculamos arriba
            const subtotal = cantBaseCalculada * costoBaseReal;
            tdSubtotal.textContent = `S/ ${subtotal.toFixed(2)}`;
            
            if (factorSeleccionado > 1) {
                infoConv.innerHTML = `Saldrán: <strong>${cantBaseCalculada.toFixed(2)} ${linea.unidad_base}</strong>`;
            } else {
                infoConv.textContent = `Unidad base: ${linea.unidad_base}`;
            }

            recalcularTotalDevolucion();
        };

        inputCant.addEventListener('input', recalcularLinea);
        selectUnidad.addEventListener('change', recalcularLinea);
    }

    function recalcularTotalDevolucion() {
        let total = 0;
        tbodyDevolucion.querySelectorAll('tr').forEach((fila) => {
            const cant = parseFloat(fila.querySelector('.input-devolver').value || 0);
            const selectU = fila.querySelector('.dev-select-unidad');
            const factor = parseFloat(selectU.options[selectU.selectedIndex]?.dataset.factor || 1);
            const costoBase = parseFloat(fila.dataset.costoBase || 0);
            total += (cant * factor) * costoBase;
        });
        devolucionTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    // --- EVENTOS PRINCIPALES ---
    btnGuardarOrden.addEventListener('click', async () => {
        if (!idProveedor.value) return Swal.fire('Falta Proveedor', 'Debe seleccionar un proveedor.', 'warning');
        if (!fechaEntrega.value) return Swal.fire('Falta Fecha', 'La fecha de entrega estimada es obligatoria.', 'warning');

        const detalle = [];
        let errorDetalle = false;
        let errorCentroCosto = false;

        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            const datos = filaToPayload(fila);
            const selectCentroCosto = fila.querySelector('.detalle-centro-costo');

            if (datos.id_item > 0) {
                if (selectCentroCosto) selectCentroCosto.classList.remove('is-invalid', 'border-danger');

                if (datos.cantidad <= 0 || datos.cantidad_base <= 0 || datos.factor_conversion_aplicado <= 0) {
                    errorDetalle = true;
                }

                if (!datos.id_centro_costo || datos.id_centro_costo <= 0) {
                    errorCentroCosto = true;
                    if (selectCentroCosto) selectCentroCosto.classList.add('is-invalid', 'border-danger'); 
                }

                detalle.push(datos);
            }
        });

        if (detalle.length === 0) return Swal.fire({ icon: 'error', title: 'Orden vacía', text: 'Debe agregar al menos un producto a la orden de compra.' });
        if (errorCentroCosto) return Swal.fire('Falta Centro de Costo', 'Debe seleccionar un Centro de Costo para todos los ítems de la orden.', 'warning');
        if (errorDetalle) return Swal.fire('Verifique cantidades', 'Hay líneas con conversión o cantidad inválida.', 'warning');

        try {
            const payload = {
                id: Number(ordenEnEdicionId || 0),
                id_proveedor: Number(idProveedor.value),
                fecha_entrega: fechaEntrega.value,
                observaciones: observaciones.value,
                tipo_impuesto: tipoImpuesto ? tipoImpuesto.value : 'incluido',
                detalle,
            };

            const res = await postJson(urls.guardar, payload, btnGuardarOrden);
            await Swal.fire('Guardado', res.mensaje, 'success');
            modalOrden.hide();
            recargarPagina();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    });

    btnConfirmarRecepcion.addEventListener('click', async () => {
        try {
            const filas = [...tbodyRecepcion.querySelectorAll('tr')];
            const detalle = filas.map(fila => {
                const idAlmacen = fila.querySelector('.fila-almacen-rec').value;
                const cantidad = parseFloat(fila.querySelector('.recepcion-cantidad').value || 0);
                
                return {
                    id_documento_detalle: Number(fila.dataset.idDetalle),
                    id_almacen: Number(idAlmacen),
                    cantidad: cantidad
                };
            }).filter(d => d.cantidad > 0);

            if (detalle.length === 0) throw new Error('Debe ingresar cantidad en al menos un producto.');
            if (detalle.some(d => !d.id_almacen)) throw new Error('Seleccione un almacén destino para todas las filas.');
            if (tbodyRecepcion.querySelector('.is-invalid')) throw new Error('Corrija las cantidades en rojo. No puede recibir más de lo pendiente.');

            let esParcial = false;
            const resumenPorItem = {}; 
            filas.forEach(f => {
                const id = f.dataset.idDetalle;
                resumenPorItem[id] = (resumenPorItem[id] || 0) + parseFloat(f.querySelector('.recepcion-cantidad').value || 0);
            });

            filas.forEach(f => {
                const pendiente = parseFloat(f.dataset.pendienteTotal);
                if (resumenPorItem[f.dataset.idDetalle] < pendiente - EPSILON_RECEPCION) esParcial = true;
            });

            if (esParcial && !cerrarForzadoRecepcion.checked) {
                const resp = await Swal.fire({
                    icon: 'info', title: 'Recepción Parcial', 
                    text: 'Está ingresando menos cantidad de la esperada. La orden quedará abierta con saldo pendiente. ¿Desea continuar?', 
                    showCancelButton: true, confirmButtonText: 'Sí, ingresar parcial'
                });
                if (!resp.isConfirmed) return;
            }

            const payload = await postJson(urls.recepcionar, {
                id_orden: Number(recepcionOrdenId.value || 0),
                cerrar_forzado: cerrarForzadoRecepcion.checked,
                detalle: detalle
            }, btnConfirmarRecepcion);

            await Swal.fire('Ingresado', payload.mensaje, 'success');
            modalRecepcion.hide();
            recargarPagina();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    if (btnConfirmarDevolucion) {
        btnConfirmarDevolucion.addEventListener('click', async () => {
            if (!devolucionMotivo.value) return Swal.fire('Aviso', 'Seleccione un motivo.', 'warning');
            
            const detalle = [];
            let totalDevolverBase = 0;

            tbodyDevolucion.querySelectorAll('tr').forEach(tr => {
                const cant = parseFloat(tr.querySelector('.input-devolver').value || 0);
                if (cant > 0) {
                    const selectU = tr.querySelector('.dev-select-unidad');
                    const factor = parseFloat(selectU.options[selectU.selectedIndex]?.dataset.factor || 1);
                    
                    detalle.push({
                        id_documento_detalle: Number(tr.dataset.idDetalle),
                        id_item: Number(tr.dataset.idItem),
                        id_unidad: selectU.value ? Number(selectU.value) : null,
                        factor: factor,
                        cantidad_input: cant,
                        cantidad_base: cant * factor,
                        costo_base: parseFloat(tr.dataset.costoBase)
                    });
                    totalDevolverBase += (cant * factor);
                }
            });

            if (detalle.length === 0 || totalDevolverBase <= 0) {
                return Swal.fire('Aviso', 'Ingrese al menos una cantidad a devolver mayor a cero.', 'warning');
            }

            try {
                const separador = urls.index.includes('?') ? '&' : '?';
                const urlPost = `${urls.index}${separador}accion=guardar_devolucion`;

                const payload = {
                    id_orden: Number(devolucionOrdenId.value),
                    motivo: devolucionMotivo.value,
                    resolucion: devolucionResolucion.value,
                    detalle: detalle
                };

                const res = await postJson(urlPost, payload, btnConfirmarDevolucion);
                await Swal.fire('Éxito', res.mensaje, 'success');
                modalDevolucion.hide();
                recargarPagina();
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        });
    }

    tbodyTabla.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        const fila = target.closest('tr');
        const id = Number(fila.dataset.id);

        if (target.classList.contains('btn-editar')) {
            try {
                const separador = urls.index.includes('?') ? '&' : '?';
                const res = await fetch(`${urls.index}${separador}accion=ver&id=${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json();

                if (json.ok && json.data) {
                    const d = json.data;
                    limpiarModalOrden();
                    setOrdenEnEdicion(d.id);
                    if (tomSelectProveedor) tomSelectProveedor.setValue(d.id_proveedor);
                    else idProveedor.value = d.id_proveedor;

                    fechaEntrega.value = d.fecha_entrega || '';
                    observaciones.value = d.observaciones || '';
                    if (tipoImpuesto && d.tipo_impuesto) tipoImpuesto.value = d.tipo_impuesto;

                    if (d.detalle && d.detalle.length > 0) d.detalle.forEach((item) => agregarFila(item));
                    else agregarFila();

                    const estadoDoc = Number(d.estado || 0);
                    setModoSoloLectura(estadoDoc !== 0, estadoDoc);
                    modalOrden.show();
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'No se pudo cargar la orden.', 'error');
            }
            return;
        }

        if (target.classList.contains('btn-aprobar')) {
            const confirm = await Swal.fire({
                title: '¿Aprobar Orden?', text: 'Una orden aprobada quedará lista para recepción y ya no será editable.',
                icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar',
            });

            if (!confirm.isConfirmed) return;

            try {
                const res = await postJson(urls.aprobar, { id }, target);
                await Swal.fire('Aprobada', res.mensaje, 'success');
                recargarPagina();
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
            return;
        }

        if (target.classList.contains('btn-anular')) {
            const confirm = await Swal.fire({
                title: '¿Anular Orden?', text: 'Esta acción no se puede deshacer.',
                icon: 'error', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular',
            });

            if (!confirm.isConfirmed) return;

            try {
                const res = await postJson(urls.anular, { id });
                await Swal.fire('Anulada', res.mensaje, 'success');
                recargarPagina();
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
            return;
        }

        if (target.classList.contains('btn-recepcionar')) {
            abrirModalRecepcion(id);
            return;
        }

        if (target.classList.contains('btn-devolver')) {
            abrirModalDevolucion(id);
            return;
        }
    });

    document.getElementById('btnNuevaOrden').addEventListener('click', () => {
        limpiarModalOrden();
        setOrdenEnEdicion(0);
        agregarFila();
        setModoSoloLectura(false, 0);
        modalOrden.show();
    });

    if (btnAgregarFila) {
        btnAgregarFila.addEventListener('click', () => agregarFila());
    }

    const refrescarPreciosSugeridos = async () => {
        const filas = [...tbodyDetalle.querySelectorAll('tr')];
        for (const fila of filas) {
            await aplicarPrecioSugeridoProveedor(fila);
        }
    };
    if (tomSelectProveedor) {
        tomSelectProveedor.on('change', refrescarPreciosSugeridos);
    } else if (idProveedor) {
        idProveedor.addEventListener('change', refrescarPreciosSugeridos);
    }

    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach((el) => {
        el.addEventListener('change', recargarPagina);
    });

    filtroBusqueda.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            recargarPagina();
        }
    });
})();
