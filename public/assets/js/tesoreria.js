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

        bankFields.forEach(f => f.style.display = esCaja ? 'none' : 'block');
        if (cciWrap) cciWrap.style.display = (esCaja || esBilletera) ? 'none' : 'block';

        if (numeroLabel) numeroLabel.textContent = esBilletera ? 'N° de Teléfono' : 'N° de cuenta';
        if (numeroInput) {
            numeroInput.placeholder = esBilletera ? '999999999' : '000-0000000';
            numeroInput.maxLength = esBilletera ? 9 : 80;
        }

        setTipoCuentaOptions(tipo);
    };

    if (tipoCuentaSelect) {
        tipoCuentaSelect.addEventListener('change', syncFormUX);
        syncFormUX();
    }

    // ========================================================================
    // 2. CONFIRMACIÓN DE OPERACIONES (SweetAlert2)
    // ========================================================================
    document.querySelectorAll('.js-form-confirm').forEach(form => {
        form.addEventListener('submit', function(e) {
            // EVITAR DOBLE SUBMIT O SUBMIT SI HAY ERRORES PREVIOS
            if (e.defaultPrevented) return; 

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
                    if (btn) btn.disabled = true;
                    this.submit();
                }
            });
        });
    });

    document.querySelectorAll('.js-switch-estado-cuenta').forEach(sw => {
        sw.addEventListener('change', function () {
            const form = this.closest('form');
            if (form) form.submit();
        });
    });

    if (modalCuentaEl && formCuenta) {
        modalCuentaEl.addEventListener('hidden.bs.modal', () => {
            const esEdicion = tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true';
            if (!esEdicion) {
                formCuenta.reset();
                if (tipoCuentaSelect) tipoCuentaSelect.dispatchEvent(new Event('change'));
            }
        });
    }

    // ========================================================================
    // 3. APOYO FORMULARIO DE PRÉSTAMOS (CATÁLOGO DE ENTIDADES)
    // ========================================================================
    const prestamoEntidadCatalogo = document.getElementById('prestamoEntidadCatalogo');
    const prestamoEntidadNombre = document.getElementById('prestamoEntidadNombre');
    if (prestamoEntidadCatalogo && prestamoEntidadNombre) {
        prestamoEntidadCatalogo.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const nombre = selected ? (selected.value || '').trim() : '';
            if (nombre !== '') {
                prestamoEntidadNombre.value = nombre;
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

            syncNaturalezaCobro();
        });

        const naturalezaCobro = document.getElementById('cobroNaturaleza');
        const grupoCapitalCobro = document.getElementById('grupoCobroCapital');
        const grupoInteresCobro = document.getElementById('grupoCobroInteres');
        const inputCapitalCobro = document.getElementById('cobroMontoCapital');
        const inputInteresCobro = document.getElementById('cobroMontoInteres');
        const inputMontoTotalCobro = document.getElementById('cobroMonto');

        function syncNaturalezaCobro() {
            if (!naturalezaCobro) return;
            const val = String(naturalezaCobro.value || 'DOCUMENTO');
            
            const mostrarCapital = val === 'CAPITAL' || val === 'MIXTO';
            const mostrarInteres = val === 'INTERES' || val === 'MIXTO';
            
            if (grupoCapitalCobro) grupoCapitalCobro.classList.toggle('d-none', !mostrarCapital);
            if (grupoInteresCobro) grupoInteresCobro.classList.toggle('d-none', !mostrarInteres);
            if (inputCapitalCobro) inputCapitalCobro.required = mostrarCapital;
            if (inputInteresCobro) inputInteresCobro.required = mostrarInteres;

            if (!mostrarCapital && inputCapitalCobro) inputCapitalCobro.value = '0.00';
            if (!mostrarInteres && inputInteresCobro) inputInteresCobro.value = '0.00';
            
            if (inputMontoTotalCobro) {
                const saldoPendiente = document.getElementById('cobroSaldo') ? document.getElementById('cobroSaldo').value : '0';
                if (val === 'MIXTO' || val === 'INTERES') {
                    inputMontoTotalCobro.removeAttribute('max');
                } else {
                    inputMontoTotalCobro.setAttribute('max', saldoPendiente);
                }
            }
            validarMontosMixtosCobro();
        }
        
        function validarMontosMixtosCobro() {
            if (!naturalezaCobro || !inputMontoTotalCobro || !inputCapitalCobro || !inputInteresCobro) return;
            
            if (naturalezaCobro.value === 'MIXTO') {
                const total = parseFloat(inputMontoTotalCobro.value) || 0;
                const capital = parseFloat(inputCapitalCobro.value) || 0;
                const interes = parseFloat(inputInteresCobro.value) || 0;
                
                if ((capital + interes).toFixed(2) !== total.toFixed(2)) {
                    inputCapitalCobro.classList.add('is-invalid');
                    inputInteresCobro.classList.add('is-invalid');
                } else {
                    inputCapitalCobro.classList.remove('is-invalid');
                    inputInteresCobro.classList.remove('is-invalid');
                }
            } else {
                inputCapitalCobro.classList.remove('is-invalid');
                inputInteresCobro.classList.remove('is-invalid');
            }
        }

        if (naturalezaCobro) {
            naturalezaCobro.addEventListener('change', syncNaturalezaCobro);
        }
        
        [inputMontoTotalCobro, inputCapitalCobro, inputInteresCobro].forEach(input => {
            if (input) input.addEventListener('input', validarMontosMixtosCobro);
        });

        const formCobro = document.getElementById('formCobro');
        if (formCobro) {
            formCobro.addEventListener('submit', function(e) {
                if (naturalezaCobro && naturalezaCobro.value === 'MIXTO') {
                    const total = parseFloat(inputMontoTotalCobro.value) || 0;
                    const capital = parseFloat(inputCapitalCobro.value) || 0;
                    const interes = parseFloat(inputInteresCobro.value) || 0;
                    
                    if ((capital + interes).toFixed(2) !== total.toFixed(2)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Montos descuadrados',
                                text: `La suma de Capital (${capital.toFixed(2)}) + Mora (${interes.toFixed(2)}) debe ser igual al Monto Total (${total.toFixed(2)}).`
                            });
                        }
                    }
                }
            });
        }

        modalCobroEl.addEventListener('hidden.bs.modal', function () {
            const form = modalCobroEl.querySelector('form');
            if (form) form.reset();
            if(inputMontoTotalCobro) inputMontoTotalCobro.classList.remove('is-invalid');
            if(inputCapitalCobro) inputCapitalCobro.classList.remove('is-invalid');
            if(inputInteresCobro) inputInteresCobro.classList.remove('is-invalid');
            syncNaturalezaCobro();
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
            
            syncNaturalezaPago();
        });

        const naturalezaPago = document.getElementById('pagoNaturaleza');
        const grupoCapital = document.getElementById('grupoPagoCapital');
        const grupoInteres = document.getElementById('grupoPagoInteres');
        const inputCapital = document.getElementById('pagoMontoCapital');
        const inputInteres = document.getElementById('pagoMontoInteres');
        
        const grupoCentroCosto = document.getElementById('grupoCentroCostoInteres');
        const inputCentroCosto = document.getElementById('pagoCentroCosto');
        const inputMontoTotal = document.getElementById('pagoMonto');

        function syncNaturalezaPago() {
            if (!naturalezaPago) return;
            const val = String(naturalezaPago.value || 'DOCUMENTO');
            
            const mostrarCapital = val === 'CAPITAL' || val === 'MIXTO';
            const mostrarInteres = val === 'INTERES' || val === 'MIXTO';
            
            if (grupoCapital) grupoCapital.classList.toggle('d-none', !mostrarCapital);
            if (grupoInteres) grupoInteres.classList.toggle('d-none', !mostrarInteres);
            if (inputCapital) inputCapital.required = mostrarCapital;
            if (inputInteres) inputInteres.required = mostrarInteres;
            
            if (grupoCentroCosto) {
                grupoCentroCosto.classList.toggle('d-none', !mostrarInteres);
                if (inputCentroCosto) inputCentroCosto.required = mostrarInteres;
            }

            if (!mostrarCapital && inputCapital) inputCapital.value = '0.00';
            if (!mostrarInteres && inputInteres) inputInteres.value = '0.00';
            if (!mostrarInteres && inputCentroCosto) inputCentroCosto.value = '';
            
            if (inputMontoTotal) {
                const saldoPendiente = document.getElementById('pagoSaldo') ? document.getElementById('pagoSaldo').value : '0';
                if (val === 'MIXTO' || val === 'INTERES') {
                    inputMontoTotal.removeAttribute('max'); 
                } else {
                    inputMontoTotal.setAttribute('max', saldoPendiente); 
                }
            }
            validarMontosMixtos();
        }
        
        function validarMontosMixtos() {
            if (!naturalezaPago || !inputMontoTotal || !inputCapital || !inputInteres) return;
            
            if (naturalezaPago.value === 'MIXTO') {
                const total = parseFloat(inputMontoTotal.value) || 0;
                const capital = parseFloat(inputCapital.value) || 0;
                const interes = parseFloat(inputInteres.value) || 0;
                
                if ((capital + interes).toFixed(2) !== total.toFixed(2)) {
                    inputCapital.classList.add('is-invalid');
                    inputInteres.classList.add('is-invalid');
                } else {
                    inputCapital.classList.remove('is-invalid');
                    inputInteres.classList.remove('is-invalid');
                }
            } else {
                inputCapital.classList.remove('is-invalid');
                inputInteres.classList.remove('is-invalid');
            }
        }

        if (naturalezaPago) {
            naturalezaPago.addEventListener('change', syncNaturalezaPago);
        }
        
        [inputMontoTotal, inputCapital, inputInteres].forEach(input => {
            if (input) input.addEventListener('input', validarMontosMixtos);
        });

        const formPagoRegular = document.getElementById('formPago');
        if (formPagoRegular) {
            formPagoRegular.addEventListener('submit', function(e) {
                if (naturalezaPago && naturalezaPago.value === 'MIXTO') {
                    const total = parseFloat(inputMontoTotal.value) || 0;
                    const capital = parseFloat(inputCapital.value) || 0;
                    const interes = parseFloat(inputInteres.value) || 0;
                    
                    if ((capital + interes).toFixed(2) !== total.toFixed(2)) {
                        e.preventDefault(); 
                        e.stopImmediatePropagation();
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Montos descuadrados',
                                text: `La suma de Capital (${capital.toFixed(2)}) + Interés (${interes.toFixed(2)}) debe ser igual al Monto Total (${total.toFixed(2)}).`
                            });
                        }
                    }
                }
            });
        }

        modalPagoEl.addEventListener('hidden.bs.modal', function () {
            const formPago = modalPagoEl.querySelector('form');
            if (formPago) formPago.reset();
            
            const txtSaldo = document.getElementById('textoSaldoDisponible');
            if(txtSaldo) txtSaldo.innerHTML = '';
            
            if(inputMontoTotal) inputMontoTotal.classList.remove('is-invalid');
            if(inputCapital) inputCapital.classList.remove('is-invalid');
            if(inputInteres) inputInteres.classList.remove('is-invalid');
            
            syncNaturalezaPago();
        });
    }

    // ========================================================================
    // 6. LÓGICA DE SALDOS Y LÍMITES (PAGO ESPECÍFICO Y MANUAL)
    // ========================================================================
    
    const selectCuenta = document.getElementById('selectCuentaOrigen');
    const textoSaldo = document.getElementById('textoSaldoDisponible');
    const inputMontoCxp = document.getElementById('pagoMonto');
    const inputPendienteCxp = document.getElementById('pagoSaldo');

    if(selectCuenta && inputMontoCxp && inputPendienteCxp && textoSaldo) {
        function actualizarMaximoEspecifico() {
            const opt = selectCuenta.options[selectCuenta.selectedIndex];
            const pendiente = parseFloat(inputPendienteCxp.value) || 0;

            if(!opt || opt.value === "") {
                textoSaldo.innerHTML = "";
                inputMontoCxp.setAttribute('max', pendiente);
                return;
            }
            
            const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
            textoSaldo.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: $${saldoCuenta.toFixed(2)}`;
            
            // Limitamos solo si no es mixto/interés
            const natPago = document.getElementById('pagoNaturaleza');
            if (!natPago || (natPago.value !== 'MIXTO' && natPago.value !== 'INTERES')) {
                let maximo = Math.min(saldoCuenta, pendiente);
                if(maximo < 0) maximo = 0;
                inputMontoCxp.setAttribute('max', maximo);
                
                if(parseFloat(inputMontoCxp.value) > maximo) {
                    inputMontoCxp.value = maximo.toFixed(2);
                }
            } else {
                // Si es mixto, el banco solo nos limita lo que hay en caja
                let maximo = saldoCuenta < 0 ? 0 : saldoCuenta;
                inputMontoCxp.setAttribute('max', maximo);
            }
        }

        selectCuenta.addEventListener('change', actualizarMaximoEspecifico);

        inputMontoCxp.addEventListener('input', function() {
            const maxVal = parseFloat(this.getAttribute('max'));
            if(!isNaN(maxVal) && parseFloat(this.value) > maxVal) {
                this.value = maxVal.toFixed(2);
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        inputMontoCxp.addEventListener('focus', actualizarMaximoEspecifico);
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

        if(modalPagoManualEl) {
            modalPagoManualEl.addEventListener('hidden.bs.modal', function () {
                const formPagoManual = modalPagoManualEl.querySelector('form');
                if (formPagoManual) formPagoManual.reset();
                textoSaldoManual.innerHTML = '';
                inputMontoManual.classList.remove('is-invalid');
            });
        }
    }

    // ========================================================================
    // 7. SALDOS INICIALES (TERCERO CON BÚSQUEDA AJAX)
    // ========================================================================
    const formSaldoInicial = document.getElementById('formSaldoInicial');
    if (formSaldoInicial) {
        const terceroSelect = document.getElementById('saldoInicialTercero');
        const fechaEmision = document.getElementById('saldoInicialFechaEmision');
        const fechaVencimiento = document.getElementById('saldoInicialFechaVencimiento');
        const radiosTipo = Array.from(document.querySelectorAll('input[name="tipo_deuda"]'));
        const helpTercero = document.getElementById('saldoInicialTerceroHelp');
        const tercerosUrl = formSaldoInicial.getAttribute('data-url-terceros') || '';

        const hoy = new Date().toISOString().slice(0, 10);
        if (fechaEmision && !fechaEmision.value) fechaEmision.value = hoy;
        if (fechaVencimiento && !fechaVencimiento.value) fechaVencimiento.value = hoy;

        const obtenerTipo = () => {
            const selected = radiosTipo.find(r => r.checked);
            return selected ? selected.value : 'CLIENTE';
        };

        const resetSelect = () => {
            if (!terceroSelect) return;
            terceroSelect.innerHTML = '<option value="">Escriba para buscar...</option>';
        };

        const cargarTerceros = async (q = '') => {
            if (!terceroSelect) return;
            const tipo = obtenerTipo();
            const url = `${tercerosUrl}&tipo=${encodeURIComponent(tipo)}&q=${encodeURIComponent(q)}`;

            try {
                terceroSelect.disabled = true;
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const items = Array.isArray(data.items) ? data.items : [];

                terceroSelect.innerHTML = '<option value="">Seleccione...</option>';
                items.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = String(item.id || '');
                    opt.textContent = String(item.nombre_completo || '');
                    terceroSelect.appendChild(opt);
                });

                if (helpTercero) {
                    helpTercero.textContent = tipo === 'CLIENTE'
                        ? 'Mostrando clientes activos.'
                        : 'Mostrando proveedores activos.';
                }
            } catch (e) {
                resetSelect();
                if (helpTercero) helpTercero.textContent = 'No se pudo cargar la búsqueda. Recargue la página.';
            } finally {
                terceroSelect.disabled = false;
            }
        };

        radiosTipo.forEach(r => r.addEventListener('change', () => {
            resetSelect();
            cargarTerceros('');
        }));

        let debounceTimer = null;
        if (terceroSelect) {
            terceroSelect.addEventListener('mousedown', () => {
                cargarTerceros('');
            });
        }

        // Campo de búsqueda rápido usando prompt para mantener UI Kit sin dependencias externas.
        if (helpTercero) {
            helpTercero.addEventListener('click', () => {
                const termino = window.prompt('Buscar tercero por nombre:', '') || '';
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(() => cargarTerceros(termino.trim()), 120);
            });
            helpTercero.style.cursor = 'pointer';
            helpTercero.title = 'Click para buscar por nombre';
        }

        cargarTerceros('');
    }
});
