// ==============================================================
// MÓDULO PAGOS: pagos.js (Tesorería, Cobros Inmediatos y Saldos)
// ==============================================================

// --- DATOS DE TESORERÍA INYECTADOS EN EL CONTENEDOR DE LA VISTA ---
import { cuentasDisponibles, metodosDisponibles } from './config.js';
export { cuentasDisponibles, metodosDisponibles };

// --- REFERENCIAS DOM: COBRO INMEDIATO (VENTAS) ---
// Las exportamos para que venta.js pueda bloquear/desbloquear controles según el total o tipo de operación
export const DOM_PAGOS = {
    switchCobroContainer: document.getElementById('switchCobroContainer'),
    switchCobroInmediato: document.getElementById('switchCobroInmediato'),
    seccionCobroInmediato: document.getElementById('seccionCobroInmediato'),
    contenedorMetodosPago: document.getElementById('contenedorMetodosPago'),
    totalPagadoInmediato: document.getElementById('totalPagadoInmediato'),
    btnAgregarPagoInmediato: document.getElementById('btnAgregarPagoInmediato')
};

// --- ESTADO VIRTUAL DE SALDOS ---
export let saldoFavorAplicado = 0;

export function limpiarSaldoFavor() {
    saldoFavorAplicado = 0;
}

// ==========================================
// 1. FILTRADO DINÁMICO (LA MAGIA DE OPCIÓN B)
// ==========================================
// Esta función se exporta porque la usa logistica.js para los cobros en despacho
export function filtrarMetodosPorCuentaVentas(selectCuenta, selectMetodo) {
    if (!selectCuenta || !selectMetodo) return;

    const idCuentaSeleccionada = parseInt(selectCuenta.value);
    const valorPrevio = selectMetodo.value; 
    
    // Limpiamos el select
    selectMetodo.innerHTML = '<option value="" selected disabled>Método...</option>';

    const arrayCuentas = Array.isArray(cuentasDisponibles) 
                         ? cuentasDisponibles 
                         : Object.values(cuentasDisponibles || {});

    const arrayMetodos = Array.isArray(metodosDisponibles)
                         ? metodosDisponibles
                         : Object.values(metodosDisponibles || {});

    if (!idCuentaSeleccionada) {
        selectMetodo.disabled = true;
        return;
    }

    const cuentaObj = arrayCuentas.find(c => parseInt(c.id) === idCuentaSeleccionada);
    if (!cuentaObj) return;

    let metodosPermitidos = [];
    let tieneFiltro = false; 

    let rawMetodos = cuentaObj.metodos_pago;

    if (rawMetodos !== null && rawMetodos !== "" && rawMetodos !== "null" && rawMetodos !== "[]" && rawMetodos !== undefined) {
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
            console.error("No se pudo parsear el JSON de métodos:", rawMetodos);
        }
    }

    // Normalizamos para comparar tanto por ID como por Nombre (insensible a mayúsculas/acentos)
    const permitidosNormalizados = metodosPermitidos.map(m => String(m).trim().toLowerCase());

    arrayMetodos.forEach(m => {
        const idStr = String(m.id).trim().toLowerCase();
        const nombreStr = String(m.nombre).trim().toLowerCase();

        // Es válido si no hay filtro, o si coincide el ID o el Nombre con los checks guardados
        const esValido = !tieneFiltro || permitidosNormalizados.some(p => p === idStr || p === nombreStr || nombreStr.includes(p));

        if (esValido) {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.nombre;
            selectMetodo.appendChild(opt);
        }
    });

    // ========================================================
    // AUTO-SELECCIÓN POR DEFECTO
    // ========================================================
    const opcionesValidas = selectMetodo.querySelectorAll('option:not([disabled])');

    if (opcionesValidas.length === 0) {
        // No tiene métodos vinculados
        selectMetodo.innerHTML = '<option value="" selected disabled>Sin métodos configurados</option>';
        selectMetodo.disabled = true;
    } else if (opcionesValidas.length === 1) {
        // 👇 SI SOLO HAY 1 MÉTODO (Ej: Solo Efectivo), SE SELECCIONA AUTOMÁTICAMENTE
        selectMetodo.disabled = false;
        selectMetodo.value = opcionesValidas[0].value;
        // Disparamos el evento para habilitar el campo del monto
        selectMetodo.dispatchEvent(new Event('change'));
    } else {
        // Hay varios métodos: si existía uno antes lo mantiene, si no deja para que el usuario elija
        selectMetodo.disabled = false;
        if (valorPrevio && [...opcionesValidas].some(o => o.value === String(valorPrevio))) {
            selectMetodo.value = valorPrevio;
        } else {
            selectMetodo.value = "";
        }
    }
}

// ==========================================
// 2. LÓGICA DE COBRO INMEDIATO (VENTAS)
// ==========================================

export function calcularTotalCobroInmediato() {
    if (!DOM_PAGOS.contenedorMetodosPago) return;
    
    let pagosFisicos = 0;
    const filas = DOM_PAGOS.contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato');
    
    filas.forEach(fila => {
        pagosFisicos += parseFloat(fila.querySelector('.input-monto-inmediato').value) || 0;
    });
    
    // El total real que cubre el cliente es su dinero físico + su crédito
    const totalCubierto = pagosFisicos + saldoFavorAplicado;
    
    if (DOM_PAGOS.totalPagadoInmediato) {
        // Texto dinámico para que el cajero sepa qué es físico y qué es saldo
        if (saldoFavorAplicado > 0) {
            DOM_PAGOS.totalPagadoInmediato.innerHTML = `S/ ${totalCubierto.toFixed(2)} <br><small class="text-secondary" style="font-size: 0.7rem;">(S/ ${pagosFisicos.toFixed(2)} físico + S/ ${saldoFavorAplicado.toFixed(2)} crédito)</small>`;
        } else {
            DOM_PAGOS.totalPagadoInmediato.innerHTML = `S/ ${totalCubierto.toFixed(2)}`;
        }
        
        const ventaTotal = document.getElementById('ventaTotal');
        const totalPedido = parseFloat(ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0') || 0;

        if (totalCubierto > totalPedido) DOM_PAGOS.totalPagadoInmediato.className = 'fw-bold fs-5 text-danger'; 
        else if (totalCubierto === totalPedido && totalCubierto > 0) DOM_PAGOS.totalPagadoInmediato.className = 'fw-bold fs-5 text-success';
        else DOM_PAGOS.totalPagadoInmediato.className = 'fw-bold fs-5 text-dark';
    }

    if (DOM_PAGOS.btnAgregarPagoInmediato) {
        if (filas.length === 0) {
            DOM_PAGOS.btnAgregarPagoInmediato.disabled = false;
        } else {
            const ultimaFila = filas[filas.length - 1];
            const cuenta = ultimaFila.querySelector('.select-cuenta-inmediato').value;
            const metodo = ultimaFila.querySelector('.select-metodo-inmediato').value;
            const monto = parseFloat(ultimaFila.querySelector('.input-monto-inmediato').value) || 0;
            
            DOM_PAGOS.btnAgregarPagoInmediato.disabled = !(cuenta && metodo && monto > 0);
        }
    }
}

export function agregarFilaPagoInmediato(montoSugerido = '') {
    if (!DOM_PAGOS.contenedorMetodosPago) return;
    
    let opcionesCuentas = '<option value="" selected disabled>Cuenta Destino...</option>';
    cuentasDisponibles.forEach(c => { opcionesCuentas += `<option value="${c.id}">${c.nombre} (${c.moneda})</option>`; });

    const numFilas = DOM_PAGOS.contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato').length;

    const div = document.createElement('div');
    div.className = 'd-flex flex-column flex-sm-row gap-2 align-items-start align-items-sm-center bg-white p-2 rounded border border-success-subtle fila-pago-inmediato';
    
    div.innerHTML = `
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
                <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle">S/</span>
                <input type="number" class="form-control text-end text-success fw-bold border-secondary-subtle input-monto-inmediato" min="0" step="0.01" placeholder="0.00" value="${montoSugerido}" required readonly>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-quitar-pago ${numFilas === 0 ? 'd-none' : ''} px-2" title="Quitar pago"><i class="bi bi-trash"></i></button>
        </div>
    `;

    DOM_PAGOS.contenedorMetodosPago.appendChild(div);

    const selCuentaInmediato = div.querySelector('.select-cuenta-inmediato');
    const selMetodoInmediato = div.querySelector('.select-metodo-inmediato');
    const inputMontoInmediato = div.querySelector('.input-monto-inmediato');
    const btnQuitar = div.querySelector('.btn-quitar-pago');

    selCuentaInmediato.addEventListener('change', () => {
        // 1. Esto filtra los métodos y autoselecciona si solo hay uno
        filtrarMetodosPorCuentaVentas(selCuentaInmediato, selMetodoInmediato);
        
        // 2. Si se logró autoseleccionar un método, desbloqueamos el monto
        inputMontoInmediato.readOnly = !selMetodoInmediato.value;
        
        calcularTotalCobroInmediato();
    });

    selMetodoInmediato.addEventListener('change', () => {
        inputMontoInmediato.readOnly = !selMetodoInmediato.value;
        if (selMetodoInmediato.value && !inputMontoInmediato.value) {
            inputMontoInmediato.focus();
        }
        calcularTotalCobroInmediato();
    });

    inputMontoInmediato.addEventListener('input', calcularTotalCobroInmediato);
    
    btnQuitar.addEventListener('click', () => {
        div.remove();
        const filasRestantes = DOM_PAGOS.contenedorMetodosPago.querySelectorAll('.fila-pago-inmediato');
        if (filasRestantes.length === 1) filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
        calcularTotalCobroInmediato();
    });

    calcularTotalCobroInmediato();
    if (cuentasDisponibles.length === 1) {
        selCuentaInmediato.value = cuentasDisponibles[0].id;
        selCuentaInmediato.dispatchEvent(new Event('change'));
    }
    return div;
}

// ==========================================
// 3. LÓGICA DE SALDOS A FAVOR
// ==========================================

export function renderAlertaSaldoFavor(saldoFavor) {
    const contenedorSaldo = document.getElementById('alertaSaldoFavorContenedor');
    if (!contenedorSaldo) return;

    const saldo = Number(saldoFavor || 0);
    if (saldo > 0) {
        contenedorSaldo.innerHTML = `
            <div class="alert alert-success d-flex align-items-center justify-content-between p-2 mb-2 shadow-sm border-success rounded" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-wallet2 fs-5 text-success"></i>
                    <div class="lh-sm">
                        <span class="text-success small fw-semibold">Saldo disponible:</span>
                        <span class="text-success-emphasis fw-bold small">S/ ${saldo.toFixed(2)}</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-success fw-bold py-1 px-3 shadow-sm" id="btnAplicarSaldoFavor" data-saldo="${saldo}">
                    Usar
                </button>
            </div>
        `;
        document.getElementById('btnAplicarSaldoFavor').addEventListener('click', (e) => {
            aplicarSaldoFavorAutomatizado(Number(e.target.dataset.saldo));
        });
        return;
    }
    contenedorSaldo.innerHTML = '';
}

function aplicarSaldoFavorAutomatizado(saldoDisponible) {
    const btnUsar = document.getElementById('btnAplicarSaldoFavor');
    const ventaTotal = document.getElementById('ventaTotal');
    const totalPedido = parseFloat(ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0') || 0;

    // 👇 1. LÓGICA PARA QUITAR EL SALDO (DESHACER) 👇
    if (saldoFavorAplicado > 0) {
        saldoFavorAplicado = 0; // Reseteamos nuestra variable virtual
        if (btnUsar) {
            btnUsar.innerHTML = 'Usar';
            btnUsar.classList.replace('btn-secondary', 'btn-success');
        }
        calcularTotalCobroInmediato();
        return; // Salimos de la función
    }

    // 👇 2. LÓGICA PARA APLICAR EL SALDO 👇
    if (totalPedido <= 0) return;

    // Guardamos el monto virtualmente
    saldoFavorAplicado = Math.min(saldoDisponible, totalPedido);

    // Encendemos el switch de cobro si estaba apagado
    if (DOM_PAGOS.switchCobroInmediato && !DOM_PAGOS.switchCobroInmediato.checked) {
        DOM_PAGOS.switchCobroInmediato.checked = true;
        DOM_PAGOS.seccionCobroInmediato.classList.remove('d-none');
    }
    
    // Limpiamos cajas de texto previas para evitar el problema de los S/ 13.00 de tu imagen
    DOM_PAGOS.contenedorMetodosPago.innerHTML = ''; 

    // Cambiamos el botón para que ahora diga "Quitar"
    if (btnUsar) {
        // YA NO USAMOS disabled = true;
        btnUsar.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i>Quitar (S/ ${saldoFavorAplicado.toFixed(2)})`;
        btnUsar.classList.replace('btn-success', 'btn-secondary');
    }

    // Si el pedido es mayor al saldo (ej. Pedido S/ 100 y Saldo S/ 40), agregamos fila para cobrar la diferencia física
    const saldoRestante = totalPedido - saldoFavorAplicado;
    if (saldoRestante > 0.001) {
        agregarFilaPagoInmediato(saldoRestante.toFixed(2));
    }

    calcularTotalCobroInmediato();
}

// ==========================================
// 4. INICIALIZADOR DEL MÓDULO PAGOS
// ==========================================
export function initPagos() {
    // Event Listener: Switch Cobro Inmediato (Ventas)
    DOM_PAGOS.switchCobroInmediato?.addEventListener('change', (e) => {
        const ventaTotal = document.getElementById('ventaTotal');
        const totalTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalNumerico = parseFloat(totalTexto) || 0;

        if (e.target.checked && totalNumerico <= 0) {
            e.target.checked = false;
            DOM_PAGOS.seccionCobroInmediato.classList.add('d-none');
            DOM_PAGOS.contenedorMetodosPago.innerHTML = '';
            return;
        }

        if (e.target.checked) {
            DOM_PAGOS.seccionCobroInmediato.classList.remove('d-none');
            DOM_PAGOS.contenedorMetodosPago.innerHTML = '';
            agregarFilaPagoInmediato(totalNumerico > 0 ? totalNumerico.toFixed(2) : '');
        } else {
            DOM_PAGOS.seccionCobroInmediato.classList.add('d-none');
            DOM_PAGOS.contenedorMetodosPago.innerHTML = '';
            calcularTotalCobroInmediato();
        }
    });

    // Event Listener: Botón Agregar otro Método de Pago (Ventas)
    DOM_PAGOS.btnAgregarPagoInmediato?.addEventListener('click', () => {
        const ventaTotal = document.getElementById('ventaTotal');
        const totalTexto = ventaTotal ? ventaTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalPedido = parseFloat(totalTexto) || 0;
        
        let totalPagadoHastaAhora = 0;
        DOM_PAGOS.contenedorMetodosPago.querySelectorAll('.input-monto-inmediato').forEach(inp => {
            totalPagadoHastaAhora += parseFloat(inp.value) || 0;
        });

        let faltante = totalPedido - totalPagadoHastaAhora;
        if (faltante < 0) faltante = 0;

        agregarFilaPagoInmediato(faltante > 0 ? faltante.toFixed(2) : '');
        DOM_PAGOS.contenedorMetodosPago.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
    });
}