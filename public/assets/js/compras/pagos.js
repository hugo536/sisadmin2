// ==============================================================
// MÓDULO COMPRAS: pagos.js (Tesorería, Pagos Inmediatos y T.C.)
// ==============================================================

// --- VARIABLES GLOBALES INYECTADAS DESDE PHP ---
export const cuentasDisponibles = Array.isArray(window.TESORERIA_CUENTAS) 
    ? window.TESORERIA_CUENTAS 
    : Object.values(window.TESORERIA_CUENTAS || {});

export const metodosDisponibles = Array.isArray(window.TESORERIA_METODOS) 
    ? window.TESORERIA_METODOS 
    : Object.values(window.TESORERIA_METODOS || {});

// --- REFERENCIAS DOM: PAGO INMEDIATO (COMPRAS) ---
export const DOM_PAGOS_COMPRAS = {
    switchCobroContainerCompra: document.getElementById('switchCobroContainerCompra'),
    switchCobroInmediatoCompra: document.getElementById('switchCobroInmediatoCompra'),
    seccionCobroInmediatoCompra: document.getElementById('seccionCobroInmediatoCompra'),
    contenedorMetodosPagoCompra: document.getElementById('contenedorMetodosPagoCompra'),
    btnAgregarPagoInmediatoCompra: document.getElementById('btnAgregarPagoInmediatoCompra'),
    totalPagadoInmediatoCompra: document.getElementById('totalPagadoInmediatoCompra')
};

// ==========================================
// 1. FILTRADO DINÁMICO DE MÉTODOS DE PAGO
// ==========================================
export function filtrarMetodosPorCuentaCompras(selectCuenta, selectMetodo) {
    if (!selectCuenta || !selectMetodo) return;

    const idCuentaSeleccionada = parseInt(selectCuenta.value);
    const valorPrevio = selectMetodo.value; 
    
    selectMetodo.innerHTML = '<option value="" selected disabled>Método...</option>';

    if (!idCuentaSeleccionada) {
        metodosDisponibles.forEach(m => selectMetodo.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
        return;
    }

    const cuentaObj = cuentasDisponibles.find(c => parseInt(c.id) === idCuentaSeleccionada);
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

    metodosDisponibles.forEach(m => {
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

// ==========================================
// 2. LÓGICA DE PAGOS Y CÁLCULOS
// ==========================================
export function calcularTotalPagoInmediatoCompra() {
    if (!DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra) return;
    let total = 0;
    const filas = DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato');
    
    filas.forEach(fila => {
        const monto = parseFloat(fila.querySelector('.input-monto-inmediato').value) || 0;
        total += monto;
    });
    
    if (DOM_PAGOS_COMPRAS.totalPagadoInmediatoCompra) {
        const sim = document.getElementById('ordenMoneda')?.value === 'USD' ? '$' : 'S/';
        DOM_PAGOS_COMPRAS.totalPagadoInmediatoCompra.innerHTML = `<span class="simbolo-moneda">${sim}</span> ${total.toFixed(2)}`;
        
        const ordenTotal = document.getElementById('ordenTotal');
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalPedido = parseFloat(totalTexto) || 0;

        if (total > totalPedido) DOM_PAGOS_COMPRAS.totalPagadoInmediatoCompra.className = 'fw-bold fs-5 text-danger'; 
        else if (total === totalPedido && total > 0) DOM_PAGOS_COMPRAS.totalPagadoInmediatoCompra.className = 'fw-bold fs-5 text-success';
        else DOM_PAGOS_COMPRAS.totalPagadoInmediatoCompra.className = 'fw-bold fs-5 text-dark';
    }

    if (DOM_PAGOS_COMPRAS.btnAgregarPagoInmediatoCompra) {
        if (filas.length === 0) {
            DOM_PAGOS_COMPRAS.btnAgregarPagoInmediatoCompra.disabled = false;
        } else {
            const ultimaFila = filas[filas.length - 1];
            const cuenta = ultimaFila.querySelector('.select-cuenta-inmediato').value;
            const metodo = ultimaFila.querySelector('.select-metodo-inmediato').value;
            const monto = parseFloat(ultimaFila.querySelector('.input-monto-inmediato').value) || 0;
            
            DOM_PAGOS_COMPRAS.btnAgregarPagoInmediatoCompra.disabled = !(cuenta && metodo && monto > 0);
        }
    }
}

export function agregarFilaPagoInmediatoCompra(montoSugerido = '') {
    if (!DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra) return;
    
    let opcionesCuentas = '<option value="" selected disabled>Cuenta Origen...</option>';
    cuentasDisponibles.forEach(c => { 
        const saldo = parseFloat(c.saldo_actual || c.saldo || 0);
        const monedaCuenta = c.moneda || 'PEN';
        opcionesCuentas += `<option value="${c.id}" data-saldo="${saldo}" data-moneda="${monedaCuenta}">${c.nombre} (Disp: ${monedaCuenta} ${saldo.toFixed(2)})</option>`; 
    });

    const numFilas = DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato').length;
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

    DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.appendChild(div);

    const selCuentaInmediato = div.querySelector('.select-cuenta-inmediato');
    const selMetodoInmediato = div.querySelector('.select-metodo-inmediato');
    const inputMontoInmediato = div.querySelector('.input-monto-inmediato');
    const btnQuitar = div.querySelector('.btn-quitar-pago');
    
    // Elementos del Tipo de Cambio
    const seccionTC = div.querySelector('.seccion-tipo-cambio');
    const inputTC = div.querySelector('.input-tc-inmediato');
    const spanMontoDebito = div.querySelector('.monto-final-debito');
    const spanLabelMoneda = div.querySelector('.moneda-cuenta-label');

    const calcularDebitoReal = () => {
        const opt = selCuentaInmediato.options[selCuentaInmediato.selectedIndex];
        if (!opt || !opt.value) return;

        const monedaCuenta = opt.getAttribute('data-moneda');
        const monedaOrden = document.getElementById('ordenMoneda').value;
        const montoPago = parseFloat(inputMontoInmediato.value) || 0;

        if (monedaCuenta !== monedaOrden) {
            seccionTC.classList.remove('d-none');
            spanLabelMoneda.textContent = monedaCuenta;
            inputTC.required = true;

            const tc = parseFloat(inputTC.value) || 0;
            
            let debitoReal = 0;
            if (monedaOrden === 'USD' && monedaCuenta === 'PEN') {
                debitoReal = montoPago * tc;
            } else if (monedaOrden === 'PEN' && monedaCuenta === 'USD') {
                debitoReal = tc > 0 ? (montoPago / tc) : 0;
            }

            spanMontoDebito.textContent = debitoReal.toFixed(2);

            const saldoDisp = parseFloat(opt.getAttribute('data-saldo')) || 0;
            if (debitoReal > saldoDisp) {
                spanMontoDebito.classList.replace('text-danger', 'text-bg-danger');
            } else {
                spanMontoDebito.classList.replace('text-bg-danger', 'text-danger');
            }

        } else {
            seccionTC.classList.add('d-none');
            inputTC.required = false;
            inputTC.value = '';
            spanMontoDebito.textContent = montoPago.toFixed(2);
            
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
        const filasRestantes = DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.querySelectorAll('.fila-pago-inmediato');
        if (filasRestantes.length === 1) filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
        calcularTotalPagoInmediatoCompra();
    });

    calcularDebitoReal();
    calcularTotalPagoInmediatoCompra();
    return div;
}

// ==========================================
// 3. INICIALIZADOR DE EVENTOS GLOBALES DE PAGO
// ==========================================
export function initPagosCompras() {
    DOM_PAGOS_COMPRAS.switchCobroInmediatoCompra?.addEventListener('change', (e) => {
        const ordenTotal = document.getElementById('ordenTotal');
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalNumerico = parseFloat(totalTexto) || 0;

        if (e.target.checked && totalNumerico <= 0) {
            e.target.checked = false;
            DOM_PAGOS_COMPRAS.seccionCobroInmediatoCompra.classList.add('d-none');
            DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.innerHTML = '';
            return;
        }

        if (e.target.checked) {
            DOM_PAGOS_COMPRAS.seccionCobroInmediatoCompra.classList.remove('d-none');
            DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.innerHTML = '';
            agregarFilaPagoInmediatoCompra(totalNumerico > 0 ? totalNumerico.toFixed(2) : '');
        } else {
            DOM_PAGOS_COMPRAS.seccionCobroInmediatoCompra.classList.add('d-none');
            DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.innerHTML = '';
            calcularTotalPagoInmediatoCompra();
        }
    });

    DOM_PAGOS_COMPRAS.btnAgregarPagoInmediatoCompra?.addEventListener('click', () => {
        const ordenTotal = document.getElementById('ordenTotal');
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalPedido = parseFloat(totalTexto) || 0;
        
        let totalPagadoHastaAhora = 0;
        DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.querySelectorAll('.input-monto-inmediato').forEach(inp => {
            totalPagadoHastaAhora += parseFloat(inp.value) || 0;
        });

        let faltante = totalPedido - totalPagadoHastaAhora;
        if (faltante < 0) faltante = 0;

        agregarFilaPagoInmediatoCompra(faltante > 0 ? faltante.toFixed(2) : '');
        DOM_PAGOS_COMPRAS.contenedorMetodosPagoCompra.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
    });
}