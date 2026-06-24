(async function initCompras() {

    const modalDevolucionEl = document.getElementById('modalDevolucionCompra');
    const modalDevolucion = modalDevolucionEl ? new bootstrap.Modal(modalDevolucionEl, { focus: false }) : null;
    const devolucionOrdenId = document.getElementById('devolucionOrdenId');
    const devolucionMotivo = document.getElementById('devolucionMotivo');
    const devolucionResolucion = document.getElementById('devolucionResolucion');
    const devolucionResolucionHint = document.getElementById('devolucionResolucionHint');
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
        revertirBorrador: app.dataset.urlRevertirBorrador,
        anular: app.dataset.urlAnular,
        recepcionar: app.dataset.urlRecepcionar,
        unidadesItem: app.dataset.urlUnidadesItem,
        precioSugerido: app.dataset.urlPrecioSugerido,
    };

    const cacheUnidades = new Map();

    function actualizarHintResolucionDevolucion() {
        if (!devolucionResolucionHint || !devolucionResolucion) return;

        const resolucion = devolucionResolucion.value;
        if (resolucion === 'descuento_cxp') {
            devolucionResolucionHint.textContent = '✅ Recomendado cuando tienes facturas pendientes: reduce tu cuenta por pagar automáticamente.';
            devolucionResolucionHint.className = 'form-text text-secondary mt-1';
            return;
        }

        devolucionResolucionHint.textContent = '💸 Úsalo cuando el proveedor te devolverá dinero (caja/transferencia). No descuenta la deuda automáticamente.';
        devolucionResolucionHint.className = 'form-text text-secondary mt-1';
    }

    function actualizarLogicaDevolucionCompra() {
        const filaSwitchReemplazo = document.getElementById('filaSwitchReemplazoCompra');
        const checkReemplazo = document.getElementById('devolucionEsperarReemplazo');
        const motivoActual = devolucionMotivo?.value || '';

        if (filaSwitchReemplazo && checkReemplazo) {
            if (motivoActual === 'Producto defectuoso / Garantía' || motivoActual === 'Producto incorrecto') {
                filaSwitchReemplazo.classList.remove('d-none');
            } else {
                filaSwitchReemplazo.classList.add('d-none');
                checkReemplazo.checked = false;
            }
        }
    }

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

    function initSelectLocal(target, options = {}) {
        if (typeof window !== 'undefined' && window.AppSelects && typeof window.AppSelects.initLocal === 'function') {
            return window.AppSelects.initLocal(target, options);
        }
        
        console.warn('AppSelects no detectado a tiempo. Usando configuración local de emergencia.');
        return new TomSelect(target, Object.assign({
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            searchField: ['text', 'value'],
            plugins: ['clear_button']
        }, options));
    }

    let tomSelectProveedor = null;
    if (document.getElementById('idProveedor') && tomSelectListo) {
        tomSelectProveedor = initSelectLocal('#idProveedor', {
            placeholder: 'Escribe para buscar proveedor...',
            dropdownParent: 'body',
        });
    }

    // --- VARIABLES ORDEN DE COMPRA ---
    const modalOrdenElement = document.getElementById('modalOrdenCompra');
    const modalOrden = new bootstrap.Modal(modalOrdenElement, { focus: false });
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
    const switchCobroContainerCompra = document.getElementById('switchCobroContainerCompra');
    const switchCobroInmediatoCompra = document.getElementById('switchCobroInmediatoCompra');
    const seccionCobroInmediatoCompra = document.getElementById('seccionCobroInmediatoCompra');
    const contenedorMetodosPagoCompra = document.getElementById('contenedorMetodosPagoCompra');
    const btnAgregarPagoInmediatoCompra = document.getElementById('btnAgregarPagoInmediatoCompra');
    const totalPagadoInmediatoCompra = document.getElementById('totalPagadoInmediatoCompra');
    const cuentasDisponibles = Array.isArray(window.TESORERIA_CUENTAS) 
        ? window.TESORERIA_CUENTAS 
        : Object.values(window.TESORERIA_CUENTAS || {});

    const metodosDisponibles = Array.isArray(window.TESORERIA_METODOS) 
        ? window.TESORERIA_METODOS 
        : Object.values(window.TESORERIA_METODOS || {});

    // --- VARIABLES RECEPCIÓN PARCIAL / MULTI-ALMACÉN ---
    const modalRecepcionEl = document.getElementById('modalRecepcionCompra');
    const modalRecepcion = new bootstrap.Modal(modalRecepcionEl, { focus: false });
    const recepcionOrdenId = document.getElementById('recepcionOrdenId');
    const recepcionProveedorNombre = document.getElementById('recepcionProveedorNombre');
    const recepcionFecha = document.getElementById('recepcionFecha');
    const recepcionObservaciones = document.getElementById('recepcionObservaciones');
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

    function obtenerFechaLocalISO() {
        const ahora = new Date();
        const year = ahora.getFullYear();
        const month = String(ahora.getMonth() + 1).padStart(2, '0');
        const day = String(ahora.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatearCantidadRecepcion(valor) {
        return Number(valor || 0).toFixed(DECIMALES_RECEPCION);
    }

    function formatearFechaDMY(fechaTexto) {
        const texto = String(fechaTexto || '').trim();
        if (!texto) return '-';

        const soloFecha = texto.split(' ')[0].slice(0, 10);
        const match = soloFecha.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return texto;

        const [, anio, mes, dia] = match;
        return `${dia}/${mes}/${anio}`;
    }

    function recargarPagina() {
        const nextUrl = new URL(window.location.href);
        const params = nextUrl.searchParams;

        params.delete('accion');
        if (filtroBusqueda.value.trim()) params.set('q', filtroBusqueda.value.trim()); else params.delete('q');
        if (filtroEstado.value !== '') params.set('estado', filtroEstado.value); else params.delete('estado');
        if (filtroFechaDesde.value) params.set('fecha_desde', filtroFechaDesde.value); else params.delete('fecha_desde');
        if (filtroFechaHasta.value) params.set('fecha_hasta', filtroFechaHasta.value); else params.delete('fecha_hasta');

        if (typeof window.navigateWithoutReload === 'function') {
            window.navigateWithoutReload(nextUrl, false);
            return;
        }

        window.location.href = nextUrl.toString();
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
        const sim = document.getElementById('ordenMoneda')?.value === 'USD' ? '$' : 'S/';
        fila.querySelector('.detalle-subtotal').innerHTML = `<span class="simbolo-moneda">${sim}</span> ${subtotal.toFixed(2)}`;
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

        const sim = document.getElementById('ordenMoneda')?.value === 'USD' ? '$' : 'S/';
        if (ordenSubtotal) ordenSubtotal.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${subtotal.toFixed(2)}`;
        if (ordenIgv) ordenIgv.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${igv.toFixed(2)}`;
        if (ordenTotal) ordenTotal.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${total.toFixed(2)}`;

        // Validación Dinámica del Switch de Pago en Compras
        if (switchCobroInmediatoCompra) {
            if (total <= 0) {
                switchCobroInmediatoCompra.disabled = true;
                
                if (switchCobroInmediatoCompra.checked) {
                    switchCobroInmediatoCompra.checked = false;
                    if (seccionCobroInmediatoCompra) seccionCobroInmediatoCompra.classList.add('d-none');
                    if (contenedorMetodosPagoCompra) contenedorMetodosPagoCompra.innerHTML = '';
                    calcularTotalPagoInmediatoCompra();
                }
            } else {
                if (!modalSoloLecturaActiva) {
                    switchCobroInmediatoCompra.disabled = false;
                }
            }
        }
        
        // Reemplaza este bloque dentro de recalcularTotalGeneral()
        if (switchCobroInmediatoCompra && switchCobroInmediatoCompra.checked) {
            const filasPago = contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato');
            
            if (filasPago.length === 1) { 
                const inputMonto = filasPago[0].querySelector('.input-monto-inmediato');
                inputMonto.value = total.toFixed(2);
                
                // 👇 LA MAGIA PRO: Disparamos el evento 'input' artificialmente.
                // Esto engaña a JS haciéndole creer que el usuario tecleó el nuevo número,
                // lo que ejecutará en cascada el cálculo del T.C. y la validación de saldos.
                inputMonto.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                calcularTotalPagoInmediatoCompra();
            }
        }
    }

    if (tipoImpuesto) {
        tipoImpuesto.addEventListener('change', recalcularTotalGeneral);
    }

    const ordenMoneda = document.getElementById('ordenMoneda');
    if (ordenMoneda) {
        ordenMoneda.addEventListener('change', () => {
            recalcularTotalGeneral();
            const sim = ordenMoneda.value === 'USD' ? '$' : 'S/';
            document.querySelectorAll('.simbolo-moneda').forEach(el => el.textContent = sim);
        });
    }

    async function actualizarUnidadPorItem(fila, itemGuardado = null) {
        const inputItem = fila.querySelector('.detalle-item');
        const inputUnidad = fila.querySelector('.detalle-unidad-compra');
        const info = fila.querySelector('.detalle-conversion-info');
        const inputCosto = fila.querySelector('.detalle-costo');
        const requestToken = String(Date.now() + Math.random());
        fila.dataset.unidadRequestToken = requestToken;

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
            if (fila.dataset.unidadRequestToken !== requestToken) {
                return;
            }
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
            tomSelectItem = initSelectLocal(inputItem, {
                placeholder: 'Buscar ítem...',
                dropdownParent: 'body',
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

        if (ordenMoneda) {
            ordenMoneda.disabled = deshabilitar;
        }

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

        if (switchCobroContainerCompra) {
            switchCobroContainerCompra.style.display = deshabilitar ? 'none' : 'block';
        }
        if (switchCobroInmediatoCompra) {
            switchCobroInmediatoCompra.disabled = deshabilitar;
            if (deshabilitar) switchCobroInmediatoCompra.checked = false;
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
        
        if (document.getElementById('ordenMoneda')) document.getElementById('ordenMoneda').value = 'PEN';
        ordenTotal.innerHTML = `<span class="simbolo-moneda">S/</span> 0.00`;
        if (ordenSubtotal) ordenSubtotal.innerHTML = `<span class="simbolo-moneda">S/</span> 0.00`;
        if (ordenIgv) ordenIgv.innerHTML = `<span class="simbolo-moneda">S/</span> 0.00`;
        
        fechaEntrega.value = obtenerFechaLocalISO();
        
        if (switchCobroContainerCompra) switchCobroContainerCompra.style.display = 'block';
        if (switchCobroInmediatoCompra) {
            switchCobroInmediatoCompra.checked = false;
            switchCobroInmediatoCompra.disabled = true;
        }
        if (seccionCobroInmediatoCompra) seccionCobroInmediatoCompra.classList.add('d-none');
        if (contenedorMetodosPagoCompra) contenedorMetodosPagoCompra.innerHTML = '';
        
        calcularTotalPagoInmediatoCompra();
        setModoSoloLectura(false, 0);
    }

    async function getJson(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await res.json();
        if (!res.ok || !payload.ok) throw new Error(payload.mensaje || 'Error del servidor');
        return payload;
    }

    async function abrirModalRecepcion(idOrden) {
        try {
            const separador = urls.index.includes('?') ? '&' : '?';
            const res = await getJson(`${urls.index}${separador}accion=ver&id=${idOrden}`);
            const orden = res.data;

            recepcionOrdenId.value = orden.id;
            cerrarForzadoRecepcion.checked = false;
            
            if (recepcionProveedorNombre) {
                const proveedor = String(orden.proveedor || '').trim();
                recepcionProveedorNombre.textContent = proveedor ? `- ${proveedor}` : '';
            }
            
            if (recepcionFecha) {
                recepcionFecha.value = orden.fecha_recepcion_sugerida || obtenerFechaLocalISO();
                
                if (orden.fecha_orden) {
                    const fechaMinima = String(orden.fecha_orden).split(' ')[0];
                    recepcionFecha.min = fechaMinima;
                } else {
                    recepcionFecha.removeAttribute('min');
                }
            }
            
            if (recepcionObservaciones) {
                recepcionObservaciones.value = '';
            }
            
            tbodyRecepcion.innerHTML = '';

            const detalle = Array.isArray(orden.detalle) ? orden.detalle : [];
            detalle.forEach((linea) => {
                if (Number(linea.cantidad_pendiente) > 0.0001) {
                    agregarFilaRecepcion(linea, null);
                }
            });
            
            if (tbodyRecepcion.children.length === 0) {
                Swal.fire('Aviso', 'Esta orden ya no tiene cantidades pendientes por recepcionar.', 'info');
                return;
            }
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

    async function abrirModalDevolucion(idOrden) {
        try {
            const separador = urls.index.includes('?') ? '&' : '?';
            const res = await getJson(`${urls.index}${separador}accion=ver&id=${idOrden}`);
            const orden = res.data;

            devolucionOrdenId.value = orden.id;
            devolucionMotivo.value = '';
            if (devolucionResolucion) {
                devolucionResolucion.value = 'descuento_cxp';
                actualizarHintResolucionDevolucion();
            }
            tbodyDevolucion.innerHTML = '';
            devolucionTotal.textContent = 'S/ 0.00';

            const detalle = Array.isArray(orden.detalle) ? orden.detalle : [];
            let lineasRecibidas = 0;

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
        
        const factorCompra = parseFloat(linea.factor_conversion_aplicado || 1);
        const cantidadBaseTotal = parseFloat(linea.cantidad_base || 1);
        const subtotalLinea = parseFloat(linea.subtotal || 0);
        
        const costoBaseReal = cantidadBaseTotal > 0 ? (subtotalLinea / cantidadBaseTotal) : 0;
        const costoCompraDisplay = costoBaseReal * factorCompra; 

        const cantidadRecibidaEnUnidadCompra = factorCompra > 0 ? (cantRecibidaBase / factorCompra) : cantRecibidaBase;
        const unidadCompraLabel = (linea.unidad_nombre || '').trim();
        const mostrarResumenUnidadCompra = unidadCompraLabel !== '' && Math.abs(factorCompra - 1) > 0.0001;

        tr.dataset.idDetalle = linea.id;
        tr.dataset.idItem = linea.id_item;
        tr.dataset.costoBase = costoBaseReal; 
        tr.dataset.maxBase = cantRecibidaBase; 

        tr.innerHTML = `
            <td class="align-middle py-3 ps-3">
                <div class="fw-bold text-dark" style="font-size: 0.95rem;">${linea.item_nombre || ''}</div>
                <small class="text-muted dev-info-conversion">Unidad base: ${linea.unidad_base}</small>
            </td>
            <td class="text-center align-middle">
                ${mostrarResumenUnidadCompra ? `
                    <div class="fw-semibold text-dark">
                        ${cantidadRecibidaEnUnidadCompra.toFixed(2)} ${unidadCompraLabel}
                    </div>
                ` : ''}
                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-bold mt-1">
                    ${cantRecibidaBase.toFixed(2)} ${linea.unidad_base}
                </span>
            </td>
            <td class="text-center align-middle">
                <div class="fw-semibold text-secondary">S/ ${costoCompraDisplay.toFixed(2)}</div>
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
            
            if (linea.id_item_unidad) {
                selectUnidad.value = String(linea.id_item_unidad);
            }

            if (selectUnidad.value === '' && factorCompra > 1) {
                const optCompra = document.createElement('option');
                optCompra.value = `compra_${linea.id_item_unidad || 'orig'}`;
                optCompra.dataset.factor = String(factorCompra);
                optCompra.textContent = `${linea.unidad_nombre || 'Unidad compra'} (x ${factorCompra})`;
                selectUnidad.appendChild(optCompra);
                selectUnidad.value = optCompra.value;
            }
        } catch (e) {
            console.warn("No se pudieron cargar unidades para el ítem", linea.id_item);
            if (factorCompra > 1) {
                const optCompra = document.createElement('option');
                optCompra.value = `compra_${linea.id_item_unidad || 'orig'}`;
                optCompra.dataset.factor = String(factorCompra);
                optCompra.textContent = `${linea.unidad_nombre || 'Unidad compra'} (x ${factorCompra})`;
                selectUnidad.appendChild(optCompra);
                selectUnidad.value = optCompra.value;
            }
        }

        const recalcularLinea = () => {
            let cantInput = parseFloat(inputCant.value || 0);
            const factorSeleccionado = parseFloat(selectUnidad.options[selectUnidad.selectedIndex]?.dataset.factor || 1);
            
            let cantBaseCalculada = cantInput * factorSeleccionado;
            
            const maxInputVisible = parseFloat((cantRecibidaBase / factorSeleccionado).toFixed(2));
            
            if (Math.abs(cantInput - maxInputVisible) < 0.001) {
                cantBaseCalculada = cantRecibidaBase;
                inputCant.classList.remove('is-invalid', 'border-danger');
            } 
            else if (cantBaseCalculada > cantRecibidaBase + 0.0001) {
                cantInput = cantRecibidaBase / factorSeleccionado;
                cantBaseCalculada = cantRecibidaBase;
                inputCant.value = cantInput.toFixed(2); 
                inputCant.classList.add('is-invalid', 'border-danger');
            } else {
                inputCant.classList.remove('is-invalid', 'border-danger');
            }

            tr.dataset.cantBaseExacta = cantBaseCalculada;

            const costoUnitarioSegunUnidad = costoBaseReal * factorSeleccionado;
            const subtotal = cantInput * costoUnitarioSegunUnidad;
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
            const costoUnitarioSegunUnidad = costoBase * factor;
            total += cant * costoUnitarioSegunUnidad;
        });
        devolucionTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    // --- EVENTOS PRINCIPALES ---
    btnGuardarOrden.addEventListener('click', async () => {
        if (!idProveedor.value) return Swal.fire('Falta Proveedor', 'Debe seleccionar un proveedor.', 'warning');
        if (!fechaEntrega.value) return Swal.fire('Falta Fecha', 'La fecha de emisión es obligatoria.', 'warning');

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

        let esCobroInmediato = false;
        let metodosPagoFinales = [];

        if (switchCobroInmediatoCompra && switchCobroInmediatoCompra.checked && !modalSoloLecturaActiva) {
            esCobroInmediato = true;
            const filasPago = contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato');
            let montosPorCuenta = {};
            let saldosPorCuenta = {};
            let nombresCuentas = {};
            let sumaTotalPagos = 0;
            let errorPagos = false;

            filasPago.forEach(fila => {
                const selCuenta = fila.querySelector('.select-cuenta-inmediato');
                const selMetodo = fila.querySelector('.select-metodo-inmediato');
                const inputMonto = fila.querySelector('.input-monto-inmediato');

                if (!selCuenta.value || !selMetodo.value || !inputMonto.value) {
                    errorPagos = true;
                    return;
                }

                const idCuenta = selCuenta.value;
                const idMetodo = selMetodo.value; 
                const monto = parseFloat(inputMonto.value) || 0;
                const optCuenta = selCuenta.options[selCuenta.selectedIndex];
                const saldoDisp = parseFloat(optCuenta.getAttribute('data-saldo')) || 0;
                const nombreCuenta = optCuenta.text.split('(')[0].trim();

                if (monto <= 0) errorPagos = true;

                if (!montosPorCuenta[idCuenta]) {
                    montosPorCuenta[idCuenta] = 0;
                    saldosPorCuenta[idCuenta] = saldoDisp;
                    nombresCuentas[idCuenta] = nombreCuenta;
                }
                montosPorCuenta[idCuenta] += monto;
                sumaTotalPagos += monto;

                // Capturar Tipo de Cambio si está visible
                const inputTC = fila.querySelector('.input-tc-inmediato');
                const tcValor = (!fila.querySelector('.seccion-tipo-cambio').classList.contains('d-none') && inputTC) 
                                ? parseFloat(inputTC.value) || 0 
                                : 1; // Si no hay choque de moneda, T.C es 1

                if (tcValor <= 0 && !fila.querySelector('.seccion-tipo-cambio').classList.contains('d-none')) {
                    errorPagos = true; // Forzamos error si olvidó poner el T.C.
                }

                metodosPagoFinales.push({
                    id_cuenta: Number(idCuenta),
                    id_metodo: Number(idMetodo),
                    monto: monto,
                    tipo_cambio: tcValor // <-- ESTO VIAJA AL BACKEND
                });
            });

            if (errorPagos) {
                return Swal.fire('Error en Pagos', 'Complete la cuenta, el método y un monto mayor a cero en el pago rápido.', 'warning');
            }

            let erroresSaldo = [];
            for (const idC in montosPorCuenta) {
                if (montosPorCuenta[idC] > saldosPorCuenta[idC]) {
                    erroresSaldo.push(`La cuenta <b>${nombresCuentas[idC]}</b> no tiene fondos suficientes. Intentas retirar S/ ${montosPorCuenta[idC].toFixed(2)} pero solo dispone de S/ ${saldosPorCuenta[idC].toFixed(2)}.`);
                }
            }

            if (erroresSaldo.length > 0) {
                return Swal.fire({ icon: 'error', title: 'Fondos insuficientes', html: erroresSaldo.join('<br><br>') });
            }

            const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
            const totalPedido = parseFloat(totalTexto) || 0;
            if (sumaTotalPagos > totalPedido) {
                return Swal.fire('Aviso', 'El total pagado no puede superar el total de la orden de compra.', 'warning');
            }
        }

        try {
            const payload = {
                id: Number(ordenEnEdicionId || 0),
                id_proveedor: Number(idProveedor.value),
                fecha_emision: fechaEntrega.value,
                observaciones: observaciones.value,
                tipo_impuesto: tipoImpuesto ? tipoImpuesto.value : 'incluido',
                
                // 👇 ASEGÚRATE DE TENER ESTA LÍNEA 👇
                moneda: document.getElementById('ordenMoneda').value, 
                
                detalle,
                cobro_inmediato: esCobroInmediato,
                metodos_pago: metodosPagoFinales 
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
                fecha_recepcion: (recepcionFecha?.value || '').trim(),
                observaciones: (recepcionObservaciones?.value || '').trim(),
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
                    
                    const cantidadBaseExacta = parseFloat(tr.dataset.cantBaseExacta || (cant * factor));
                    
                    detalle.push({
                        id_documento_detalle: Number(tr.dataset.idDetalle),
                        id_item: Number(tr.dataset.idItem),
                        id_unidad: selectU.value ? Number(selectU.value) : null,
                        factor: factor,
                        cantidad_input: cant,
                        cantidad_base: cantidadBaseExacta, 
                        costo_base: parseFloat(tr.dataset.costoBase)
                    });
                    totalDevolverBase += cantidadBaseExacta;
                }
            });

            if (detalle.length === 0 || totalDevolverBase <= 0) {
                return Swal.fire('Aviso', 'Ingrese al menos una cantidad a devolver mayor a cero.', 'warning');
            }

            try {
                const separador = urls.index.includes('?') ? '&' : '?';
                const urlPost = `${urls.index}${separador}accion=guardar_devolucion`;

                const checkReemplazo = document.getElementById('devolucionEsperarReemplazo');
                const esperarReemplazo = checkReemplazo ? checkReemplazo.checked : true;

                const payload = {
                    id_orden: Number(devolucionOrdenId.value),
                    motivo: devolucionMotivo.value,
                    resolucion: devolucionResolucion.value,
                    esperar_reemplazo: esperarReemplazo, 
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

    devolucionMotivo?.addEventListener('change', actualizarLogicaDevolucionCompra);
    actualizarLogicaDevolucionCompra();

    function ocultarTooltipBoton(boton) {
        if (!boton || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        const tooltip = bootstrap.Tooltip.getInstance(boton);
        if (tooltip) {
            tooltip.hide();
        }
    }

    tbodyTabla.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        target.blur();
        ocultarTooltipBoton(target);

        const fila = target.closest('tr');
        const id = Number(target.dataset.id || fila?.dataset?.id || 0);
        if (!id) {
            Swal.fire('Error', 'No se pudo identificar la orden seleccionada. Recarga la página e inténtalo de nuevo.', 'error');
            return;
        }

        if (target.classList.contains('btn-editar')) {
            try {
                const separador = urls.index.includes('?') ? '&' : '?';
                const res = await fetch(`${urls.index}${separador}accion=ver&id=${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json();

                if (json.ok && json.data) {
                    const d = json.data;
                    const estadoDoc = Number(d.estado || 0);

                    if (estadoDoc >= 3) {
                        const modalResumenEl = document.getElementById('modalResumenCompra');
                        if (!modalResumenEl) throw new Error('El modal de resumen no está disponible.');

                        document.getElementById('resumenCompraCodigo').textContent = d.codigo || '-';
                        
                        const filaTabla = target.closest('tr');
                        const nombreProveedor = filaTabla?.querySelector('td:nth-child(2)')?.textContent?.trim() || 'Proveedor';
                        const fechaRecepcionTabla = filaTabla?.querySelector('.bi-box-arrow-in-down')?.parentElement?.textContent?.trim() || '-';
                        
                        document.getElementById('resumenCompraProveedor').textContent = nombreProveedor;
                        document.getElementById('resumenCompraFechaOrden').textContent = formatearFechaDMY(d.fecha_orden);
                        document.getElementById('resumenCompraFechaRecepcion').textContent = fechaRecepcionTabla;
                        document.getElementById('resumenCompraObservaciones').textContent = d.observaciones || 'Sin observaciones.';
                        
                        // Determinamos el símbolo para el modal de resumen también
                        const sim = d.moneda === 'USD' ? '$' : 'S/';
                        document.getElementById('resumenCompraTotalFinal').textContent = `${sim} ${Number(d.total || 0).toFixed(2)}`;

                        const tbodyResumen = document.querySelector('#tablaResumenProductosCompra tbody');
                        tbodyResumen.innerHTML = '';

                        if (d.detalle && d.detalle.length > 0) {
                            d.detalle.forEach(item => {
                                const factor = Number(item.factor_conversion_aplicado || 1);
                                const cantPedidaCompra = Number(item.cantidad || 0); 
                                const cantPedidaBase = cantPedidaCompra * factor;    
                                const cantRecibidaBase = Number(item.cantidad_recibida || 0); 
                                const cantRecibidaCompra = factor > 0 ? (cantRecibidaBase / factor) : cantRecibidaBase; 

                                const unidadCompra = item.unidad_nombre || 'UND';
                                const unidadBase = item.unidad_base || 'UND';
                                const requiereSubtitulo = factor > 1; 

                                const precio = Number(item.costo_unitario || 0);
                                const subtotal = cantRecibidaCompra * precio; 

                                let htmlPedida = `<span class="d-block fw-bold text-dark">${cantPedidaCompra.toFixed(2)} ${unidadCompra}</span>`;
                                if (requiereSubtitulo) {
                                    htmlPedida += `<small class="text-muted">(${cantPedidaBase.toFixed(2)} ${unidadBase})</small>`;
                                }

                                let htmlRecibida = `<span class="d-block fw-bold text-success">${cantRecibidaCompra.toFixed(2)} ${unidadCompra}</span>`;
                                if (requiereSubtitulo) {
                                    htmlRecibida += `<small class="text-muted">(${cantRecibidaBase.toFixed(2)} ${unidadBase})</small>`;
                                }

                                const trItem = document.createElement('tr');
                                trItem.innerHTML = `
                                    <td class="ps-3 py-2 fw-semibold text-dark">${item.item_nombre || '-'}</td>
                                    <td class="text-center py-2 align-middle">${htmlPedida}</td>
                                    <td class="text-center py-2 align-middle">${htmlRecibida}</td>
                                    <td class="text-end py-2 text-muted align-middle">${sim} ${precio.toFixed(2)}</td>
                                    <td class="text-end pe-3 py-2 fw-bold text-dark align-middle">${sim} ${subtotal.toFixed(2)}</td>
                                `;
                                tbodyResumen.appendChild(trItem);
                            });
                        } else {
                            tbodyResumen.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay productos registrados.</td></tr>';
                        }

                        const modalResumen = bootstrap.Modal.getOrCreateInstance(modalResumenEl);
                        modalResumen.show();
                        return; 
                    }
                    
                    limpiarModalOrden();
                    setOrdenEnEdicion(d.id);
                    if (tomSelectProveedor) tomSelectProveedor.setValue(d.id_proveedor);
                    else idProveedor.value = d.id_proveedor;

                    fechaEntrega.value = d.fecha_orden || d.fecha_entrega || '';
                    observaciones.value = d.observaciones || '';
                    if (tipoImpuesto && d.tipo_impuesto) tipoImpuesto.value = d.tipo_impuesto;

                    const monedaSelect = document.getElementById('ordenMoneda');
                    if (monedaSelect) {
                        monedaSelect.value = d.moneda || 'PEN';
                        monedaSelect.dispatchEvent(new Event('change')); 
                    }

                    if (d.detalle && d.detalle.length > 0) d.detalle.forEach((item) => agregarFila(item));
                    else agregarFila();

                    if (d.cobro_inmediato == 1 || d.cobro_inmediato === true) {
                        if (switchCobroInmediatoCompra) {
                            switchCobroInmediatoCompra.checked = true;
                            if (seccionCobroInmediatoCompra) seccionCobroInmediatoCompra.classList.remove('d-none');
                            
                            if (d.metodos_pago && Array.isArray(d.metodos_pago)) {
                                d.metodos_pago.forEach(pago => {
                                    const divPago = agregarFilaPagoInmediatoCompra(pago.monto);
                                    if (divPago) {
                                        const selCuenta = divPago.querySelector('.select-cuenta-inmediato');
                                        const selMetodo = divPago.querySelector('.select-metodo-inmediato');
                                        selCuenta.value = pago.id_cuenta;
                                        
                                        if (typeof filtrarMetodosPorCuentaCompras === 'function') {
                                            filtrarMetodosPorCuentaCompras(selCuenta, selMetodo);
                                        }
                                        selMetodo.disabled = false;
                                        selMetodo.value = pago.id_metodo;
                                    }
                                });
                            }
                        }
                    }
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

        if (target.classList.contains('btn-revertir-borrador')) {
            const confirm = await Swal.fire({
                title: '¿Revertir a borrador?',
                text: 'La orden volverá al estado inicial para que puedas editarla antes de recepción.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, revertir',
            });

            if (!confirm.isConfirmed) return;

            try {
                const res = await postJson(urls.revertirBorrador, { id }, target);
                await Swal.fire('Revertida', res.mensaje, 'success');
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

    if (filtroFechaDesde && filtroFechaHasta) {
        filtroFechaDesde.addEventListener('change', () => {
            if (filtroFechaDesde.value) {
                filtroFechaHasta.min = filtroFechaDesde.value; 
                if (filtroFechaHasta.value && filtroFechaHasta.value < filtroFechaDesde.value) {
                    filtroFechaHasta.value = filtroFechaDesde.value;
                }
            } else {
                filtroFechaHasta.min = '';
            }
        });

        filtroFechaHasta.addEventListener('change', () => {
            if (filtroFechaHasta.value) {
                filtroFechaDesde.max = filtroFechaHasta.value; 
                if (filtroFechaDesde.value && filtroFechaDesde.value > filtroFechaHasta.value) {
                    filtroFechaDesde.value = filtroFechaHasta.value;
                }
            } else {
                filtroFechaDesde.max = '';
            }
        });

        if (filtroFechaDesde.value) filtroFechaHasta.min = filtroFechaDesde.value;
        if (filtroFechaHasta.value) filtroFechaDesde.max = filtroFechaHasta.value;
    }

    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                recargarPagina();
            }
        });
    }

    if (filtroEstado) {
        filtroEstado.addEventListener('change', recargarPagina);
    }
    
    const btnFiltrarFechas = document.getElementById('btnFiltrarFechas'); 
    if (btnFiltrarFechas) {
        btnFiltrarFechas.addEventListener('click', () => {
            recargarPagina();
        });
    }

    if (fechaEntrega && !fechaEntrega.value) {
        fechaEntrega.value = obtenerFechaLocalISO();
    }

    function filtrarMetodosPorCuentaCompras(selectCuenta, selectMetodo) {
        if (!selectCuenta || !selectMetodo) return;

        const idCuentaSeleccionada = parseInt(selectCuenta.value);
        const valorPrevio = selectMetodo.value; 
        
        selectMetodo.innerHTML = '<option value="" selected disabled>Método...</option>';

        const arrayCuentas = Array.isArray(window.TESORERIA_CUENTAS) ? window.TESORERIA_CUENTAS : Object.values(window.TESORERIA_CUENTAS || {});
        const arrayMetodos = Array.isArray(window.TESORERIA_METODOS) ? window.TESORERIA_METODOS : Object.values(window.TESORERIA_METODOS || {});

        if (!idCuentaSeleccionada) {
            arrayMetodos.forEach(m => selectMetodo.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
            return;
        }

        const cuentaObj = arrayCuentas.find(c => parseInt(c.id) === idCuentaSeleccionada);
        if (!cuentaObj) return;

        let metodosPermitidos = [];
        let tieneFiltro = false; 

        let rawMetodos = cuentaObj.metodos_pago;

        if (rawMetodos === null || rawMetodos === "" || rawMetodos === "null" || rawMetodos === "[]") {
            tieneFiltro = true;       
            metodosPermitidos = [];   
        } 
        else if (rawMetodos !== undefined) {
            try {
                let parsed = rawMetodos;
                while(typeof parsed === 'string') { 
                    parsed = JSON.parse(parsed); 
                }
                
                if (Array.isArray(parsed)) {
                    metodosPermitidos = parsed;
                    tieneFiltro = true;
                }
            } catch (e) {
                console.error("Error al parsear el JSON de métodos:", rawMetodos);
            }
        }

        const permitidosNormalizados = metodosPermitidos.map(m => String(m).trim().toLowerCase());
        let primerValido = null;
        let encontroPrevio = false;

        arrayMetodos.forEach(m => {
            const nombreDB = String(m.nombre).trim().toLowerCase();
            const esValido = !tieneFiltro || permitidosNormalizados.some(p => nombreDB.includes(p) || p.includes(nombreDB));

            if (esValido) {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.nombre;
                selectMetodo.appendChild(opt);

                if (!primerValido) primerValido = m.id;
                if (String(m.id) === String(valorPrevio)) encontroPrevio = true;
            }
        });

        if (selectMetodo.options.length <= 1) {
            selectMetodo.innerHTML = '<option value="" selected disabled>Sin métodos configurados</option>';
        } else {
            if (encontroPrevio) selectMetodo.value = valorPrevio;
            else if (primerValido) selectMetodo.value = primerValido;
        }
    }

    function calcularTotalPagoInmediatoCompra() {
        if (!contenedorMetodosPagoCompra) return;
        let total = 0;
        const filas = contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato');
        
        filas.forEach(fila => {
            const monto = parseFloat(fila.querySelector('.input-monto-inmediato').value) || 0;
            total += monto;
        });
        
        if (totalPagadoInmediatoCompra) {
            const sim = document.getElementById('ordenMoneda')?.value === 'USD' ? '$' : 'S/';
            totalPagadoInmediatoCompra.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${total.toFixed(2)}`;
            const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
            const totalPedido = parseFloat(totalTexto) || 0;

            if (total > totalPedido) totalPagadoInmediatoCompra.className = 'fw-bold fs-5 text-danger'; 
            else if (total === totalPedido && total > 0) totalPagadoInmediatoCompra.className = 'fw-bold fs-5 text-success';
            else totalPagadoInmediatoCompra.className = 'fw-bold fs-5 text-dark';
        }

        if (btnAgregarPagoInmediatoCompra) {
            if (filas.length === 0) {
                btnAgregarPagoInmediatoCompra.disabled = false;
            } else {
                const ultimaFila = filas[filas.length - 1];
                const cuenta = ultimaFila.querySelector('.select-cuenta-inmediato').value;
                const metodo = ultimaFila.querySelector('.select-metodo-inmediato').value;
                const monto = parseFloat(ultimaFila.querySelector('.input-monto-inmediato').value) || 0;
                
                btnAgregarPagoInmediatoCompra.disabled = !(cuenta && metodo && monto > 0);
            }
        }
    }

    function agregarFilaPagoInmediatoCompra(montoSugerido = '') {
        if (!contenedorMetodosPagoCompra) return;
        
        let opcionesCuentas = '<option value="" selected disabled>Cuenta Origen...</option>';
        cuentasDisponibles.forEach(c => { 
            const saldo = parseFloat(c.saldo_actual || c.saldo || 0);
            const monedaCuenta = c.moneda || 'PEN'; // Extraemos la moneda de la cuenta
            opcionesCuentas += `<option value="${c.id}" data-saldo="${saldo}" data-moneda="${monedaCuenta}">${c.nombre} (Disp: ${monedaCuenta} ${saldo.toFixed(2)})</option>`; 
        });

        const numFilas = contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato').length;
        const div = document.createElement('div');
        div.className = 'd-flex flex-column gap-2 bg-white p-2 rounded border border-success-subtle fila-pago-inmediato mb-2';
        
        const sim = document.getElementById('ordenMoneda')?.value === 'USD' ? '$' : 'S/';

        div.innerHTML = `
            <div class="d-flex flex-column flex-sm-row gap-2 align-items-start align-items-sm-center w-100">
                <div class="w-100">
                    <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-cuenta-inmediato" required>
                        ${opcionesCuentas}
                    </select>
                </div>
                <div class="w-100">
                    <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-metodo-inmediato" required disabled>
                        <option value="" selected disabled>Método...</option>
                    </select>
                </div>
                <div class="w-100 d-flex gap-2 align-items-center">
                    <div class="input-group input-group-sm w-100">
                        <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle simbolo-moneda">${sim}</span>
                        <input type="number" class="form-control text-end text-success fw-bold border-secondary-subtle input-monto-inmediato" min="0.01" step="0.01" placeholder="0.00" value="${montoSugerido}" required readonly>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-quitar-pago ${numFilas === 0 ? 'd-none' : ''} px-2" title="Quitar pago"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            
            <!-- SECCIÓN TIPO DE CAMBIO (Oculta por defecto) -->
            <div class="seccion-tipo-cambio d-none bg-light p-2 rounded border border-warning-subtle d-flex flex-wrap gap-3 align-items-center mt-1">
                <span class="text-warning-emphasis fw-bold small"><i class="bi bi-arrow-left-right"></i> Conversión Requerida:</span>
                <div class="input-group input-group-sm" style="width: 140px;">
                    <span class="input-group-text bg-white border-warning-subtle text-muted">T.C.</span>
                    <input type="number" class="form-control border-warning-subtle input-tc-inmediato" step="0.001" min="0.001" placeholder="Ej: 3.80">
                </div>
                <div class="small fw-bold text-secondary ms-auto">
                    Se debitarán: <span class="text-danger monto-final-debito">0.00</span> <span class="moneda-cuenta-label">PEN</span>
                </div>
            </div>
        `;

        contenedorMetodosPagoCompra.appendChild(div);

        const selCuentaInmediato = div.querySelector('.select-cuenta-inmediato');
        const selMetodoInmediato = div.querySelector('.select-metodo-inmediato');
        const inputMontoInmediato = div.querySelector('.input-monto-inmediato');
        const btnQuitar = div.querySelector('.btn-quitar-pago');
        
        // Elementos del Tipo de Cambio
        const seccionTC = div.querySelector('.seccion-tipo-cambio');
        const inputTC = div.querySelector('.input-tc-inmediato');
        const spanMontoDebito = div.querySelector('.monto-final-debito');
        const spanLabelMoneda = div.querySelector('.moneda-cuenta-label');

        // Función interna para calcular el débito real
        const calcularDebitoReal = () => {
            const opt = selCuentaInmediato.options[selCuentaInmediato.selectedIndex];
            if (!opt || !opt.value) return;

            const monedaCuenta = opt.getAttribute('data-moneda');
            const monedaOrden = document.getElementById('ordenMoneda').value;
            const montoPago = parseFloat(inputMontoInmediato.value) || 0;

            if (monedaCuenta !== monedaOrden) {
                // Hay choque de monedas, mostramos el T.C.
                seccionTC.classList.remove('d-none');
                spanLabelMoneda.textContent = monedaCuenta;
                inputTC.required = true;

                const tc = parseFloat(inputTC.value) || 0;
                
                // LÓGICA DE CONVERSIÓN
                // Si la orden es en USD y la cuenta en PEN -> Multiplicamos (USD -> PEN)
                // Si la orden es en PEN y la cuenta en USD -> Dividimos (PEN -> USD)
                let debitoReal = 0;
                if (monedaOrden === 'USD' && monedaCuenta === 'PEN') {
                    debitoReal = montoPago * tc;
                } else if (monedaOrden === 'PEN' && monedaCuenta === 'USD') {
                    debitoReal = tc > 0 ? (montoPago / tc) : 0;
                }

                spanMontoDebito.textContent = debitoReal.toFixed(2);

                // Validar saldo en base al débito real, NO al monto del pago
                const saldoDisp = parseFloat(opt.getAttribute('data-saldo')) || 0;
                if (debitoReal > saldoDisp) {
                    spanMontoDebito.classList.replace('text-danger', 'text-bg-danger');
                } else {
                    spanMontoDebito.classList.replace('text-bg-danger', 'text-danger');
                }

            } else {
                // Monedas iguales, ocultamos el T.C.
                seccionTC.classList.add('d-none');
                inputTC.required = false;
                inputTC.value = '';
                spanMontoDebito.textContent = montoPago.toFixed(2);
                
                // Validar saldo normal
                const saldoDisp = parseFloat(opt.getAttribute('data-saldo')) || 0;
                inputMontoInmediato.setAttribute('max', saldoDisp > 0 ? saldoDisp : 0);
                if(parseFloat(inputMontoInmediato.value) > saldoDisp) {
                    inputMontoInmediato.value = saldoDisp > 0 ? saldoDisp.toFixed(2) : '';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'info', title: 'Monto reajustado', text: 'El monto supera el saldo disponible de esta cuenta.', timer: 2500, showConfirmButton: false });
                    }
                }
            }
        };

        selCuentaInmediato.addEventListener('change', () => {
            filtrarMetodosPorCuentaCompras(selCuentaInmediato, selMetodoInmediato);
            selMetodoInmediato.disabled = !selCuentaInmediato.value;
            selMetodoInmediato.value = '';
            inputMontoInmediato.readOnly = true;

            calcularDebitoReal();
            calcularTotalPagoInmediatoCompra();
        });

        selMetodoInmediato.addEventListener('change', () => {
            inputMontoInmediato.readOnly = !selMetodoInmediato.value;
            if (selMetodoInmediato.value && !inputMontoInmediato.value) {
                inputMontoInmediato.focus();
            }
            calcularTotalPagoInmediatoCompra();
        });

        inputMontoInmediato.addEventListener('input', () => {
            calcularTotalPagoInmediatoCompra();
            calcularDebitoReal();
        });

        inputTC.addEventListener('input', calcularDebitoReal);
        
        btnQuitar.addEventListener('click', () => {
            div.remove();
            const filasRestantes = contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato');
            if (filasRestantes.length === 1) filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
            calcularTotalPagoInmediatoCompra();
        });

        // Trigger inicial si se crea con cuenta ya seleccionada
        calcularDebitoReal();
        calcularTotalPagoInmediatoCompra();
        return div;
    }

    switchCobroInmediatoCompra?.addEventListener('change', (e) => {
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalNumerico = parseFloat(totalTexto) || 0;

        if (e.target.checked && totalNumerico <= 0) {
            e.target.checked = false;
            seccionCobroInmediatoCompra.classList.add('d-none');
            contenedorMetodosPagoCompra.innerHTML = '';
            return;
        }

        if (e.target.checked) {
            seccionCobroInmediatoCompra.classList.remove('d-none');
            contenedorMetodosPagoCompra.innerHTML = '';
            agregarFilaPagoInmediatoCompra(totalNumerico > 0 ? totalNumerico.toFixed(2) : '');
        } else {
            seccionCobroInmediatoCompra.classList.add('d-none');
            contenedorMetodosPagoCompra.innerHTML = '';
            calcularTotalPagoInmediatoCompra();
        }
    });

    btnAgregarPagoInmediatoCompra?.addEventListener('click', () => {
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalPedido = parseFloat(totalTexto) || 0;
        
        let totalPagadoHastaAhora = 0;
        contenedorMetodosPagoCompra.querySelectorAll('.input-monto-inmediato').forEach(inp => {
            totalPagadoHastaAhora += parseFloat(inp.value) || 0;
        });

        let faltante = totalPedido - totalPagadoHastaAhora;
        if (faltante < 0) faltante = 0;

        agregarFilaPagoInmediatoCompra(faltante > 0 ? faltante.toFixed(2) : '');
        contenedorMetodosPagoCompra.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
    });
})();