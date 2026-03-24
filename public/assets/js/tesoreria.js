(function arrancarTesoreria(intentos) {
    'use strict';
    if (intentos === undefined) intentos = 20;

    // 1. Buscamos el contenedor principal
    const tesoreriaApp =
        document.getElementById('tesoreriaSaldosInicialesApp') ||
        document.getElementById('tesoreriaCuentasApp') ||
        document.getElementById('tesoreriaCxcApp') ||
        document.getElementById('tesoreriaCxpApp') ||
        document.getElementById('tesoreriaMovimientosApp') ||
        document.getElementById('tesoreriaPrestamosApp');
    
    // 2. EL TRUCO SPA: Si el HTML aún no ha sido inyectado, esperamos 50ms y reintentamos.
    if (!tesoreriaApp) {
        if (intentos > 0) {
            setTimeout(() => arrancarTesoreria(intentos - 1), 50);
        }
        return; // Detenemos ESTE intento, pero el setTimeout lanzará uno nuevo.
    }

    // ========================================================================
    // --- A PARTIR DE AQUÍ ESTAMOS 100% SEGUROS DE QUE EL HTML YA EXISTE ---
    // ========================================================================

    // INICIALIZACIÓN DE LA TABLA DE CUENTAS
    const tablaCuentas = document.getElementById('tablaCuentas');
    if (tablaCuentas && window.ERPTable && window.ERPTable.createTableManager) {
        if (!tablaCuentas.dataset.iniciada) { // Evitar doble inicialización
            window.ERPTable.createTableManager({
                tableSelector: tablaCuentas,
                rowsSelector: 'tbody tr', 
                rowsPerPage: 25,
                paginationControls: '#cuentasPaginationControls',
                paginationInfo: '#cuentasPaginationInfo',
                infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} resultados`,
                emptyText: 'Mostrando 0-0 de 0 resultados'
            }).init();
            tablaCuentas.dataset.iniciada = 'true';
        }
    }

    // ========================================================================
    // 0. INICIALIZACIÓN GLOBAL
    // ========================================================================
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        if (typeof bootstrap !== 'undefined') {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        }
    });

    const modalCuentaEl = document.getElementById('modalCuentaTesoreria');
    const formCuenta = document.getElementById('formCuentaTesoreria');
    
    if (tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true' && modalCuentaEl) {
        if (typeof bootstrap !== 'undefined') {
            // Le damos 150ms al DOM para que termine de procesarse antes de llamar a Bootstrap
            setTimeout(() => {
                console.log("Abriendo modal de edición..."); // Para que lo veas en la consola
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalCuentaEl);
                modalInstance.show();
            }, 150);
        }
    }

    if (tesoreriaApp) {
        const params = new URLSearchParams(window.location.search);
        if (params.get('ok') === '1') {
            const action = (params.get('action') || '').toLowerCase();
            const isUpdate = action === 'updated';
            const isDelete = action === 'deleted';
            const isTransfer = action === 'transfer';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: isTransfer
                        ? 'Transferencia registrada'
                        : (isDelete ? 'Cuenta eliminada' : (isUpdate ? 'Cuenta actualizada' : 'Cuenta guardada')),
                    text: isTransfer
                        ? 'La transferencia interna se registró correctamente.'
                        : (isDelete
                        ? 'La cuenta se eliminó correctamente.'
                        : (isUpdate ? 'La cuenta se actualizó correctamente.' : 'La cuenta se guardó correctamente.')),
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
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        newForm.addEventListener('submit', function(e) {
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
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        newForm.addEventListener('submit', function(e) {
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
        const newSw = sw.cloneNode(true);
        sw.parentNode.replaceChild(newSw, sw);
        newSw.addEventListener('change', function () {
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
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-open-cobro');
        if (!btn) return;
        
        const idOrigen = btn.getAttribute('data-id-origen') || '';
        const moneda = (btn.getAttribute('data-moneda') || '').trim();
        const saldo = parseFloat(btn.getAttribute('data-saldo') || 0).toFixed(2);
        
        const inputIdOrigen = document.getElementById('cobroIdOrigen');
        const inputMoneda = document.getElementById('cobroMoneda');
        const inputSaldo = document.getElementById('cobroSaldo');
        const inputMonto = document.getElementById('cobroMonto');

        if (inputIdOrigen) inputIdOrigen.value = idOrigen;
        if (inputMoneda) inputMoneda.value = moneda;
        if (inputSaldo) inputSaldo.value = saldo;
        
        if (inputMonto) {
            inputMonto.value = saldo;
            inputMonto.setAttribute('max', saldo);
        }

        const selectCuentaDestino = document.getElementById('selectCuentaDestino');
        if (selectCuentaDestino && moneda) {
            const opciones = selectCuentaDestino.querySelectorAll('option');
            let primeraOpcionValida = null;

            opciones.forEach(opcion => {
                if (opcion.value === "") {
                    opcion.selected = true;
                    opcion.style.display = '';
                    opcion.disabled = false;
                    return;
                }

                const tieneAdvertencia = opcion.dataset.tieneAdvertencia === '1';
                if (tieneAdvertencia) {
                    opcion.style.display = 'none';
                    opcion.disabled = true;
                    return;
                }
                
                if (opcion.textContent.toUpperCase().includes(`(${moneda.toUpperCase()})`)) {
                    opcion.style.display = '';
                    opcion.disabled = false;
                    if (!primeraOpcionValida) primeraOpcionValida = opcion.value;
                } else {
                    opcion.style.display = 'none';
                    opcion.disabled = true;
                }
            });

            if (primeraOpcionValida) {
                selectCuentaDestino.value = primeraOpcionValida;
            } else {
                selectCuentaDestino.value = "";
            }
        }

        const naturalezaSelect = document.getElementById('cobroNaturaleza');
        if (naturalezaSelect) {
            naturalezaSelect.dispatchEvent(new Event('change'));
        }
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

    const modalCobroEl = document.getElementById('modalCobro'); 
    if (modalCobroEl) {
        modalCobroEl.addEventListener('hidden.bs.modal', function () {
            const form = modalCobroEl.querySelector('form');
            if (form) form.reset();
            if(inputMontoTotalCobro) inputMontoTotalCobro.classList.remove('is-invalid');
            if(inputCapitalCobro) inputCapitalCobro.classList.remove('is-invalid');
            if(inputInteresCobro) inputInteresCobro.classList.remove('is-invalid');
            
            const selectCuentaDestino = document.getElementById('selectCuentaDestino');
            if (selectCuentaDestino) {
                const opciones = selectCuentaDestino.querySelectorAll('option');
                opciones.forEach(opcion => {
                    opcion.style.display = '';
                    opcion.disabled = false;
                });
                selectCuentaDestino.value = ""; 
            }

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
            
            // --- NUEVO CÓDIGO: Filtro de cuentas para CXP ---
            const selectCuentaOrigen = document.getElementById('selectCuentaOrigen');
            if (selectCuentaOrigen && moneda) {
                const opciones = selectCuentaOrigen.querySelectorAll('option');
                let primeraOpcionValida = null;

                opciones.forEach(opcion => {
                    if (opcion.value === "") {
                        opcion.selected = true;
                        opcion.style.display = '';
                        opcion.disabled = false;
                        return;
                    }

                    if (opcion.textContent.toUpperCase().includes(`(${moneda.toUpperCase()})`)) {
                        opcion.style.display = '';
                        opcion.disabled = false;
                        if (!primeraOpcionValida) primeraOpcionValida = opcion.value;
                    } else {
                        opcion.style.display = 'none';
                        opcion.disabled = true;
                    }
                });

                if (primeraOpcionValida) {
                    selectCuentaOrigen.value = primeraOpcionValida;
                    selectCuentaOrigen.dispatchEvent(new Event('change')); 
                } else {
                    selectCuentaOrigen.value = "";
                }
            }
            
            syncNaturalezaPago();
        });

        // Funciones auxiliares restauradas
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
            
            const selectCuentaOrigen = document.getElementById('selectCuentaOrigen');
            if (selectCuentaOrigen) {
                const opciones = selectCuentaOrigen.querySelectorAll('option');
                opciones.forEach(opcion => {
                    opcion.style.display = '';
                    opcion.disabled = false;
                });
                selectCuentaOrigen.value = ""; 
            }

            syncNaturalezaPago();
        });
    } // FIN DE SECCION 5 RESTAURADO

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
                
                const opciones = selectCuentaManual.querySelectorAll('option');
                opciones.forEach(op => {
                    op.style.display = '';
                    op.disabled = false;
                });
            });
        }
    }

    // --- Lógica de filtrado para PAGO MANUAL ---
    const selectMonedaManual = document.querySelector('#modalPagoManual select[name="moneda"]');
    if (selectMonedaManual && selectCuentaManual) {
        selectMonedaManual.addEventListener('change', function() {
            const moneda = this.value;
            const opciones = selectCuentaManual.querySelectorAll('option');
            
            opciones.forEach(opcion => {
                if (opcion.value === "") {
                    opcion.selected = true;
                    opcion.style.display = '';
                    opcion.disabled = false;
                    return;
                }
                
                if (moneda && opcion.textContent.includes(`(${moneda})`)) {
                    opcion.style.display = '';
                    opcion.disabled = false;
                } else if (moneda) {
                    opcion.style.display = 'none';
                    opcion.disabled = true;
                } else {
                    opcion.style.display = '';
                    opcion.disabled = false;
                }
            });
            
            selectCuentaManual.dispatchEvent(new Event('change'));
        });
    }

    // ========================================================================
    // 7. ESTADO DE CUENTA (NUEVO FLUJO: SALDOS INICIALES, ITEMS Y AMORTIZACIONES)
    // ========================================================================
    const formSaldoInicial = document.getElementById('formSaldoInicial');
    if (formSaldoInicial) {

        let totalAmortizacionesRemotas = 0;
        let totalAmortizacionesLocales = 0;

        const terceroSelectEl = document.getElementById('saldoInicialTercero');
        const radiosTipo = Array.from(document.querySelectorAll('input[name="tipo_deuda"]'));
        const labelTipoCliente = document.getElementById('labelTipoCliente');
        const labelTipoProveedor = document.getElementById('labelTipoProveedor');
        const btnGuardar = document.getElementById('btnGuardarCuentaTercero');
        const tercerosUrl = formSaldoInicial.getAttribute('data-url-terceros') || '';
        
        const verificarCuentaUrl = '?ruta=tesoreria/ajax_verificar_cuenta_tercero';

        if (terceroSelectEl && typeof TomSelect !== 'undefined') {
            if (terceroSelectEl.tomselect) {
                terceroSelectEl.tomselect.destroy();
            }

            const getTipoSeleccionado = () => {
                const radioChecked = document.querySelector('input[name="tipo_deuda"]:checked');
                return radioChecked ? radioChecked.value : 'CLIENTE';
            };

            const getPlaceholderByTipo = (tipo) => tipo === 'PROVEEDOR'
                ? 'Buscar proveedor activo...'
                : 'Buscar cliente o distribuidor activo...';

            const tsTerceros = new TomSelect(terceroSelectEl, {
                valueField: 'id',
                labelField: 'nombre_completo',
                searchField: ['nombre_completo'],
                placeholder: getPlaceholderByTipo(getTipoSeleccionado()),
                preload: true,
                controlInput: '<input type="text" class="form-control shadow-none" autocomplete="off">',
                
                load: function(query, callback) {
                    const tipo = getTipoSeleccionado();
                    const separador = tercerosUrl.includes('?') ? '&' : '?';
                    const url = `${tercerosUrl}${separador}tipo=${encodeURIComponent(tipo)}&q=${encodeURIComponent(query)}`;

                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(res => res.json())
                        .then(json => callback(json.items || []))
                        .catch(() => callback());
                },
                render: {
                    option: function(item, escape) {
                        return `<div class="py-2 px-3">
                                    <span class="fw-bold text-dark d-block">${escape(item.nombre_completo)}</span>
                                </div>`;
                    },
                    item: function(item, escape) {
                        return `<div class="fw-bold text-dark">${escape(item.nombre_completo)}</div>`;
                    }
                },
                onChange: function(value) {
                    if (!value) {
                        desbloquearNaturaleza();
                        resetearBotonGuardar();
                        totalAmortizacionesRemotas = 0;
                        totalAmortizacionesLocales = 0;
                        limpiarDetalleCompras();
                        renderAmortizaciones([]);
                        calcularSaldosReales();
                        return;
                    }

                    const tipo = getTipoSeleccionado();
                    fetch(`${verificarCuentaUrl}&id=${value}&tipo=${tipo}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(res => res.json())
                        .then(async data => {
                            if (data.ok && data.tiene_cuenta) {
                                bloquearNaturaleza();
                                
                                if (btnGuardar) {
                                    btnGuardar.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Actualizar Saldo Inicial';
                                    btnGuardar.classList.replace('btn-primary', 'btn-success');
                                }

                                totalAmortizacionesRemotas = data.total_amortizaciones || 0;
                                totalAmortizacionesLocales = 0;
                                await cargarDetalleGuardado(data.items_guardados || []);
                                renderAmortizaciones(data.amortizaciones || []);
                                calcularSaldosReales();
                                
                            } else {
                                desbloquearNaturaleza();
                                resetearBotonGuardar();
                                totalAmortizacionesRemotas = 0;
                                totalAmortizacionesLocales = 0;
                                limpiarDetalleCompras();
                                renderAmortizaciones([]);
                                calcularSaldosReales();
                            }
                        })
                        .catch(err => console.error('Error al verificar cuenta:', err));
                }
            });

            function bloquearNaturaleza() {
                radiosTipo.forEach(radio => radio.disabled = true);
                if (labelTipoCliente) labelTipoCliente.classList.add('opacity-50');
                if (labelTipoProveedor) labelTipoProveedor.classList.add('opacity-50');
            }

            function desbloquearNaturaleza() {
                radiosTipo.forEach(radio => radio.disabled = false);
                if (labelTipoCliente) labelTipoCliente.classList.remove('opacity-50');
                if (labelTipoProveedor) labelTipoProveedor.classList.remove('opacity-50');
            }

            function resetearBotonGuardar() {
                if (btnGuardar) {
                    btnGuardar.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Guardar Saldo Inicial';
                    btnGuardar.classList.replace('btn-success', 'btn-primary');
                }
            }

            const recargarOpcionesTerceros = (tipo) => {
                const separador = tercerosUrl.includes('?') ? '&' : '?';
                const url = `${tercerosUrl}${separador}tipo=${encodeURIComponent(tipo)}&q=`;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.json())
                    .then(json => {
                        const items = json.items || [];
                        tsTerceros.clearOptions();
                        tsTerceros.addOptions(items);
                        tsTerceros.refreshOptions(false);
                    })
                    .catch(() => {
                        tsTerceros.clearOptions();
                        tsTerceros.refreshOptions(false);
                    });
            };

            const helpTercero = document.getElementById('saldoInicialTerceroHelp');
            radiosTipo.forEach(r => {
                r.addEventListener('change', () => {
                    tsTerceros.clear(true);
                    tsTerceros.loadedSearches = {};
                    tsTerceros.settings.placeholder = getPlaceholderByTipo(r.value);
                    if (tsTerceros.control_input) {
                        tsTerceros.control_input.placeholder = tsTerceros.settings.placeholder;
                    } 
                    tsTerceros.clearOptions();
                    tsTerceros.loadedSearches = {};
                    tsTerceros.load('');
                    
                    if (helpTercero) {
                        helpTercero.innerHTML = r.value === 'CLIENTE'
                            ? '<i class="bi bi-person-lines-fill me-1"></i>Buscando en catálogo de Clientes y Distribuidores.'
                            : '<i class="bi bi-shop me-1"></i>Buscando en catálogo de Proveedores.';
                    }
                    recargarOpcionesTerceros(r.value);
                });
            });
        }

        const itemsSelectEl = document.getElementById('buscadorItemsSaldo');
        const itemsUrl = formSaldoInicial.getAttribute('data-url-items') || '';
        const unidadesItemUrl = formSaldoInicial.getAttribute('data-url-item-unidades') || '';
        const btnAgregarItemDetalle = document.getElementById('btnAgregarItemDetalle');
        const fechaIngresoInput = formSaldoInicial.querySelector('input[name="fecha_emision"]');
        const inputCantidadAgregar = document.getElementById('saldoDetalleCantidad');
        const selectUnidadAgregar = document.getElementById('saldoDetalleUnidad');
        const inputSubtotalAgregar = document.getElementById('saldoDetalleSubtotal');
        const cacheUnidades = new Map();

        async function obtenerUnidadesItemTesoreria(idItem) {
            if (!idItem || !unidadesItemUrl) return [];
            if (cacheUnidades.has(idItem)) return cacheUnidades.get(idItem);
            const separador = unidadesItemUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${unidadesItemUrl}${separador}id_item=${encodeURIComponent(idItem)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data?.mensaje || 'No se pudieron cargar unidades de conversión.');
            }
            const unidades = Array.isArray(data.items) ? data.items : [];
            cacheUnidades.set(idItem, unidades);
            return unidades;
        }
        
        let tsItems = null;
        let itemSeleccionadoTemporal = null; 

        function getOpcionesUnidadesHtml(unidades = []) {
            return ['<option value="">Base</option>']
                .concat(unidades.map(unidad => {
                    const factor = parseFloat(unidad.factor_conversion || 1);
                    const nombre = unidad.nombre || 'Unidad';
                    return `<option value="${unidad.id}" data-factor="${factor}" data-nombre="${nombre}">${nombre} (x${factor.toFixed(4)})</option>`;
                }))
                .join('');
        }

        async function refrescarUnidadesEnPanel(item) {
            if (!selectUnidadAgregar) return;
            selectUnidadAgregar.innerHTML = '<option value="">Base</option>';
            if (!item?.id) return;
            try {
                const unidades = await obtenerUnidadesItemTesoreria(Number(item.id));
                selectUnidadAgregar.innerHTML = getOpcionesUnidadesHtml(unidades);
            } catch (error) {
                console.warn('No se pudieron cargar unidades para panel de agregado:', error);
            }
        }

        if (itemsSelectEl && typeof TomSelect !== 'undefined') {
            if (itemsSelectEl.tomselect) {
                itemsSelectEl.tomselect.destroy();
            }

            tsItems = new TomSelect(itemsSelectEl, {
                valueField: 'id',
                labelField: 'nombre',
                searchField: ['nombre', 'sku', 'descripcion'],
                placeholder: '🔍 Busque un producto por nombre o código...',
                preload: 'focus',
                hideSelected: false,
                controlInput: '<input type="text" class="form-control shadow-none" autocomplete="off">',
                
                load: function(query, callback) {
                    const separador = itemsUrl.includes('?') ? '&' : '?';
                    const url = `${itemsUrl}${separador}q=${encodeURIComponent(query)}`;

                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(res => {
                            if (!res.ok) {
                                throw new Error(`HTTP error! status: ${res.status}`);
                            }
                            return res.json();
                        })
                        .then(json => {
                            callback(json.items || []);
                        })
                        .catch(err => {
                            console.error('Error al cargar ítems:', err);
                            callback();
                        });
                },
                render: {
                    option: function(item, escape) {
                        return `<div class="py-2 px-3 border-bottom">
                                    <span class="badge bg-secondary me-2">${escape(item.sku || 'N/A')}</span>
                                    <span class="fw-bold text-dark">${escape(item.nombre)}</span>
                                    <small class="d-block text-muted mt-1">Precio ref: S/ ${parseFloat(item.precio_venta||0).toFixed(2)} / ${escape(item.unidad_base||'')}</small>
                                </div>`;
                    },
                    no_results: function(data, escape) {
                        return '<div class="no-results py-2 px-3 text-muted">Sin coincidencias para tu búsqueda.</div>';
                    }
                },
                onChange: function(value) {
                    if (!value) {
                        itemSeleccionadoTemporal = null;
                        if (inputSubtotalAgregar) inputSubtotalAgregar.value = '0.00';
                        if (inputCantidadAgregar) inputCantidadAgregar.value = '1';
                        if (selectUnidadAgregar) selectUnidadAgregar.innerHTML = '<option value="">Base</option>';
                        return;
                    }
                    itemSeleccionadoTemporal = this.options[value];
                    if (inputSubtotalAgregar) {
                        inputSubtotalAgregar.value = parseFloat(itemSeleccionadoTemporal?.precio_venta || 0).toFixed(2);
                    }
                    refrescarUnidadesEnPanel(itemSeleccionadoTemporal);
                }
            });
        }

        const tbody = document.querySelector('#tablaDetalleSaldos tbody');
        const filaVacia = document.getElementById('filaVaciaMensaje');
        const inputMontoSaldos = document.getElementById('saldoInicialMontoManual');
        const tbodyAmortizaciones = document.querySelector('#tablaAmortizaciones tbody');
        const filaVaciaAmortizaciones = document.getElementById('filaVaciaAmortizaciones');
        const modalEditarDetalleEl = document.getElementById('modalEditarDetalleCompra');
        const formEditarDetalleCompra = document.getElementById('formEditarDetalleCompra');
        const detalleEditRowIndex = document.getElementById('detalleEditRowIndex');
        const detalleEditFecha = document.getElementById('detalleEditFecha');
        const detalleEditCantidad = document.getElementById('detalleEditCantidad');
        const detalleEditUnidad = document.getElementById('detalleEditUnidad');
        const detalleEditSubtotal = document.getElementById('detalleEditSubtotal');
        let modalEditarDetalle = null;
        let detalleRowEnEdicion = null;

        if (btnAgregarItemDetalle) {
            btnAgregarItemDetalle.addEventListener('click', async function() {
                if (!itemSeleccionadoTemporal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'Por favor, busque y seleccione un producto primero.',
                        confirmButtonColor: '#0d6efd'
                    });
                    return;
                }

                const cantidad = Math.max(parseFloat(inputCantidadAgregar?.value || 1), 0.01);
                const subtotal = Math.max(parseFloat(inputSubtotalAgregar?.value || 0), 0);
                const unidadId = selectUnidadAgregar?.value || '';
                const unidadOption = unidadId ? selectUnidadAgregar?.options?.[selectUnidadAgregar.selectedIndex] : null;

                await agregarFilaDetalle(itemSeleccionadoTemporal, {
                    cantidad,
                    subtotal,
                    unidadId,
                    unidadNombre: unidadOption?.dataset?.nombre || '',
                    unidadFactor: unidadOption?.dataset?.factor || '1'
                });
                
                if (tsItems) {
                    tsItems.clear(true);
                }
                itemSeleccionadoTemporal = null;
                if (inputCantidadAgregar) inputCantidadAgregar.value = '1';
                if (inputSubtotalAgregar) inputSubtotalAgregar.value = '0.00';
                if (selectUnidadAgregar) selectUnidadAgregar.innerHTML = '<option value="">Base</option>';
            });
        }

        async function agregarFilaDetalle(item, valoresIniciales = {}) {
            if(filaVacia) filaVacia.style.display = 'none';

            let unidades = [];
            try {
                unidades = await obtenerUnidadesItemTesoreria(Number(item.id));
            } catch (error) {
                console.warn('No se pudieron cargar unidades para tesorería:', error);
            }

            const fechaFila = fechaIngresoInput?.value || new Date().toISOString().slice(0, 10);
            const opcionesUnidades = getOpcionesUnidadesHtml(unidades);
            const cantidadInicial = Math.max(parseFloat(valoresIniciales.cantidad ?? 1), 0.01);
            const subtotalInicial = Math.max(parseFloat(valoresIniciales.subtotal ?? item.precio_venta ?? 0), 0);
            const unidadIdInicial = valoresIniciales.unidadId ? String(valoresIniciales.unidadId) : '';

            const tr = document.createElement('tr');
            tr.classList.add('js-detalle-row');
            tr.innerHTML = `
                <td data-label="Fecha">
                    <input type="hidden" name="detalle_fecha[]" class="js-fecha" value="${fechaFila}">
                    <span class="small fw-semibold js-fecha-label">${fechaFila}</span>
                </td>
                <td data-label="Ítem">
                    <input type="hidden" name="detalle_item_id[]" value="${item.id}">
                    <input type="hidden" name="detalle_item_nombre[]" value="${item.nombre}">
                    <span class="fw-bold text-dark small">${item.nombre}</span>
                </td>
                <td data-label="Cantidad">
                    <input type="hidden" name="detalle_cantidad[]" class="js-cant" value="${cantidadInicial.toFixed(2)}">
                    <span class="small d-block text-end js-cant-label">${cantidadInicial.toFixed(2)}</span>
                </td>
                <td data-label="Unid. conversión">
                    <input type="hidden" name="detalle_item_unidad_id[]" class="js-unidad-id" value="">
                    <span class="small d-block text-end js-unidad-label">Base</span>
                    <input type="hidden" name="detalle_item_unidad_nombre[]" class="js-unidad-nombre" value="">
                    <input type="hidden" name="detalle_item_unidad_factor[]" class="js-unidad-factor" value="1">
                </td>
                <td data-label="Subtotal">
                    <input type="hidden" name="detalle_subtotal[]" class="js-subtotal-input" value="${subtotalInicial.toFixed(2)}">
                    <span class="small d-block text-end js-subtotal-label">${subtotalInicial.toFixed(2)}</span>
                </td>
                <td class="text-center text-md-end">
                    <button type="button" class="btn btn-sm btn-outline-primary border-0 js-edit me-1"><i class="bi bi-pencil-square"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 js-remove"><i class="bi bi-trash3"></i></button>
                </td>
            `;

            if(tbody) tbody.appendChild(tr);
            
            const inCant = tr.querySelector('.js-cant');
            const inSub = tr.querySelector('.js-subtotal-input');
            const inFecha = tr.querySelector('.js-fecha');
            const inUnidadId = tr.querySelector('.js-unidad-id');
            const inUnidadNombre = tr.querySelector('.js-unidad-nombre');
            const inUnidadFactor = tr.querySelector('.js-unidad-factor');
            const labelFecha = tr.querySelector('.js-fecha-label');
            const labelCant = tr.querySelector('.js-cant-label');
            const labelSubtotal = tr.querySelector('.js-subtotal-label');
            const labelUnidad = tr.querySelector('.js-unidad-label');
            const btnDel = tr.querySelector('.js-remove');
            const btnEdit = tr.querySelector('.js-edit');

            const recalcular = () => {
                calcularSaldosReales();
            };

            const selectTmp = document.createElement('select');
            selectTmp.innerHTML = opcionesUnidades;
            selectTmp.value = unidadIdInicial;
            if (selectTmp.value !== unidadIdInicial) {
                selectTmp.value = '';
            }
            const unidadOption = selectTmp.options[selectTmp.selectedIndex] || null;
            inUnidadId.value = selectTmp.value || '';
            inUnidadNombre.value = valoresIniciales.unidadNombre || unidadOption?.dataset?.nombre || '';
            inUnidadFactor.value = valoresIniciales.unidadFactor || unidadOption?.dataset?.factor || '1';
            labelUnidad.textContent = inUnidadNombre.value || 'Base';
            labelCant.textContent = Number(inCant.value || 0).toFixed(2);
            labelSubtotal.textContent = Number(inSub.value || 0).toFixed(2);
            labelFecha.textContent = inFecha.value || '-';
            tr.dataset.unidadesHtml = opcionesUnidades;
            btnEdit?.addEventListener('click', () => abrirModalEditarDetalle(tr));

            if (valoresIniciales.unidadNombre || valoresIniciales.unidadFactor) {
                inUnidadNombre.value = valoresIniciales.unidadNombre || '';
                inUnidadFactor.value = valoresIniciales.unidadFactor || '1';
                labelUnidad.textContent = inUnidadNombre.value || 'Base';
            }
            
            btnDel.addEventListener('click', () => {
                tr.remove();
                calcularSaldosReales();
                if (tbody && tbody.querySelectorAll('tr:not(#filaVaciaMensaje)').length === 0) {
                    if(filaVacia) filaVacia.style.display = '';
                }
            });

            recalcular();
        }

        function abrirModalEditarDetalle(tr) {
            if (!tr || !modalEditarDetalle) return;
            const inFecha = tr.querySelector('.js-fecha');
            const inCant = tr.querySelector('.js-cant');
            const inSub = tr.querySelector('.js-subtotal-input');
            const inUnidadId = tr.querySelector('.js-unidad-id');
            detalleRowEnEdicion = tr;
            if (detalleEditRowIndex) detalleEditRowIndex.value = String(Array.from(tbody?.querySelectorAll('.js-detalle-row') || []).indexOf(tr));
            if (detalleEditFecha) detalleEditFecha.value = inFecha?.value || '';
            if (detalleEditCantidad) detalleEditCantidad.value = Number(inCant?.value || 0).toFixed(2);
            if (detalleEditSubtotal) detalleEditSubtotal.value = Number(inSub?.value || 0).toFixed(2);
            if (detalleEditUnidad) {
                detalleEditUnidad.innerHTML = tr.dataset.unidadesHtml || '<option value="">Base</option>';
                detalleEditUnidad.value = inUnidadId?.value || '';
                if (detalleEditUnidad.value !== (inUnidadId?.value || '')) {
                    detalleEditUnidad.value = '';
                }
            }
            modalEditarDetalle.show();
        }

        function limpiarDetalleCompras() {
            if (!tbody) return;
            tbody.querySelectorAll('tr:not(#filaVaciaMensaje)').forEach(tr => tr.remove());
            if (filaVacia) filaVacia.style.display = '';
        }

        async function cargarDetalleGuardado(items = []) {
            limpiarDetalleCompras();
            if (!Array.isArray(items) || items.length === 0) {
                return;
            }

            for (const item of items) {
                await agregarFilaDetalle({
                    id: item.id_item,
                    nombre: item.nombre || 'Ítem',
                    precio_venta: item.subtotal || item.precio_unitario || 0
                });

                const fila = tbody ? tbody.querySelector('tr:last-child') : null;
                if (!fila) return;
                const inCant = fila.querySelector('.js-cant');
                const inSub = fila.querySelector('.js-subtotal-input');
                const inFecha = fila.querySelector('.js-fecha');
                const inUnidadId = fila.querySelector('.js-unidad-id');
                const labelFecha = fila.querySelector('.js-fecha-label');
                const labelCant = fila.querySelector('.js-cant-label');
                const labelSubtotal = fila.querySelector('.js-subtotal-label');
                if (inCant) inCant.value = parseFloat(item.cantidad || 0).toFixed(2);
                if (inSub) inSub.value = parseFloat(item.subtotal || item.precio_unitario || 0).toFixed(2);
                if (inFecha) inFecha.value = item.fecha || (fechaIngresoInput?.value || '');
                if (labelCant) labelCant.textContent = Number(inCant?.value || 0).toFixed(2);
                if (labelSubtotal) labelSubtotal.textContent = Number(inSub?.value || 0).toFixed(2);
                if (labelFecha) labelFecha.textContent = inFecha?.value || '-';
                if (inUnidadId && item.id_item_unidad) {
                    inUnidadId.value = String(item.id_item_unidad);
                    const selectTmp = document.createElement('select');
                    selectTmp.innerHTML = fila.dataset.unidadesHtml || '';
                    selectTmp.value = String(item.id_item_unidad);
                    const opt = selectTmp.options[selectTmp.selectedIndex];
                    fila.querySelector('.js-unidad-nombre').value = opt?.dataset?.nombre || '';
                    fila.querySelector('.js-unidad-factor').value = opt?.dataset?.factor || '1';
                    const unidadLabel = fila.querySelector('.js-unidad-label');
                    if (unidadLabel) unidadLabel.textContent = opt?.dataset?.nombre || 'Base';
                }
                calcularSaldosReales();
            }
        }

        function renderAmortizaciones(amortizaciones = []) {
            if (!tbodyAmortizaciones) return;

            tbodyAmortizaciones.querySelectorAll('tr:not(#filaVaciaAmortizaciones)').forEach(tr => tr.remove());

            if (!Array.isArray(amortizaciones) || amortizaciones.length === 0) {
                if (filaVaciaAmortizaciones) filaVaciaAmortizaciones.style.display = '';
                return;
            }

            if (filaVaciaAmortizaciones) filaVaciaAmortizaciones.style.display = 'none';

            amortizaciones.forEach(amort => {
                const tr = document.createElement('tr');
                const monto = parseFloat(amort.monto || 0);
                tr.innerHTML = `
                    <td data-label="Fecha">${amort.fecha || '-'}</td>
                    <td data-label="Ref. Pago">${amort.referencia || '-'}</td>
                    <td data-label="Método">${amort.metodo || '-'}</td>
                    <td data-label="Monto" class="text-end fw-bold">${monto.toFixed(2)}</td>
                    <td class="text-center text-md-end">
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 js-edit-amort me-1"><i class="bi bi-pencil-square"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 js-del-amort"><i class="bi bi-trash3"></i></button>
                    </td>
                `;
                tbodyAmortizaciones.appendChild(tr);
            });
        }

        function calcularSaldosReales() {
            let totalCompras = 0;

            if (tbody) {
                tbody.querySelectorAll('tr:not(#filaVaciaMensaje)').forEach(tr => {
                    const subtotal = parseFloat(tr.querySelector('.js-subtotal-input')?.value) || 0;
                    totalCompras += subtotal;
                });
            }

            let saldoReal = totalCompras - (totalAmortizacionesRemotas + totalAmortizacionesLocales);
            
            if (saldoReal < 0) saldoReal = 0;

            if(inputMontoSaldos) {
                inputMontoSaldos.value = saldoReal.toFixed(2);
            }
        }

        const btnRegistrarPagoPrevio = document.getElementById('btnRegistrarPagoPrevio');
        const modalPagoPrevioEl = document.getElementById('modalPagoPrevio');
        const formPagoPrevioLocal = document.getElementById('formPagoPrevioLocal');
        let modalPagoPrevio = null;
        let editandoPagoIndex = null;
        let pagosLocales = [];

        function sincronizarPagosLocales() {
            totalAmortizacionesLocales = pagosLocales.reduce((acc, item) => acc + (parseFloat(item.monto) || 0), 0);
            const inputsViejos = formSaldoInicial.querySelectorAll('input[name^="amortizacion_local_"]');
            inputsViejos.forEach(input => input.remove());

            pagosLocales.forEach((pago, idx) => {
                [['fecha', pago.fecha], ['referencia', pago.referencia], ['metodo', pago.metodo], ['monto', pago.monto]].forEach(([campo, valor]) => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `amortizacion_local_${campo}[${idx}]`;
                    hidden.value = valor || '';
                    formSaldoInicial.appendChild(hidden);
                });
            });

            renderAmortizaciones([
                ...(window.amortizacionesRemotasCache || []),
                ...pagosLocales.map((pago, idx) => ({ ...pago, es_local: 1, _local_index: idx }))
            ]);
            calcularSaldosReales();
        }

        if (btnRegistrarPagoPrevio && modalPagoPrevioEl && typeof bootstrap !== 'undefined') {
            modalPagoPrevio = new bootstrap.Modal(modalPagoPrevioEl);
            btnRegistrarPagoPrevio.addEventListener('click', () => {
                editandoPagoIndex = null;
                if (formPagoPrevioLocal) formPagoPrevioLocal.reset();
                const fechaDefault = fechaIngresoInput?.value || new Date().toISOString().slice(0, 10);
                const inFecha = document.getElementById('pagoPrevioFecha');
                if (inFecha) inFecha.value = fechaDefault;
                modalPagoPrevio.show();
            });
        }

        if (formPagoPrevioLocal) {
            formPagoPrevioLocal.addEventListener('submit', (event) => {
                event.preventDefault();
                const fecha = document.getElementById('pagoPrevioFecha')?.value || '';
                const referencia = (document.getElementById('pagoPrevioReferencia')?.value || '').trim();
                const metodo = (document.getElementById('pagoPrevioMetodo')?.value || '').trim();
                const monto = parseFloat(document.getElementById('pagoPrevioMonto')?.value || 0);
                if (!fecha || monto <= 0) {
                    Swal.fire('Atención', 'Debe ingresar fecha y monto válido.', 'warning');
                    return;
                }
                const data = { fecha, referencia, metodo, monto: Number(monto.toFixed(2)), es_local: 1 };
                if (editandoPagoIndex !== null) {
                    pagosLocales[editandoPagoIndex] = data;
                } else {
                    pagosLocales.push(data);
                }
                editandoPagoIndex = null;
                modalPagoPrevio?.hide();
                sincronizarPagosLocales();
            });
        }

        if (modalEditarDetalleEl && typeof bootstrap !== 'undefined') {
            modalEditarDetalle = new bootstrap.Modal(modalEditarDetalleEl);
        }

        if (formEditarDetalleCompra) {
            formEditarDetalleCompra.addEventListener('submit', (event) => {
                event.preventDefault();
                if (!detalleRowEnEdicion) return;
                const fecha = detalleEditFecha?.value || '';
                const cantidad = Math.max(parseFloat(detalleEditCantidad?.value || 0), 0.01);
                const subtotal = Math.max(parseFloat(detalleEditSubtotal?.value || 0), 0);
                const unidadId = detalleEditUnidad?.value || '';
                if (!fecha) {
                    Swal.fire('Atención', 'Debe ingresar una fecha válida.', 'warning');
                    return;
                }
                const inFecha = detalleRowEnEdicion.querySelector('.js-fecha');
                const inCant = detalleRowEnEdicion.querySelector('.js-cant');
                const inSub = detalleRowEnEdicion.querySelector('.js-subtotal-input');
                const inUnidadId = detalleRowEnEdicion.querySelector('.js-unidad-id');
                const inUnidadNombre = detalleRowEnEdicion.querySelector('.js-unidad-nombre');
                const inUnidadFactor = detalleRowEnEdicion.querySelector('.js-unidad-factor');
                const lbFecha = detalleRowEnEdicion.querySelector('.js-fecha-label');
                const lbCant = detalleRowEnEdicion.querySelector('.js-cant-label');
                const lbSub = detalleRowEnEdicion.querySelector('.js-subtotal-label');
                const lbUnidad = detalleRowEnEdicion.querySelector('.js-unidad-label');
                if (inFecha) inFecha.value = fecha;
                if (inCant) inCant.value = cantidad.toFixed(2);
                if (inSub) inSub.value = subtotal.toFixed(2);
                if (inUnidadId) inUnidadId.value = unidadId;
                const opt = detalleEditUnidad?.options?.[detalleEditUnidad.selectedIndex] || null;
                if (inUnidadNombre) inUnidadNombre.value = opt?.dataset?.nombre || '';
                if (inUnidadFactor) inUnidadFactor.value = opt?.dataset?.factor || '1';
                if (lbFecha) lbFecha.textContent = fecha;
                if (lbCant) lbCant.textContent = cantidad.toFixed(2);
                if (lbSub) lbSub.textContent = subtotal.toFixed(2);
                if (lbUnidad) lbUnidad.textContent = inUnidadNombre?.value || 'Base';
                modalEditarDetalle?.hide();
                calcularSaldosReales();
            });
        }

        const renderAmortizacionesOriginal = renderAmortizaciones;
        renderAmortizaciones = function(amortizaciones = []) {
            window.amortizacionesRemotasCache = amortizaciones.filter(a => !a.es_local);
            renderAmortizacionesOriginal(amortizaciones);
            tbodyAmortizaciones?.querySelectorAll('tr:not(#filaVaciaAmortizaciones)').forEach((tr, index) => {
                const btnEditAmort = tr.querySelector('.js-edit-amort');
                const btnDelAmort = tr.querySelector('.js-del-amort');
                const fila = amortizaciones[index];
                if (!fila?.es_local) {
                    btnEditAmort?.classList.add('d-none');
                    btnDelAmort?.classList.add('d-none');
                    return;
                }
                btnEditAmort?.addEventListener('click', () => {
                    editandoPagoIndex = Number(fila._local_index);
                    if (editandoPagoIndex < 0) return;
                    document.getElementById('pagoPrevioFecha').value = fila.fecha || '';
                    document.getElementById('pagoPrevioReferencia').value = fila.referencia || '';
                    document.getElementById('pagoPrevioMetodo').value = fila.metodo || '';
                    document.getElementById('pagoPrevioMonto').value = Number(fila.monto || 0).toFixed(2);
                    modalPagoPrevio?.show();
                });
                btnDelAmort?.addEventListener('click', () => {
                    pagosLocales = pagosLocales.filter((_, i) => i !== Number(fila._local_index));
                    sincronizarPagosLocales();
                });
            });
        };
    }

    // ========================================================================
    // 8. LÓGICA DE TRANSFERENCIAS INTERNAS (Saldos y Validaciones)
    // ========================================================================
    const transferOrigenSelect = document.getElementById('selectCuentaOrigen');
    const transferDestinoSelect = document.getElementById('selectCuentaDestino');
    const transferMontoInput = document.getElementById('inputMontoTransferencia');

    if (transferOrigenSelect && transferDestinoSelect && transferMontoInput) {
        transferOrigenSelect.addEventListener('change', function() {
            const origenId = this.value;

            Array.from(transferDestinoSelect.options).forEach(opt => {
                if (opt.value !== "" && opt.value === origenId) {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            });
            
            if (transferDestinoSelect.value === origenId) {
                transferDestinoSelect.value = "";
            }

            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value !== "") {
                const saldo = parseFloat(selectedOption.getAttribute('data-saldo')) || 0;
                transferMontoInput.setAttribute('max', saldo);
                transferMontoInput.setAttribute('title', 'Saldo máximo disponible: ' + saldo);
            } else {
                transferMontoInput.removeAttribute('max');
                transferMontoInput.removeAttribute('title');
            }
        });
    }
})();