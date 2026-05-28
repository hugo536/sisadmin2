/**
 * Lógica específica para Tesorería - Préstamos Bancarios
 * Archivo: assets/js/tesoreria/prestamos.js
 */
(function arrancarPrestamos() {
    'use strict';

    const prestamosApp = document.getElementById('tesoreriaPrestamosApp');
    if (!prestamosApp) return;

    // ========================================================================
    // 1. APOYO FORMULARIO DE NUEVO PRÉSTAMO (CATÁLOGO DE ENTIDADES)
    // ========================================================================
    const selectCatalogo = document.getElementById('prestamoEntidadCatalogo');
    const inputNombre = document.getElementById('prestamoEntidadNombre');
    
    if (selectCatalogo && inputNombre) {
        selectCatalogo.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const nombre = selected ? (selected.value || '').trim() : '';
            if (nombre !== '') {
                inputNombre.value = nombre;
            }
        });
    }

    // ========================================================================
    // 2. LÓGICA DEL MODAL DE PAGO DE CUOTAS
    // ========================================================================
    const modalPago = document.getElementById('modalPago');
    const formPago = document.getElementById('formPago');
    
    // Inputs
    const inputTotal = document.getElementById('pagoMonto');
    const selectCuenta = document.getElementById('selectCuentaOrigen');
    const textSaldo = document.getElementById('textoSaldoDisponible');
    
    // Desglose
    const naturalezaSelect = document.getElementById('pagoNaturaleza');
    const inputCapital = document.getElementById('pagoMontoCapital');
    const inputInteres = document.getElementById('pagoMontoInteres');
    const grupoCapital = document.getElementById('grupoPagoCapital');
    const grupoInteres = document.getElementById('grupoPagoInteres');
    const grupoCentroCosto = document.getElementById('grupoCentroCostoInteres');
    const inputCentroCosto = document.getElementById('pagoCentroCosto');

    // Función Global de redondeo seguro
    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    if (modalPago) {
        // A. Cargar datos al abrir el modal
        modalPago.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const idOrigen = button.getAttribute('data-id-origen');
            const moneda = button.getAttribute('data-moneda');
            const saldo = parseFloat(button.getAttribute('data-saldo')) || 0;
            
            document.getElementById('pagoIdOrigen').value = idOrigen || '';
            document.getElementById('pagoMoneda').value = moneda || '';
            document.getElementById('pagoSaldo').value = saldo.toFixed(2);
            
            if (inputTotal) {
                inputTotal.value = saldo.toFixed(2);
                // NOTA: Para préstamos, el 'max' final lo dictará el saldo de la cuenta de origen.
            }
            
            // Forzar actualización de la UI
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
            if (selectCuenta) selectCuenta.dispatchEvent(new Event('change'));
        });

        // B. Limpiar modal al cerrar
        modalPago.addEventListener('hidden.bs.modal', function () {
            if (formPago) formPago.reset();
            if (textSaldo) textSaldo.innerHTML = '';
            [inputTotal, inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }

    // C. Control de Saldo Máximo según la Cuenta Origen
    if (selectCuenta && textSaldo && inputTotal) {
        selectCuenta.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if(!opt || opt.value === "") {
                textSaldo.innerHTML = "";
                inputTotal.removeAttribute('max');
                return;
            }
            
            const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
            const monedaStr = opt.getAttribute('data-moneda') || '';
            
            textSaldo.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: ${monedaStr} ${saldoCuenta.toFixed(2)}`;
            
            const maximo = saldoCuenta > 0 ? saldoCuenta : 0;
            inputTotal.setAttribute('max', maximo);
            
            if(parseFloat(inputTotal.value) > maximo) {
                inputTotal.value = maximo.toFixed(2);
            }
            validarNaturaleza(); // Re-validar si el total cambió
        });

        inputTotal.addEventListener('input', function() {
            const maxVal = parseFloat(this.getAttribute('max'));
            if(!isNaN(maxVal) && parseFloat(this.value) > maxVal) {
                this.value = maxVal.toFixed(2);
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }

    // D. Lógica de Naturaleza (Desglose Capital/Interés)
    const validarNaturaleza = () => {
        if (!naturalezaSelect || !inputTotal) return;
        
        if (naturalezaSelect.value === 'MIXTO') {
            const total = parseFloat(inputTotal.value || 0);
            const cap = parseFloat(inputCapital?.value || 0);
            const int = parseFloat(inputInteres?.value || 0);
            
            if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                inputCapital?.classList.add('is-invalid');
                inputInteres?.classList.add('is-invalid');
            } else {
                inputCapital?.classList.remove('is-invalid');
                inputInteres?.classList.remove('is-invalid');
            }
        } else {
            inputCapital?.classList.remove('is-invalid');
            inputInteres?.classList.remove('is-invalid');
        }
    };

    if (naturalezaSelect) {
        naturalezaSelect.addEventListener('change', () => {
            const val = naturalezaSelect.value;
            const mostrarCapital = val === 'CAPITAL' || val === 'MIXTO';
            const mostrarInteres = val === 'INTERES' || val === 'MIXTO';

            grupoCapital?.classList.toggle('d-none', !mostrarCapital);
            grupoInteres?.classList.toggle('d-none', !mostrarInteres);
            
            if (inputCapital) inputCapital.required = mostrarCapital;
            if (inputInteres) inputInteres.required = mostrarInteres;

            // Centro de costo solo si hay interés
            if (grupoCentroCosto) {
                grupoCentroCosto.classList.toggle('d-none', !mostrarInteres);
                if (inputCentroCosto) inputCentroCosto.required = mostrarInteres;
            }

            // Limpieza de inputs si se ocultan
            if (!mostrarCapital && inputCapital) inputCapital.value = '0.00';
            if (!mostrarInteres && inputInteres) inputInteres.value = '0.00';
            if (!mostrarInteres && inputCentroCosto) inputCentroCosto.value = '';

            validarNaturaleza();
        });
    }

    [inputTotal, inputCapital, inputInteres].forEach(el => el?.addEventListener('input', validarNaturaleza));

    // E. Validación final antes del Submit
    if (formPago) {
        formPago.addEventListener('submit', (e) => {
            if (naturalezaSelect?.value === 'MIXTO') {
                const total = parseFloat(inputTotal.value || 0);
                const cap = parseFloat(inputCapital.value || 0);
                const int = parseFloat(inputInteres.value || 0);
                
                if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                    e.preventDefault(); 
                    e.stopImmediatePropagation();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Montos descuadrados', 'La suma de Capital + Interés debe ser exactamente igual al Monto Total a pagar.', 'error');
                    }
                }
            }
        });
    }

})();