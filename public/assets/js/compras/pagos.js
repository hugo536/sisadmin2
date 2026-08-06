// ==============================================================
// MÓDULO COMPRAS: pagos.js (Tesorería, Pagos Inmediatos y T.C.)
// ==============================================================

// IMPORTAMOS LOS DATOS SEGUROS DESDE CONFIG.JS EN LUGAR DE WINDOW
import { cuentasDisponibles, metodosDisponibles } from './config.js';

// Las re-exportamos por si algún otro archivo (como compra.js) las necesita
export { cuentasDisponibles, metodosDisponibles };

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
            while(typeof parsed === 'string') { parsed = JSON.parse(parsed); }
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
    const contenedorMetodosPagoCompra = document.getElementById('contenedorMetodosPagoCompra');
    const totalPagadoInmediatoCompra = document.getElementById('totalPagadoInmediatoCompra');
    const btnAgregarPagoInmediatoCompra = document.getElementById('btnAgregarPagoInmediatoCompra');
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
        
        const ordenTotal = document.getElementById('ordenTotal');
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

export function agregarFilaPagoInmediatoCompra(montoSugerido = '') {
    const contenedorMetodosPagoCompra = document.getElementById('contenedorMetodosPagoCompra');
    if (!contenedorMetodosPagoCompra) return;
    
    let opcionesCuentas = '<option value="" selected disabled>Cuenta Origen...</option>';
    cuentasDisponibles.forEach(c => { 
        const saldo = parseFloat(c.saldo_actual || c.saldo || 0);
        const monedaCuenta = c.moneda || 'PEN';
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
            if (debitoReal > saldoDisp) spanMontoDebito.classList.replace('text-danger', 'text-bg-danger');
            else spanMontoDebito.classList.replace('text-bg-danger', 'text-danger');

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
        if (selMetodoInmediato.value && !inputMontoInmediato.value) inputMontoInmediato.focus();
        calcularTotalPagoInmediatoCompra();
    });

    inputMontoInmediato.addEventListener('input', () => {
        calcularTotalPagoInmediatoCompra();
        calcularDebitoReal();
    });

    inputTC.addEventListener('input', calcularDebitoReal);
    
    btnQuitar.addEventListener('click', () => {
        div.remove();
        const filasRestantes = document.getElementById('contenedorMetodosPagoCompra').querySelectorAll('.fila-pago-inmediato');
        if (filasRestantes.length === 1) filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
        calcularTotalPagoInmediatoCompra();
    });

    calcularDebitoReal();
    calcularTotalPagoInmediatoCompra();
    return div;
}

export function initPagosCompras() {
    const switchCobro = document.getElementById('switchCobroInmediatoCompra');
    const seccionCobro = document.getElementById('seccionCobroInmediatoCompra');
    const contenedorMetodos = document.getElementById('contenedorMetodosPagoCompra');
    const btnAgregar = document.getElementById('btnAgregarPagoInmediatoCompra');

    switchCobro?.addEventListener('change', (e) => {
        const ordenTotal = document.getElementById('ordenTotal');
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalNumerico = parseFloat(totalTexto) || 0;

        if (e.target.checked && totalNumerico <= 0) {
            e.target.checked = false;
            if(seccionCobro) seccionCobro.classList.add('d-none');
            if(contenedorMetodos) contenedorMetodos.innerHTML = '';
            return;
        }

        if (e.target.checked) {
            if(seccionCobro) seccionCobro.classList.remove('d-none');
            if(contenedorMetodos) contenedorMetodos.innerHTML = '';
            agregarFilaPagoInmediatoCompra(totalNumerico > 0 ? totalNumerico.toFixed(2) : '');
        } else {
            if(seccionCobro) seccionCobro.classList.add('d-none');
            if(contenedorMetodos) contenedorMetodos.innerHTML = '';
            calcularTotalPagoInmediatoCompra();
        }
    });

    btnAgregar?.addEventListener('click', () => {
        const ordenTotal = document.getElementById('ordenTotal');
        const totalTexto = ordenTotal ? ordenTotal.textContent.replace(/[^\d.-]/g, '') : '0';
        const totalPedido = parseFloat(totalTexto) || 0;
        
        let totalPagadoHastaAhora = 0;
        document.getElementById('contenedorMetodosPagoCompra').querySelectorAll('.input-monto-inmediato').forEach(inp => {
            totalPagadoHastaAhora += parseFloat(inp.value) || 0;
        });

        let faltante = totalPedido - totalPagadoHastaAhora;
        if (faltante < 0) faltante = 0;

        agregarFilaPagoInmediatoCompra(faltante > 0 ? faltante.toFixed(2) : '');
        document.getElementById('contenedorMetodosPagoCompra').querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
    });
}