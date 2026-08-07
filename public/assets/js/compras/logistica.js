// ==============================================================
// MÓDULO COMPRAS: logistica.js (Recepciones y Devoluciones)
// ==============================================================

import { getJson, obtenerFechaLocalISO } from '../api.js';
import { urls, postJsonConCarga, recargarPagina } from './config.js';

const cacheUnidades = new Map();
const DECIMALES_RECEPCION = 4;
const EPSILON_RECEPCION = 0.0001;

function formatearCantidadRecepcion(valor) {
    return Number(valor || 0).toFixed(DECIMALES_RECEPCION);
}

async function obtenerUnidadesItem(idItem) {
    if (!idItem || idItem <= 0) return [];
    if (cacheUnidades.has(idItem)) return cacheUnidades.get(idItem);

    const separador = urls.unidadesItem.includes('?') ? '&' : '?';
    const res = await fetch(`${urls.unidadesItem}${separador}accion=unidades_item&id_item=${idItem}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.mensaje || 'No se pudieron cargar unidades de conversión.');

    const items = Array.isArray(json.items) ? json.items : [];
    cacheUnidades.set(idItem, items);
    return items;
}

function recalcularTotalDevolucion() {
    const tbodyDevolucion = document.querySelector('#tablaDetalleDevolucion tbody');
    const devolucionTotal = document.getElementById('devolucionTotal');
    if(!tbodyDevolucion || !devolucionTotal) return;

    let total = 0;
    tbodyDevolucion.querySelectorAll('tr').forEach((fila) => {
        const cant = parseFloat(fila.querySelector('.input-devolver').value || 0);
        const selectU = fila.querySelector('.dev-select-unidad');
        const factor = parseFloat(selectU.options[selectU.selectedIndex]?.dataset.factor || 1);
        const costoBase = parseFloat(fila.dataset.costoBase || 0);
        total += cant * (costoBase * factor);
    });
    devolucionTotal.textContent = `S/ ${total.toFixed(2)}`;
}

async function agregarFilaDevolucion(linea, cantRecibidaBase) {
    const tbodyDevolucion = document.querySelector('#tablaDetalleDevolucion tbody');
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

    // Aquí inyectamos el historial de devoluciones en la celda
    const devueltoPrevio = parseFloat(linea.cantidad_devuelta || 0);
    const htmlDevolucionPrevia = devueltoPrevio > 0 
        ? `<div class="text-danger small mt-2 fw-bold"><i class="bi bi-arrow-return-left"></i> Ya devolviste: ${devueltoPrevio.toFixed(2)} ${linea.unidad_base}</div>` 
        : '';

    tr.innerHTML = `
        <td class="align-middle py-3 ps-3">
            <div class="fw-bold text-dark" style="font-size: 0.95rem;">${linea.item_nombre || ''}</div>
            <small class="text-muted dev-info-conversion">Unidad base: ${linea.unidad_base}</small>
        </td>
        <td class="text-center align-middle">
            ${mostrarResumenUnidadCompra ? `<div class="fw-semibold text-dark">${cantidadRecibidaEnUnidadCompra.toFixed(2)} ${unidadCompraLabel}</div>` : ''}
            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-bold mt-1">${cantRecibidaBase.toFixed(2)} ${linea.unidad_base}</span>
            ${htmlDevolucionPrevia}
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
            <input type="number" class="form-control form-control-sm text-center input-devolver fw-bold text-warning-emphasis border-warning mx-auto shadow-none" min="0" step="0.01" value="0.00" style="max-width: 100px;">
        </td>
        <td class="text-end align-middle pe-4 fw-bold text-dark subtotal-fila-dev">S/ 0.00</td>
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
        
        if (linea.id_item_unidad) selectUnidad.value = String(linea.id_item_unidad);

        if (selectUnidad.value === '' && factorCompra > 1) {
            const optCompra = document.createElement('option');
            optCompra.value = `compra_${linea.id_item_unidad || 'orig'}`;
            optCompra.dataset.factor = String(factorCompra);
            optCompra.textContent = `${linea.unidad_nombre || 'Unidad compra'} (x ${factorCompra})`;
            selectUnidad.appendChild(optCompra);
            selectUnidad.value = optCompra.value;
        }
    } catch (e) {
        console.warn("No se pudieron cargar unidades", e);
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
        
        if (factorSeleccionado > 1) infoConv.innerHTML = `Saldrán: <strong>${cantBaseCalculada.toFixed(2)} ${linea.unidad_base}</strong>`;
        else infoConv.textContent = `Unidad base: ${linea.unidad_base}`;

        recalcularTotalDevolucion();
    };

    inputCant.addEventListener('input', recalcularLinea);
    selectUnidad.addEventListener('change', recalcularLinea);
}

export async function abrirModalDevolucion(idOrden) {
    try {
        const separador = urls.index.includes('?') ? '&' : '?';
        const res = await getJson(`${urls.index}${separador}accion=ver&id=${idOrden}`);
        const orden = res.data;

        // Mostrar alerta de devoluciones previas si existe el historial
        const alertaPrevias = document.getElementById('alertaDevolucionesPrevias');
        if (alertaPrevias) {
            if (orden.devoluciones_historial && orden.devoluciones_historial.length > 0) {
                alertaPrevias.classList.remove('d-none');
            } else {
                alertaPrevias.classList.add('d-none');
            }
        }

        document.getElementById('devolucionOrdenId').value = orden.id;
        document.getElementById('devolucionMotivo').value = '';
        
        const tbodyDevolucion = document.querySelector('#tablaDetalleDevolucion tbody');
        if(tbodyDevolucion) tbodyDevolucion.innerHTML = '';
        document.getElementById('devolucionTotal').textContent = 'S/ 0.00';

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
        const modalEl = document.getElementById('modalDevolucionCompra');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (error) {
        Swal.fire('Error', error.message || 'No se pudo preparar la devolución.', 'error');
    }
}

function agregarFilaRecepcion(linea, filaReferencia = null) {
    const tbodyRecepcion = document.querySelector('#tablaDetalleRecepcion tbody');
    const selectTemplateAlmacen = document.getElementById('recepcionAlmacen');
    
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

    if (filaReferencia) filaReferencia.insertAdjacentElement('afterend', tr);
    else tbodyRecepcion.appendChild(tr);

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

export async function abrirModalRecepcion(idOrden) {
    try {
        const separador = urls.index.includes('?') ? '&' : '?';
        const res = await getJson(`${urls.index}${separador}accion=ver&id=${idOrden}`);
        const orden = res.data;

        document.getElementById('recepcionOrdenId').value = orden.id;
        const cerrarForzadoRecepcion = document.getElementById('cerrarForzadoRecepcion');
        if(cerrarForzadoRecepcion) cerrarForzadoRecepcion.checked = false;
        
        const recepcionProveedorNombre = document.getElementById('recepcionProveedorNombre');
        if (recepcionProveedorNombre) {
            const proveedor = String(orden.proveedor || '').trim();
            recepcionProveedorNombre.textContent = proveedor ? `- ${proveedor}` : '';
        }
        
        const recepcionFecha = document.getElementById('recepcionFecha');
        if (recepcionFecha) {
            recepcionFecha.value = orden.fecha_recepcion_sugerida || obtenerFechaLocalISO();
            if (orden.fecha_orden) recepcionFecha.min = String(orden.fecha_orden).split(' ')[0];
            else recepcionFecha.removeAttribute('min');
        }
        
        const recepcionObservaciones = document.getElementById('recepcionObservaciones');
        if (recepcionObservaciones) recepcionObservaciones.value = '';
        
        const tbodyRecepcion = document.querySelector('#tablaDetalleRecepcion tbody');
        if(tbodyRecepcion) tbodyRecepcion.innerHTML = '';

        const detalle = Array.isArray(orden.detalle) ? orden.detalle : [];
        detalle.forEach((linea) => {
            if (Number(linea.cantidad_pendiente) > 0.0001) agregarFilaRecepcion(linea, null);
        });
        
        if (tbodyRecepcion && tbodyRecepcion.children.length === 0) {
            Swal.fire('Aviso', 'Esta orden ya no tiene cantidades pendientes por recepcionar.', 'info');
            return;
        }
        
        const modalEl = document.getElementById('modalRecepcionCompra');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (error) {
        Swal.fire('Error', error.message || 'No se pudo preparar la recepción.', 'error');
    }
}

export function initLogistica() {
    // ==========================================
    // ENGANCHES DE DEVOLUCIÓN (Ahora con submit)
    // ==========================================
    const formDevolucionCompra = document.getElementById('formDevolucionCompra');
    
    formDevolucionCompra?.addEventListener('submit', async (e) => {
        e.preventDefault(); // Evita que recargue la página
        
        const devolucionMotivo = document.getElementById('devolucionMotivo');
        const btnConfirmarDevolucion = document.getElementById('btnConfirmarDevolucion');

        if (!devolucionMotivo.value) return Swal.fire('Aviso', 'Seleccione un motivo.', 'warning');
        
        const detalle = [];
        let totalDevolverBase = 0;

        document.querySelector('#tablaDetalleDevolucion tbody').querySelectorAll('tr').forEach(tr => {
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

        if (detalle.length === 0 || totalDevolverBase <= 0) return Swal.fire('Aviso', 'Ingrese al menos una cantidad.', 'warning');

        try {
            // Usamos la nueva ruta MVC limpia que agregamos en config.js
            const urlPost = urls.devolver;

            const checkReemplazo = document.getElementById('devolucionEsperarReemplazo');
            const esperarReemplazo = checkReemplazo ? checkReemplazo.checked : true;

            const payload = {
                id_orden: Number(document.getElementById('devolucionOrdenId').value),
                motivo: devolucionMotivo.value,
                esperar_reemplazo: false, // Forzado a falso
                detalle: detalle
            };

            const res = await postJsonConCarga(urlPost, payload, btnConfirmarDevolucion);
            await Swal.fire('Éxito', res.mensaje, 'success');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDevolucionCompra')).hide();
            recargarPagina();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    });

    // ==========================================
    // ENGANCHES DE RECEPCIÓN (Ahora con submit)
    // ==========================================
    const formRecepcionCompra = document.getElementById('formRecepcionCompra');
    
    formRecepcionCompra?.addEventListener('submit', async (e) => {
        e.preventDefault(); // Evita que recargue la página
        
        const btnConfirmarRecepcion = document.getElementById('btnConfirmarRecepcion');

        try {
            const filas = [...document.querySelector('#tablaDetalleRecepcion tbody').querySelectorAll('tr')];
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
            if (document.querySelector('#tablaDetalleRecepcion tbody').querySelector('.is-invalid')) throw new Error('Corrija las cantidades en rojo.');

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

            const cerrarForzadoRecepcion = document.getElementById('cerrarForzadoRecepcion');
            if (esParcial && cerrarForzadoRecepcion && !cerrarForzadoRecepcion.checked) {
                const resp = await Swal.fire({
                    icon: 'info', title: 'Recepción Parcial', 
                    text: 'Está ingresando menos cantidad. La orden quedará abierta. ¿Desea continuar?', 
                    showCancelButton: true, confirmButtonText: 'Sí, ingresar parcial'
                });
                if (!resp.isConfirmed) return;
            }

            const payload = {
                id_orden: Number(document.getElementById('recepcionOrdenId').value || 0),
                cerrar_forzado: cerrarForzadoRecepcion ? cerrarForzadoRecepcion.checked : false,
                fecha_recepcion: (document.getElementById('recepcionFecha')?.value || '').trim(),
                observaciones: (document.getElementById('recepcionObservaciones')?.value || '').trim(),
                detalle: detalle
            };

            const res = await postJsonConCarga(urls.recepcionar, payload, btnConfirmarRecepcion);
            await Swal.fire('Ingresado', res.mensaje, 'success');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRecepcionCompra')).hide();
            recargarPagina();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });
}