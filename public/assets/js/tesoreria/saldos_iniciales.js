/**
 * Lógica específica para Tesorería - Saldos Iniciales
 * Archivo: assets/js/tesoreria/saldos_iniciales.js
 */
(function arrancarSaldosIniciales() {
    'use strict';

    const app = document.getElementById('tesoreriaSaldosInicialesApp');
    if (!app) return;

    const formSaldoInicial = document.getElementById('formSaldoInicial');
    if (!formSaldoInicial) return;

    let totalAmortizacionesRemotas = 0;
    let totalAmortizacionesLocales = 0;

    const terceroSelectEl = document.getElementById('saldoInicialTercero');
    const radiosTipo = Array.from(document.querySelectorAll('input[name="tipo_deuda"]'));
    const labelTipoCliente = document.getElementById('labelTipoCliente');
    const labelTipoProveedor = document.getElementById('labelTipoProveedor');
    const btnGuardar = document.getElementById('btnGuardarCuentaTercero');
    const tercerosUrl = formSaldoInicial.getAttribute('data-url-terceros') || '';
    const verificarCuentaUrl = '?ruta=tesoreria/ajax_verificar_cuenta_tercero';

    // ========================================================================
    // 1. TOMSELECT: TERCEROS (CLIENTES/PROVEEDORES)
    // ========================================================================
    if (terceroSelectEl && typeof TomSelect !== 'undefined') {
        const getTipoSeleccionado = () => {
            const radioChecked = document.querySelector('input[name="tipo_deuda"]:checked');
            return radioChecked ? radioChecked.value : 'CLIENTE';
        };

        const getPlaceholderByTipo = (tipo) => tipo === 'PROVEEDOR'
            ? 'Buscar proveedor activo...'
            : 'Buscar cliente o distribuidor activo...';

        const tsTerceros = new TomSelect(terceroSelectEl, {
            valueField: 'id',
            labelField: 'nombre_completo',
            searchField: ['nombre_completo'],
            placeholder: getPlaceholderByTipo(getTipoSeleccionado()),
            preload: true,
            controlInput: '<input type="text" class="form-control shadow-none" autocomplete="off">',
            
            load: function(query, callback) {
                const tipo = getTipoSeleccionado();
                const separador = tercerosUrl.includes('?') ? '&' : '?';
                const url = `${tercerosUrl}${separador}tipo=${encodeURIComponent(tipo)}&q=${encodeURIComponent(query)}`;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.json())
                    .then(json => callback(json.items || []))
                    .catch(() => callback());
            },
            render: {
                option: function(item, escape) {
                    return `<div class="py-2 px-3"><span class="fw-bold text-dark d-block">${escape(item.nombre_completo)}</span></div>`;
                },
                item: function(item, escape) {
                    return `<div class="fw-bold text-dark">${escape(item.nombre_completo)}</div>`;
                }
            },
            onChange: function(value) {
                if (!value) {
                    desbloquearNaturaleza();
                    resetearBotonGuardar();
                    totalAmortizacionesRemotas = 0;
                    totalAmortizacionesLocales = 0;
                    if (radioModoDetalle) radioModoDetalle.checked = true;
                    if (inputMontoBaseManual) inputMontoBaseManual.value = '0.00';
                    actualizarModoRegistroUI('DETALLE');
                    limpiarDetalleCompras();
                    renderAmortizaciones([]);
                    calcularSaldosReales();
                    return;
                }

                const tipo = getTipoSeleccionado();
                fetch(`${verificarCuentaUrl}&id=${value}&tipo=${tipo}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.json())
                    .then(async data => {
                        if (data.ok && data.tiene_cuenta) {
                            bloquearNaturaleza();
                            
                            if (btnGuardar) {
                                btnGuardar.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Actualizar Saldo Inicial';
                                btnGuardar.classList.replace('btn-primary', 'btn-success');
                            }

                            totalAmortizacionesRemotas = data.total_amortizaciones || 0;
                            totalAmortizacionesLocales = 0;
                            const modoGuardado = data.modo_registro === 'MANUAL' ? 'MANUAL' : 'DETALLE';
                            
                            if (modoGuardado === 'MANUAL' && radioModoManual) radioModoManual.checked = true;
                            else if (radioModoDetalle) radioModoDetalle.checked = true;
                            
                            if (inputMontoBaseManual) {
                                const montoBase = parseFloat(data.monto_base_referencial || 0);
                                inputMontoBaseManual.value = montoBase > 0 ? montoBase.toFixed(2) : '0.00';
                            }
                            
                            actualizarModoRegistroUI(modoGuardado);
                            await cargarDetalleGuardado(data.items_guardados || []);
                            renderAmortizaciones(data.amortizaciones || []);
                            calcularSaldosReales();
                            
                        } else {
                            desbloquearNaturaleza();
                            resetearBotonGuardar();
                            totalAmortizacionesRemotas = 0;
                            totalAmortizacionesLocales = 0;
                            if (radioModoDetalle) radioModoDetalle.checked = true;
                            if (inputMontoBaseManual) inputMontoBaseManual.value = '0.00';
                            actualizarModoRegistroUI('DETALLE');
                            limpiarDetalleCompras();
                            renderAmortizaciones([]);
                            calcularSaldosReales();
                        }
                    })
                    .catch(err => console.error('Error al verificar cuenta:', err));
            }
        });

        function bloquearNaturaleza() {
            radiosTipo.forEach(radio => radio.disabled = true);
            if (labelTipoCliente) labelTipoCliente.classList.add('opacity-50');
            if (labelTipoProveedor) labelTipoProveedor.classList.add('opacity-50');
        }

        function desbloquearNaturaleza() {
            radiosTipo.forEach(radio => radio.disabled = false);
            if (labelTipoCliente) labelTipoCliente.classList.remove('opacity-50');
            if (labelTipoProveedor) labelTipoProveedor.classList.remove('opacity-50');
        }

        function resetearBotonGuardar() {
            if (btnGuardar) {
                btnGuardar.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Guardar Saldo Inicial';
                btnGuardar.classList.replace('btn-success', 'btn-primary');
            }
        }

        const recargarOpcionesTerceros = (tipo) => {
            const separador = tercerosUrl.includes('?') ? '&' : '?';
            const url = `${tercerosUrl}${separador}tipo=${encodeURIComponent(tipo)}&q=`;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.json())
                .then(json => {
                    tsTerceros.clearOptions();
                    tsTerceros.addOptions(json.items || []);
                    tsTerceros.refreshOptions(false);
                })
                .catch(() => { tsTerceros.clearOptions(); tsTerceros.refreshOptions(false); });
        };

        radiosTipo.forEach(r => {
            r.addEventListener('change', () => {
                tsTerceros.clear(true);
                tsTerceros.loadedSearches = {};
                tsTerceros.settings.placeholder = getPlaceholderByTipo(r.value);
                if (tsTerceros.control_input) tsTerceros.control_input.placeholder = tsTerceros.settings.placeholder;
                tsTerceros.clearOptions();
                tsTerceros.load('');
                recargarOpcionesTerceros(r.value);
            });
        });
    }

    // ========================================================================
    // 2. TOMSELECT Y LÓGICA DE ÍTEMS / COMPRAS
    // ========================================================================
    const itemsSelectEl = document.getElementById('buscadorItemsSaldo');
    const itemsUrl = formSaldoInicial.getAttribute('data-url-items') || '';
    const unidadesItemUrl = formSaldoInicial.getAttribute('data-url-item-unidades') || '';
    const btnAgregarItemDetalle = document.getElementById('btnAgregarItemDetalle');
    const fechaIngresoInput = formSaldoInicial.querySelector('input[name="fecha_emision"]');
    const inputCantidadAgregar = document.getElementById('saldoDetalleCantidad');
    const selectUnidadAgregar = document.getElementById('saldoDetalleUnidad');
    const inputSubtotalAgregar = document.getElementById('saldoDetalleSubtotal');
    
    const bloqueMontoBaseManual = document.getElementById('bloqueMontoBaseManual');
    const inputMontoBaseManual = document.getElementById('saldoInicialMontoBaseManual');
    const radiosModoRegistro = Array.from(document.querySelectorAll('input[name="modo_registro"]'));
    const radioModoDetalle = document.getElementById('modoRegistroDetalle');
    const radioModoManual = document.getElementById('modoRegistroManual');
    
    const tbody = document.querySelector('#tablaDetalleSaldos tbody');
    const filaVacia = document.getElementById('filaVaciaMensaje');
    const inputMontoSaldos = document.getElementById('saldoInicialMontoManual');
    const cacheUnidades = new Map();

    async function obtenerUnidadesItemTesoreria(idItem) {
        if (!idItem || !unidadesItemUrl) return [];
        if (cacheUnidades.has(idItem)) return cacheUnidades.get(idItem);
        const separador = unidadesItemUrl.includes('?') ? '&' : '?';
        const response = await fetch(`${unidadesItemUrl}${separador}id_item=${encodeURIComponent(idItem)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data?.mensaje || 'Error al cargar unidades.');
        const unidades = Array.isArray(data.items) ? data.items : [];
        cacheUnidades.set(idItem, unidades);
        return unidades;
    }

    let tsItems = null;
    let itemSeleccionadoTemporal = null; 
    let modoRegistroActual = radioModoManual?.checked ? 'MANUAL' : 'DETALLE';

    function obtenerModoRegistro() {
        const radio = document.querySelector('input[name="modo_registro"]:checked');
        return radio?.value === 'MANUAL' ? 'MANUAL' : 'DETALLE';
    }

    function actualizarModoRegistroUI(modo = 'DETALLE') {
        modoRegistroActual = modo === 'MANUAL' ? 'MANUAL' : 'DETALLE';
        const esManual = modoRegistroActual === 'MANUAL';

        if (bloqueMontoBaseManual) bloqueMontoBaseManual.classList.toggle('d-none', !esManual);
        if (inputMontoBaseManual) {
            inputMontoBaseManual.disabled = !esManual;
            if (!esManual && !inputMontoBaseManual.value) inputMontoBaseManual.value = '0.00';
        }
        if (itemsSelectEl?.tomselect) {
            itemsSelectEl.tomselect.wrapper.classList.toggle('opacity-50', esManual);
            if (itemsSelectEl.tomselect.control_input) itemsSelectEl.tomselect.control_input.disabled = esManual;
        }
        if (btnAgregarItemDetalle) {
            btnAgregarItemDetalle.disabled = esManual;
            btnAgregarItemDetalle.classList.toggle('disabled', esManual);
        }
        if (inputCantidadAgregar) inputCantidadAgregar.disabled = esManual;
        if (selectUnidadAgregar) selectUnidadAgregar.disabled = esManual;
        if (inputSubtotalAgregar) inputSubtotalAgregar.disabled = esManual;

        calcularSaldosReales();
    }

    function getOpcionesUnidadesHtml(unidades = []) {
        return ['<option value="">Base</option>'].concat(unidades.map(u => {
            const factor = parseFloat(u.factor_conversion || 1);
            return `<option value="${u.id}" data-factor="${factor}" data-nombre="${u.nombre || 'Unidad'}">${u.nombre || 'Unidad'} (x${factor.toFixed(4)})</option>`;
        })).join('');
    }

    async function refrescarUnidadesEnPanel(item) {
        if (!selectUnidadAgregar) return;
        selectUnidadAgregar.innerHTML = '<option value="">Base</option>';
        if (!item?.id) return;
        try {
            const unidades = await obtenerUnidadesItemTesoreria(Number(item.id));
            selectUnidadAgregar.innerHTML = getOpcionesUnidadesHtml(unidades);
        } catch (error) {
            console.warn('No se pudieron cargar unidades:', error);
        }
    }

    if (itemsSelectEl && typeof TomSelect !== 'undefined') {
        tsItems = new TomSelect(itemsSelectEl, {
            valueField: 'id',
            labelField: 'nombre',
            searchField: ['nombre', 'sku', 'descripcion'],
            placeholder: '🔍 Busque un producto por nombre o código...',
            preload: 'focus',
            controlInput: '<input type="text" class="form-control shadow-none" autocomplete="off">',
            load: function(query, callback) {
                const sep = itemsUrl.includes('?') ? '&' : '?';
                fetch(`${itemsUrl}${sep}q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.json())
                    .then(json => callback(json.items || []))
                    .catch(() => callback());
            },
            render: {
                option: function(item, escape) {
                    return `<div class="py-2 px-3 border-bottom">
                                <span class="badge bg-secondary me-2">${escape(item.sku || 'N/A')}</span>
                                <span class="fw-bold text-dark">${escape(item.nombre)}</span>
                                <small class="d-block text-muted mt-1">Precio ref: S/ ${parseFloat(item.precio_venta||0).toFixed(2)}</small>
                            </div>`;
                }
            },
            onChange: function(value) {
                if (!value) {
                    itemSeleccionadoTemporal = null;
                    if (inputSubtotalAgregar) inputSubtotalAgregar.value = '0.00';
                    if (inputCantidadAgregar) inputCantidadAgregar.value = '1';
                    if (selectUnidadAgregar) selectUnidadAgregar.innerHTML = '<option value="">Base</option>';
                    return;
                }
                itemSeleccionadoTemporal = this.options[value];
                if (inputSubtotalAgregar) inputSubtotalAgregar.value = parseFloat(itemSeleccionadoTemporal?.precio_venta || 0).toFixed(2);
                refrescarUnidadesEnPanel(itemSeleccionadoTemporal);
            }
        });
    }

    if (btnAgregarItemDetalle) {
        btnAgregarItemDetalle.addEventListener('click', async function() {
            if (!itemSeleccionadoTemporal) {
                return Swal.fire('Atención', 'Seleccione un producto primero.', 'warning');
            }
            const cantidad = Math.max(parseFloat(inputCantidadAgregar?.value || 1), 0.01);
            const subtotal = Math.max(parseFloat(inputSubtotalAgregar?.value || 0), 0);
            const unidadId = selectUnidadAgregar?.value || '';
            const unidadOption = unidadId ? selectUnidadAgregar?.options?.[selectUnidadAgregar.selectedIndex] : null;

            await agregarFilaDetalle(itemSeleccionadoTemporal, {
                cantidad, subtotal, unidadId,
                unidadNombre: unidadOption?.dataset?.nombre || '',
                unidadFactor: unidadOption?.dataset?.factor || '1'
            });
            
            if (tsItems) tsItems.clear(true);
            itemSeleccionadoTemporal = null;
            if (inputCantidadAgregar) inputCantidadAgregar.value = '1';
            if (inputSubtotalAgregar) inputSubtotalAgregar.value = '0.00';
            if (selectUnidadAgregar) selectUnidadAgregar.innerHTML = '<option value="">Base</option>';
        });
    }

    async function agregarFilaDetalle(item, valores = {}) {
        if(filaVacia) filaVacia.style.display = 'none';
        let unidades = [];
        try { unidades = await obtenerUnidadesItemTesoreria(Number(item.id)); } catch (e) {}

        const fechaFila = fechaIngresoInput?.value || new Date().toISOString().slice(0, 10);
        const ops = getOpcionesUnidadesHtml(unidades);
        const cant = Math.max(parseFloat(valores.cantidad ?? 1), 0.01);
        const sub = Math.max(parseFloat(valores.subtotal ?? item.precio_venta ?? 0), 0);
        const uId = valores.unidadId ? String(valores.unidadId) : '';

        const tr = document.createElement('tr');
        tr.classList.add('js-detalle-row');
        tr.innerHTML = `
            <td data-label="Fecha"><input type="hidden" name="detalle_fecha[]" class="js-fecha" value="${fechaFila}"><span class="small fw-semibold js-fecha-label">${fechaFila}</span></td>
            <td data-label="Ítem"><input type="hidden" name="detalle_item_id[]" value="${item.id}"><input type="hidden" name="detalle_item_nombre[]" value="${item.nombre}"><span class="fw-bold text-dark small">${item.nombre}</span></td>
            <td data-label="Cantidad"><input type="hidden" name="detalle_cantidad[]" class="js-cant" value="${cant.toFixed(2)}"><span class="small d-block text-md-center js-cant-label">${cant.toFixed(2)}</span></td>
            <td data-label="Unidad"><input type="hidden" name="detalle_item_unidad_id[]" class="js-unidad-id" value="${uId}"><input type="hidden" name="detalle_item_unidad_nombre[]" class="js-unidad-nombre" value="${valores.unidadNombre || ''}"><input type="hidden" name="detalle_item_unidad_factor[]" class="js-unidad-factor" value="${valores.unidadFactor || '1'}"><span class="small d-block text-md-center js-unidad-label">${valores.unidadNombre || 'Base'}</span></td>
            <td data-label="Subtotal"><input type="hidden" name="detalle_subtotal[]" class="js-subtotal-input" value="${sub.toFixed(2)}"><span class="small d-block text-md-end js-subtotal-label">${sub.toFixed(2)}</span></td>
            <td class="text-center text-md-end">
                <button type="button" class="btn btn-sm btn-outline-primary border-0 js-edit me-1"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger border-0 js-remove"><i class="bi bi-trash3"></i></button>
            </td>`;
        
        tr.dataset.unidadesHtml = ops;
        if(tbody) tbody.appendChild(tr);

        tr.querySelector('.js-remove').addEventListener('click', () => {
            tr.remove();
            calcularSaldosReales();
            if (tbody && tbody.querySelectorAll('tr:not(#filaVaciaMensaje)').length === 0 && filaVacia) filaVacia.style.display = '';
        });
        tr.querySelector('.js-edit').addEventListener('click', () => abrirModalEditarDetalle(tr));
        calcularSaldosReales();
    }

    function limpiarDetalleCompras() {
        if (!tbody) return;
        tbody.querySelectorAll('tr:not(#filaVaciaMensaje)').forEach(tr => tr.remove());
        if (filaVacia) filaVacia.style.display = '';
    }

    async function cargarDetalleGuardado(items = []) {
        limpiarDetalleCompras();
        for (const item of items) {
            await agregarFilaDetalle(
                { id: item.id_item, nombre: item.nombre || 'Ítem' },
                { cantidad: item.cantidad, subtotal: item.subtotal || item.precio_unitario, unidadId: item.id_item_unidad }
            );
            // Lógica interna para mapear los nombres de unidad guardados
            const fila = tbody.querySelector('tr:last-child');
            if(fila && item.id_item_unidad) {
                const tmp = document.createElement('select');
                tmp.innerHTML = fila.dataset.unidadesHtml || '';
                tmp.value = String(item.id_item_unidad);
                const opt = tmp.options[tmp.selectedIndex];
                if(opt) {
                    fila.querySelector('.js-unidad-nombre').value = opt.dataset.nombre || '';
                    fila.querySelector('.js-unidad-factor').value = opt.dataset.factor || '1';
                    fila.querySelector('.js-unidad-label').textContent = opt.dataset.nombre || 'Base';
                }
            }
        }
        calcularSaldosReales();
    }

    // ========================================================================
    // 3. MODAL DE EDICIÓN DE DETALLE
    // ========================================================================
    let modalEditarDetalle = null;
    let detalleRowEnEdicion = null;
    const modalEditarDetalleEl = document.getElementById('modalEditarDetalleCompra');
    const formEditarDetalleCompra = document.getElementById('formEditarDetalleCompra');
    
    if (modalEditarDetalleEl && typeof bootstrap !== 'undefined') {
        modalEditarDetalle = new bootstrap.Modal(modalEditarDetalleEl);
    }

    function abrirModalEditarDetalle(tr) {
        if (!tr || !modalEditarDetalle) return;
        detalleRowEnEdicion = tr;
        document.getElementById('detalleEditFecha').value = tr.querySelector('.js-fecha')?.value || '';
        document.getElementById('detalleEditCantidad').value = Number(tr.querySelector('.js-cant')?.value || 0).toFixed(2);
        document.getElementById('detalleEditSubtotal').value = Number(tr.querySelector('.js-subtotal-input')?.value || 0).toFixed(2);
        
        const sel = document.getElementById('detalleEditUnidad');
        sel.innerHTML = tr.dataset.unidadesHtml || '<option value="">Base</option>';
        sel.value = tr.querySelector('.js-unidad-id')?.value || '';
        if (sel.value !== (tr.querySelector('.js-unidad-id')?.value || '')) sel.value = '';
        
        modalEditarDetalle.show();
    }

    if (formEditarDetalleCompra) {
        formEditarDetalleCompra.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!detalleRowEnEdicion) return;
            const f = document.getElementById('detalleEditFecha').value;
            const c = Math.max(parseFloat(document.getElementById('detalleEditCantidad').value || 0), 0.01);
            const s = Math.max(parseFloat(document.getElementById('detalleEditSubtotal').value || 0), 0);
            const sel = document.getElementById('detalleEditUnidad');
            
            if (!f) return Swal.fire('Atención', 'Ingrese fecha válida.', 'warning');

            detalleRowEnEdicion.querySelector('.js-fecha').value = f;
            detalleRowEnEdicion.querySelector('.js-fecha-label').textContent = f;
            detalleRowEnEdicion.querySelector('.js-cant').value = c.toFixed(2);
            detalleRowEnEdicion.querySelector('.js-cant-label').textContent = c.toFixed(2);
            detalleRowEnEdicion.querySelector('.js-subtotal-input').value = s.toFixed(2);
            detalleRowEnEdicion.querySelector('.js-subtotal-label').textContent = s.toFixed(2);
            
            detalleRowEnEdicion.querySelector('.js-unidad-id').value = sel.value;
            const opt = sel.options[sel.selectedIndex];
            detalleRowEnEdicion.querySelector('.js-unidad-nombre').value = opt?.dataset?.nombre || '';
            detalleRowEnEdicion.querySelector('.js-unidad-factor').value = opt?.dataset?.factor || '1';
            detalleRowEnEdicion.querySelector('.js-unidad-label').textContent = opt?.dataset?.nombre || 'Base';

            modalEditarDetalle.hide();
            calcularSaldosReales();
        });
    }

    // ========================================================================
    // 4. AMORTIZACIONES Y SALDOS
    // ========================================================================
    const tbodyAmortizaciones = document.querySelector('#tablaAmortizaciones tbody');
    const filaVaciaAmortizaciones = document.getElementById('filaVaciaAmortizaciones');
    let pagosLocales = [];
    let editandoPagoIndex = null;
    const modalPagoPrevioEl = document.getElementById('modalPagoPrevio');
    let modalPagoPrevio = modalPagoPrevioEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalPagoPrevioEl) : null;

    function renderAmortizaciones(amortizaciones = []) {
        window.amortizacionesRemotasCache = amortizaciones.filter(a => !a.es_local);
        if (!tbodyAmortizaciones) return;
        tbodyAmortizaciones.querySelectorAll('tr:not(#filaVaciaAmortizaciones)').forEach(tr => tr.remove());

        if (amortizaciones.length === 0) {
            if (filaVaciaAmortizaciones) filaVaciaAmortizaciones.style.display = '';
            return;
        }
        if (filaVaciaAmortizaciones) filaVaciaAmortizaciones.style.display = 'none';

        amortizaciones.forEach((amort, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Fecha">${amort.fecha || '-'}</td>
                <td data-label="Ref. Pago">${amort.referencia || '-'}</td>
                <td data-label="Método">${amort.metodo || '-'}</td>
                <td data-label="Monto" class="text-end fw-bold">${parseFloat(amort.monto || 0).toFixed(2)}</td>
                <td class="text-center text-md-end">
                    <button type="button" class="btn btn-sm btn-outline-primary border-0 js-edit-amort me-1 ${!amort.es_local ? 'd-none' : ''}"><i class="bi bi-pencil-square"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 js-del-amort ${!amort.es_local ? 'd-none' : ''}"><i class="bi bi-trash3"></i></button>
                </td>`;
            tbodyAmortizaciones.appendChild(tr);

            if (amort.es_local) {
                tr.querySelector('.js-edit-amort').addEventListener('click', () => {
                    editandoPagoIndex = Number(amort._local_index);
                    document.getElementById('pagoPrevioFecha').value = amort.fecha || '';
                    document.getElementById('pagoPrevioReferencia').value = amort.referencia || '';
                    document.getElementById('pagoPrevioMetodo').value = amort.metodo || '';
                    document.getElementById('pagoPrevioMonto').value = Number(amort.monto || 0).toFixed(2);
                    modalPagoPrevio?.show();
                });
                tr.querySelector('.js-del-amort').addEventListener('click', () => {
                    pagosLocales = pagosLocales.filter((_, i) => i !== Number(amort._local_index));
                    sincronizarPagosLocales();
                });
            }
        });
    }

    function sincronizarPagosLocales() {
        totalAmortizacionesLocales = pagosLocales.reduce((acc, item) => acc + (parseFloat(item.monto) || 0), 0);
        formSaldoInicial.querySelectorAll('input[name^="amortizacion_local_"]').forEach(inpt => inpt.remove());
        pagosLocales.forEach((pago, idx) => {
            ['fecha', 'referencia', 'metodo', 'monto'].forEach(campo => {
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = `amortizacion_local_${campo}[${idx}]`; h.value = pago[campo] || '';
                formSaldoInicial.appendChild(h);
            });
        });
        renderAmortizaciones([...(window.amortizacionesRemotasCache || []), ...pagosLocales.map((p, i) => ({ ...p, es_local: 1, _local_index: i }))]);
        calcularSaldosReales();
    }

    function calcularSaldosReales() {
        let totalCompras = 0;
        if (obtenerModoRegistro() === 'MANUAL') {
            totalCompras = Math.max(parseFloat(inputMontoBaseManual?.value || 0), 0);
        } else if (tbody) {
            tbody.querySelectorAll('tr:not(#filaVaciaMensaje)').forEach(tr => {
                totalCompras += parseFloat(tr.querySelector('.js-subtotal-input')?.value || 0);
            });
        }
        let saldoReal = totalCompras - (totalAmortizacionesRemotas + totalAmortizacionesLocales);
        if(inputMontoSaldos) inputMontoSaldos.value = Math.max(saldoReal, 0).toFixed(2);
    }

    const btnRegistrarPagoPrevio = document.getElementById('btnRegistrarPagoPrevio');
    if (btnRegistrarPagoPrevio && modalPagoPrevio) {
        btnRegistrarPagoPrevio.addEventListener('click', () => {
            editandoPagoIndex = null;
            document.getElementById('formPagoPrevioLocal').reset();
            document.getElementById('pagoPrevioFecha').value = fechaIngresoInput?.value || new Date().toISOString().slice(0, 10);
            modalPagoPrevio.show();
        });
    }

    const formPagoPrevioLocal = document.getElementById('formPagoPrevioLocal');
    if (formPagoPrevioLocal) {
        formPagoPrevioLocal.addEventListener('submit', (e) => {
            e.preventDefault();
            const data = {
                fecha: document.getElementById('pagoPrevioFecha').value,
                referencia: document.getElementById('pagoPrevioReferencia').value.trim(),
                metodo: document.getElementById('pagoPrevioMetodo').value.trim(),
                monto: Number(parseFloat(document.getElementById('pagoPrevioMonto').value || 0).toFixed(2))
            };
            if (!data.fecha || data.monto <= 0) return Swal.fire('Atención', 'Ingrese fecha y monto.', 'warning');
            
            if (editandoPagoIndex !== null) pagosLocales[editandoPagoIndex] = data;
            else pagosLocales.push(data);
            
            modalPagoPrevio?.hide();
            sincronizarPagosLocales();
        });
    }

    radiosModoRegistro.forEach(r => r.addEventListener('change', () => actualizarModoRegistroUI(obtenerModoRegistro())));
    inputMontoBaseManual?.addEventListener('input', calcularSaldosReales);

    // Arrancar la UI en estado base
    actualizarModoRegistroUI(obtenerModoRegistro());

})();