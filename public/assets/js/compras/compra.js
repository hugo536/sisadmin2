// ==============================================================
// MÓDULO COMPRAS: compra.js (Creación, Edición y Resumen)
// ==============================================================

import { getJson, obtenerFechaLocalISO, esperarTomSelect } from '../api.js';
import { urls, postJsonConCarga, recargarPagina } from './config.js';
import { calcularTotalPagoInmediatoCompra, agregarFilaPagoInmediatoCompra, filtrarMetodosPorCuentaCompras } from './pagos.js';

// --- ESTADO LOCAL ---
let ordenEnEdicionId = 0;
let modalSoloLecturaActiva = false;
let tomSelectProveedor = null;
let tomSelectListo = false;
const cacheUnidades = new Map();

// ==========================================
// 1. UTILIDADES LOCALES Y TOMSELECT
// ==========================================
function formatearFechaDMY(fechaTexto) {
    const texto = String(fechaTexto || '').trim();
    if (!texto) return '-';
    const soloFecha = texto.split(' ')[0].slice(0, 10);
    const match = soloFecha.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return texto;
    const [, anio, mes, dia] = match;
    return `${dia}/${mes}/${anio}`;
}

function initSelectLocal(target, options = {}) {
    if (typeof window !== 'undefined' && window.AppSelects && typeof window.AppSelects.initLocal === 'function') {
        return window.AppSelects.initLocal(target, options);
    }
    return new TomSelect(target, Object.assign({
        create: false,
        sortField: { field: 'text', direction: 'asc' },
        searchField: ['text', 'value'],
        plugins: ['clear_button']
    }, options));
}

function setOrdenEnEdicion(id = 0) {
    const parsedId = Number(id || 0);
    ordenEnEdicionId = Number.isFinite(parsedId) ? parsedId : 0;
    const ordenId = document.getElementById('ordenId');
    if (ordenId) ordenId.value = String(ordenEnEdicionId);
}

// ==========================================
// 2. LÓGICA DE FILAS Y PRECIOS
// ==========================================
async function obtenerUnidadesItem(idItem) {
    if (!idItem || idItem <= 0) return [];
    if (cacheUnidades.has(idItem)) return cacheUnidades.get(idItem);

    const separador = urls.unidadesItem.includes('?') ? '&' : '?';
    const res = await fetch(`${urls.unidadesItem}${separador}accion=unidades_item&id_item=${idItem}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.mensaje || 'Error al cargar unidades.');

    const items = Array.isArray(json.items) ? json.items : [];
    cacheUnidades.set(idItem, items);
    return items;
}

async function aplicarPrecioSugeridoProveedor(fila) {
    if (modalSoloLecturaActiva) return;
    const idProveedor = document.getElementById('idProveedor');
    const idProv = Number(idProveedor?.value || 0);
    const inputItem = fila.querySelector('.detalle-item');
    const inputUnidad = fila.querySelector('.detalle-unidad-compra');
    const inputCosto = fila.querySelector('.detalle-costo');
    const idItem = Number(inputItem?.value || 0);
    const idUnidad = inputUnidad && !inputUnidad.classList.contains('d-none') ? Number(inputUnidad.value || 0) : 0;

    if (idProv <= 0 || idItem <= 0 || !urls.precioSugerido) return;

    const separador = urls.precioSugerido.includes('?') ? '&' : '?';
    const res = await fetch(`${urls.precioSugerido}${separador}accion=precio_sugerido_proveedor&id_proveedor=${idProv}&id_item=${idItem}&id_unidad=${idUnidad}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await res.json();
    
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
    const ordenMoneda = document.getElementById('ordenMoneda');
    const { cantidad, costo_unitario } = filaToPayload(fila);
    const subtotal = cantidad * costo_unitario;
    const sim = ordenMoneda?.value === 'USD' ? '$' : 'S/';
    fila.querySelector('.detalle-subtotal').innerHTML = `<span class="simbolo-moneda">${sim}</span> ${subtotal.toFixed(2)}`;
    recalcularTotalGeneral();
}

function recalcularTotalGeneral() {
    const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
    let sumaLineas = 0;
    tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
        const item = filaToPayload(fila);
        sumaLineas += item.cantidad * item.costo_unitario;
    });

    const tipoImpuesto = document.getElementById('tipoImpuesto');
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
    } else {
        subtotal = total = sumaLineas;
        igv = 0;
    }

    const ordenMoneda = document.getElementById('ordenMoneda');
    const sim = ordenMoneda?.value === 'USD' ? '$' : 'S/';
    
    const ordenSubtotal = document.getElementById('ordenSubtotal');
    const ordenIgv = document.getElementById('ordenIgv');
    const ordenTotal = document.getElementById('ordenTotal');
    if (ordenSubtotal) ordenSubtotal.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${subtotal.toFixed(2)}`;
    if (ordenIgv) ordenIgv.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${igv.toFixed(2)}`;
    if (ordenTotal) ordenTotal.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${total.toFixed(2)}`;

    const switchCobroInmediatoCompra = document.getElementById('switchCobroInmediatoCompra');
    if (switchCobroInmediatoCompra) {
        if (total <= 0) {
            switchCobroInmediatoCompra.disabled = true;
            if (switchCobroInmediatoCompra.checked) {
                switchCobroInmediatoCompra.checked = false;
                const seccion = document.getElementById('seccionCobroInmediatoCompra');
                const contenedor = document.getElementById('contenedorMetodosPagoCompra');
                if (seccion) seccion.classList.add('d-none');
                if (contenedor) contenedor.innerHTML = '';
                calcularTotalPagoInmediatoCompra();
            }
        } else {
            if (!modalSoloLecturaActiva) {
                switchCobroInmediatoCompra.disabled = false;
            }
        }
    }
    
    if (switchCobroInmediatoCompra && switchCobroInmediatoCompra.checked) {
        const contenedor = document.getElementById('contenedorMetodosPagoCompra');
        const filasPago = contenedor.querySelectorAll('.fila-pago-inmediato');
        if (filasPago.length === 1) { 
            const inputMonto = filasPago[0].querySelector('.input-monto-inmediato');
            inputMonto.value = total.toFixed(2);
            inputMonto.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            calcularTotalPagoInmediatoCompra();
        }
    }
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
        if (fila.dataset.unidadRequestToken !== requestToken) return;

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
    const templateFila = document.getElementById('templateFilaDetalle');
    const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
    if(!templateFila || !tbodyDetalle) return;

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
        if(input) {
            input.addEventListener('input', () => recalcularFila(fila));
            input.addEventListener('change', () => recalcularFila(fila));
        }
    });

    if(inputUnidad) {
        inputUnidad.addEventListener('change', async () => {
            await aplicarPrecioSugeridoProveedor(fila);
            recalcularFila(fila);
        });
    }

    if (inputCentroCosto) {
        inputCentroCosto.addEventListener('change', () => {
            if (inputCentroCosto.value) inputCentroCosto.classList.remove('is-invalid', 'border-danger');
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
            Swal.fire({ icon: 'warning', title: 'Ítem duplicado', text: 'Este producto ya está en la lista.', confirmButtonColor: '#3085d6' });
            if (tomSelectItem) tomSelectItem.clear();
            else inputItem.value = '';
            return;
        }
        await actualizarUnidadPorItem(fila, null);
    };

    if (tomSelectItem) tomSelectItem.on('change', onCambioItem);
    else inputItem.addEventListener('change', (e) => onCambioItem(e.target.value));

    btnQuitar?.addEventListener('click', () => {
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
            if (tomSelectItem && !modalSoloLecturaActiva) tomSelectItem.focus();
        }, 100);
    }
}

// ==========================================
// 3. CONTROL DE MODALES (LECTURA Y EDICIÓN)
// ==========================================
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

    const modalOrdenElement = document.getElementById('modalOrdenCompra');
    const tituloModalOrden = document.querySelector('#modalOrdenCompra .modal-title');

    if (modalOrdenElement) modalOrdenElement.classList.toggle('modal-orden-solo-lectura', deshabilitar);

    if (tituloModalOrden) {
        if (deshabilitar && Number(estado) === 3) {
            tituloModalOrden.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Compra finalizada (solo lectura)';
        } else if (deshabilitar) {
            tituloModalOrden.innerHTML = '<i class="bi bi-eye me-2"></i>Orden de Compra (solo lectura)';
        } else {
            tituloModalOrden.innerHTML = '<i class="bi bi-receipt-cutoff me-2"></i>Orden de Compra';
        }
    }

    const arrInputs = [
        document.getElementById('idProveedor'),
        document.getElementById('fechaEntrega'),
        document.getElementById('observaciones'),
        document.getElementById('ordenMoneda')
    ];

    arrInputs.forEach((el) => {
        if (!el) return;
        el.disabled = deshabilitar;
        if(el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') el.readOnly = deshabilitar;
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

    document.querySelector('#tablaDetalleCompra tbody')?.querySelectorAll('tr').forEach((fila) => {
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

    const btnAgregarFila = document.getElementById('btnAgregarFila');
    if (btnAgregarFila) {
        btnAgregarFila.style.display = deshabilitar ? 'none' : 'inline-block';
        btnAgregarFila.disabled = deshabilitar;
    }

    const switchCobroContainerCompra = document.getElementById('switchCobroContainerCompra');
    const switchCobroInmediatoCompra = document.getElementById('switchCobroInmediatoCompra');

    if (switchCobroContainerCompra) {
        switchCobroContainerCompra.style.display = deshabilitar ? 'none' : 'block';
    }
    if (switchCobroInmediatoCompra) {
        switchCobroInmediatoCompra.disabled = deshabilitar;
        if (deshabilitar) switchCobroInmediatoCompra.checked = false;
    }

    const btnGuardarOrden = document.getElementById('btnGuardarOrden');
    if (btnGuardarOrden) btnGuardarOrden.style.display = deshabilitar ? 'none' : 'block';
}

function limpiarModalOrden() {
    const formOrden = document.getElementById('formOrdenCompra');
    formOrden?.reset();
    setOrdenEnEdicion(0);
    
    const idProveedor = document.getElementById('idProveedor');
    if (tomSelectProveedor) tomSelectProveedor.clear();
    else if(idProveedor) idProveedor.value = '';

    const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
    if(tbodyDetalle) {
        tbodyDetalle.querySelectorAll('.detalle-item').forEach((select) => {
            if (select.tomselect) select.tomselect.destroy();
        });
        tbodyDetalle.innerHTML = '';
    }
    
    const ordenMoneda = document.getElementById('ordenMoneda');
    if (ordenMoneda) ordenMoneda.value = 'PEN';

    ['ordenTotal', 'ordenSubtotal', 'ordenIgv'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.innerHTML = `<span class="simbolo-moneda">S/</span> 0.00`;
    });
    
    const fechaEntrega = document.getElementById('fechaEntrega');
    if(fechaEntrega) fechaEntrega.value = obtenerFechaLocalISO();
    
    const switchCont = document.getElementById('switchCobroContainerCompra');
    const switchCobro = document.getElementById('switchCobroInmediatoCompra');
    const seccionCobro = document.getElementById('seccionCobroInmediatoCompra');
    const contMetodos = document.getElementById('contenedorMetodosPagoCompra');

    if (switchCont) switchCont.style.display = 'block';
    if (switchCobro) {
        switchCobro.checked = false;
        switchCobro.disabled = true;
    }
    if (seccionCobro) seccionCobro.classList.add('d-none');
    if (contMetodos) contMetodos.innerHTML = '';
    
    calcularTotalPagoInmediatoCompra();
    setModoSoloLectura(false, 0);
}

// ==========================================
// 4. EXPORTACIONES Y EVENTOS PRINCIPALES
// ==========================================
export async function abrirModalResumenCompra(id, target = null) {
    const separador = urls.index.includes('?') ? '&' : '?';
    const json = await getJson(`${urls.index}${separador}accion=ver&id=${id}`);

    if (!json.ok || !json.data) return;

    const d = json.data;
    const modalResumenEl = document.getElementById('modalResumenCompra');
    if (!modalResumenEl) throw new Error('El modal de resumen no está disponible.');

    document.getElementById('resumenCompraCodigo').textContent = d.codigo || '-';
    
    const filaTabla = target?.closest('tr');
    const nombreProveedor = filaTabla?.querySelector('td:nth-child(2)')?.textContent?.trim() || d.proveedor || 'Proveedor';
    const fechaRecepcionTabla = filaTabla?.querySelector('.bi-box-arrow-in-down')?.parentElement?.textContent?.trim() || formatearFechaDMY(d.fecha_recepcion || d.fecha_entrega || d.fecha_orden) || '-';
    
    document.getElementById('resumenCompraProveedor').textContent = nombreProveedor;
    document.getElementById('resumenCompraFechaOrden').textContent = formatearFechaDMY(d.fecha_orden);
    document.getElementById('resumenCompraFechaRecepcion').textContent = fechaRecepcionTabla;
    document.getElementById('resumenCompraObservaciones').textContent = d.observaciones || 'Sin observaciones.';
    
    const sim = d.moneda === 'USD' ? '$' : 'S/';
    document.getElementById('resumenCompraTotalFinal').textContent = `${sim} ${Number(d.total || 0).toFixed(2)}`;

    const tbodyResumen = document.querySelector('#tablaResumenProductosCompra tbody');
    if (tbodyResumen) {
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
    }

    bootstrap.Modal.getOrCreateInstance(modalResumenEl).show();
}

export async function abrirModalCompra(id, target) {
    try {
        const separador = urls.index.includes('?') ? '&' : '?';
        const json = await getJson(`${urls.index}${separador}accion=ver&id=${id}`);

        if (json.ok && json.data) {
            const d = json.data;
            const estadoDoc = Number(d.estado || 0);

            if (estadoDoc >= 3) {
                await abrirModalResumenCompra(id, target);
                return;
            }
            
            limpiarModalOrden();
            setOrdenEnEdicion(d.id);
            
            const idProveedor = document.getElementById('idProveedor');
            if (tomSelectProveedor) tomSelectProveedor.setValue(d.id_proveedor);
            else if(idProveedor) idProveedor.value = d.id_proveedor;

            const fechaEntrega = document.getElementById('fechaEntrega');
            const observaciones = document.getElementById('observaciones');
            const tipoImpuesto = document.getElementById('tipoImpuesto');
            const ordenMoneda = document.getElementById('ordenMoneda');

            if(fechaEntrega) fechaEntrega.value = d.fecha_orden || d.fecha_entrega || '';
            if(observaciones) observaciones.value = d.observaciones || '';
            if (tipoImpuesto && d.tipo_impuesto) tipoImpuesto.value = d.tipo_impuesto;

            if (ordenMoneda) {
                ordenMoneda.value = d.moneda || 'PEN';
                ordenMoneda.dispatchEvent(new Event('change')); 
            }

            if (d.detalle && d.detalle.length > 0) d.detalle.forEach((item) => agregarFila(item));
            else agregarFila();

            if (d.cobro_inmediato == 1 || d.cobro_inmediato === true) {
                const switchCobroInmediatoCompra = document.getElementById('switchCobroInmediatoCompra');
                if (switchCobroInmediatoCompra) {
                    switchCobroInmediatoCompra.checked = true;
                    const seccionCobroInmediatoCompra = document.getElementById('seccionCobroInmediatoCompra');
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
            
            const modalOrdenElement = document.getElementById('modalOrdenCompra');
            bootstrap.Modal.getOrCreateInstance(modalOrdenElement).show();
        }
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'No se pudo cargar la orden.', 'error');
    }
}

export async function initCompras() {
    tomSelectListo = await esperarTomSelect();
    
    if (document.getElementById('idProveedor') && tomSelectListo) {
        tomSelectProveedor = initSelectLocal('#idProveedor', {
            placeholder: 'Escribe para buscar proveedor...',
            dropdownParent: 'body',
        });
    }

    document.getElementById('btnNuevaOrden')?.addEventListener('click', () => {
        limpiarModalOrden();
        setOrdenEnEdicion(0);
        agregarFila();
        setModoSoloLectura(false, 0);
        const modalOrdenElement = document.getElementById('modalOrdenCompra');
        bootstrap.Modal.getOrCreateInstance(modalOrdenElement).show();
    });

    const btnAgregarFila = document.getElementById('btnAgregarFila');
    if (btnAgregarFila) btnAgregarFila.addEventListener('click', () => agregarFila());

    const refrescarPreciosSugeridos = async () => {
        const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
        if(!tbodyDetalle) return;
        const filas = [...tbodyDetalle.querySelectorAll('tr')];
        for (const fila of filas) {
            await aplicarPrecioSugeridoProveedor(fila);
        }
    };

    const idProveedor = document.getElementById('idProveedor');
    if (tomSelectProveedor) tomSelectProveedor.on('change', refrescarPreciosSugeridos);
    else if (idProveedor) idProveedor.addEventListener('change', refrescarPreciosSugeridos);

    const tipoImpuesto = document.getElementById('tipoImpuesto');
    if (tipoImpuesto) tipoImpuesto.addEventListener('change', recalcularTotalGeneral);
    
    const ordenMoneda = document.getElementById('ordenMoneda');
    if (ordenMoneda) {
        ordenMoneda.addEventListener('change', () => {
            recalcularTotalGeneral();
            const sim = ordenMoneda.value === 'USD' ? '$' : 'S/';
            document.querySelectorAll('.simbolo-moneda').forEach(el => el.textContent = sim);
        });
    }

    const fechaEntrega = document.getElementById('fechaEntrega');
    if (fechaEntrega && !fechaEntrega.value) fechaEntrega.value = obtenerFechaLocalISO();

    const btnGuardarOrden = document.getElementById('btnGuardarOrden');
    btnGuardarOrden?.addEventListener('click', async () => {
        const idProv = document.getElementById('idProveedor');
        if (!idProv || !idProv.value) return Swal.fire('Falta Proveedor', 'Debe seleccionar un proveedor.', 'warning');
        
        const fEntrega = document.getElementById('fechaEntrega');
        if (!fEntrega || !fEntrega.value) return Swal.fire('Falta Fecha', 'La fecha de emisión es obligatoria.', 'warning');

        const detalle = [];
        let errorDetalle = false;
        let errorCentroCosto = false;

        const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
        if(!tbodyDetalle) return;

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

        if (detalle.length === 0) return Swal.fire({ icon: 'error', title: 'Orden vacía', text: 'Debe agregar al menos un producto.' });
        if (errorCentroCosto) return Swal.fire('Falta Centro de Costo', 'Debe seleccionar un Centro de Costo.', 'warning');
        if (errorDetalle) return Swal.fire('Verifique cantidades', 'Hay líneas con conversión o cantidad inválida.', 'warning');

        let esCobroInmediato = false;
        let metodosPagoFinales = [];

        const switchCobro = document.getElementById('switchCobroInmediatoCompra');
        if (switchCobro && switchCobro.checked && !modalSoloLecturaActiva) {
            esCobroInmediato = true;
            const contenedorMetodos = document.getElementById('contenedorMetodosPagoCompra');
            const filasPago = contenedorMetodos.querySelectorAll('.fila-pago-inmediato');
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
                const monedaCuenta = String(optCuenta.getAttribute('data-moneda') || 'PEN').toUpperCase();
                const monedaOrdenSel = String(document.getElementById('ordenMoneda')?.value || 'PEN').toUpperCase();
                const nombreCuenta = optCuenta.text.split('(')[0].trim();

                if (monto <= 0) errorPagos = true;

                const inputTC = fila.querySelector('.input-tc-inmediato');
                const seccionTC = fila.querySelector('.seccion-tipo-cambio');
                const requiereTC = seccionTC && !seccionTC.classList.contains('d-none');
                const tcValor = (requiereTC && inputTC) ? parseFloat(inputTC.value) || 0 : 1; 

                if (tcValor <= 0 && requiereTC) errorPagos = true;

                let montoDebitoCuenta = monto;
                if (monedaOrdenSel === 'USD' && monedaCuenta === 'PEN') {
                    montoDebitoCuenta = monto * tcValor;
                } else if (monedaOrdenSel === 'PEN' && monedaCuenta === 'USD') {
                    montoDebitoCuenta = tcValor > 0 ? (monto / tcValor) : 0;
                }

                if (!montosPorCuenta[idCuenta]) {
                    montosPorCuenta[idCuenta] = 0;
                    saldosPorCuenta[idCuenta] = saldoDisp;
                    nombresCuentas[idCuenta] = `${nombreCuenta} (${monedaCuenta})`;
                }
                montosPorCuenta[idCuenta] += montoDebitoCuenta;
                sumaTotalPagos += monto;

                metodosPagoFinales.push({
                    id_cuenta: Number(idCuenta),
                    id_metodo: Number(idMetodo),
                    monto: monto,
                    tipo_cambio: tcValor 
                });
            });

            if (errorPagos) return Swal.fire('Error en Pagos', 'Complete la cuenta, el método y un monto mayor a cero.', 'warning');

            let erroresSaldo = [];
            for (const idC in montosPorCuenta) {
                if (montosPorCuenta[idC] > saldosPorCuenta[idC]) {
                    erroresSaldo.push(`La cuenta <b>${nombresCuentas[idC]}</b> no tiene fondos suficientes. Intentas retirar ${montosPorCuenta[idC].toFixed(2)} pero solo dispone de ${saldosPorCuenta[idC].toFixed(2)}.`);
                }
            }

            if (erroresSaldo.length > 0) return Swal.fire({ icon: 'error', title: 'Fondos insuficientes', html: erroresSaldo.join('<br><br>') });

            const ordenTotal = document.getElementById('ordenTotal');
            const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
            const totalPedido = parseFloat(totalTexto) || 0;
            if (sumaTotalPagos > totalPedido) return Swal.fire('Aviso', 'El total pagado no puede superar el total de la orden.', 'warning');
        }

        try {
            const payload = {
                id: Number(ordenEnEdicionId || 0),
                id_proveedor: Number(idProv.value),
                fecha_emision: fEntrega.value,
                observaciones: document.getElementById('observaciones')?.value || '',
                tipo_impuesto: document.getElementById('tipoImpuesto') ? document.getElementById('tipoImpuesto').value : 'incluido',
                moneda: document.getElementById('ordenMoneda')?.value || 'PEN', 
                detalle,
                cobro_inmediato: esCobroInmediato,
                metodos_pago: metodosPagoFinales 
            };

            const res = await postJsonConCarga(urls.guardar, payload, btnGuardarOrden);
            await Swal.fire('Guardado', res.mensaje, 'success');
            const modalOrdenElement = document.getElementById('modalOrdenCompra');
            bootstrap.Modal.getOrCreateInstance(modalOrdenElement).hide();
            recargarPagina();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    });
}