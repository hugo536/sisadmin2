(async function initVentas() {
    // --- VARIABLES GLOBALES DE IMPRESIÓN ---
    window.pedidoIdPendienteImpresion = window.pedidoIdPendienteImpresion || 0;

    window.imprimirPedido = function(id) {
        const app = document.getElementById('ventasApp');
        if (!app) return;

        window.pedidoIdPendienteImpresion = Number(id) || 0;

        const inputPaginas = document.getElementById('cantidadPaginasPedido');
        const selectTipo = document.getElementById('tipoDocumentoImprimir');
        if (inputPaginas) inputPaginas.value = 1;
        if (selectTipo) selectTipo.value = 'imprimir'; // Por defecto Pedido Interno

        const modalEl = document.getElementById('modalImpresionPedido');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    // --- EVENTO NUEVA VENTA ---
    const btnNuevaVenta = document.getElementById('btnNuevaVenta');
    if (btnNuevaVenta) {
        btnNuevaVenta.addEventListener('click', async () => {
            try {
                limpiarModalVenta();
                await agregarFilaVenta(); 
                actualizarBloqueoFormularioPorCliente();

                const btnGuardar = document.getElementById('btnGuardarVenta');
                btnGuardar.style.display = 'block';
                btnGuardar.textContent = 'Guardar Pedido';

                if (!document.getElementById('alertaBorradorInfo')) {
                    const contenedor = document.getElementById('alertaBorradorContenedor');
                    if (contenedor) {
                        contenedor.innerHTML = `<span id="alertaBorradorInfo" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium px-2 py-1"><i class="bi bi-info-circle me-1"></i>Borrador: No descuenta stock físico</span>`;
                    }
                }

                modalVenta.show();
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'No se pudo abrir el formulario de pedido.', 'error');
            }
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

    // --- 1. CLIENTES (Tom Select con AJAX) ---
    let tomSelectCliente = null;
    const idClienteEl = document.getElementById('idCliente');
    const tomSelectListo = await esperarTomSelect();

    if (!tomSelectListo) {
        console.warn('TomSelect no se pudo cargar en Ventas. Revise conectividad o CDN.');
    }

    const obtenerDropdownParentModalVenta = () => document.body;

    if (idClienteEl && tomSelectListo) {
        tomSelectCliente = new TomSelect("#idCliente", {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            allowEmptyOption: true,
            plugins: ['clear_button'],
            placeholder: "Buscar cliente por nombre o documento...",
            dropdownParent: obtenerDropdownParentModalVenta(),
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
    const modalDevolucionVentaEl = document.getElementById('modalDevolucionVenta');
    const modalDevolucionVenta = modalDevolucionVentaEl ? new bootstrap.Modal(modalDevolucionVentaEl) : null;

    // Endurecer limpieza de estado visual para evitar "pantalla bloqueada" por
    // backdrops huérfanos o body con clases de scroll-lock cuando el modal cierra.
    modalVentaEl?.addEventListener('hidden.bs.modal', () => {
        const hayModalesAbiertos = document.querySelectorAll('.modal.show').length > 0;
        if (!hayModalesAbiertos) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
            document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        }
    });

    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    const templateFilaVenta = document.getElementById('templateFilaVenta');
    
    const ventaId = document.getElementById('ventaId');
    const idCliente = document.getElementById('idCliente');
    const fechaEmision = document.getElementById('fechaEmision');
    const ventaObservaciones = document.getElementById('ventaObservaciones');

    const despachoFecha = document.getElementById('despachoFecha');
    
    // --- VARIABLES DE VENTA/DONACIÓN ---
    const tipoOperacion = document.getElementById('tipoOperacion');
    const tipoImpuesto = document.getElementById('tipoImpuesto');
    const ventaSubtotal = document.getElementById('ventaSubtotal');
    const ventaIgv = document.getElementById('ventaIgv');
    const ventaTotal = document.getElementById('ventaTotal');
    const ventaPesoTotal = document.getElementById('ventaPesoTotal');

    const tbodyDespacho = document.querySelector('#tablaDetalleDespacho tbody');
    const despachoDocumentoId = document.getElementById('despachoDocumentoId');
    const despachoObservaciones = document.getElementById('despachoObservaciones');
    const cerrarForzado = document.getElementById('cerrarForzado');

    // --- NUEVO: VARIABLES DE TESORERÍA Y COBRO INMEDIATO ---
    const cuentasDisponibles = window.TESORERIA_CUENTAS || [];
    const metodosDisponibles = window.TESORERIA_METODOS || [];
    
    const switchCobroContainer = document.getElementById('switchCobroContainer');
    const switchCobroInmediato = document.getElementById('switchCobroInmediato');
    const seccionCobroInmediato = document.getElementById('seccionCobroInmediato');
    const contenedorMetodosPago = document.getElementById('contenedorMetodosPago');
    const btnAgregarPagoInmediato = document.getElementById('btnAgregarPagoInmediato');
    const totalPagadoInmediato = document.getElementById('totalPagadoInmediato');
    
    // --- NUEVO: Referencias DOM para Envases Retornables ---
    const seccionRetornoEnvases = document.getElementById('seccionRetornoEnvasesDespacho');
    const contenedorRetornoEnvases = document.getElementById('contenedorRetornoEnvases');
    // -------------------------------------------------------

    const tbodyDevolucionVenta = document.querySelector('#tablaDetalleDevolucionVenta tbody');
    const devolucionVentaDocumentoId = document.getElementById('devolucionVentaDocumentoId');
    const devolucionVentaMotivo = document.getElementById('devolucionVentaMotivo');
    const devolucionVentaResolucion = document.getElementById('devolucionVentaResolucion');
    const devolucionVentaTotal = document.getElementById('devolucionVentaTotal');
    const devolucionVentaMotivoHint = document.getElementById('devolucionVentaMotivoHint');
    const devolucionVentaResolucionHint = document.getElementById('devolucionVentaResolucionHint');

    const DEVOLUCION_VENTA_MOTIVOS = {
        producto_incorrecto: { label: 'Producto incorrecto entregado', reingresaInventario: true, hint: 'La mercadería regresa al stock vendible.' },
        error_despacho: { label: 'Error de despacho / cantidad excedente', reingresaInventario: true, hint: 'La devolución corrige la salida y repone stock vendible.' },
        cliente_rechaza: { label: 'Cliente rechaza pedido (packs sellados)', reingresaInventario: true, hint: 'La mercadería vuelve al stock vendible si está sellada e intacta.' },
        producto_defectuoso: { label: 'Producto defectuoso, roto o dañado', reingresaInventario: false, hint: 'No reingresa a stock vendible (cuarentena/merma).' },
    };

    const DEVOLUCION_VENTA_RESOLUCIONES = {
        saldo_favor: 'Se registra como saldo a favor del cliente (sin salida de caja).',
        descuento_cxc: 'Se descuenta en CxC / próxima facturación.',
        salida_dinero: 'Se registra para reembolso en tesorería (salida de dinero).',
        reembolso_dinero: 'Se registra para reembolso en tesorería (salida de dinero).',
    };

    function actualizarHintDevolucionVenta() {
        if (devolucionVentaMotivoHint) {
            const motivoSeleccionado = devolucionVentaMotivo?.value || '';
            const motivoCfg = DEVOLUCION_VENTA_MOTIVOS[motivoSeleccionado];
            devolucionVentaMotivoHint.textContent = motivoCfg
                ? motivoCfg.hint
                : 'Selecciona un motivo para definir cómo tratar la mercadería devuelta.';
        }

        if (devolucionVentaResolucionHint) {
            const resolucionSeleccionada = devolucionVentaResolucion?.value || '';
            const resolucionHint = DEVOLUCION_VENTA_RESOLUCIONES[resolucionSeleccionada];
            devolucionVentaResolucionHint.textContent = resolucionHint
                || 'Selecciona una resolución comercial para registrar el impacto financiero.';
        }
    }

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');
    const filtroOrdenFecha = document.getElementById('filtroOrdenFecha');

    const estadoBusquedaItems = { tieneAcuerdo: false, listaVacia: false };
    let bloqueoEdicionVenta = false;

    function clienteSeleccionado() {
        return Number(tomSelectCliente ? tomSelectCliente.getValue() : idCliente.value || 0) > 0;
    }

    function actualizarBloqueoFormularioPorCliente() {
        const bloquearControlesVenta = bloqueoEdicionVenta || !clienteSeleccionado();
        const esDonacion = tipoOperacion && tipoOperacion.value === 'DONACION';

        const btnAgregarFilaVenta = document.getElementById('btnAgregarFilaVenta');
        const btnGuardarVenta = document.getElementById('btnGuardarVenta');

        if (btnAgregarFilaVenta) btnAgregarFilaVenta.disabled = bloquearControlesVenta;
        if (btnGuardarVenta) btnGuardarVenta.disabled = bloquearControlesVenta;

        if (switchCobroContainer) {
            switchCobroContainer.style.display = (bloqueoEdicionVenta || esDonacion) ? 'none' : 'block';
        }

        if (switchCobroInmediato) {
            switchCobroInmediato.disabled = bloquearControlesVenta || esDonacion;
            if (bloquearControlesVenta || esDonacion) switchCobroInmediato.checked = false;
        }

        if (seccionCobroInmediato && (bloquearControlesVenta || esDonacion)) {
            seccionCobroInmediato.classList.add('d-none');
            if (esDonacion && contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
        }

        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            fila.querySelectorAll('input, button').forEach((control) => {
                control.disabled = bloquearControlesVenta;
            });

            const selectItem = fila.querySelector('.detalle-item');
            if (selectItem?.tomselect) {
                if (bloquearControlesVenta) selectItem.tomselect.disable();
                else selectItem.tomselect.enable();
            } else if (selectItem) {
                selectItem.disabled = bloquearControlesVenta;
            }
        });
    }

    // ==============================================================
    // --- NUEVO: LÓGICA DE COBRO INMEDIATO MÚLTIPLE ---
    // ==============================================================
    function calcularTotalCobroInmediato() {
        if (!contenedorMetodosPago) return;
        let total = 0;
        contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato').forEach(fila => {
            const monto = parseFloat(fila.querySelector('.input-monto-inmediato').value) || 0;
            total += monto;
        });
        
        if (totalPagadoInmediato) {
            totalPagadoInmediato.textContent = `S/ ${total.toFixed(2)}`;
            
            // Lógica de colores UX:
            const totalTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
            const totalPedido = parseFloat(totalTexto) || 0;

            if (total > totalPedido) {
                totalPagadoInmediato.className = 'fw-bold fs-5 text-danger'; // Rojo si se pasó
            } else if (total === totalPedido && total > 0) {
                totalPagadoInmediato.className = 'fw-bold fs-5 text-success'; // Verde si está exacto
            } else {
                totalPagadoInmediato.className = 'fw-bold fs-5 text-dark'; // Oscuro si falta
            }
        }
    }

    function agregarFilaPagoInmediato(montoSugerido = '') {
        if (!contenedorMetodosPago) return;
        
        let opcionesCuentas = '<option value="" selected disabled>Cuenta Destino...</option>';
        cuentasDisponibles.forEach(c => { opcionesCuentas += `<option value="${c.id}">${c.nombre} (${c.moneda})</option>`; });

        let opcionesMetodos = '<option value="" selected disabled>Método...</option>';
        metodosDisponibles.forEach(m => { opcionesMetodos += `<option value="${m.id}">${m.nombre}</option>`; });

        const numFilas = contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato').length;

        const div = document.createElement('div');
        div.className = 'd-flex flex-column flex-sm-row gap-2 align-items-start align-items-sm-center bg-white p-2 rounded border border-success-subtle fila-pago-inmediato';
        div.innerHTML = `
            <div class="w-100">
                <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-cuenta-inmediato" required>
                    ${opcionesCuentas}
                </select>
            </div>
            <div class="w-100">
                <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-metodo-inmediato" required>
                    ${opcionesMetodos}
                </select>
            </div>
            <div class="w-100 d-flex gap-2 align-items-center">
                <div class="input-group input-group-sm w-100">
                    <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle">S/</span>
                    <input type="number" class="form-control text-end text-success fw-bold border-secondary-subtle input-monto-inmediato" min="0" step="0.01" placeholder="0.00" value="${montoSugerido}" required>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-quitar-pago ${numFilas === 0 ? 'd-none' : ''} px-2" title="Quitar pago"><i class="bi bi-trash"></i></button>
            </div>
        `;

        contenedorMetodosPago.appendChild(div);

        div.querySelector('.input-monto-inmediato').addEventListener('input', calcularTotalCobroInmediato);
        div.querySelector('.btn-quitar-pago').addEventListener('click', () => {
            div.remove();
            const filasRestantes = contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato');
            if (filasRestantes.length === 1) filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
            calcularTotalCobroInmediato();
        });
        calcularTotalCobroInmediato();
    }

    switchCobroInmediato?.addEventListener('change', (e) => {
        if (e.target.checked) {
            seccionCobroInmediato.classList.remove('d-none');
            contenedorMetodosPago.innerHTML = '';
            
            const totalTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
            const totalNumerico = parseFloat(totalTexto) || 0;
            agregarFilaPagoInmediato(totalNumerico > 0 ? totalNumerico.toFixed(2) : '');
        } else {
            seccionCobroInmediato.classList.add('d-none');
            contenedorMetodosPago.innerHTML = '';
            calcularTotalCobroInmediato();
        }
    });

    btnAgregarPagoInmediato?.addEventListener('click', () => {
        // Calcular cuánto falta pagar automáticamente
        const totalTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalPedido = parseFloat(totalTexto) || 0;
        
        let totalPagadoHastaAhora = 0;
        contenedorMetodosPago.querySelectorAll('.input-monto-inmediato').forEach(inp => {
            totalPagadoHastaAhora += parseFloat(inp.value) || 0;
        });

        let faltante = totalPedido - totalPagadoHastaAhora;
        if (faltante < 0) faltante = 0;

        // Agrega la fila con el monto sugerido que falta
        agregarFilaPagoInmediato(faltante > 0 ? faltante.toFixed(2) : '');
        contenedorMetodosPago.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
    });

    function obtenerFechaLocalISO() {
        const ahora = new Date();
        const year = ahora.getFullYear();
        const month = String(ahora.getMonth() + 1).padStart(2, '0');
        const day = String(ahora.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function configurarInputCantidad(inputCantidad, permiteDecimales, valor = null) {
        const decimalesHabilitados = Number(permiteDecimales) === 1;
        inputCantidad.dataset.permiteDecimales = decimalesHabilitados ? '1' : '0';
        inputCantidad.step = decimalesHabilitados ? '0.01' : '1';
        inputCantidad.min = '0';

        if (valor !== null) {
            if (valor === '') {
                inputCantidad.value = '';
                return;
            }
            const numero = Number(valor || 0);
            const normalizado = Math.max(0, numero);
            inputCantidad.value = decimalesHabilitados
                ? normalizado.toFixed(2)
                : String(Math.round(normalizado));
        }
    }

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

    // --- NUEVO: FUNCION PARA NUMERAR FILAS ---
    function actualizarNumeracionFilas() {
        const filas = tbodyVenta.querySelectorAll('tr');
        filas.forEach((fila, index) => {
            const celdaNumero = fila.querySelector('.fila-numero');
            if (celdaNumero) {
                celdaNumero.textContent = index + 1;
            }
        });
    }

    // --- LÓGICA VENTA (CALCULOS E IMPUESTOS) ---
    function filaVentaPayload(fila) {
        const selectElement = fila.querySelector('.detalle-item');
        const idValue = selectElement && selectElement.tomselect ? selectElement.tomselect.getValue() : (selectElement ? selectElement.value : '');

        return {
            id_item: idValue || '',
            cantidad: parseFloat(fila.querySelector('.detalle-cantidad').value || 0),
            precio_unitario: parseFloat(fila.querySelector('.detalle-precio').value || 0),
        };
    }

    function obtenerPesoUnitarioFila(fila) {
        const selectElement = fila.querySelector('.detalle-item');
        const idValue = selectElement && selectElement.tomselect ? selectElement.tomselect.getValue() : (selectElement ? selectElement.value : '');
        if (!idValue) return 0;

        if (selectElement?.tomselect?.options?.[idValue]) {
            return Number(selectElement.tomselect.options[idValue].pesoKg || 0);
        }

        return Number(fila.dataset.pesoKg || 0);
    }
   
    function recalcularTotalVenta() {
        let sumaLineas = 0;
        let pesoTotalKg = 0;
        const esDonacion = tipoOperacion && tipoOperacion.value === 'DONACION'; 

        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            const data = filaVentaPayload(fila);
            const subtotal = data.cantidad * data.precio_unitario;
            
            // Cálculos de Peso
            const pesoUnitarioKg = obtenerPesoUnitarioFila(fila);
            const pesoLineaKg = data.cantidad * pesoUnitarioKg;
            
            sumaLineas += subtotal;
            pesoTotalKg += pesoLineaKg;
            
            // --- NUEVO: Actualizar la UI del peso en la fila ---
            const infoPeso = fila.querySelector('.detalle-peso-info');
            if (infoPeso) {
                if (pesoUnitarioKg > 0) {
                    infoPeso.classList.remove('d-none');
                    infoPeso.querySelector('.peso-unitario').textContent = pesoUnitarioKg.toFixed(3);
                    infoPeso.querySelector('.peso-subtotal').textContent = pesoLineaKg.toFixed(3);
                } else {
                    infoPeso.classList.add('d-none');
                }
            }
            // --------------------------------------------------

            const celdaSubtotal = fila.querySelector('.detalle-subtotal');
            if (esDonacion) {
                celdaSubtotal.innerHTML = `
                    <div class="d-flex flex-column align-items-end" style="line-height: 1.2;">
                        <span class="text-decoration-line-through text-muted opacity-75" style="font-size: 0.75rem;">Ref: S/ ${subtotal.toFixed(2)}</span>
                        <span class="text-success fw-bold mt-1">S/ 0.00</span>
                    </div>
                `;
            } else {
                celdaSubtotal.textContent = `S/ ${subtotal.toFixed(2)}`;
            }
        });

        let subtotal = 0;
        let igv = 0;
        let total = 0;
        const tipo = tipoImpuesto ? tipoImpuesto.value : 'exonerado';

        if (esDonacion) {
            subtotal = 0;
            igv = 0;
            total = 0;
            if (ventaTotal) {
                ventaTotal.classList.remove('text-primary');
                ventaTotal.classList.add('text-success');
            }
        } else {
            if (ventaTotal) {
                ventaTotal.classList.add('text-primary');
                ventaTotal.classList.remove('text-success');
            }
            if (tipo === 'incluido') {
                total = sumaLineas;
                subtotal = total / 1.18;
                igv = total - subtotal;
            } else if (tipo === 'mas_igv') {
                subtotal = sumaLineas;
                igv = subtotal * 0.18;
                total = subtotal + igv;
            } else { 
                subtotal = sumaLineas;
                igv = 0;
                total = subtotal;
            }
        }

        if (ventaSubtotal) ventaSubtotal.textContent = `S/ ${subtotal.toFixed(2)}`;
        if (ventaIgv) ventaIgv.textContent = `S/ ${igv.toFixed(2)}`;
        if (ventaTotal) ventaTotal.textContent = esDonacion ? 'S/ 0.00 (GRATUITO)' : `S/ ${total.toFixed(2)}`;
        
        // --- MODIFICADO: Actualizar el span del peso total ---
        if (ventaPesoTotal) ventaPesoTotal.textContent = `${pesoTotalKg.toFixed(3)} kg`;

        // --- NUEVO: ACTUALIZAR AUTOMÁTICAMENTE EL MONTO DE COBRO INMEDIATO ---
        if (switchCobroInmediato && switchCobroInmediato.checked && !esDonacion) {
            const filasPago = contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato');
            if (filasPago.length === 1) { 
                filasPago[0].querySelector('.input-monto-inmediato').value = total.toFixed(2);
                calcularTotalCobroInmediato();
            }
        }
    }

    if (tipoImpuesto) {
        tipoImpuesto.addEventListener('change', recalcularTotalVenta);
    }
    
    if (tipoOperacion) {
        tipoOperacion.addEventListener('change', () => {
            if (tipoOperacion.value === 'DONACION') {
                if (tipoImpuesto) {
                    tipoImpuesto.value = 'exonerado';
                    tipoImpuesto.disabled = true;
                }
                if (switchCobroInmediato) switchCobroInmediato.checked = false;
                if (seccionCobroInmediato) seccionCobroInmediato.classList.add('d-none');
                if (contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
                if (totalPagadoInmediato) totalPagadoInmediato.textContent = 'S/ 0.00';
            } else {
                if (tipoImpuesto && !tipoImpuesto.hasAttribute('data-readonly')) {
                    tipoImpuesto.disabled = false;
                }
            }
            actualizarBloqueoFormularioPorCliente();
            recalcularTotalVenta();
        });
    }

    function obtenerItemsSeleccionados(excluirFila = null) {
        const seleccionados = new Set();
        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            if (fila === excluirFila) return;
            const selectEl = fila.querySelector('.detalle-item');
            const idItem = selectEl && selectEl.tomselect ? selectEl.tomselect.getValue() : (selectEl?.value || '');
            if (idItem !== '') seleccionados.add(idItem);
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
        const selectEl = fila.querySelector('.detalle-item');
        const idItem = selectEl && selectEl.tomselect ? selectEl.tomselect.getValue() : (selectEl?.value || '');
        if (!idItem) return;
        const cantidad = Number(fila.querySelector('.detalle-cantidad').value || 0);
        
        const inputPrecio = fila.querySelector('.detalle-precio');
        const precioNuevo = await obtenerPrecioItem(idItem, cantidad > 0 ? cantidad : 1);
        
        if (precioNuevo === null) return;
        

        if (precioNuevo > 0) {
            inputPrecio.value = precioNuevo.toFixed(4);
        }
        recalcularTotalVenta();
    }

    async function agregarFilaVenta(item = null, esBorrador = true) {
        const fragment = templateFilaVenta.content.cloneNode(true);
        const fila = fragment.querySelector('tr');
        tbodyVenta.appendChild(fragment);
        const filaReal = tbodyVenta.lastElementChild;

        const inputCantidad = filaReal.querySelector('.detalle-cantidad');
        const inputPrecio = filaReal.querySelector('.detalle-precio');
        const selectItem = filaReal.querySelector('.detalle-item');
        const btnQuitar = filaReal.querySelector('.btn-quitar-fila');

        if (!esBorrador) {
            inputCantidad.readOnly = true;
            inputCantidad.classList.add('bg-light', 'border-0');
            inputPrecio.readOnly = true;
            inputPrecio.classList.add('bg-light', 'border-0');
            if (btnQuitar) btnQuitar.style.display = 'none';
        }

        inputCantidad.addEventListener('input', async () => {
            validarCantidadVsStock(filaReal);
            await refrescarPrecioFila(filaReal);
            recalcularTotalVenta();
        });
        inputPrecio.addEventListener('input', recalcularTotalVenta);
        
        btnQuitar.addEventListener('click', () => {
            if (selectItem.tomselect) selectItem.tomselect.destroy();
            filaReal.remove();
            actualizarNumeracionFilas(); // <-- NUEVO: Actualizar números
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
            dropdownParent: obtenerDropdownParentModalVenta(),
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
                            text: `${prod.nombre || ''}`,
                            stock: parseFloat(prod.stock_actual || 0),
                            precio: parseFloat(prod.precio_venta || 0),
                            permiteDecimales: Number(prod.permite_decimales || 0),
                            pesoKg: Number(prod.peso_kg || 0)
                        }));
                        callback(items);
                    }).catch(() => callback());
            },
            onChange: function(value) {
                const selectedOption = this.options[value];
                if (selectedOption) {
                    const idSeleccionado = value || '';
                    const repetido = idSeleccionado !== '' && obtenerItemsSeleccionados(filaReal).has(idSeleccionado);
                    if (repetido) {
                        this.clear(true);
                        filaReal.querySelector('.detalle-stock').textContent = '0.00';
                        Swal.fire('Producto repetido', 'No se permiten productos repetidos en el pedido.', 'warning');
                        recalcularTotalVenta();
                        return;
                    }

                    filaReal.querySelector('.detalle-stock').textContent = selectedOption.stock.toFixed(2);
                    // Permitimos que la cajita reciba hasta 4 decimales
                    inputPrecio.value = selectedOption.precio.toFixed(4);
                    filaReal.dataset.pesoKg = String(Number(selectedOption.pesoKg || 0));
                    
                    // MEJORA UX: Evitar el 0 molesto
                    let valorActual = inputCantidad.value;
                    if (valorActual === '0' || valorActual === '0.00' || valorActual === '') {
                        valorActual = ''; // Lo forzamos a vacío
                    }
                    configurarInputCantidad(inputCantidad, selectedOption.permiteDecimales, valorActual);
                    
                    // MEJORA UX EXTRA: Enfocar la caja de texto automáticamente
                    setTimeout(() => inputCantidad.focus(), 50);
                }
                validarCantidadVsStock(filaReal);
                recalcularTotalVenta();
            },
            render: {
                no_results: () => {
                    if (estadoBusquedaItems.tieneAcuerdo && estadoBusquedaItems.listaVacia) {
                        return '<div class="no-results">Lista de productos vacía para este cliente</div>';
                    }
                    return '<div class="no-results">No se encontraron productos disponibles</div>';
                },
                loading: (data, escape) => '<div class="spinner-border spinner-border-sm text-primary m-2"></div> buscando...',
                option: function(data, escape) {
                    const stockColor = data.stock <= 0 ? 'text-danger fw-bold' : 'text-success';
                    
                    // --- MEJORA UX: Mostrar decimales solo si está permitido ---
                    let stockLabel = 'SIN STOCK';
                    if (data.stock > 0) {
                        stockLabel = (data.permiteDecimales === 1) 
                            ? Number(data.stock).toFixed(2) 
                            : String(Math.round(data.stock)); // Mostrar como entero
                    }

                    return `<div class="py-2 d-flex justify-content-between align-items-center">
                        <div><div class="fw-bold text-dark">${escape(data.text)}</div></div>
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
                text: `${item.item_nombre || ''}`,
                stock: Number(item.stock_actual || 0), 
                precio: Number(item.precio_unitario),
                permiteDecimales: Number(item.permite_decimales || 0),
                pesoKg: Number(item.peso_kg || 0)
            });
            tom.setValue(item.id_item);
            filaReal.dataset.pesoKg = String(Number(item.peso_kg || 0));
            
            if (!esBorrador) tom.disable(); 

            configurarInputCantidad(inputCantidad, item.permite_decimales, item.cantidad || 0);
            // Respetamos los 4 decimales del precio guardado en la BD
            inputPrecio.value = Number(item.precio_unitario || 0).toFixed(4);
            
            // Mostrar stock inicial de la línea cargada (sin depender de selectedOption del onChange)
            const stockItem = Number(item.stock_actual || 0);
            const stockMostrar = Number(item.permite_decimales || 0) === 1
                ? stockItem.toFixed(2)
                : String(Math.round(stockItem));
            filaReal.querySelector('.detalle-stock').textContent = stockMostrar;
            
            if (!esBorrador) {
                const cantDespachada = Number(item.cantidad_despachada || 0);
                const cantCancelada = Number(item.cantidad_cancelada || 0);
                const infoDespacho = document.createElement('div');
                infoDespacho.innerHTML = `
                    <span class="badge ${cantDespachada < item.cantidad ? 'bg-warning text-dark' : 'bg-success'} mt-1">Entregado: ${cantDespachada}</span>
                    ${cantCancelada > 0.0001 ? `<span class="badge bg-danger ms-1 mt-1">Cancelado: ${cantCancelada}</span>` : ''}
                `;
                inputCantidad.parentElement.appendChild(infoDespacho);
            } else {
                validarCantidadVsStock(filaReal); 
            }
        }

        if (!item && esBorrador) {
            configurarInputCantidad(inputCantidad, 0, '');
            setTimeout(() => {
                filaReal.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (tom) tom.focus(); 
            }, 100);
        }

        actualizarNumeracionFilas(); // <-- NUEVO: Actualizar números
        recalcularTotalVenta();
    }

    function limpiarModalVenta() {
        bloqueoEdicionVenta = false;
        ventaId.value = 0;
        if (tomSelectCliente) {
            tomSelectCliente.clear();
            tomSelectCliente.clearOptions();
        }
        fechaEmision.value = obtenerFechaLocalISO();
        ventaObservaciones.value = '';
        
        if (tipoOperacion) {
            tipoOperacion.value = 'VENTA';
            tipoOperacion.disabled = false;
        }
        if (tipoImpuesto) {
            tipoImpuesto.value = 'exonerado';
            tipoImpuesto.disabled = false;
            tipoImpuesto.removeAttribute('data-readonly');
        }

        tbodyVenta.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) select.tomselect.destroy();
        });

        tbodyVenta.innerHTML = '';
        if (ventaSubtotal) ventaSubtotal.textContent = 'S/ 0.00';
        if (ventaIgv) ventaIgv.textContent = 'S/ 0.00';
        if (ventaTotal) {
            ventaTotal.textContent = 'S/ 0.00';
            ventaTotal.classList.add('text-primary');
            ventaTotal.classList.remove('text-success');
        }
        // --- MODIFICADO: Resetear usando textContent ---
        if (ventaPesoTotal) ventaPesoTotal.textContent = '0.000 kg'; 
        
        const btnGuardar = document.getElementById('btnGuardarVenta');
        if (btnGuardar) btnGuardar.textContent = 'Guardar Pedido';
        
        const contenedorAlerta = document.getElementById('alertaBorradorContenedor');
        if (contenedorAlerta) contenedorAlerta.innerHTML = '';
        
        // Limpiar sección devoluciones
        const seccionDevoluciones = document.getElementById('seccionDevolucionesVenta');
        if (seccionDevoluciones) {
            seccionDevoluciones.classList.add('d-none');
            const tbodyDevHistorico = document.querySelector('#tablaDevolucionesHistorico tbody');
            if (tbodyDevHistorico) tbodyDevHistorico.innerHTML = '';
        }

        // --- NUEVO: Limpieza UI Cobro Inmediato ---
        if (switchCobroContainer) switchCobroContainer.style.display = 'block';
        if (switchCobroInmediato) switchCobroInmediato.checked = false;
        if (seccionCobroInmediato) seccionCobroInmediato.classList.add('d-none');
        if (contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
        if (totalPagadoInmediato) totalPagadoInmediato.textContent = 'S/ 0.00';

        actualizarBloqueoFormularioPorCliente();
    }

    document.getElementById('btnAgregarFilaVenta')?.addEventListener('click', async () => {
        await agregarFilaVenta();
        actualizarBloqueoFormularioPorCliente();
    });

    async function refrescarFilasPorCambioCliente() {
        const filas = [...tbodyVenta.querySelectorAll('tr')];
        for (const fila of filas) {
            await refrescarPrecioFila(fila);
        }
        recalcularTotalVenta();
    }

    idCliente?.addEventListener('change', async () => {
        await refrescarFilasPorCambioCliente();
        actualizarBloqueoFormularioPorCliente();
    });

    if (tomSelectCliente) {
        tomSelectCliente.on('change', async () => {
            await refrescarFilasPorCambioCliente();
            actualizarBloqueoFormularioPorCliente();
        });
    }

    document.getElementById('btnGuardarVenta')?.addEventListener('click', async () => {
        try {
            const clienteIdActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idCliente.value || 0);
            if (!clienteIdActual) throw new Error('Debe seleccionar Cliente / Beneficiario antes de continuar.');

            const filasArray = [...tbodyVenta.querySelectorAll('tr')];
            if (filasArray.length === 0) throw new Error('Debe agregar al menos un producto.');
            
            const detalle = filasArray.map((fila) => filaVentaPayload(fila));
            const ids = new Set();
            let excedeStock = false; 

            for (let i = 0; i < filasArray.length; i++) {
                const fila = filasArray[i];
                const data = detalle[i];
                
                if (!data.id_item || data.id_item === '0') {
                    throw new Error('Seleccione un producto en todas las filas.');
                }
                if (ids.has(data.id_item)) {
                    throw new Error('No se permiten productos repetidos en el pedido.');
                }
                ids.add(data.id_item);
                
                if (!validarCantidadVsStock(fila)) {
                    excedeStock = true;
                }
            }

            // --- NUEVO: Validar Cobro Inmediato ---
            let esCobroInmediato = false;
            const metodosPagoFinales = [];

            if (switchCobroInmediato && switchCobroInmediato.checked && tipoOperacion.value !== 'DONACION') {
                esCobroInmediato = true;
                contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato').forEach(fila => {
                    const idCuenta = fila.querySelector('.select-cuenta-inmediato').value;
                    const idMetodo = fila.querySelector('.select-metodo-inmediato').value;
                    const monto = parseFloat(fila.querySelector('.input-monto-inmediato').value) || 0;
                    
                    if (idCuenta && idMetodo && monto > 0) {
                        metodosPagoFinales.push({ id_cuenta: idCuenta, id_metodo: idMetodo, monto: monto });
                    }
                });

                if (metodosPagoFinales.length === 0) {
                    throw new Error("Debe completar Cuenta, Método y Monto para el cobro inmediato.");
                }
            }

            // --- NUEVO: Validar que el pago coincida EXACTAMENTE con el total del pedido ---
            if (esCobroInmediato) {
                let sumaPagos = 0;
                metodosPagoFinales.forEach(p => sumaPagos += p.monto);
                
                const totalPedTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
                const totalPedNumerico = parseFloat(totalPedTexto) || 0;

                // Tolerancia de 1 céntimo por temas de redondeo en JS
                if (Math.abs(sumaPagos - totalPedNumerico) > 0.01) {
                    throw new Error(`El total de los métodos de pago (S/ ${sumaPagos.toFixed(2)}) no coincide con el total del pedido (S/ ${totalPedNumerico.toFixed(2)}). Ajuste los montos.`);
                }
            }
            // ---------------------------------------------------------------------------------

            if (excedeStock) {
                const confirmacion = await Swal.fire({
                    icon: 'warning',
                    title: 'Stock excedido',
                    text: 'Uno o más productos superan el stock disponible. Al ser un borrador, puedes guardarlo. ¿Estás seguro de continuar?',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, guardar pedido',
                    cancelButtonText: 'Cancelar'
                });

                if (!confirmacion.isConfirmed) {
                    return; 
                }
            }

            const payload = await postJson(urls.guardar, {
                id: Number(ventaId.value || 0),
                id_cliente: Number(tomSelectCliente ? tomSelectCliente.getValue() : idCliente.value || 0),
                tipo_operacion: tipoOperacion ? tipoOperacion.value : 'VENTA', 
                fecha_emision: fechaEmision.value,
                observaciones: ventaObservaciones.value,
                tipo_impuesto: tipoImpuesto ? tipoImpuesto.value : 'exonerado',
                detalle: detalle,
                // --- AÑADE ESTAS DOS LÍNEAS ---
                cobro_inmediato: esCobroInmediato,
                metodos_pago: metodosPagoFinales
            });

            await Swal.fire('Guardado', payload.mensaje, 'success');
            modalVenta.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // ==========================================
    // --- LÓGICA DESPACHO MULTI-ALMACÉN ---
    // ==========================================
    let envasesRequeridosActuales = []; // --- NUEVO: Estado global de envases requeridos ---

    function actualizarTotalDevolucionVenta() {
        if (!tbodyDevolucionVenta || !devolucionVentaTotal) return;

        let total = 0;
        tbodyDevolucionVenta.querySelectorAll('tr').forEach((tr) => {
            const cantidad = Number(tr.querySelector('.input-devolver-venta')?.value || 0);
            const precio = Number(tr.dataset.precio || 0);
            total += (cantidad * precio);
        });

        devolucionVentaTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    function agregarFilaDevolucionVenta(linea) {
        if (!tbodyDevolucionVenta) return;

        const cantidadDespachada = Number(linea.cantidad_despachada || 0);
        const precioUnitario = Number(linea.precio_unitario || 0);

        const tr = document.createElement('tr');
        tr.dataset.idDetalle = Number(linea.id || 0);
        tr.dataset.idItem = String(linea.id_item || '');
        tr.dataset.max = String(cantidadDespachada);
        tr.dataset.precio = String(precioUnitario);

        tr.innerHTML = `
            <td class="align-middle py-3 ps-3">
                <div class="fw-bold text-dark" style="font-size: 0.95rem;">${linea.item_nombre || ''}</div>
            </td>
            <td class="text-center align-middle">
                <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2 fw-bold">
                    ${cantidadDespachada.toFixed(2)}
                </span>
            </td>
            <td class="text-center align-middle fw-semibold text-secondary">
                S/ ${precioUnitario.toFixed(2)}
            </td>
            <td class="align-middle px-2">
                <input type="number" class="form-control form-control-sm text-center input-devolver-venta fw-bold text-warning-emphasis border-warning mx-auto shadow-none"
                       min="0" max="${cantidadDespachada}" step="0.01" value="0.00" style="max-width: 120px;">
            </td>
            <td class="text-end align-middle pe-4 fw-bold text-dark subtotal-fila-dev-venta">S/ 0.00</td>
        `;

        const inputCantidad = tr.querySelector('.input-devolver-venta');
        const tdSubtotal = tr.querySelector('.subtotal-fila-dev-venta');

        const recalcularFila = () => {
            const maximo = Number(tr.dataset.max || 0);
            let cantidad = Number(inputCantidad.value || 0);

            if (cantidad < 0) cantidad = 0;
            if (cantidad > maximo) cantidad = maximo;

            inputCantidad.value = cantidad.toFixed(2);

            const subtotal = cantidad * precioUnitario;
            tdSubtotal.textContent = `S/ ${subtotal.toFixed(2)}`;
            actualizarTotalDevolucionVenta();
        };

        inputCantidad.addEventListener('input', recalcularFila);
        inputCantidad.addEventListener('change', recalcularFila);

        tbodyDevolucionVenta.appendChild(tr);
    }

    async function abrirModalDevolucionVenta(idDocumento) {
        if (!modalDevolucionVenta || !tbodyDevolucionVenta || !devolucionVentaDocumentoId) {
            throw new Error('El modal de devolución no está disponible en la vista actual.');
        }

        const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
        const venta = payload.data || {};
        const detalle = Array.isArray(venta.detalle) ? venta.detalle : [];

        devolucionVentaDocumentoId.value = String(Number(venta.id || idDocumento));
        if (devolucionVentaMotivo) devolucionVentaMotivo.value = '';
        if (devolucionVentaResolucion) devolucionVentaResolucion.value = 'descuento_cxc';
        tbodyDevolucionVenta.innerHTML = '';
        if (devolucionVentaTotal) devolucionVentaTotal.textContent = 'S/ 0.00';

        let lineasDisponibles = 0;
        detalle.forEach((linea) => {
            if (Number(linea.cantidad_despachada || 0) > 0.0001) {
                lineasDisponibles++;
                agregarFilaDevolucionVenta(linea);
            }
        });

        if (lineasDisponibles === 0) {
            throw new Error('Este pedido no tiene cantidades despachadas disponibles para devolución.');
        }

        actualizarHintDevolucionVenta();
        actualizarTotalDevolucionVenta();
        modalDevolucionVenta.show();
    }

    async function abrirModalDespacho(idDocumento) {
        const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
        const venta = payload.data;

        despachoDocumentoId.value = venta.id;
        despachoObservaciones.value = '';
        cerrarForzado.checked = false;
        tbodyDespacho.innerHTML = '';
        
        // --- NUEVO: Resetear estado de envases ---
        envasesRequeridosActuales = [];
        if (contenedorRetornoEnvases) contenedorRetornoEnvases.innerHTML = '';
        if (seccionRetornoEnvases) seccionRetornoEnvases.classList.add('d-none');

        // Configurar Fecha de Despacho
        if (despachoFecha) {
            despachoFecha.value = obtenerFechaLocalISO(); // Siempre "hoy" por defecto
            
            // Extraer solo la parte "YYYY-MM-DD" de la fecha de creación para el límite mínimo
            if (venta.created_at) {
                despachoFecha.min = venta.created_at.substring(0, 10);
            } else if (venta.fecha_emision) {
                despachoFecha.min = venta.fecha_emision;
            }
        }

        (venta.detalle || []).forEach((linea) => {
            if (Number(linea.cantidad_pendiente) > 0.0001) {
                agregarFilaDespacho(linea, null);
                
                // --- NUEVO: Mapear los envases que requiere este producto ---
                if (linea.envases_retornables && linea.envases_retornables.length > 0) {
                    linea.envases_retornables.forEach(env => {
                        const idEnv = env.id_envase;
                        const reqItem = {
                            id_detalle: linea.id,
                            id_envase: idEnv,
                            nombre: env.nombre_envase,
                            factor: Number(env.factor || 1)
                        };
                        envasesRequeridosActuales.push(reqItem);
                    });
                }
            }
        });
        
        dibujarTablaEnvases(); // --- NUEVO: Pintar la tabla inicial ---
        modalDespacho.show();
    }

    // --- NUEVO: Función para dibujar y actualizar los envases vacíos (DISEÑO MODERNO) ---
    function dibujarTablaEnvases() {
        if (!seccionRetornoEnvases || !contenedorRetornoEnvases) return;

        if (envasesRequeridosActuales.length === 0) {
            seccionRetornoEnvases.classList.add('d-none');
            return;
        }

        const totalesLlenos = {}; 
        
        const filasDespacho = [...tbodyDespacho.querySelectorAll('tr')];
        filasDespacho.forEach(f => {
            const idDetalle = Number(f.dataset.idDetalle);
            const cantDespachando = Number(f.querySelector('.despacho-cantidad')?.value || 0);
            
            if (cantDespachando > 0) {
                const envasesDeEstaLinea = envasesRequeridosActuales.filter(e => e.id_detalle === idDetalle);
                envasesDeEstaLinea.forEach(env => {
                    if (!totalesLlenos[env.id_envase]) {
                        totalesLlenos[env.id_envase] = { nombre: env.nombre, cantidad: 0 };
                    }
                    totalesLlenos[env.id_envase].cantidad += (cantDespachando * env.factor);
                });
            }
        });

        contenedorRetornoEnvases.innerHTML = '';
        let hayEnvasesAEntregar = false;

        for (const [idEnvase, datos] of Object.entries(totalesLlenos)) {
            const cantLlenos = Math.round(datos.cantidad);
            if (cantLlenos <= 0) continue;
            
            hayEnvasesAEntregar = true;
            
            // Mantener el valor si el usuario ya lo había escrito
            const divExistente = contenedorRetornoEnvases.querySelector(`div[data-id-envase="${idEnvase}"]`);
            const valorPrevio = divExistente ? divExistente.querySelector('.input-retorno-vacio').value : cantLlenos;

            const itemDiv = document.createElement('div');
            // Clases flex para que en PC se vea horizontal y en móvil se apile verticalmente si no cabe
            itemDiv.className = 'd-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between bg-white p-2 rounded shadow-sm border border-info-subtle item-envase-retorno';
            itemDiv.dataset.idEnvase = idEnvase;
            itemDiv.innerHTML = `
                <div class="fw-bold text-dark mb-2 mb-sm-0 d-flex align-items-center w-100">
                    <i class="bi bi-box-seam me-2 text-muted"></i> <span class="text-truncate">${datos.nombre}</span>
                </div>
                
                <div class="d-flex align-items-center justify-content-between justify-content-sm-end w-100 gap-3">
                    <div class="text-center px-2">
                        <small class="text-muted d-block lh-1" style="font-size: 0.7rem;">ENTREGAS</small>
                        <span class="fw-bold text-dark">${cantLlenos}</span>
                    </div>
                    
                    <div class="input-group flex-nowrap shadow-sm" style="width: 140px;">
                        <span class="input-group-text bg-light border-info-subtle text-muted" style="font-size: 0.8rem;">Retorna</span>
                        <input type="number" class="form-control text-center text-success fw-bold border-info-subtle input-retorno-vacio" min="0" value="${valorPrevio}">
                    </div>
                </div>
            `;
            contenedorRetornoEnvases.appendChild(itemDiv);
        }

        if (hayEnvasesAEntregar) {
            seccionRetornoEnvases.classList.remove('d-none');
        } else {
            seccionRetornoEnvases.classList.add('d-none');
        }
    }
    // --------------------------------------------------------------------------

    function agregarFilaDespacho(linea, filaReferencia = null) {
        let opcionesHTML = '<option value="">Seleccione...</option>';
        let disabledState = '';
        const almacenesDisp = linea.almacenes_disponibles || [];
        
        let mejorAlmacenId = '';
        let maxStock = -1;

        if (almacenesDisp.length === 0) {
            opcionesHTML = '<option value="">Sin stock en ningún almacén</option>';
            disabledState = 'disabled';
        } else {
            almacenesDisp.forEach(alm => {
                const stockDisponible = Number.parseFloat(alm.stock_actual || 0) || 0;
                opcionesHTML += `<option value="${alm.id}" data-stock="${stockDisponible}">${alm.nombre} (Dispo: ${stockDisponible})</option>`;
                
                if (stockDisponible > maxStock) {
                    maxStock = stockDisponible;
                    mejorAlmacenId = alm.id;
                }
            });
        }

        const tr = document.createElement('tr');
        tr.dataset.idDetalle = linea.id;
        tr.dataset.idItem = linea.id_item;
        tr.dataset.pendienteTotal = linea.cantidad_pendiente;

        if (almacenesDisp.length === 0) {
            tr.classList.add('table-danger', 'opacity-75');
        }

        tr.innerHTML = `
            <td class="align-middle py-3">
                <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">${linea.item_nombre || ''}</div>
                
                <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                    <span>Pedido Original: <strong class="text-dark">${Number(linea.cantidad)}</strong></span>
                    <span class="text-secondary opacity-50">|</span>
                    <span>Pendiente:</span> 
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
                const btn = fila.querySelector('.btn-quitar-despacho');
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
                dibujarTablaEnvases(); // <-- NUEVO: Actualizar envases
                return;
            }

            const yaExiste = obtenerFilasGrupo().some(f => f !== tr && f.querySelector('.fila-almacen').value === idAlmacen);
            if (yaExiste) {
                Swal.fire('Almacén duplicado', 'No puede seleccionar el mismo almacén para este producto.', 'warning');
                selectAlmacen.value = '';
                spanStock.textContent = '-';
                inputCant.value = 0;
                validarGrupoItem(linea.id);
                dibujarTablaEnvases(); // <-- NUEVO: Actualizar envases
                return;
            }

            const optionSel = selectAlmacen.options[selectAlmacen.selectedIndex];
            const stock = optionSel ? Math.floor(Number.parseFloat(optionSel.dataset.stock || '0') || 0) : 0;

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
            dibujarTablaEnvases(); // <-- NUEVO: Actualizar envases
        });

        inputCant.addEventListener('input', () => {
            if (inputCant.value.includes('.')) {
                inputCant.value = Math.floor(parseFloat(inputCant.value || 0));
            }
            sincronizarGrupo(tr);
            validarGrupoItem(linea.id);
            dibujarTablaEnvases(); // <-- NUEVO: Actualiza la tabla de envases en tiempo real
        });

        btnSplit.addEventListener('click', () => {
            const filas = obtenerFilasGrupo();
            const almacenesConStock = (linea.almacenes_disponibles || []).filter(alm => parseFloat(alm.stock_actual) > 0).length;

            if (almacenesConStock <= 1 || filas.length >= almacenesConStock) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin stock adicional',
                    text: 'Este producto no tiene stock disponible en otros almacenes para seguir fraccionando.'
                });
                return;
            }

            if (filas.length >= 3) {
                Swal.fire('Límite alcanzado', 'Solo se permite despachar desde un máximo de 3 almacenes a la vez.', 'info');
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
            dibujarTablaEnvases(); // <-- NUEVO: Actualizar envases
        });

        actualizarModoGrupo();

        if (!filaReferencia && mejorAlmacenId) {
            selectAlmacen.value = mejorAlmacenId;
            setTimeout(() => {
                selectAlmacen.dispatchEvent(new Event('change'));
            }, 50);
        }
    }

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

            if (cant > stock && stockStr !== '-') {
                input.classList.add('is-invalid');
                input.title = `Solo hay ${stock} en este almacén`;
            } else {
                input.classList.remove('is-invalid');
                input.title = "";
            }
        });

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

    document.getElementById('btnGuardarDespacho').addEventListener('click', async () => {
        try {
            const filas = [...tbodyDespacho.querySelectorAll('tr')];
            
            const detalle = filas.map(fila => {
                const idAlmacen = fila.querySelector('.fila-almacen').value;
                const cantidad = parseFloat(fila.querySelector('.despacho-cantidad').value || 0);
                
                return {
                    id_documento_detalle: Number(fila.dataset.idDetalle),
                    id_almacen: Number(idAlmacen),
                    cantidad: cantidad
                };
            }).filter(d => d.cantidad > 0); 

            if (detalle.length === 0) throw new Error('Ingrese cantidades a despachar.');
            if (detalle.some(d => !d.id_almacen)) throw new Error('Seleccione almacén para todas las filas con cantidad.');
            if (tbodyDespacho.querySelector('.is-invalid')) throw new Error('Corrija las cantidades marcadas en rojo (exceden stock o pendiente).');

            // --- NUEVO: Validar fecha de despacho ---
            const fechaDespachoVal = despachoFecha ? despachoFecha.value : '';
            if (!fechaDespachoVal) throw new Error('Debe especificar la fecha de despacho.');
            // ----------------------------------------

            const resumenPorItem = {}; 
            filas.forEach(f => {
                const id = f.dataset.idDetalle;
                const cant = parseFloat(f.querySelector('.despacho-cantidad').value || 0);
                resumenPorItem[id] = (resumenPorItem[id] || 0) + cant;
            });

            let esParcial = false;
            filas.forEach(f => {
                const id = f.dataset.idDetalle;
                const pendiente = parseFloat(f.dataset.pendienteTotal);
                const despachando = resumenPorItem[id] || 0;
                if (despachando < pendiente - 0.01) esParcial = true;
            });

            // --- NUEVO: Capturar Envases Retornados (Adaptado al nuevo DOM) ---
            const envasesDevueltos = [];
            if (contenedorRetornoEnvases && !seccionRetornoEnvases.classList.contains('d-none')) {
                contenedorRetornoEnvases.querySelectorAll('.item-envase-retorno').forEach(div => {
                    const cant = Number(div.querySelector('.input-retorno-vacio').value || 0);
                    if (cant > 0) {
                        envasesDevueltos.push({
                            id_envase: Number(div.dataset.idEnvase),
                            cantidad: cant
                        });
                    }
                });
            }
            // -----------------------------------------

            if (esParcial && cerrarForzado.checked) {
                const resp = await Swal.fire({
                    icon: 'warning',
                    title: 'Cerrar pedido de forma forzada',
                    text: 'Se despachará de forma parcial y el saldo restante quedará cancelado para este pedido. ¿Desea continuar?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cerrar pedido'
                });
                if (!resp.isConfirmed) return;
            }

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
                fecha_despacho: fechaDespachoVal, 
                cerrar_forzado: cerrarForzado.checked,
                detalle: detalle,
                envases_devueltos: envasesDevueltos // <-- MANDAMOS LA DATA AL SERVIDOR
            });

            await Swal.fire('Despachado', payload.mensaje, 'success');
            modalDespacho.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // ==========================================
    // --- MEJORA PREMIUM: FILTROS Y RECARGAS ---
    // ==========================================
    
    function recargarTabla() {
        // Leemos la URL actual para no perder la ruta base (?ruta=ventas)
        const urlParams = new URLSearchParams(window.location.search);
        
        // Limpiamos 'accion' para que sea una carga de vista normal y no un JSON AJAX
        urlParams.delete('accion');

        // Seteamos o eliminamos parámetros según lo que el usuario tenga en pantalla
        if (filtroBusqueda.value.trim()) urlParams.set('q', filtroBusqueda.value.trim()); else urlParams.delete('q');
        if (filtroEstado.value !== '') urlParams.set('estado', filtroEstado.value); else urlParams.delete('estado');
        if (filtroFechaDesde.value) urlParams.set('fecha_desde', filtroFechaDesde.value); else urlParams.delete('fecha_desde');
        if (filtroFechaHasta.value) urlParams.set('fecha_hasta', filtroFechaHasta.value); else urlParams.delete('fecha_hasta');
        if (filtroOrdenFecha && filtroOrdenFecha.value) urlParams.set('orden_fecha', filtroOrdenFecha.value); else urlParams.delete('orden_fecha');
        
        // Recargamos la página aplicando la URL construida (persistencia total)
        window.location.search = urlParams.toString();
    }

    // PROTECCIÓN DE RANGO DE FECHAS
    if (filtroFechaDesde && filtroFechaHasta) {
        filtroFechaDesde.addEventListener('change', () => {
            if (filtroFechaDesde.value) {
                // El 'Hasta' no puede ser anterior al 'Desde'
                filtroFechaHasta.min = filtroFechaDesde.value; 
                // Auto-corrección si el usuario se equivocó
                if (filtroFechaHasta.value && filtroFechaHasta.value < filtroFechaDesde.value) {
                    filtroFechaHasta.value = filtroFechaDesde.value;
                }
            } else {
                filtroFechaHasta.min = '';
            }
            recargarTabla();
        });

        filtroFechaHasta.addEventListener('change', () => {
            if (filtroFechaHasta.value) {
                // El 'Desde' no puede ser posterior al 'Hasta'
                filtroFechaDesde.max = filtroFechaHasta.value; 
                // Auto-corrección si el usuario se equivocó
                if (filtroFechaDesde.value && filtroFechaDesde.value > filtroFechaHasta.value) {
                    filtroFechaDesde.value = filtroFechaHasta.value;
                }
            } else {
                filtroFechaDesde.max = '';
            }
            recargarTabla();
        });

        // Inicializamos las restricciones visuales al cargar la página
        if (filtroFechaDesde.value) filtroFechaHasta.min = filtroFechaDesde.value;
        if (filtroFechaHasta.value) filtroFechaDesde.max = filtroFechaHasta.value;
    }

    // EVENTOS PARA EL RESTO DE FILTROS
    [filtroBusqueda, filtroEstado, filtroOrdenFecha].forEach(el => {
        if(el) {
            // Para el buscador, esperamos que presione Enter para no recargar a cada letra
            if (el.id === 'filtroBusqueda') {
                el.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') recargarTabla();
                });
            } else {
                el.addEventListener('change', recargarTabla);
            }
        }
    });
    
    // --- EVENTO DE CLICS EN LA TABLA (EDICIÓN, ANULACIÓN, IMPRESIÓN) ---
    document.querySelector('#tablaVentas tbody')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const tr = btn.closest('tr');
        const id = Number(btn.dataset.id || tr?.dataset.id || 0);

        if (!id) {
            Swal.fire('Error', 'No se encontró el identificador del pedido.', 'error');
            return;
        }

        if (btn.classList.contains('btn-editar')) {
            try {
                const payload = await getJson(`${urls.index}&accion=ver&id=${id}`);
                const venta = payload.data;
                if (!venta || !venta.id) throw new Error('No se encontró información del pedido seleccionado.');
                limpiarModalVenta();
                ventaId.value = venta.id;
                
                const esBorrador = Number(venta.estado) === 0;
                bloqueoEdicionVenta = !esBorrador;
                
                const nombreCliente = tr?.querySelector('td:nth-child(2)')?.textContent?.trim() || 'Cliente';
                if (tomSelectCliente) {
                    tomSelectCliente.addOption({ id: venta.id_cliente, text: nombreCliente });
                    tomSelectCliente.setValue(venta.id_cliente);
                    if (!esBorrador) tomSelectCliente.disable();
                    else tomSelectCliente.enable();
                } else {
                    idCliente.innerHTML = `<option value="${venta.id_cliente}">${nombreCliente}</option>`;
                    idCliente.value = venta.id_cliente;
                    idCliente.disabled = !esBorrador;
                }
                
                const inputFecha = document.getElementById('fechaEmision');
                const inputObs = document.getElementById('ventaObservaciones');
                
                inputFecha.value = venta.fecha_emision || '';
                inputObs.value = venta.observaciones || '';
                
                if (tipoOperacion) {
                    tipoOperacion.value = venta.tipo_operacion || 'VENTA';
                    tipoOperacion.disabled = !esBorrador;
                }
                if (tipoImpuesto) {
                    tipoImpuesto.value = venta.tipo_impuesto || 'exonerado';
                    if (!esBorrador || (tipoOperacion && tipoOperacion.value === 'DONACION')) {
                        tipoImpuesto.disabled = true;
                    } else {
                        tipoImpuesto.disabled = false;
                    }
                }
                
                inputFecha.readOnly = !esBorrador;
                inputObs.readOnly = !esBorrador;

                const btnGlobalAdd = document.getElementById('btnAgregarFilaVenta');
                if (btnGlobalAdd) btnGlobalAdd.style.display = esBorrador ? 'inline-block' : 'none';

                if (venta.detalle && venta.detalle.length) {
                    for (const linea of venta.detalle) await agregarFilaVenta(linea, esBorrador);
                } else {
                    await agregarFilaVenta(null, esBorrador);
                }
                
                const btnGuardar = document.getElementById('btnGuardarVenta');
                if (esBorrador) {
                    btnGuardar.style.display = 'block';
                    btnGuardar.textContent = 'Actualizar Pedido';
                    document.getElementById('alertaBorradorContenedor').innerHTML = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium px-2 py-1"><i class="bi bi-info-circle me-1"></i>Borrador: No descuenta stock físico</span>`;
                    // Mostrar switch si es borrador
                    if (switchCobroContainer) switchCobroContainer.style.display = 'block';
                } else {
                    btnGuardar.style.display = 'none';
                    // Ocultar switch si ya está aprobado/despachado
                    if (switchCobroContainer) switchCobroContainer.style.display = 'none';
                }

                actualizarBloqueoFormularioPorCliente();

                // --- NUEVO: PINTAR HISTORIAL DE DEVOLUCIONES ---
                const seccionDevoluciones = document.getElementById('seccionDevolucionesVenta');
                const tbodyDevHistorico = document.querySelector('#tablaDevolucionesHistorico tbody');
                
                if (seccionDevoluciones && tbodyDevHistorico) {
                    tbodyDevHistorico.innerHTML = ''; // Limpiar historial anterior
                    
                    if (venta.devoluciones && venta.devoluciones.length > 0) {
                        seccionDevoluciones.classList.remove('d-none'); // Mostrar la sección
                        
                        venta.devoluciones.forEach(dev => {
                            let detallesHTML = '<ul class="mb-0 ps-3 text-muted" style="font-size: 0.85rem;">';
                            (dev.detalle || []).forEach(item => {
                                detallesHTML += `<li>${Number(item.cantidad).toFixed(2)}x ${item.item_nombre}</li>`;
                            });
                            detallesHTML += '</ul>';

                            // Formatear la resolución para que sea más legible
                            let resTexto = dev.tipo_resolucion;
                            if (resTexto === 'descuento_cxc') resTexto = 'Nota de Crédito (CxC)';
                            else if (resTexto === 'reembolso_dinero') resTexto = 'Reembolso (Caja/Bancos)';
                            else if (resTexto === 'saldo_favor') resTexto = 'Saldo a Favor';

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="ps-3 text-dark fw-semibold" style="font-size: 0.9rem;">${dev.created_at.substring(0, 16)}</td>
                                <td>
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;">${dev.motivo}</div>
                                    <div class="badge bg-secondary-subtle text-secondary mt-1 border border-secondary-subtle">${resTexto}</div>
                                </td>
                                <td>${detallesHTML}</td>
                                <td class="text-end pe-4 fw-bold text-danger">S/ ${Number(dev.total_devuelto).toFixed(2)}</td>
                            `;
                            tbodyDevHistorico.appendChild(tr);
                        });
                    } else {
                        seccionDevoluciones.classList.add('d-none'); // Ocultar si no hay devoluciones
                    }
                }
                // -----------------------------------------------
                
                modalVenta.show();
            } catch (err) {
                console.error('Error al abrir pedido:', err);
                Swal.fire('Error', err.message || 'No se pudo cargar', 'error');
            }
        }

        // --- NUEVO: BOTÓN REVERTIR A BORRADOR ---
        if (btn.classList.contains('btn-revertir')) {
            const ok = await Swal.fire({ 
                icon: 'warning', 
                title: '¿Revertir a Borrador?', 
                text: 'El pedido volverá a estado inicial y podrá ser editado. Se eliminará la cuenta por cobrar (si existe).',
                showCancelButton: true, 
                confirmButtonText: 'Sí, revertir',
                confirmButtonColor: '#ffc107', // Color advertencia
                cancelButtonColor: '#6c757d'
            });
            
            if (ok.isConfirmed) {
                try {
                    // Usamos tu helper postJson. 
                    // Asegúrate de que tu backend reciba la acción 'revertir'
                    const res = await postJson(`${urls.index}&accion=revertir`, { id });
                    await Swal.fire('Revertido', res.mensaje, 'success');
                    recargarTabla();
                } catch (err) { 
                    Swal.fire('Error', err.message, 'error'); 
                }
            }
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

        if (btn.classList.contains('btn-devolucion')) {
            try { await abrirModalDevolucionVenta(id); } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }

        // LÓGICA DEL BOTÓN DE IMPRESIÓN (NUEVO)
        // Ya no necesitamos la función global, lo controlamos todo desde aquí
        const btnImprimir = btn.closest('.btn-imprimir-modal');
        if (btnImprimir) {
            window.imprimirPedido(id);
        }
    });

    document.getElementById('btnConfirmarDevolucionVenta')?.addEventListener('click', async () => {
        try {
            const motivoSeleccionado = devolucionVentaMotivo?.value || '';
            const motivoCfg = DEVOLUCION_VENTA_MOTIVOS[motivoSeleccionado];
            if (!motivoCfg) throw new Error('Seleccione un motivo de devolución válido.');

            const resolucionSeleccionada = devolucionVentaResolucion?.value || '';
            if (!DEVOLUCION_VENTA_RESOLUCIONES[resolucionSeleccionada]) {
                throw new Error('Seleccione una resolución comercial válida.');
            }

            if (resolucionSeleccionada === 'salida_dinero' || resolucionSeleccionada === 'reembolso_dinero') {
                const confirmacionTesoreria = await Swal.fire({
                    icon: 'warning',
                    title: 'Se registrará salida de dinero',
                    text: 'Verifique que tesorería procese el reembolso para completar la devolución.',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                });
                if (!confirmacionTesoreria.isConfirmed) return;
            }

            const detalle = [];
            tbodyDevolucionVenta?.querySelectorAll('tr').forEach((tr) => {
                const cantidad = Number(tr.querySelector('.input-devolver-venta')?.value || 0);
                if (cantidad <= 0) return;

                detalle.push({
                    id_documento_detalle: Number(tr.dataset.idDetalle || 0),
                    id_item: tr.dataset.idItem || '',
                    cantidad,
                    costo_unitario: Number(tr.dataset.precio || 0),
                });
            });

            if (!detalle.length) throw new Error('Ingrese al menos una cantidad a devolver mayor a cero.');

            const payload = await postJson(`${urls.index}&accion=guardar_devolucion`, {
                id_documento: Number(devolucionVentaDocumentoId?.value || 0),
                motivo: motivoCfg.label,
                motivo_codigo: motivoSeleccionado,
                resolucion: resolucionSeleccionada,
                detalle,
            });

            await Swal.fire('Éxito', payload.mensaje, 'success');
            modalDevolucionVenta?.hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    devolucionVentaMotivo?.addEventListener('change', actualizarHintDevolucionVenta);
    devolucionVentaResolucion?.addEventListener('change', actualizarHintDevolucionVenta);
    actualizarHintDevolucionVenta();

    // LÓGICA DEL BOTÓN CONFIRMAR IMPRESIÓN (NUEVO - EXTRAÍDO Y REORDENADO)
    const btnConfirmarImpresion = document.getElementById('btnConfirmarImpresionPedido');
    if (btnConfirmarImpresion) {
        btnConfirmarImpresion.addEventListener('click', () => {
            const inputPaginas = document.getElementById('cantidadPaginasPedido');
            const selectTipo = document.getElementById('tipoDocumentoImprimir');
            
            if (!app || !inputPaginas || window.pedidoIdPendienteImpresion <= 0) return;

            const baseUrl = app.dataset.urlIndex;
            const paginas = Math.max(1, Math.min(20, Number(inputPaginas.value) || 1));
            const accionImpresion = selectTipo ? selectTipo.value : 'imprimir';

            const modalEl = document.getElementById('modalImpresionPedido');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }

            window.open(`${baseUrl}&accion=${accionImpresion}&id=${window.pedidoIdPendienteImpresion}&paginas=${paginas}`, '_blank');
        });
    }
})();
