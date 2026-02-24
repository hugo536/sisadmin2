document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('comprasApp');
    if (!app) return;

    const urls = {
        index: app.dataset.urlIndex,
        guardar: app.dataset.urlGuardar,
        aprobar: app.dataset.urlAprobar,
        anular: app.dataset.urlAnular,
        recepcionar: app.dataset.urlRecepcionar,
        unidadesItem: app.dataset.urlUnidadesItem,
    };

    const cacheUnidades = new Map();

    let tomSelectProveedor = null;
    if (document.getElementById('idProveedor')) {
        tomSelectProveedor = new TomSelect('#idProveedor', {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Escribe para buscar proveedor...',
            dropdownParent: 'body',
        });
    }

    const modalOrden = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
    const modalRecepcion = new bootstrap.Modal(document.getElementById('modalRecepcionCompra'));

    const tablaCompras = document.getElementById('tablaCompras');
    const tbodyTabla = tablaCompras.querySelector('tbody');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    const formOrden = document.getElementById('formOrdenCompra');
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

    const recepcionOrdenId = document.getElementById('recepcionOrdenId');
    const recepcionAlmacen = document.getElementById('recepcionAlmacen');
    const btnConfirmarRecepcion = document.getElementById('btnConfirmarRecepcion');

    let ordenEnEdicionId = 0;

    function setOrdenEnEdicion(id = 0) {
        const parsedId = Number(id || 0);
        ordenEnEdicionId = Number.isFinite(parsedId) ? parsedId : 0;
        ordenId.value = String(ordenEnEdicionId);
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

        try {
            return JSON.parse(raw);
        } catch (_) {
            throw new Error('No se pudo interpretar la respuesta del servidor.');
        }
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
            if (!response.ok || !json.ok) {
                throw new Error(json.mensaje || 'Error en la operación.');
            }
            return json;
        } finally {
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalText;
            }
        }
    }

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

    function getUnidadBaseDesdeSelect(inputItem) {
        const selected = inputItem.options[inputItem.selectedIndex];
        return selected?.dataset?.unidadBase || 'UND';
    }

    function filaToPayload(fila) {
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const info = fila.querySelector('.detalle-conversion-info'); // Asegúrate de tener un span con esta clase debajo del select

        const idItem = Number(inputItem.value || 0);
        const cantidad = parseFloat(fila.querySelector('.detalle-cantidad').value || 0);
        const costoUnitario = parseFloat(fila.querySelector('.detalle-costo').value || 0);

        let factor = parseFloat(inputUnidad.selectedOptions?.[0]?.dataset?.factor || 1);
        if (!Number.isFinite(factor) || factor <= 0) factor = 1;

        const unidadNombre = inputUnidad.classList.contains('d-none')
            ? getUnidadBaseDesdeSelect(inputItem)
            : (inputUnidad.selectedOptions?.[0]?.text.split(' (')[0] || 'UND'); // Extraemos el nombre limpio

        const cantidadBase = cantidad * factor;

        // --- MEJORA: UI Intuitiva para la conversión ---
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
        };
    }

    function recalcularFila(fila) {
        const { cantidad, costo_unitario } = filaToPayload(fila);
        const subtotal = cantidad * costo_unitario;
        fila.querySelector('.detalle-subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
        recalcularTotalGeneral();
    }

    function recalcularTotalGeneral() {
        let total = 0;
        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            const item = filaToPayload(fila);
            total += item.cantidad * item.costo_unitario;
        });
        ordenTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    async function actualizarUnidadPorItem(fila, itemGuardado = null) {
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const info = fila.querySelector('.detalle-conversion-info');
        const inputCosto = fila.querySelector('.detalle-costo');

        // Reiniciar el select
        inputUnidad.innerHTML = '<option value="">Unidad de compra...</option>';
        inputUnidad.classList.add('d-none');
        inputUnidad.disabled = true;

        const selected = inputItem.options[inputItem.selectedIndex];
        if (!selected) return;

        const requiereFactor = Number(selected.dataset.requiereFactorConversion || 0) === 1;
        const unidadBase = selected.dataset.unidadBase || 'UND';
        
        // --- MEJORA: Autocompletar costo referencial si es un ítem nuevo ---
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
            
            // --- MEJORA: Renderizado profesional de las opciones ---
            unidades.forEach((u) => {
                const option = document.createElement('option');
                option.value = String(u.id || '');
                option.dataset.factor = String(u.factor_conversion || '1');
                
                // Formateo limpio del factor (ej: 1000 en vez de 1000.0000)
                const factorLimpio = parseFloat(u.factor_conversion).toString();
                
                // El texto que verá el usuario. Usamos 'text' o 'nombre' dependiendo del backend.
                const nombreSelect = u.text || u.nombre;
                option.textContent = `${nombreSelect} (Equivale a ${factorLimpio} ${unidadBase})`;
                
                inputUnidad.appendChild(option);
            });

            inputUnidad.classList.remove('d-none');
            inputUnidad.disabled = false;

            if (itemGuardado?.id_item_unidad) {
                inputUnidad.value = String(itemGuardado.id_item_unidad);
            } else if (inputUnidad.options.length > 1) {
                // Seleccionamos la primera unidad por defecto (después del placeholder)
                inputUnidad.selectedIndex = 1;
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Atención', 'No se pudieron cargar las unidades de este ítem.', 'warning');
        }

        recalcularFila(fila);
    }

    function agregarFila(item = null) {
        const clone = templateFila.content.cloneNode(true);
        const fila = clone.querySelector('tr');

        const inputItem = fila.querySelector('.detalle-item');
        const inputCantidad = fila.querySelector('.detalle-cantidad');
        const inputCosto = fila.querySelector('.detalle-costo');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const btnQuitar = fila.querySelector('.btn-quitar-fila');

        tbodyDetalle.appendChild(fila);

        const tomSelectItem = new TomSelect(inputItem, {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Buscar ítem...',
            dropdownParent: 'body',
        });

        [inputCantidad, inputCosto, inputUnidad].forEach((input) => {
            input.addEventListener('input', () => recalcularFila(fila));
            input.addEventListener('change', () => recalcularFila(fila));
        });

        tomSelectItem.on('change', async (value) => {
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
                tomSelectItem.clear();
                return;
            }

            await actualizarUnidadPorItem(fila, null);
        });

        btnQuitar.addEventListener('click', () => {
            tomSelectItem.destroy();
            fila.remove();
            recalcularTotalGeneral();
        });

        if (item) {
            tomSelectItem.setValue(item.id_item);
            inputCantidad.value = item.cantidad;
            inputCosto.value = item.costo_unitario;
            actualizarUnidadPorItem(fila, item);
        } else {
            actualizarUnidadPorItem(fila, null);
        }

        recalcularFila(fila);
    }

    function setModoSoloLectura(esSoloLectura = false, estado = 0) {
        const deshabilitar = Boolean(esSoloLectura);

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

        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            fila.querySelectorAll('input, select, button').forEach((control) => {
                if (control.classList.contains('btn-quitar-fila')) {
                    control.style.display = deshabilitar ? 'none' : '';
                    control.disabled = deshabilitar;
                    return;
                }

                if (control.classList.contains('detalle-subtotal')) return;

                control.disabled = deshabilitar;
                if (control.tagName === 'INPUT') {
                    control.readOnly = deshabilitar;
                }
            });

            const selectItem = fila.querySelector('.detalle-item');
            if (selectItem?.tomselect) {
                if (deshabilitar) selectItem.tomselect.disable();
                else selectItem.tomselect.enable();
            }
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

        if (tomSelectProveedor) {
            tomSelectProveedor.clear();
        } else {
            idProveedor.value = '';
        }

        tbodyDetalle.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) select.tomselect.destroy();
        });

        tbodyDetalle.innerHTML = '';
        ordenTotal.textContent = 'S/ 0.00';
        setModoSoloLectura(false, 0);
    }

    btnGuardarOrden.addEventListener('click', async () => {
        if (!idProveedor.value) {
            return Swal.fire('Falta Proveedor', 'Debe seleccionar un proveedor.', 'warning');
        }

        if (!fechaEntrega.value) {
            return Swal.fire('Falta Fecha', 'La fecha de entrega estimada es obligatoria.', 'warning');
        }

        const detalle = [];
        let errorDetalle = false;

        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            const datos = filaToPayload(fila);
            if (datos.id_item > 0) {
                if (datos.cantidad <= 0 || datos.cantidad_base <= 0 || datos.factor_conversion_aplicado <= 0) {
                    errorDetalle = true;
                }
                detalle.push(datos);
            }
        });

        if (detalle.length === 0) {
            return Swal.fire({
                icon: 'error',
                title: 'Orden vacía',
                text: 'Debe agregar al menos un producto a la orden de compra.',
            });
        }

        if (errorDetalle) {
            return Swal.fire('Verifique cantidades', 'Hay líneas con conversión o cantidad inválida.', 'warning');
        }

        try {
            const payload = {
                id: Number(ordenEnEdicionId || 0),
                id_proveedor: Number(idProveedor.value),
                fecha_entrega: fechaEntrega.value,
                observaciones: observaciones.value,
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
        const almacenId = Number(recepcionAlmacen.value);
        if (almacenId <= 0) return Swal.fire('Atención', 'Seleccione un almacén de destino.', 'warning');

        const confirm = await Swal.fire({
            title: '¿Confirmar ingreso?',
            text: 'Se actualizará el stock físico del almacén seleccionado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, recepcionar',
        });

        if (!confirm.isConfirmed) return;

        try {
            const res = await postJson(urls.recepcionar, {
                id_orden: Number(recepcionOrdenId.value),
                id_almacen: almacenId,
            }, btnConfirmarRecepcion);

            await Swal.fire('Éxito', res.mensaje, 'success');
            modalRecepcion.hide();
            recargarPagina();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    });

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

                    if (d.detalle && d.detalle.length > 0) {
                        d.detalle.forEach((item) => agregarFila(item));
                    } else {
                        agregarFila();
                    }

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
                title: '¿Aprobar Orden?',
                text: 'Una orden aprobada quedará lista para recepción y ya no será editable.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, aprobar',
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
                title: '¿Anular Orden?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, anular',
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
            recepcionOrdenId.value = id;
            recepcionAlmacen.value = '';
            modalRecepcion.show();
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

    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach((el) => {
        el.addEventListener('change', recargarPagina);
    });

    filtroBusqueda.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            recargarPagina();
        }
    });
});
