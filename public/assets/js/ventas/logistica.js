// ==============================================================
// MÓDULO LOGÍSTICA: logistica.js (Despachos y Devoluciones)
// ==============================================================

import { urls, recargarTabla, cuentasDisponibles, metodosDisponibles } from './config.js';
import { renderAlertaSaldoFavor, filtrarMetodosPorCuentaVentas } from './pagos.js';
// Importamos las herramientas globales desde la raíz
import { getJson, postJson, obtenerFechaLocalISO } from '../api.js';


// --- VARIABLES DE ESTADO LOGÍSTICA ---
let envasesRequeridosActuales = []; 
let totalPendienteDespacho = 0;

// --- REFERENCIAS DOM: DEVOLUCIONES ---
const modalDevolucionVentaEl = document.getElementById('modalDevolucionVenta');
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

// --- REFERENCIAS DOM: DESPACHO ---
const modalDespachoEl = document.getElementById('modalDespacho');
const tbodyDespacho = document.querySelector('#tablaDetalleDespacho tbody');
const despachoDocumentoId = document.getElementById('despachoDocumentoId');
const despachoObservaciones = document.getElementById('despachoObservaciones');
const cerrarForzado = document.getElementById('cerrarForzado');
const despachoClienteNombre = document.getElementById('despachoClienteNombre');
const despachoFecha = document.getElementById('despachoFecha');

const seccionCobroDespacho = document.getElementById('seccionCobroDespacho');
const contenedorMetodosPagoDespacho = document.getElementById('contenedorMetodosPagoDespacho');
const btnAgregarPagoDespacho = document.getElementById('btnAgregarPagoDespacho');
const totalPagadoDespacho = document.getElementById('totalPagadoDespacho');
const switchCobroDespachoContainer = document.getElementById('switchCobroDespachoContainer');
const switchCobroDespacho = document.getElementById('switchCobroDespacho');
const mensajePagoCompletoDespacho = document.getElementById('mensajePagoCompletoDespacho');

const seccionRetornoEnvases = document.getElementById('seccionRetornoEnvasesDespacho');
const contenedorRetornoEnvases = document.getElementById('contenedorRetornoEnvases');

// ==========================================
// UTILIDADES LOCALES
// ==========================================

// Limpiar el nombre del cliente cuando se cierra el modal de despacho
modalDespachoEl?.addEventListener('hidden.bs.modal', () => {
    if (despachoClienteNombre) despachoClienteNombre.textContent = '';
});

// ==============================================================
// 1. MÓDULO: DEVOLUCIONES DE VENTA
// ==============================================================

function actualizarHintDevolucionVenta() {
    const motivoActual = devolucionVentaMotivo?.value || '';
    
    if (devolucionVentaMotivoHint) {
        const motivoCfg = DEVOLUCION_VENTA_MOTIVOS[motivoActual];
        devolucionVentaMotivoHint.textContent = motivoCfg ? motivoCfg.hint : 'Selecciona un motivo para definir cómo tratar la mercadería devuelta.';
    }

    if (devolucionVentaResolucionHint) {
        const resolucionSeleccionada = devolucionVentaResolucion?.value || '';
        const resolucionHint = DEVOLUCION_VENTA_RESOLUCIONES[resolucionSeleccionada];
        devolucionVentaResolucionHint.textContent = resolucionHint || 'Selecciona una resolución comercial para registrar el impacto financiero.';
    }

    const checkReemplazo = document.getElementById('devolucionEnviarReemplazo');
    const filaSwitchReemplazo = document.getElementById('filaSwitchReemplazo');

    if (filaSwitchReemplazo && checkReemplazo) {
        if (motivoActual === 'producto_defectuoso') {
            filaSwitchReemplazo.classList.remove('d-none');
        } else {
            filaSwitchReemplazo.classList.add('d-none');
            checkReemplazo.checked = false;
        }
    }
}

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

// Función expuesta a app.js para abrir el modal desde la tabla principal
export async function abrirModalDevolucionVenta(idDocumento) {
    if (!modalDevolucionVentaEl || !tbodyDevolucionVenta || !devolucionVentaDocumentoId) {
        throw new Error('El modal de devolución no está disponible en la vista actual.');
    }

    const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
    const venta = payload.data || {};
    const detalle = Array.isArray(venta.detalle) ? venta.detalle : [];

    devolucionVentaDocumentoId.value = String(Number(venta.id || idDocumento));
    if (devolucionVentaMotivo) devolucionVentaMotivo.value = '';
    tbodyDevolucionVenta.innerHTML = '';
    if (devolucionVentaTotal) devolucionVentaTotal.textContent = 'S/ 0.00';

    if (devolucionVentaResolucion) {
        const montoPagado = Number(venta.monto_pagado || 0);
        const totalPedido = Number(venta.total || 0);
        
        devolucionVentaResolucion.innerHTML = ''; 

        if (montoPagado < totalPedido) {
            devolucionVentaResolucion.innerHTML = `
                <optgroup label="🔄 Ajuste de Deuda (Sin pagos previos)">
                    <option value="descuento_cxc" selected>Reducción / Anulación de Deuda</option>
                </optgroup>
            `;
        } 
        else {
            devolucionVentaResolucion.innerHTML = `
                <optgroup label="💳 Saldo a Favor (No sale dinero)">
                    <option value="saldo_favor" selected>Nota de Crédito (Descontar de futuras compras / CxC)</option>
                </optgroup>
                <optgroup label="💵 Salida de Dinero (Tesorería)">
                    <option value="reembolso_dinero">Reembolso al cliente (Efectivo / Transferencia)</option>
                </optgroup>
            `;
        }
    }

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
    bootstrap.Modal.getOrCreateInstance(modalDevolucionVentaEl).show();
}

// Event Listeners Estáticos para Devoluciones
devolucionVentaMotivo?.addEventListener('change', actualizarHintDevolucionVenta);
devolucionVentaResolucion?.addEventListener('change', actualizarHintDevolucionVenta);

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

        const checkReemplazo = document.getElementById('devolucionEnviarReemplazo');
        const enviarReemplazo = checkReemplazo ? checkReemplazo.checked : false;

        const payload = await postJson(`${urls.index}&accion=guardar_devolucion`, {
            id_documento: Number(devolucionVentaDocumentoId?.value || 0),
            motivo: motivoCfg.label,
            motivo_codigo: motivoSeleccionado,
            resolucion: resolucionSeleccionada,
            enviar_reemplazo: enviarReemplazo, 
            detalle,
        });

        await Swal.fire('Éxito', payload.mensaje, 'success');
        bootstrap.Modal.getInstance(modalDevolucionVentaEl)?.hide();
        recargarTabla();
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
});

// ==============================================================
// 2. MÓDULO: DESPACHOS MULTI-ALMACÉN Y ENVASES
// ==============================================================

function calcularTotalCobroDespacho() {
    if (!contenedorMetodosPagoDespacho) return;
    let total = 0;
    const filas = contenedorMetodosPagoDespacho.querySelectorAll('.fila-pago-despacho');
    
    filas.forEach(fila => {
        const monto = parseFloat(fila.querySelector('.input-monto-despacho').value) || 0;
        total += monto;
    });
    
    if (totalPagadoDespacho) {
        totalPagadoDespacho.textContent = `S/ ${total.toFixed(2)}`;
        
        if (total > totalPendienteDespacho) totalPagadoDespacho.className = 'fw-bold fs-5 text-danger';
        else if (total === totalPendienteDespacho && total > 0) totalPagadoDespacho.className = 'fw-bold fs-5 text-success';
        else totalPagadoDespacho.className = 'fw-bold fs-5 text-dark';
    }

    if (btnAgregarPagoDespacho) {
        if (filas.length === 0) {
            btnAgregarPagoDespacho.disabled = false;
        } else {
            const ultimaFila = filas[filas.length - 1];
            const cuenta = ultimaFila.querySelector('.select-cuenta-despacho').value;
            const metodo = ultimaFila.querySelector('.select-metodo-despacho').value;
            const monto = parseFloat(ultimaFila.querySelector('.input-monto-despacho').value) || 0;
            
            btnAgregarPagoDespacho.disabled = !(cuenta && metodo && monto > 0);
        }
    }
}

function agregarFilaPagoDespacho(montoSugerido = '') {
    if (!contenedorMetodosPagoDespacho) return;
    
    let opcionesCuentas = '<option value="" selected disabled>Cuenta Destino...</option>';
    cuentasDisponibles.forEach(c => { opcionesCuentas += `<option value="${c.id}">${c.nombre} (${c.moneda})</option>`; });

    const numFilas = contenedorMetodosPagoDespacho.querySelectorAll('.fila-pago-despacho').length;

    const div = document.createElement('div');
    div.className = 'd-flex flex-column flex-sm-row gap-2 align-items-start align-items-sm-center bg-white p-2 rounded border border-success-subtle fila-pago-despacho';
    
    div.innerHTML = `
        <div class="w-100">
            <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-cuenta-despacho" required>
                ${opcionesCuentas}
            </select>
        </div>
        <div class="w-100">
            <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-metodo-despacho" required disabled>
                <option value="" selected disabled>Método...</option>
            </select>
        </div>
        <div class="w-100 d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm w-100">
                <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle">S/</span>
                <input type="number" class="form-control text-end text-success fw-bold border-secondary-subtle input-monto-despacho" min="0" step="0.01" placeholder="0.00" value="${montoSugerido}" required readonly>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-quitar-pago-despacho ${numFilas === 0 ? 'd-none' : ''} px-2" title="Quitar pago"><i class="bi bi-trash"></i></button>
        </div>
    `;

    contenedorMetodosPagoDespacho.appendChild(div);

    const selCuentaDespacho = div.querySelector('.select-cuenta-despacho');
    const selMetodoDespacho = div.querySelector('.select-metodo-despacho');
    const inputMontoDespacho = div.querySelector('.input-monto-despacho');
    const btnQuitar = div.querySelector('.btn-quitar-pago-despacho');

    selCuentaDespacho.addEventListener('change', () => {
        filtrarMetodosPorCuentaVentas(selCuentaDespacho, selMetodoDespacho);
        selMetodoDespacho.disabled = !selCuentaDespacho.value;
        selMetodoDespacho.value = '';
        inputMontoDespacho.readOnly = true;
        calcularTotalCobroDespacho();
    });

    selMetodoDespacho.addEventListener('change', () => {
        inputMontoDespacho.readOnly = !selMetodoDespacho.value;
        if (selMetodoDespacho.value && !inputMontoDespacho.value) {
            inputMontoDespacho.focus();
        }
        calcularTotalCobroDespacho();
    });

    inputMontoDespacho.addEventListener('input', calcularTotalCobroDespacho);
    
    btnQuitar.addEventListener('click', () => {
        div.remove();
        const filasRestantes = contenedorMetodosPagoDespacho.querySelectorAll('.fila-pago-despacho');
        if (filasRestantes.length === 1) filasRestantes[0].querySelector('.btn-quitar-pago-despacho').classList.add('d-none');
        calcularTotalCobroDespacho();
    });

    calcularTotalCobroDespacho();
    return div;
}

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
        
        const divExistente = contenedorRetornoEnvases.querySelector(`div[data-id-envase="${idEnvase}"]`);
        const valorPrevio = divExistente ? divExistente.querySelector('.input-retorno-vacio').value : 0; 

        const itemDiv = document.createElement('div');
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
            opcionesHTML += `<option value="${alm.id}" data-stock="${stockDisponible}">${alm.nombre}</option>`;
            
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
            dibujarTablaEnvases(); 
            return;
        }

        const yaExiste = obtenerFilasGrupo().some(f => f !== tr && f.querySelector('.fila-almacen').value === idAlmacen);
        if (yaExiste) {
            Swal.fire('Almacén duplicado', 'No puede seleccionar el mismo almacén para este producto.', 'warning');
            selectAlmacen.value = '';
            spanStock.textContent = '-';
            inputCant.value = 0;
            validarGrupoItem(linea.id);
            dibujarTablaEnvases(); 
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
        dibujarTablaEnvases(); 
    });

    inputCant.addEventListener('input', () => {
        if (inputCant.value.includes('.')) {
            inputCant.value = Math.floor(parseFloat(inputCant.value || 0));
        }
        sincronizarGrupo(tr);
        validarGrupoItem(linea.id);
        dibujarTablaEnvases(); 
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
        dibujarTablaEnvases(); 
    });

    actualizarModoGrupo();

    if (!filaReferencia && mejorAlmacenId) {
        selectAlmacen.value = mejorAlmacenId;
        setTimeout(() => {
            selectAlmacen.dispatchEvent(new Event('change'));
        }, 50);
    }
}

// Función expuesta a app.js para abrir el modal de despacho
export async function abrirModalDespacho(idDocumento) {
    const payload = await getJson(`${urls.index}&accion=ver&id=${idDocumento}`);
    const venta = payload.data;
    renderAlertaSaldoFavor(venta.saldo_favor_cliente || 0);

    despachoDocumentoId.value = venta.id;
    despachoObservaciones.value = '';
    if (cerrarForzado) cerrarForzado.checked = false;
    tbodyDespacho.innerHTML = '';

    if (despachoClienteNombre) {
        const cliente = String(venta.cliente || '').trim();
        despachoClienteNombre.textContent = cliente ? `- ${cliente}` : '';
    }

    const totalVenta = Number(venta.total || 0);
    const montoPagado = Number(venta.monto_pagado || 0);
    totalPendienteDespacho = Math.max(0, totalVenta - montoPagado);

    if (switchCobroDespachoContainer) {
        if (totalPendienteDespacho > 0.001) {
            switchCobroDespachoContainer.style.display = 'block';
            if (mensajePagoCompletoDespacho) mensajePagoCompletoDespacho.classList.add('d-none');
            
            if (switchCobroDespacho) {
                switchCobroDespacho.disabled = false;
                switchCobroDespacho.checked = false;
            }
            if (seccionCobroDespacho) seccionCobroDespacho.classList.add('d-none');
            if (contenedorMetodosPagoDespacho) contenedorMetodosPagoDespacho.innerHTML = '';
        } else {
            switchCobroDespachoContainer.style.display = 'none'; 
            if (mensajePagoCompletoDespacho) mensajePagoCompletoDespacho.classList.remove('d-none'); 
            
            if (switchCobroDespacho) {
                switchCobroDespacho.checked = false;
                switchCobroDespacho.disabled = true;
            }
            if (seccionCobroDespacho) seccionCobroDespacho.classList.add('d-none');
        }
    }
    
    envasesRequeridosActuales = [];
    if (contenedorRetornoEnvases) contenedorRetornoEnvases.innerHTML = '';
    if (seccionRetornoEnvases) seccionRetornoEnvases.classList.add('d-none');

    if (despachoFecha) {
        const hoy = obtenerFechaLocalISO();
        let fechaMinima = '';

        if (venta.fecha_emision) {
            fechaMinima = String(venta.fecha_emision).split(' ')[0];
        } else if (venta.created_at) {
            fechaMinima = venta.created_at.substring(0, 10);
        }

        if (fechaMinima) {
            despachoFecha.min = fechaMinima;
            despachoFecha.value = hoy < fechaMinima ? fechaMinima : hoy;
        } else {
            despachoFecha.removeAttribute('min'); 
            despachoFecha.value = hoy;
        }
    }

    (venta.detalle || []).forEach((linea) => {
        if (Number(linea.cantidad_pendiente) > 0.0001) {
            agregarFilaDespacho(linea, null);
            
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
    
    dibujarTablaEnvases(); 
    bootstrap.Modal.getOrCreateInstance(modalDespachoEl).show();
}

// Event Listeners Estáticos para Cobros en Despacho
switchCobroDespacho?.addEventListener('change', (e) => {
    if (e.target.checked) {
        seccionCobroDespacho.classList.remove('d-none');
        contenedorMetodosPagoDespacho.innerHTML = '';
        agregarFilaPagoDespacho(totalPendienteDespacho > 0 ? totalPendienteDespacho.toFixed(2) : '');
    } else {
        seccionCobroDespacho.classList.add('d-none');
        contenedorMetodosPagoDespacho.innerHTML = '';
        calcularTotalCobroDespacho();
    }
});

btnAgregarPagoDespacho?.addEventListener('click', () => {
    let totalPagadoHastaAhora = 0;
    contenedorMetodosPagoDespacho.querySelectorAll('.input-monto-despacho').forEach(inp => {
        totalPagadoHastaAhora += parseFloat(inp.value) || 0;
    });

    let faltante = totalPendienteDespacho - totalPagadoHastaAhora;
    if (faltante < 0) faltante = 0;

    agregarFilaPagoDespacho(faltante > 0 ? faltante.toFixed(2) : '');
    contenedorMetodosPagoDespacho.querySelectorAll('.btn-quitar-pago-despacho').forEach(btn => btn.classList.remove('d-none'));
});

// Guardar Despacho
document.getElementById('btnGuardarDespacho')?.addEventListener('click', async () => {
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

        const fechaDespachoVal = despachoFecha ? despachoFecha.value : '';
        if (!fechaDespachoVal) throw new Error('Debe especificar la fecha de despacho.');

        let esCobroDespacho = false;
        const metodosPagoDespachoFinales = [];

        if (switchCobroDespacho && switchCobroDespacho.checked) {
            esCobroDespacho = true;
            contenedorMetodosPagoDespacho.querySelectorAll('.fila-pago-despacho').forEach(fila => {
                const idCuenta = fila.querySelector('.select-cuenta-despacho').value;
                const idMetodo = fila.querySelector('.select-metodo-despacho').value;
                const monto = parseFloat(fila.querySelector('.input-monto-despacho').value) || 0;
                
                if (idCuenta && idMetodo && monto > 0) {
                    metodosPagoDespachoFinales.push({ id_cuenta: idCuenta, id_metodo: idMetodo, monto: monto });
                }
            });

            if (metodosPagoDespachoFinales.length === 0) {
                throw new Error("Debe completar Cuenta, Método y Monto para el cobro en despacho.");
            }

            let sumaPagos = 0;
            metodosPagoDespachoFinales.forEach(p => sumaPagos += p.monto);
            const diferencia = totalPendienteDespacho - sumaPagos;

            if (diferencia > 0.01) {
                const confirmacionPago = await Swal.fire({
                    icon: 'warning',
                    title: 'Pago Incompleto',
                    text: `Falta cobrar S/ ${diferencia.toFixed(2)}. ¿Deseas despachar y dejar ese saldo como deuda?`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, despachar con deuda',
                    cancelButtonText: 'No, corregir pago',
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d'
                });
                if (!confirmacionPago.isConfirmed) return;
            } else if (diferencia < -0.01) {
                throw new Error(`El total ingresado (S/ ${sumaPagos.toFixed(2)}) supera la deuda pendiente (S/ ${totalPendienteDespacho.toFixed(2)}).`);
            }
        }

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

        const envasesDevueltos = [];
        let advertenciaEnvases = []; 

        if (contenedorRetornoEnvases && !seccionRetornoEnvases.classList.contains('d-none')) {
            contenedorRetornoEnvases.querySelectorAll('.item-envase-retorno').forEach(div => {
                const nombreEnvase = div.querySelector('.text-truncate').textContent;
                const cantEntregada = Number(div.querySelector('.text-center .text-dark').textContent || 0);
                const cantDevuelta = Number(div.querySelector('.input-retorno-vacio').value || 0);

                if (cantDevuelta > 0) {
                    envasesDevueltos.push({
                        id_envase: Number(div.dataset.idEnvase),
                        cantidad: cantDevuelta
                    });
                }

                if (cantEntregada !== cantDevuelta) {
                    let diferencia = cantEntregada - cantDevuelta;
                    let tipoDiferencia = diferencia > 0 ? 'faltan' : 'sobran';
                    advertenciaEnvases.push(`<b>${nombreEnvase}:</b> Se entregan ${cantEntregada}, pero retorna ${cantDevuelta} (<i>${tipoDiferencia} ${Math.abs(diferencia)}</i>)`);
                }
            });
        }

        if (advertenciaEnvases.length > 0) {
            const confirmacionEnvases = await Swal.fire({
                icon: 'warning',
                title: 'Discrepancia en Envases',
                html: `Las cantidades entregadas no coinciden con los retornos vacíos:<br><br>
                    <div class="text-start bg-light p-3 rounded border text-muted" style="font-size: 0.9rem;">
                        ${advertenciaEnvases.join('<br>')}
                    </div><br>
                    ¿Estás seguro de continuar? Se registrará este saldo a favor o en contra del cliente.`,
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Sí, continuar',
                cancelButtonText: '<i class="bi bi-pencil me-1"></i> No, corregir'
            });

            if (!confirmacionEnvases.isConfirmed) {
                return; 
            }
        }

        if (esParcial && cerrarForzado && cerrarForzado.checked) {
            const resp = await Swal.fire({
                icon: 'warning',
                title: 'Cerrar pedido de forma forzada',
                text: 'Se despachará de forma parcial y el saldo restante quedará cancelado para este pedido. ¿Desea continuar?',
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar pedido'
            });
            if (!resp.isConfirmed) return;
        }

        if (esParcial && (!cerrarForzado || !cerrarForzado.checked)) {
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
            cerrar_forzado: cerrarForzado ? cerrarForzado.checked : false,
            detalle: detalle,
            envases_devueltos: envasesDevueltos,
            cobro_inmediato: esCobroDespacho,
            metodos_pago: metodosPagoDespachoFinales
        });

        await Swal.fire('Despachado', payload.mensaje, 'success');
        bootstrap.Modal.getInstance(modalDespachoEl)?.hide();
        recargarTabla();
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
});