document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // ========================================================================
    // 0. INICIALIZACIÓN GLOBAL
    // ========================================================================
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const tesoreriaApp = document.getElementById('tesoreriaCuentasApp');
    const modalCuentaEl = document.getElementById('modalCuentaTesoreria');
    const formCuenta = document.getElementById('formCuentaTesoreria');
    
    // Auto-abrir modal en caso de edición
    if (tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true' && modalCuentaEl) {
        bootstrap.Modal.getOrCreateInstance(modalCuentaEl).show();
    }

    // Alertas de éxito post-redirección
    if (tesoreriaApp) {
        const params = new URLSearchParams(window.location.search);
        if (params.get('ok') === '1') {
            const action = (params.get('action') || '').toLowerCase();
            const isUpdate = action === 'updated';
            const isDelete = action === 'deleted';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: isDelete ? 'Cuenta eliminada' : (isUpdate ? 'Cuenta actualizada' : 'Cuenta guardada'),
                    text: isDelete
                        ? 'La cuenta se eliminó correctamente.'
                        : (isUpdate ? 'La cuenta se actualizó correctamente.' : 'La cuenta se guardó correctamente.'),
                    confirmButtonText: 'Aceptar'
                });
            }
            // Limpiar URL
            params.delete('ok');
            params.delete('action');
            const nextUrl = params.toString() ? `${window.location.pathname}?${params.toString()}` : window.location.pathname;
            window.history.replaceState({}, '', nextUrl);
        }
    }

    // ========================================================================
    // 1. GESTIÓN DE FORMULARIO DE CUENTAS (UX)
    // ========================================================================
    const tipoCuentaSelect = document.getElementById('cuentaTipo');
    const codigoInput = document.getElementById('cuentaCodigo');
    const tipoCuentaInput = document.getElementById('cuentaTipoCuenta');
    const entidadSelect = document.getElementById('cuentaEntidad');
    const bankFields = document.querySelectorAll('.js-bank-field');
    const numeroInput = document.getElementById('cuentaNumero');
    const numeroLabel = document.getElementById('cuentaNumeroLabel');
    const cciWrap = document.getElementById('cuentaCciWrap');
    const cciInput = document.getElementById('cuentaCci');

    const setTipoCuentaOptions = (tipo) => {
        if (!tipoCuentaInput) return;
        const selected = (tipoCuentaInput.dataset.selected || tipoCuentaInput.value || '').toUpperCase();
        tipoCuentaInput.innerHTML = '<option value="">Seleccionar...</option>';
        
        const opciones = {
            BANCO: ['AHORROS', 'CORRIENTE', 'MAESTRA'],
            BILLETERA: ['YAPE', 'PLIN', 'LUKITA', 'OTRA']
        }[tipo] || [];

        opciones.forEach(opc => {
            const opt = document.createElement('option');
            opt.value = opc;
            opt.textContent = opc.charAt(0) + opc.slice(1).toLowerCase();
            if (selected === opc) opt.selected = true;
            tipoCuentaInput.appendChild(opt);
        });
    };

    const syncFormUX = () => {
        if (!tipoCuentaSelect) return;
        const tipo = tipoCuentaSelect.value.toUpperCase();
        const esCaja = tipo === 'CAJA';
        const esBilletera = tipo === 'BILLETERA';

        // Visibilidad de campos bancarios
        bankFields.forEach(f => f.style.display = esCaja ? 'none' : 'block');
        if (cciWrap) cciWrap.style.display = (esCaja || esBilletera) ? 'none' : 'block';

        // Etiquetas dinámicas
        if (numeroLabel) numeroLabel.textContent = esBilletera ? 'N° de Teléfono' : 'N° de cuenta';
        if (numeroInput) {
            numeroInput.placeholder = esBilletera ? '999999999' : '000-0000000';
            numeroInput.maxLength = esBilletera ? 9 : 80;
        }

        setTipoCuentaOptions(tipo);
    };

    if (tipoCuentaSelect) {
        tipoCuentaSelect.addEventListener('change', syncFormUX);
        syncFormUX(); // Ejecución inicial
    }

    // ========================================================================
    // 2. CONFIRMACIÓN DE OPERACIONES (SweetAlert2)
    // ========================================================================
    document.querySelectorAll('.js-form-confirm').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: '¿Confirmar operación?',
                text: 'Esta acción actualizará los saldos y se registrará en contabilidad.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
                    }
                    this.submit();
                }
            });
        });
    });

    document.querySelectorAll('.js-form-delete-cuenta').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            Swal.fire({
                title: '¿Eliminar cuenta?',
                text: 'Esta cuenta no tiene movimientos. La eliminación no se podrá deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = true;
                    }
                    this.submit();
                }
            });
        });
    });

    document.querySelectorAll('.js-switch-estado-cuenta').forEach(sw => {
        sw.addEventListener('change', function () {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    // ========================================================================
    // 3. LIMPIEZA AL CERRAR MODALES DE CUENTA
    // ========================================================================
    if (modalCuentaEl && formCuenta) {
        modalCuentaEl.addEventListener('hidden.bs.modal', () => {
            const esEdicion = tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true';
            if (!esEdicion) {
                formCuenta.reset();
                if (tipoCuentaSelect) {
                    tipoCuentaSelect.dispatchEvent(new Event('change'));
                }
            }
        });
    }

    // ========================================================================
    // 4. RELLENAR DATOS EN EL MODAL DE COBRO REGULAR (CXC)
    // ========================================================================
    const modalCobroEl = document.getElementById('modalCobro');
    if (modalCobroEl) {
        modalCobroEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const idOrigen = button.getAttribute('data-id-origen');
            const moneda = button.getAttribute('data-moneda');
            const saldo = button.getAttribute('data-saldo');
            
            const inputIdOrigen = document.getElementById('cobroIdOrigen');
            if (inputIdOrigen) inputIdOrigen.value = idOrigen || '';
            
            const inputMoneda = document.getElementById('cobroMoneda');
            if (inputMoneda) inputMoneda.value = moneda || '';
            
            const saldoNum = parseFloat(saldo) || 0;
            const saldoFormateado = saldoNum.toFixed(2);
            
            const inputSaldo = document.getElementById('cobroSaldo');
            if (inputSaldo) inputSaldo.value = saldoFormateado;
            
            const inputMonto = document.getElementById('cobroMonto');
            if (inputMonto) {
                inputMonto.value = saldoFormateado;
                inputMonto.setAttribute('max', saldoFormateado);
            }
        });

        modalCobroEl.addEventListener('hidden.bs.modal', function () {
            const formCobro = modalCobroEl.querySelector('form');
            if (formCobro) formCobro.reset();
        });
    }

    // ========================================================================
    // 5. RELLENAR DATOS EN EL MODAL DE PAGO REGULAR (CXP)
    // ========================================================================
    const modalPagoEl = document.getElementById('modalPago');
    if (modalPagoEl) {
        modalPagoEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const idOrigen = button.getAttribute('data-id-origen');
            const moneda = button.getAttribute('data-moneda');
            const saldo = button.getAttribute('data-saldo');
            
            const inputIdOrigen = document.getElementById('pagoIdOrigen');
            if (inputIdOrigen) inputIdOrigen.value = idOrigen || '';
            
            const inputMoneda = document.getElementById('pagoMoneda');
            if (inputMoneda) inputMoneda.value = moneda || '';
            
            const saldoNum = parseFloat(saldo) || 0;
            const saldoFormateado = saldoNum.toFixed(2);
            
            const inputSaldo = document.getElementById('pagoSaldo');
            if (inputSaldo) inputSaldo.value = saldoFormateado;
            
            const inputMonto = document.getElementById('pagoMonto');
            if (inputMonto) {
                inputMonto.value = saldoFormateado;
                inputMonto.setAttribute('max', saldoFormateado);
            }
        });

        modalPagoEl.addEventListener('hidden.bs.modal', function () {
            const formPago = modalPagoEl.querySelector('form');
            if (formPago) formPago.reset();
            
            // Limpiar los textos de saldo al cerrar
            const txtSaldo = document.getElementById('textoSaldoDisponible');
            if(txtSaldo) txtSaldo.innerHTML = '';
            
            const inputMonto = document.getElementById('pagoMonto');
            if(inputMonto) inputMonto.classList.remove('is-invalid');
        });
    }

    // ========================================================================
    // 6. LÓGICA DE SALDOS Y LÍMITES (PAGO ESPECÍFICO Y MANUAL)
    // ========================================================================
    
    // --- Lógica para el Modal de PAGO ESPECÍFICO ---
    const selectCuenta = document.getElementById('selectCuentaOrigen');
    const textoSaldo = document.getElementById('textoSaldoDisponible');
    const inputMonto = document.getElementById('pagoMonto');
    const inputPendiente = document.getElementById('pagoSaldo');

    if(selectCuenta && inputMonto && inputPendiente && textoSaldo) {
        function actualizarMaximoEspecifico() {
            const opt = selectCuenta.options[selectCuenta.selectedIndex];
            const pendiente = parseFloat(inputPendiente.value) || 0;

            if(!opt || opt.value === "") {
                textoSaldo.innerHTML = "";
                inputMonto.setAttribute('max', pendiente); // Vuelve al límite original de la deuda
                return;
            }
            
            const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
            textoSaldo.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: $${saldoCuenta.toFixed(2)}`;
            
            // El límite será el saldo del banco, o la deuda (lo que sea menor)
            let maximo = Math.min(saldoCuenta, pendiente);
            if(maximo < 0) maximo = 0;
            
            inputMonto.setAttribute('max', maximo);
            
            if(parseFloat(inputMonto.value) > maximo) {
                inputMonto.value = maximo.toFixed(2);
            }
        }

        selectCuenta.addEventListener('change', actualizarMaximoEspecifico);

        // Validación en tiempo real al escribir
        inputMonto.addEventListener('input', function() {
            const maxVal = parseFloat(this.getAttribute('max'));
            if(!isNaN(maxVal) && parseFloat(this.value) > maxVal) {
                this.value = maxVal.toFixed(2);
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        inputMonto.addEventListener('focus', actualizarMaximoEspecifico);
    }

    // --- Lógica para el Modal de PAGO MANUAL ---
    const modalPagoManualEl = document.getElementById('modalPagoManual');
    const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
    const textoSaldoManual = document.getElementById('textoSaldoDisponibleManual');
    const inputMontoManual = document.getElementById('montoPagarManual');

    if(selectCuentaManual && inputMontoManual && textoSaldoManual) {
        selectCuentaManual.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if(!opt || opt.value === "") {
                textoSaldoManual.innerHTML = "";
                inputMontoManual.removeAttribute('max');
                return;
            }
            
            const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
            textoSaldoManual.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: $${saldoCuenta.toFixed(2)}`;
            
            let maximo = saldoCuenta > 0 ? saldoCuenta : 0;
            inputMontoManual.setAttribute('max', maximo);
            
            if(parseFloat(inputMontoManual.value) > maximo) {
                inputMontoManual.value = maximo.toFixed(2);
            }
        });

        inputMontoManual.addEventListener('input', function() {
            const maxVal = parseFloat(this.getAttribute('max'));
            if(!isNaN(maxVal) && parseFloat(this.value) > maxVal) {
                this.value = maxVal.toFixed(2);
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });

        // Limpiar textos al cerrar el modal de pago manual
        if(modalPagoManualEl) {
            modalPagoManualEl.addEventListener('hidden.bs.modal', function () {
                const formPagoManual = modalPagoManualEl.querySelector('form');
                if (formPagoManual) formPagoManual.reset();
                textoSaldoManual.innerHTML = '';
                inputMontoManual.classList.remove('is-invalid');
            });
        }
    }
});
