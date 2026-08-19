// ==============================================================
// MÓDULO VENTAS: venta.js (Creación y Edición de Pedidos SPA)
// ==============================================================

import { urls, recargarTabla } from './config.js';
import { getJson, postJson, obtenerFechaLocalISO, esperarTomSelect, initSelectAjax } from '../api.js';
import { calcularTotalCobroInmediato, renderAlertaSaldoFavor, saldoFavorAplicado, limpiarSaldoFavor } from './pagos.js';

// --- ESTADO GLOBAL LOCAL ---
let bloqueoEdicionVenta = false;
const estadoBusquedaItems = { tieneAcuerdo: false, listaVacia: false };

// --- TOM SELECT GLOBAL ---
let tomSelectCliente = null;
let tomSelectListo = false;

// ==========================================
// 1. UTILIDADES Y LÓGICA DE FILAS
// ==========================================

function clienteSeleccionado() {
    const idClienteEl = document.getElementById('idCliente');
    return Number(tomSelectCliente ? tomSelectCliente.getValue() : idClienteEl?.value || 0) > 0;
}

function actualizarBloqueoFormularioPorCliente() {
    const bloquearControlesVenta = bloqueoEdicionVenta || !clienteSeleccionado();
    const tipoOperacion = document.getElementById('tipoOperacion');
    const esDonacion = tipoOperacion && tipoOperacion.value === 'DONACION';
    
    const ventaTotal = document.getElementById('ventaTotal');
    const totalTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
    const totalActual = parseFloat(totalTexto) || 0;

    const btnAgregarFilaVenta = document.getElementById('btnAgregarFilaVenta');
    const btnGuardarVenta = document.getElementById('btnGuardarVenta');
    const btnMostrarTablaRegalos = document.getElementById('btnMostrarTablaRegalos');
    const btnAgregarFilaRegalo = document.getElementById('btnAgregarFilaRegalo');

    if (btnAgregarFilaVenta) btnAgregarFilaVenta.disabled = bloquearControlesVenta;
    if (btnGuardarVenta) btnGuardarVenta.disabled = bloquearControlesVenta;
    if (btnMostrarTablaRegalos) btnMostrarTablaRegalos.disabled = bloquearControlesVenta;
    if (btnAgregarFilaRegalo) btnAgregarFilaRegalo.disabled = bloquearControlesVenta;

    // Componentes de Pago Inmediato
    const switchCobroContainer = document.getElementById('switchCobroContainer');
    const switchCobroInmediato = document.getElementById('switchCobroInmediato');
    const seccionCobroInmediato = document.getElementById('seccionCobroInmediato');
    const contenedorMetodosPago = document.getElementById('contenedorMetodosPago');

    if (switchCobroContainer) {
        switchCobroContainer.style.display = (bloqueoEdicionVenta || esDonacion) ? 'none' : 'block';
    }

    if (switchCobroInmediato) {
        const bloquearSwitch = bloquearControlesVenta || esDonacion || totalActual <= 0;
        switchCobroInmediato.disabled = bloquearSwitch;
        if (bloquearSwitch) switchCobroInmediato.checked = false;
    }

    if (seccionCobroInmediato && (bloquearControlesVenta || esDonacion || totalActual <= 0)) {
        seccionCobroInmediato.classList.add('d-none');
        if (esDonacion && contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
    }

    const procesarFilas = (tbody) => {
        if (!tbody) return;
        tbody.querySelectorAll('tr').forEach((fila) => {
            fila.querySelectorAll('input:not(.detalle-precio), button').forEach((control) => {
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
    };

    procesarFilas(document.querySelector('#tablaDetalleVenta tbody'));
    procesarFilas(document.querySelector('#tablaDetalleRegalos tbody'));
}

function configurarInputCantidad(inputCantidad, permiteDecimales, valor = null) {
    if (!inputCantidad) return;
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

function actualizarNumeracionFilas() {
    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    const tbodyRegalos = document.querySelector('#tablaDetalleRegalos tbody');

    if (tbodyVenta) {
        tbodyVenta.querySelectorAll('tr').forEach((fila, index) => {
            const celdaNumero = fila.querySelector('.fila-numero');
            if (celdaNumero) celdaNumero.textContent = index + 1;
        });
    }
    
    if (tbodyRegalos) {
        tbodyRegalos.querySelectorAll('tr').forEach((fila, index) => {
            const celdaNumero = fila.querySelector('.fila-numero');
            if (celdaNumero) celdaNumero.textContent = index + 1;
        });
    }
}

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
    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    const tbodyRegalos = document.querySelector('#tablaDetalleRegalos tbody');
    const tipoOperacion = document.getElementById('tipoOperacion');
    const tipoImpuesto = document.getElementById('tipoImpuesto');
    const ventaSubtotal = document.getElementById('ventaSubtotal');
    const ventaIgv = document.getElementById('ventaIgv');
    const ventaTotal = document.getElementById('ventaTotal');
    const ventaPesoTotal = document.getElementById('ventaPesoTotal');
    const switchCobroInmediato = document.getElementById('switchCobroInmediato');
    const seccionCobroInmediato = document.getElementById('seccionCobroInmediato');
    const contenedorMetodosPago = document.getElementById('contenedorMetodosPago');

    let sumaLineas = 0;
    let pesoTotalKg = 0;
    const esDonacion = tipoOperacion && tipoOperacion.value === 'DONACION'; 

    if (tbodyVenta) {
        tbodyVenta.querySelectorAll('tr').forEach((fila) => {
            const data = filaVentaPayload(fila);
            const subtotal = data.cantidad * data.precio_unitario;
            const pesoUnitarioKg = obtenerPesoUnitarioFila(fila);
            const pesoLineaKg = data.cantidad * pesoUnitarioKg;
            
            sumaLineas += subtotal;
            pesoTotalKg += pesoLineaKg;
            
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
    }

    if (tbodyRegalos) {
        tbodyRegalos.querySelectorAll('tr').forEach((fila) => {
            const cantidad = parseFloat(fila.querySelector('.detalle-cantidad').value || 0);
            const pesoUnitarioKg = obtenerPesoUnitarioFila(fila);
            const pesoLineaKg = cantidad * pesoUnitarioKg;
            
            pesoTotalKg += pesoLineaKg;

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
        });
    }

    let subtotal = 0;
    let igv = 0;
    let total = 0;
    const tipo = tipoImpuesto ? tipoImpuesto.value : 'exonerado';

    if (esDonacion) {
        subtotal = igv = total = 0;
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
            subtotal = total = sumaLineas;
            igv = 0;
        }
    }

    if (ventaSubtotal) ventaSubtotal.textContent = `S/ ${subtotal.toFixed(2)}`;
    if (ventaIgv) ventaIgv.textContent = `S/ ${igv.toFixed(2)}`;
    if (ventaTotal) ventaTotal.textContent = esDonacion ? 'S/ 0.00 (GRATUITO)' : `S/ ${total.toFixed(2)}`;
    if (ventaPesoTotal) ventaPesoTotal.textContent = `${pesoTotalKg.toFixed(3)} kg`;

    if (switchCobroInmediato) {
        if (total <= 0) {
            switchCobroInmediato.disabled = true;
            if (switchCobroInmediato.checked) {
                switchCobroInmediato.checked = false;
                if(seccionCobroInmediato) seccionCobroInmediato.classList.add('d-none');
                if(contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
                calcularTotalCobroInmediato();
            }
        } else {
            if (!bloqueoEdicionVenta && !esDonacion) {
                switchCobroInmediato.disabled = false;
            }
        }
    }

    if (switchCobroInmediato && switchCobroInmediato.checked && !esDonacion) {
        if (contenedorMetodosPago) {
            const filasPago = contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato');
            const btnUsarSaldo = document.getElementById('btnAplicarSaldoFavor');
            
            if (filasPago.length === 1 && (!btnUsarSaldo || !btnUsarSaldo.disabled)) { 
                filasPago[0].querySelector('.input-monto-inmediato').value = total.toFixed(2);
            }
        }
        calcularTotalCobroInmediato();
    }
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

function obtenerItemsSeleccionados(excluirFila = null) {
    const seleccionados = new Set();
    if (!excluirFila) return seleccionados;
    
    const tbodyPadre = excluirFila.closest('tbody');
    if (!tbodyPadre) return seleccionados;

    const filas = [...tbodyPadre.querySelectorAll('tr')];
    filas.forEach((fila) => {
        if (fila === excluirFila) return;
        const selectEl = fila.querySelector('.detalle-item');
        const idItem = selectEl && selectEl.tomselect ? selectEl.tomselect.getValue() : (selectEl?.value || '');
        if (idItem !== '') seleccionados.add(idItem);
    });
    return seleccionados;
}

async function obtenerPrecioItem(idItem, cantidad) {
    const idClienteEl = document.getElementById('idCliente');
    const idClienteActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idClienteEl?.value || 0);
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
    if (precioNuevo > 0) inputPrecio.value = precioNuevo.toFixed(4);
    
    recalcularTotalVenta();
}

async function refrescarFilasPorCambioCliente() {
    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    if(!tbodyVenta) return;
    const filas = [...tbodyVenta.querySelectorAll('tr')];
    for (const fila of filas) {
        await refrescarPrecioFila(fila);
    }
    recalcularTotalVenta();
}

async function agregarFilaVenta(item = null, esBorrador = true) {
    const templateFilaVenta = document.getElementById('templateFilaVenta');
    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    if(!templateFilaVenta || !tbodyVenta) return;

    const fragment = templateFilaVenta.content.cloneNode(true);
    const filaReal = fragment.querySelector('tr');
    tbodyVenta.appendChild(fragment);

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
        actualizarNumeracionFilas(); 
        recalcularTotalVenta();
    });

    if (!tomSelectListo) {
        selectItem.innerHTML = '<option value="">Tom Select no disponible</option>';
        selectItem.disabled = true;
        return;
    }

    const tom = initSelectAjax(selectItem, `${urls.index}&accion=buscar_items`, {
        placeholder: "Buscar producto...",
        dropdownParent: 'body', 
        load: function(query, callback) {
            const idClienteEl = document.getElementById('idCliente');
            const idClienteActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idClienteEl?.value || 0);
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
                inputPrecio.value = selectedOption.precio.toFixed(4);
                filaReal.dataset.pesoKg = String(Number(selectedOption.pesoKg || 0));
                
                let valorActual = inputCantidad.value;
                if (valorActual === '0' || valorActual === '0.00' || valorActual === '') valorActual = ''; 
                configurarInputCantidad(inputCantidad, selectedOption.permiteDecimales, valorActual);
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
            loading: () => '<div class="spinner-border spinner-border-sm text-primary m-2"></div> buscando...',
            option: function(data, escape) {
                const stockColor = data.stock <= 0 ? 'text-danger fw-bold' : 'text-success';
                let stockLabel = 'SIN STOCK';
                if (data.stock > 0) {
                    stockLabel = (data.permiteDecimales === 1) 
                        ? Number(data.stock).toFixed(2) 
                        : String(Math.round(data.stock)); 
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
        inputPrecio.value = Number(item.precio_unitario || 0).toFixed(4);
        
        const stockItem = Number(item.stock_actual || 0);
        const stockMostrar = Number(item.permite_decimales || 0) === 1 ? stockItem.toFixed(2) : String(Math.round(stockItem));
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

    actualizarNumeracionFilas(); 
    recalcularTotalVenta();
}

async function agregarFilaRegalo(item = null, esBorrador = true) {
    const templateFilaRegalo = document.getElementById('templateFilaRegalo');
    const tbodyRegalos = document.querySelector('#tablaDetalleRegalos tbody');
    if (!templateFilaRegalo || !tbodyRegalos) return;

    const fragment = templateFilaRegalo.content.cloneNode(true);
    const filaReal = fragment.querySelector('tr');
    tbodyRegalos.appendChild(fragment);

    const inputCantidad = filaReal.querySelector('.detalle-cantidad');
    const inputPrecioRef = filaReal.querySelector('.detalle-precio'); 
    const selectItem = filaReal.querySelector('.detalle-item');
    const btnQuitar = filaReal.querySelector('.btn-quitar-fila');

    if (!esBorrador) {
        inputCantidad.readOnly = true;
        inputCantidad.classList.add('bg-light', 'border-0');
        if (btnQuitar) btnQuitar.style.display = 'none';
    }

    inputCantidad.addEventListener('input', () => {
        validarCantidadVsStock(filaReal);
        recalcularTotalVenta(); 
    });
    
    btnQuitar.addEventListener('click', () => {
        if (selectItem.tomselect) selectItem.tomselect.destroy();
        filaReal.remove();
        actualizarNumeracionFilas(); 
        recalcularTotalVenta();
    });

    if (!tomSelectListo) {
        selectItem.innerHTML = '<option value="">Tom Select no disponible</option>';
        selectItem.disabled = true;
        return;
    }

    const tom = initSelectAjax(selectItem, `${urls.index}&accion=buscar_items`, {
        placeholder: "Buscar producto de regalo...",
        dropdownParent: 'body', 
        load: function(query, callback) {
            const idClienteEl = document.getElementById('idCliente');
            const idClienteActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idClienteEl?.value || 0);
            const cantidadActual = Number(inputCantidad.value || 1) || 1;
            const url = `${urls.index}&accion=buscar_items&q=${encodeURIComponent(query)}&id_cliente=${idClienteActual}&cantidad=${encodeURIComponent(cantidadActual)}`;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.json())
                .then(json => {
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
                    Swal.fire('Producto repetido', 'Este producto ya está seleccionado en la venta principal o en los regalos.', 'warning');
                    return;
                }

                filaReal.querySelector('.detalle-stock').textContent = selectedOption.stock.toFixed(2);
                inputPrecioRef.value = selectedOption.precio.toFixed(4);
                filaReal.dataset.pesoKg = String(Number(selectedOption.pesoKg || 0));
                
                let valorActual = inputCantidad.value;
                if (valorActual === '0' || valorActual === '0.00' || valorActual === '') valorActual = ''; 
                configurarInputCantidad(inputCantidad, selectedOption.permiteDecimales, valorActual);
                setTimeout(() => inputCantidad.focus(), 50);
            }
            validarCantidadVsStock(filaReal);
            recalcularTotalVenta();
        },
        render: {
            no_results: () => '<div class="no-results">No se encontraron productos</div>',
            loading: () => '<div class="spinner-border spinner-border-sm text-info m-2"></div> buscando...',
            option: function(data, escape) {
                const stockColor = data.stock <= 0 ? 'text-danger fw-bold' : 'text-success';
                let stockLabel = data.stock <= 0 ? 'SIN STOCK' : (data.permiteDecimales === 1 ? Number(data.stock).toFixed(2) : String(Math.round(data.stock))); 
                return `<div class="py-2 d-flex justify-content-between align-items-center">
                    <div><div class="fw-bold text-dark">${escape(data.text)}</div></div>
                    <div class="text-end">
                        <div class="small ${stockColor}">Stock: ${stockLabel}</div>
                        <div class="fw-bold text-muted small">Ref: S/ ${escape(Number(data.precio).toFixed(2))}</div>
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
        inputPrecioRef.value = Number(item.precio_unitario || 0).toFixed(4);
        
        const stockItem = Number(item.stock_actual || 0);
        const stockMostrar = Number(item.permite_decimales || 0) === 1 ? stockItem.toFixed(2) : String(Math.round(stockItem));
        filaReal.querySelector('.detalle-stock').textContent = stockMostrar;
    }

    actualizarNumeracionFilas(); 
    recalcularTotalVenta();
}

async function mostrarTablaRegalos() {
    const seccionRegalos = document.getElementById('seccionRegalos');
    const tbodyRegalos = document.querySelector('#tablaDetalleRegalos tbody');
    if (!seccionRegalos || !tbodyRegalos) return;

    seccionRegalos.classList.remove('d-none');
    if (tbodyRegalos.children.length === 0) {
        await agregarFilaRegalo();
    }
    seccionRegalos.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function limpiarModalVenta() {
    limpiarSaldoFavor();
    bloqueoEdicionVenta = false;
    const ventaId = document.getElementById('ventaId');
    if (ventaId) ventaId.value = 0;

    renderAlertaSaldoFavor(0);
    
    if (tomSelectCliente) {
        tomSelectCliente.clear();
        tomSelectCliente.clearOptions();
        tomSelectCliente.load('');
    }
    
    const fechaEmision = document.getElementById('fechaEmision');
    const ventaObservaciones = document.getElementById('ventaObservaciones');
    if(fechaEmision) fechaEmision.value = obtenerFechaLocalISO();
    if(ventaObservaciones) ventaObservaciones.value = '';
    
    const tipoOperacion = document.getElementById('tipoOperacion');
    const tipoImpuesto = document.getElementById('tipoImpuesto');
    if (tipoOperacion) {
        tipoOperacion.value = 'VENTA';
        tipoOperacion.disabled = false;
    }
    if (tipoImpuesto) {
        tipoImpuesto.value = 'exonerado';
        tipoImpuesto.disabled = false;
        tipoImpuesto.removeAttribute('data-readonly');
    }

    const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
    if (tbodyVenta) {
        tbodyVenta.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) select.tomselect.destroy();
        });
        tbodyVenta.innerHTML = '';
    }

    const tbodyRegalos = document.querySelector('#tablaDetalleRegalos tbody');
    if (tbodyRegalos) {
        tbodyRegalos.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) select.tomselect.destroy();
        });
        tbodyRegalos.innerHTML = '';
    }

    const seccionRegalos = document.getElementById('seccionRegalos');
    if (seccionRegalos) seccionRegalos.classList.add('d-none');

    const ventaSubtotal = document.getElementById('ventaSubtotal');
    const ventaIgv = document.getElementById('ventaIgv');
    const ventaTotal = document.getElementById('ventaTotal');
    const ventaPesoTotal = document.getElementById('ventaPesoTotal');
    
    if (ventaSubtotal) ventaSubtotal.textContent = 'S/ 0.00';
    if (ventaIgv) ventaIgv.textContent = 'S/ 0.00';
    if (ventaTotal) {
        ventaTotal.textContent = 'S/ 0.00';
        ventaTotal.classList.add('text-primary');
        ventaTotal.classList.remove('text-success');
    }
    if (ventaPesoTotal) ventaPesoTotal.textContent = '0.000 kg'; 
    
    const btnGuardar = document.getElementById('btnGuardarVenta');
    if (btnGuardar) {
        btnGuardar.textContent = 'Guardar Pedido';
        btnGuardar.style.display = 'block';
    }
    
    const contenedorAlerta = document.getElementById('alertaBorradorContenedor');
    if (contenedorAlerta) contenedorAlerta.innerHTML = '';
    
    const seccionDevoluciones = document.getElementById('seccionDevolucionesVenta');
    if (seccionDevoluciones) {
        seccionDevoluciones.classList.add('d-none');
        const tbodyDevHistorico = document.querySelector('#tablaDevolucionesHistorico tbody');
        if (tbodyDevHistorico) tbodyDevHistorico.innerHTML = '';
    }

    const switchCobroContainer = document.getElementById('switchCobroContainer');
    const switchCobroInmediato = document.getElementById('switchCobroInmediato');
    const seccionCobroInmediato = document.getElementById('seccionCobroInmediato');
    const contenedorMetodosPago = document.getElementById('contenedorMetodosPago');
    const totalPagadoInmediato = document.getElementById('totalPagadoInmediato');

    if (switchCobroContainer) switchCobroContainer.style.display = 'block';
    if (switchCobroInmediato) switchCobroInmediato.checked = false;
    if (seccionCobroInmediato) seccionCobroInmediato.classList.add('d-none');
    if (contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
    if (totalPagadoInmediato) totalPagadoInmediato.textContent = 'S/ 0.00';

    actualizarBloqueoFormularioPorCliente();
}


// ==========================================
// 3. EXPORTACIONES PRINCIPALES DE VENTAS
// ==========================================

export async function abrirModalVenta(id, tr = null) {
    try {
        const separador = urls.index.includes('?') ? '&' : '?';
        const payload = await getJson(`${urls.index}${separador}accion=ver&id=${id}`);
        const venta = payload.data;
        if (!venta || !venta.id) throw new Error('No se encontró información del pedido seleccionado.');
        renderAlertaSaldoFavor(venta.saldo_favor_cliente || 0);

        const estadoDoc = Number(venta.estado || 0);

        // Si el estado es >= 3, abrimos modal de resumen (Solo vista)
            if (estadoDoc >= 3) {
                const modalResumenEl = document.getElementById('modalResumenVenta');
                if (!modalResumenEl) throw new Error('El modal de resumen no está disponible.');

                const modalHeader = modalResumenEl.querySelector('.modal-header');
                const modalTitle = modalResumenEl.querySelector('.modal-title');
                if (estadoDoc === 9) {
                    modalHeader.className = 'modal-header bg-secondary text-white border-bottom-0 pb-4';
                    modalTitle.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Resumen de Pedido Anulado';
                } else {
                    modalHeader.className = 'modal-header bg-success text-white border-bottom-0 pb-4';
                    modalTitle.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Resumen de Venta Finalizada';
                }

                const nombreClienteTabla = tr?.querySelector('td:nth-child(2) .fw-semibold')?.textContent?.trim() || 'Cliente No Especificado';
                // ... (sigue tu código normal)
            document.getElementById('resumenVentaCodigo').textContent = venta.codigo || '-';
            document.getElementById('resumenVentaCliente').textContent = nombreClienteTabla;
            document.getElementById('resumenVentaOperacion').textContent = venta.tipo_operacion || 'VENTA';
            
            const formatearFechaVista = (fechaStr) => {
                if (!fechaStr) return '-';
                const fechaBase = String(fechaStr).trim().split(' ')[0];
                const matchIso = fechaBase.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (matchIso) return `${matchIso[3]}/${matchIso[2]}/${matchIso[1]}`;
                return fechaStr;
            };

            document.getElementById('resumenVentaFechaEmision').textContent = formatearFechaVista(venta.fecha_emision);
            document.getElementById('resumenVentaFechaDespacho').textContent = venta.fecha_despacho ? formatearFechaVista(venta.fecha_despacho) : 'Pendiente';
            
            const obsPedido = venta.observaciones ? venta.observaciones.trim() : '';
            const obsDespacho = venta.observaciones_despacho ? venta.observaciones_despacho.trim() : '';

            const elObsPedido = document.getElementById('resumenVentaObsPedido');
            if (elObsPedido) elObsPedido.innerHTML = `<i class="bi bi-file-earmark-text text-primary opacity-75 me-1"></i><strong>Pedido:</strong> <span class="${obsPedido ? 'text-dark' : 'fst-italic opacity-50'}">${obsPedido || 'Sin nota'}</span>`;
            
            const elObsDespacho = document.getElementById('resumenVentaObsDespacho');
            if (elObsDespacho) elObsDespacho.innerHTML = `<i class="bi bi-truck text-info opacity-75 me-1"></i><strong>Despacho:</strong> <span class="${obsDespacho ? 'text-dark' : 'fst-italic opacity-50'}">${obsDespacho || 'Sin guía/nota'}</span>`;

            const totalPedido = Number(venta.total || 0);
            const montoPagado = Number(venta.monto_pagado || 0);
            const deudaPendiente = Math.max(0, totalPedido - montoPagado);

            const badgePagoContenedor = document.getElementById('resumenVentaEstadoPagoBadge');
            const textoDeudaContenedor = document.getElementById('resumenVentaMontoPendiente');
            const divModalidad = document.getElementById('resumenVentaModalidadPago');
            const listaPagos = document.getElementById('lista_pagos_detallados');
            const divDeuda = document.getElementById('resumenVentaDeuda');
            const valDeuda = document.getElementById('val_deuda_pendiente');

            if (badgePagoContenedor && textoDeudaContenedor) {
                    if (listaPagos) {
                        listaPagos.innerHTML = '';
                        if (venta.pagos_detallados && venta.pagos_detallados.length > 0) {
                            let htmlPagos = '';
                            venta.pagos_detallados.forEach(pago => { htmlPagos += `<li><strong>${pago.metodo}</strong>: S/ ${Number(pago.monto).toFixed(2)}</li>`; });
                            listaPagos.innerHTML = htmlPagos;
                        } else {
                            listaPagos.innerHTML = '<li>Sin pagos registrados</li>';
                        }
                    }

                    if (estadoDoc === 9) {
                        badgePagoContenedor.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-slash-circle-fill me-1"></i>Pedido Anulado</span>';
                        textoDeudaContenedor.innerHTML = `<span class="text-muted fw-semibold">Sin deuda activa.</span>`;
                        if (divModalidad) divModalidad.style.display = 'none';
                        if (divDeuda) divDeuda.style.display = 'none';
                    } 
                    else if (deudaPendiente <= 0.001) {
                        badgePagoContenedor.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Pagado Total</span>';
                        textoDeudaContenedor.innerHTML = `Total abonado: <span class="fw-bold text-dark">S/ ${totalPedido.toFixed(2)}</span>`;
                        if (divDeuda) divDeuda.style.display = 'none';
                        if (divModalidad) divModalidad.style.display = 'block';
                    } 
                    else if (montoPagado > 0) {
                        badgePagoContenedor.innerHTML = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-pie-chart-fill me-1"></i>Pago Parcial</span>';
                        textoDeudaContenedor.innerHTML = `Abonado parcial: <span class="text-dark">S/ ${montoPagado.toFixed(2)}</span>`;
                        if (divModalidad) divModalidad.style.display = 'block';
                        if (divDeuda) {
                            divDeuda.style.display = 'block';
                            if (valDeuda) valDeuda.textContent = `S/ ${deudaPendiente.toFixed(2)}`;
                        }
                    } 
                    else {
                        badgePagoContenedor.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i>Por Cobrar</span>';
                        textoDeudaContenedor.innerHTML = `Abonado: <span class="text-dark">S/ 0.00</span>`;
                        if (divModalidad) divModalidad.style.display = 'none';
                        if (divDeuda) {
                            divDeuda.style.display = 'block';
                            if (valDeuda) valDeuda.textContent = `S/ ${deudaPendiente.toFixed(2)}`;
                        }
                    }
                }

            const tbodyResumen = document.querySelector('#tablaResumenProductos tbody');
            const pesoTotalResumenEl = document.getElementById('resumenVentaPesoTotal');
            let pesoTotalResumen = 0;
            let sumaTotalDespachada = 0;
            if(tbodyResumen) tbodyResumen.innerHTML = '';

            if (venta.detalle && venta.detalle.length > 0 && tbodyResumen) {
                venta.detalle.forEach(item => {
                    const cantSol = Number(item.cantidad || 0);
                    const cantDesp = Number(item.cantidad_despachada || 0);
                    const precio = Number(item.precio_unitario || 0);
                    const pesoUnitario = Number(item.peso_kg || 0);
                    const esBonificacion = Number(item.es_bonificacion || 0); 
                    
                    const pesoSubtotal = cantDesp * pesoUnitario;
                    const subtotalCobrar = esBonificacion === 1 ? 0 : (cantDesp * precio);
                    const subtotalReferencial = cantDesp * precio;
                    
                    pesoTotalResumen += pesoSubtotal;
                    sumaTotalDespachada += subtotalCobrar;

                    const subtituloPeso = pesoUnitario > 0
                        ? `<small class="text-muted d-block mt-1">Peso total: ${pesoSubtotal.toFixed(3)} kg</small>`
                        : '<small class="text-muted d-block mt-1">Peso total: 0.000 kg</small>';

                    let nombreItemHtml = `${item.item_nombre || '-'}`;
                    let subtotalHtml = `S/ ${subtotalCobrar.toFixed(2)}`;
                    let claseFila = '';

                    if (esBonificacion === 1) {
                        claseFila = 'bg-info bg-opacity-10'; 
                        nombreItemHtml += ` <span class="badge bg-info-subtle text-info border border-info-subtle ms-2">🎁 Regalo</span>`;
                        subtotalHtml = `<span class="text-success fw-bold">S/ 0.00</span><br><small class="text-decoration-line-through text-muted opacity-50" style="font-size: 0.7rem;">S/ ${subtotalReferencial.toFixed(2)}</small>`;
                    }

                    const trRes = document.createElement('tr');
                    if (claseFila) trRes.className = claseFila;
                    
                    trRes.innerHTML = `
                        <td class="ps-3 py-2 fw-semibold text-dark">${nombreItemHtml}${subtituloPeso}</td>
                        <td class="text-center py-2 text-muted">${cantSol.toFixed(2)}</td>
                        <td class="text-center py-2 fw-bold text-success">${cantDesp.toFixed(2)}</td>
                        <td class="text-end py-2 text-muted">S/ ${precio.toFixed(2)}</td>
                        <td class="text-end pe-3 py-2 fw-bold text-dark lh-sm">${subtotalHtml}</td>
                    `;
                    tbodyResumen.appendChild(trRes);
                });
            } else if (tbodyResumen) {
                tbodyResumen.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay productos registrados.</td></tr>';
            }

            if (pesoTotalResumenEl) pesoTotalResumenEl.textContent = `Peso total: ${pesoTotalResumen.toFixed(3)} kg`;
            const totalFinalReal = Number.isFinite(sumaTotalDespachada) ? sumaTotalDespachada : 0;
            const resVentaTotalFinal = document.getElementById('resumenVentaTotalFinal');

            // =========================================================
            // 👇 HISTORIAL DE DEVOLUCIONES Y TOTAL NETO 👇
            // =========================================================
            const contDevoluciones = document.getElementById('resumenVentaDevolucionesContenedor');
            const tbodyDevoluciones = document.querySelector('#tablaResumenDevoluciones tbody');
            
            let sumaTotalDevoluciones = 0;

            if (contDevoluciones && tbodyDevoluciones) {
                if (venta.devoluciones && venta.devoluciones.length > 0) {
                    contDevoluciones.classList.remove('d-none');
                    tbodyDevoluciones.innerHTML = '';
            
                    venta.devoluciones.forEach(dev => {
                        sumaTotalDevoluciones += Number(dev.total_devuelto || 0);

                        // Formatear la fecha
                        const fechaDev = new Date(dev.created_at);
                        const fechaFormat = fechaDev.toLocaleDateString('es-PE') + ' - ' + fechaDev.toLocaleTimeString('es-PE', {hour: '2-digit', minute:'2-digit'});
            
                        // Armar lista de productos devueltos
                        const productosList = (dev.detalle || []).map(d => 
                            `<div class="small text-dark mb-1"><span class="badge bg-secondary-subtle text-secondary me-1 border border-secondary-subtle">${Number(d.cantidad)}x</span> ${d.item_nombre}</div>`
                        ).join('');
            
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="ps-3 text-muted small fw-semibold">${fechaFormat}</td>
                            <td class="text-dark small"><div class="fw-bold">${dev.motivo || 'Devolución'}</div></td>
                            <td>${productosList}</td>
                            <td class="text-end pe-3 fw-bold text-danger">S/ ${Number(dev.total_devuelto).toFixed(2)}</td>
                        `;
                        tbodyDevoluciones.appendChild(tr);
                    });
                } else {
                    contDevoluciones.classList.add('d-none');
                    tbodyDevoluciones.innerHTML = '';
                }
            }

            // Actualizamos el Total Final considerando las devoluciones
            if (resVentaTotalFinal) {
                if (sumaTotalDevoluciones > 0) {
                    const netoReal = totalFinalReal - sumaTotalDevoluciones;
                    resVentaTotalFinal.innerHTML = `
                        <div class="text-muted text-decoration-line-through fw-normal" style="font-size: 0.9rem;">S/ ${totalFinalReal.toFixed(2)}</div>
                        <div class="text-danger small fw-semibold lh-1 mb-1">- S/ ${sumaTotalDevoluciones.toFixed(2)} (Dev.)</div>
                        <div class="text-primary fs-4 lh-1 mt-1">S/ ${netoReal.toFixed(2)}</div>
                    `;
                } else {
                    resVentaTotalFinal.innerHTML = `S/ ${totalFinalReal.toFixed(2)}`;
                }
            }
            // =========================================================
            // 👆 FIN DEL HISTORIAL DE DEVOLUCIONES Y TOTAL NETO 👆
            // =========================================================
            
            bootstrap.Modal.getOrCreateInstance(modalResumenEl).show();
            return; 
        }

        // Si es < 3 (Borrador o Edición), abrimos el Modal principal de Venta
        limpiarModalVenta();
        const ventaId = document.getElementById('ventaId');
        if (ventaId) ventaId.value = venta.id;
        
        const esBorrador = estadoDoc === 0;
        bloqueoEdicionVenta = !esBorrador;
        
        const nombreCliente = tr?.querySelector('td:nth-child(2) .fw-semibold')?.textContent?.trim() || 'Cliente';
        const idClienteEl = document.getElementById('idCliente');
        if (tomSelectCliente) {
            tomSelectCliente.addOption({ id: venta.id_cliente, text: nombreCliente, saldo_favor: Number(venta.saldo_favor_cliente || 0) });
            tomSelectCliente.setValue(venta.id_cliente);
            if (!esBorrador) tomSelectCliente.disable();
            else tomSelectCliente.enable();
        } else if (idClienteEl) {
            idClienteEl.innerHTML = `<option value="${venta.id_cliente}">${nombreCliente}</option>`;
            idClienteEl.value = venta.id_cliente;
            idClienteEl.disabled = !esBorrador;
        }
        
        const fechaEmision = document.getElementById('fechaEmision');
        const ventaObservaciones = document.getElementById('ventaObservaciones');
        const tipoOperacion = document.getElementById('tipoOperacion');
        const tipoImpuesto = document.getElementById('tipoImpuesto');

        if(fechaEmision) {
            fechaEmision.value = venta.fecha_emision ? venta.fecha_emision.split(' ')[0] : '';
            fechaEmision.readOnly = !esBorrador;
        }
        if(ventaObservaciones) {
            ventaObservaciones.value = venta.observaciones || '';
            ventaObservaciones.readOnly = !esBorrador;
        }
        
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
        
        const btnGlobalAdd = document.getElementById('btnAgregarFilaVenta');
        if (btnGlobalAdd) btnGlobalAdd.style.display = esBorrador ? 'inline-block' : 'none';

        let tieneRegalos = false;
        if (venta.detalle && venta.detalle.length) {
            for (const linea of venta.detalle) {
                if (Number(linea.es_bonificacion || 0) === 1) {
                    await agregarFilaRegalo(linea, esBorrador);
                    tieneRegalos = true;
                } else {
                    await agregarFilaVenta(linea, esBorrador);
                }
            }
        } else {
            await agregarFilaVenta(null, esBorrador);
        }

        const seccionRegalos = document.getElementById('seccionRegalos');
        if (tieneRegalos && seccionRegalos) seccionRegalos.classList.remove('d-none');
        
        const btnGuardar = document.getElementById('btnGuardarVenta');
        const switchCobroContainer = document.getElementById('switchCobroContainer');

        if (esBorrador) {
            if (btnGuardar) {
                btnGuardar.style.display = 'block';
                btnGuardar.textContent = 'Actualizar Pedido';
            }
            const alertaContenedor = document.getElementById('alertaBorradorContenedor');
            if (alertaContenedor) {
                alertaContenedor.innerHTML = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium px-2 py-1"><i class="bi bi-info-circle me-1"></i>Borrador: No descuenta stock físico</span>`;
            }
            if (switchCobroContainer) switchCobroContainer.style.display = 'block';
        } else {
            if (btnGuardar) btnGuardar.style.display = 'none';
            if (switchCobroContainer) switchCobroContainer.style.display = 'none';
        }

        actualizarBloqueoFormularioPorCliente();

        const seccionDevoluciones = document.getElementById('seccionDevolucionesVenta');
        const tbodyDevHistorico = document.querySelector('#tablaDevolucionesHistorico tbody');
        
        if (seccionDevoluciones && tbodyDevHistorico) {
            tbodyDevHistorico.innerHTML = ''; 
            
            if (venta.devoluciones && venta.devoluciones.length > 0) {
                seccionDevoluciones.classList.remove('d-none'); 
                
                venta.devoluciones.forEach(dev => {
                    let detallesHTML = '<ul class="mb-0 ps-3 text-muted" style="font-size: 0.85rem;">';
                    (dev.detalle || []).forEach(item => {
                        detallesHTML += `<li>${Number(item.cantidad).toFixed(2)}x ${item.item_nombre}</li>`;
                    });
                    detallesHTML += '</ul>';

                    let resTexto = dev.tipo_resolucion;
                    if (resTexto === 'descuento_cxc') resTexto = 'Nota de Crédito (CxC)';
                    else if (resTexto === 'reembolso_dinero') resTexto = 'Reembolso (Caja/Bancos)';
                    else if (resTexto === 'saldo_favor') resTexto = 'Saldo a Favor';

                    const trDev = document.createElement('tr');
                    trDev.innerHTML = `
                        <td class="ps-3 text-dark fw-semibold" style="font-size: 0.9rem;">${dev.created_at.substring(0, 16)}</td>
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 0.9rem;">${dev.motivo}</div>
                            <div class="badge bg-secondary-subtle text-secondary mt-1 border border-secondary-subtle">${resTexto}</div>
                        </td>
                        <td>${detallesHTML}</td>
                        <td class="text-end pe-4 fw-bold text-danger">S/ ${Number(dev.total_devuelto).toFixed(2)}</td>
                    `;
                    tbodyDevHistorico.appendChild(trDev);
                });
            } else {
                seccionDevoluciones.classList.add('d-none'); 
            }
        }
        
        const modalVentaEl = document.getElementById('modalVenta');
        if(modalVentaEl) bootstrap.Modal.getOrCreateInstance(modalVentaEl).show();
    } catch (err) {
        console.error('Error al abrir pedido:', err);
        Swal.fire('Error', err.message || 'No se pudo cargar', 'error');
    }
}

export async function revertirBorrador(id) {
    const ok = await Swal.fire({ 
        icon: 'warning', 
        title: '¿Revertir a Borrador?', 
        text: 'El pedido volverá a estado inicial y podrá ser editado. Se eliminará la cuenta por cobrar (si existe).',
        showCancelButton: true, 
        confirmButtonText: 'Sí, revertir',
        confirmButtonColor: '#ffc107', 
        cancelButtonColor: '#6c757d'
    });
    
    if (ok.isConfirmed) {
        try {
            const res = await postJson(`${urls.index}&accion=revertir`, { id });
            await Swal.fire('Revertido', res.mensaje, 'success');
            recargarTabla();
        } catch (err) { 
            Swal.fire('Error', err.message, 'error'); 
        }
    }
}

export async function initVentas() {
    tomSelectListo = await esperarTomSelect();
    
    const idClienteEl = document.getElementById('idCliente');
    // Destruimos el TomSelect anterior si estamos recargando por AJAX
    if (tomSelectCliente && idClienteEl && !idClienteEl.tomselect) {
        tomSelectCliente = null; 
    }

    if (idClienteEl && tomSelectListo && !tomSelectCliente) {
        tomSelectCliente = initSelectAjax('#idCliente', `${urls.index}&accion=buscar_clientes`, {
            allowEmptyOption: true,
            placeholder: "Buscar cliente por nombre o documento...",
            dropdownParent: 'body', 
            preload: true,
            loadThrottle: 250,
            load: function(query, callback) {
                const termino = (query || '').trim();
                const url = `${urls.index}&accion=buscar_clientes&q=${encodeURIComponent(termino)}`;
                
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then(json => {
                    const items = (json.data || []).map(item => ({
                        id: item.id,
                        text: `${item.nombre_completo} (${item.num_doc || 'S/D'})`,
                        saldo_favor: Number(item.saldo_favor || 0)
                    }));
                    callback(items);
                }).catch(() => callback());
            },
            render: {
                no_results: () => '<div class="no-results">No se encontraron coincidencias</div>',
                loading: () => '<div class="spinner-border spinner-border-sm text-primary m-2"></div> Buscando...'
            }
        });
        
        tomSelectCliente?.on('change', (value) => {
            const opt = tomSelectCliente?.options?.[value];
            renderAlertaSaldoFavor(opt?.saldo_favor || 0);
            refrescarFilasPorCambioCliente();
            actualizarBloqueoFormularioPorCliente();
        });
    }

    if (idClienteEl && !tomSelectCliente) {
        idClienteEl.addEventListener('change', () => {
            refrescarFilasPorCambioCliente();
            actualizarBloqueoFormularioPorCliente();
        });
    }

    // --- EVENTOS DEL MODAL Y BOTONES (Usamos removeEventListener viejo indirectamente mediante el orquestador o buscando clics limpios) ---
    const btnNuevaVenta = document.getElementById('btnNuevaVenta');
    if (btnNuevaVenta) {
        // Clonamos para evitar duplicar el evento al navegar por AJAX
        const nuevoBtnVenta = btnNuevaVenta.cloneNode(true);
        btnNuevaVenta.parentNode.replaceChild(nuevoBtnVenta, btnNuevaVenta);
        nuevoBtnVenta.addEventListener('click', async () => {
            try {
                limpiarModalVenta();
                await agregarFilaVenta(); 
                actualizarBloqueoFormularioPorCliente();

                const btnGuardar = document.getElementById('btnGuardarVenta');
                if (btnGuardar) {
                    btnGuardar.style.display = 'block';
                    btnGuardar.textContent = 'Guardar Pedido';
                }

                if (!document.getElementById('alertaBorradorInfo')) {
                    const contenedor = document.getElementById('alertaBorradorContenedor');
                    if (contenedor) {
                        contenedor.innerHTML = `<span id="alertaBorradorInfo" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium px-2 py-1"><i class="bi bi-info-circle me-1"></i>Borrador: No descuenta stock físico</span>`;
                    }
                }
                const modalVentaEl = document.getElementById('modalVenta');
                if(modalVentaEl) bootstrap.Modal.getOrCreateInstance(modalVentaEl).show();
            } catch (error) {
                Swal.fire('Error', 'No se pudo abrir el formulario de pedido.', 'error');
            }
        });
    }

    const modalVentaEl = document.getElementById('modalVenta');
    if (modalVentaEl) {
        modalVentaEl.addEventListener('hidden.bs.modal', () => {
            const hayModalesAbiertos = document.querySelectorAll('.modal.show').length > 0;
            if (!hayModalesAbiertos) {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
                document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
            }
        });
    }

    const rebindClick = (id, callback) => {
        const btn = document.getElementById(id);
        if (btn) {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            newBtn.addEventListener('click', callback);
        }
    };

    rebindClick('btnMostrarTablaRegalos', async () => await mostrarTablaRegalos());
    rebindClick('btnCerrarTablaRegalos', () => {
        const seccionRegalos = document.getElementById('seccionRegalos');
        if (seccionRegalos) seccionRegalos.classList.add('d-none');
    });

    rebindClick('btnAgregarFilaVenta', async () => {
        await agregarFilaVenta();
        actualizarBloqueoFormularioPorCliente();
    });
    
    rebindClick('btnAgregarFilaRegalo', async () => {
        await agregarFilaRegalo();
        actualizarBloqueoFormularioPorCliente();
    });

    const tipoImpuesto = document.getElementById('tipoImpuesto');
    if(tipoImpuesto) {
        const newTipoImpuesto = tipoImpuesto.cloneNode(true);
        tipoImpuesto.parentNode.replaceChild(newTipoImpuesto, tipoImpuesto);
        newTipoImpuesto.addEventListener('change', recalcularTotalVenta);
    }
    
    const tipoOperacion = document.getElementById('tipoOperacion');
    if(tipoOperacion) {
        const newTipoOperacion = tipoOperacion.cloneNode(true);
        tipoOperacion.parentNode.replaceChild(newTipoOperacion, tipoOperacion);
        newTipoOperacion.addEventListener('change', () => {
            const tImpuesto = document.getElementById('tipoImpuesto');
            if (newTipoOperacion.value === 'DONACION') {
                if (tImpuesto) {
                    tImpuesto.value = 'exonerado';
                    tImpuesto.disabled = true;
                }
                const switchCobroInmediato = document.getElementById('switchCobroInmediato');
                const seccionCobroInmediato = document.getElementById('seccionCobroInmediato');
                const contenedorMetodosPago = document.getElementById('contenedorMetodosPago');
                const totalPagadoInmediato = document.getElementById('totalPagadoInmediato');
                
                if (switchCobroInmediato) switchCobroInmediato.checked = false;
                if (seccionCobroInmediato) seccionCobroInmediato.classList.add('d-none');
                if (contenedorMetodosPago) contenedorMetodosPago.innerHTML = '';
                if (totalPagadoInmediato) totalPagadoInmediato.textContent = 'S/ 0.00';
            } else {
                if (tImpuesto && !tImpuesto.hasAttribute('data-readonly')) {
                    tImpuesto.disabled = false;
                }
            }
            actualizarBloqueoFormularioPorCliente();
            recalcularTotalVenta();
        });
    }

    // --- GUARDADO ---
    rebindClick('btnGuardarVenta', async () => {
        try {
            const idClienteEl = document.getElementById('idCliente');
            const clienteIdActual = Number(tomSelectCliente ? tomSelectCliente.getValue() : idClienteEl?.value || 0);
            if (!clienteIdActual) throw new Error('Debe seleccionar Cliente / Beneficiario antes de continuar.');

            const tbodyVenta = document.querySelector('#tablaDetalleVenta tbody');
            const tbodyRegalos = document.querySelector('#tablaDetalleRegalos tbody');
            const seccionRegalos = document.getElementById('seccionRegalos');

            const filasVentaArray = tbodyVenta ? [...tbodyVenta.querySelectorAll('tr')] : [];
            const filasRegaloArray = tbodyRegalos ? [...tbodyRegalos.querySelectorAll('tr')] : [];
            const seccionRegalosVisible = seccionRegalos && !seccionRegalos.classList.contains('d-none');

            if (filasVentaArray.length === 0 && (!seccionRegalosVisible || filasRegaloArray.length === 0)) {
                throw new Error('Debe agregar al menos un producto al pedido.');
            }
            
            const detalle = [];
            const ids = new Set();
            let excedeStock = false; 

            for (let i = 0; i < filasVentaArray.length; i++) {
                const fila = filasVentaArray[i];
                const data = filaVentaPayload(fila);
                data.es_bonificacion = 0; 
                
                if (!data.id_item || data.id_item === '0') throw new Error('Seleccione un producto en todas las filas de la tabla de venta.');
                
                const claveUnica = data.id_item + '_0';
                if (ids.has(claveUnica)) throw new Error('No se permiten productos repetidos en la tabla de ventas.');
                ids.add(claveUnica);

                if (data.cantidad <= 0) throw new Error('La cantidad de los productos en venta debe ser mayor a cero.');
                if (!validarCantidadVsStock(fila)) excedeStock = true;
                
                detalle.push(data);
            }

            if (seccionRegalosVisible) {
                for (let i = 0; i < filasRegaloArray.length; i++) {
                    const fila = filasRegaloArray[i];
                    const selectElement = fila.querySelector('.detalle-item');
                    const idItem = selectElement && selectElement.tomselect ? selectElement.tomselect.getValue() : (selectElement ? selectElement.value : '');
                    const cantidad = parseFloat(fila.querySelector('.detalle-cantidad').value || 0);

                    if (cantidad <= 0) {
                        throw new Error('La cantidad en los productos de regalo debe ser mayor a cero. Elimina la fila si no deseas regalar nada.');
                    }

                    const data = {
                        id_item: idItem || '',
                        cantidad: cantidad,
                        precio_unitario: parseFloat(fila.querySelector('.detalle-precio').value || 0), 
                        es_bonificacion: 1 
                    };

                    if (!data.id_item || data.id_item === '0') throw new Error('Seleccione un producto en todas las filas de regalo.');
                    
                    const claveUnica = data.id_item + '_1';
                    if (ids.has(claveUnica)) throw new Error('No se permiten productos repetidos dentro de la tabla de regalos.');
                    ids.add(claveUnica);

                    if (!validarCantidadVsStock(fila)) excedeStock = true;
                    
                    detalle.push(data);
                }
            }

            let esCobroInmediato = false;
            const metodosPagoFinales = [];
            const switchCobroInmediato = document.getElementById('switchCobroInmediato');
            const tipoOp = document.getElementById('tipoOperacion');

            if (switchCobroInmediato && switchCobroInmediato.checked && tipoOp?.value !== 'DONACION' && !bloqueoEdicionVenta) {
                esCobroInmediato = true;
                const contenedorMetodosPago = document.getElementById('contenedorMetodosPago');
                if (contenedorMetodosPago) {
                    contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato').forEach(fila => {
                        const idCuenta = fila.querySelector('.select-cuenta-inmediato').value;
                        const idMetodo = fila.querySelector('.select-metodo-inmediato').value;
                        const monto = parseFloat(fila.querySelector('.input-monto-inmediato').value) || 0;
                        
                        if (idCuenta && idMetodo && monto > 0) {
                            metodosPagoFinales.push({ id_cuenta: idCuenta, id_metodo: idMetodo, monto: monto });
                        }
                    });
                }

                if (metodosPagoFinales.length === 0) {
                    throw new Error("Debe completar Cuenta, Método y Monto para el cobro inmediato.");
                }
            }

            if (esCobroInmediato) {
                let sumaPagos = 0;
                metodosPagoFinales.forEach(p => sumaPagos += p.monto);
                
                const ventaTotal = document.getElementById('ventaTotal');
                const totalPedTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
                const totalPedNumerico = parseFloat(totalPedTexto) || 0;

                const diferencia = totalPedNumerico - sumaPagos;

                if (diferencia > 0.01) {
                    const confirmacionPago = await Swal.fire({
                        icon: 'warning',
                        title: 'Pago Incompleto',
                        text: `El total del pedido es S/ ${totalPedNumerico.toFixed(2)}, pero solo se ha registrado S/ ${sumaPagos.toFixed(2)}. ¿Deseas guardar el pedido y dejar el resto (S/ ${diferencia.toFixed(2)}) como cuenta por cobrar?`,
                        showCancelButton: true,
                        confirmButtonText: 'Sí, guardar con deuda',
                        cancelButtonText: 'No, corregir pago',
                        confirmButtonColor: '#ffc107', 
                        cancelButtonColor: '#6c757d'   
                    });

                    if (!confirmacionPago.isConfirmed) return; 
                } 
                else if (diferencia < -0.01) {
                    throw new Error(`El total ingresado (S/ ${sumaPagos.toFixed(2)}) supera el total del pedido (S/ ${totalPedNumerico.toFixed(2)}). Por favor, ajuste los montos.`);
                }
            }

            if (excedeStock) {
                const confirmacion = await Swal.fire({
                    icon: 'warning',
                    title: 'Stock excedido (Falta de producto)',
                    text: 'Las cantidades requeridas (sumando sueltos y combos) superan tu stock físico en almacén. Puedes guardar el borrador, pero no podrás despacharlo hasta que repongas el stock. ¿Guardar de todos modos?',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd', 
                    cancelButtonColor: '#6c757d', 
                    confirmButtonText: 'Sí, guardar pedido',
                    cancelButtonText: 'Cancelar'
                });

                if (!confirmacion.isConfirmed) return; 
            }

            const btnGuardarVenta = document.getElementById('btnGuardarVenta');
            const ventaIdEl = document.getElementById('ventaId');
            const fechaEmisionEl = document.getElementById('fechaEmision');
            const ventaObsEl = document.getElementById('ventaObservaciones');
            const tipoImpuestoEl = document.getElementById('tipoImpuesto');

            const payload = await postJson(urls.guardar, {
                id: Number(ventaIdEl?.value || 0),
                id_cliente: clienteIdActual,
                tipo_operacion: tipoOp ? tipoOp.value : 'VENTA', 
                fecha_emision: fechaEmisionEl?.value || '',
                observaciones: ventaObsEl?.value || '',
                tipo_impuesto: tipoImpuestoEl ? tipoImpuestoEl.value : 'exonerado',
                detalle: detalle,
                cobro_inmediato: esCobroInmediato || saldoFavorAplicado > 0, 
                saldo_favor_aplicado: saldoFavorAplicado,
                metodos_pago: metodosPagoFinales
            });

            await Swal.fire('Guardado', payload.mensaje, 'success');
            const modalVentaEl = document.getElementById('modalVenta');
            if (modalVentaEl) bootstrap.Modal.getOrCreateInstance(modalVentaEl).hide();
            recargarTabla();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });
}