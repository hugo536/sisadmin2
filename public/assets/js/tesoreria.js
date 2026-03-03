document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // ========================================================================
    // 0. INICIALIZACIÓN GLOBAL (Tooltips y Modales Auto-Open)
    // ========================================================================
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const tesoreriaApp = document.getElementById('tesoreriaCuentasApp');
    if (tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true') {
        const modalCuenta = document.getElementById('modalCuentaTesoreria');
        if (modalCuenta) {
            const myModal = new bootstrap.Modal(modalCuenta);
            myModal.show();
        }
    }

    // ========================================================================
    // 1. DELEGACIÓN DE EVENTOS: APERTURA DE MODALES (Cobros y Pagos)
    // ========================================================================
    const openModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return null;
        return bootstrap.Modal.getOrCreateInstance(el);
    };

    document.addEventListener('click', (e) => {
        const btnCobro = e.target.closest('.js-open-cobro');
        if (btnCobro) {
            const inputId = document.getElementById('cobroIdOrigen');
            const inputMoneda = document.getElementById('cobroMoneda');
            const inputSaldo = document.getElementById('cobroSaldo');
            const inputMonto = document.getElementById('cobroMonto');

            if (inputId) inputId.value = btnCobro.dataset.idOrigen || '';
            if (inputMoneda) inputMoneda.value = btnCobro.dataset.moneda || 'PEN';
            if (inputSaldo) inputSaldo.value = btnCobro.dataset.saldo || '0';
            if (inputMonto) {
                inputMonto.setAttribute('max', btnCobro.dataset.saldo || '0');
                inputMonto.value = '';
            }
            
            const modal = openModal('modalCobro');
            if (modal) modal.show();
        }

        const btnPago = e.target.closest('.js-open-pago');
        if (btnPago) {
            const inputId = document.getElementById('pagoIdOrigen');
            const inputMoneda = document.getElementById('pagoMoneda');
            const inputSaldo = document.getElementById('pagoSaldo');
            const inputMonto = document.getElementById('pagoMonto');

            if (inputId) inputId.value = btnPago.dataset.idOrigen || '';
            if (inputMoneda) inputMoneda.value = btnPago.dataset.moneda || 'PEN';
            if (inputSaldo) inputSaldo.value = btnPago.dataset.saldo || '0';
            if (inputMonto) {
                inputMonto.setAttribute('max', btnPago.dataset.saldo || '0');
                inputMonto.value = '';
            }
            
            const modal = openModal('modalPago');
            if (modal) modal.show();
        }
    });

    // ========================================================================
    // 2. VALIDACIÓN Y CONFIRMACIÓN DE FORMULARIOS (SweetAlert)
    // ========================================================================
    const validateMontoVsSaldo = (form) => {
        if (!form.classList.contains('js-form-monto')) return true;
        
        const montoEl = form.querySelector('input[name="monto"]');
        const saldoEl = form.querySelector('[data-saldo-target]');
        
        if (!montoEl || !saldoEl) return true;
        
        const monto = Number(montoEl.value || 0);
        const saldo = Number(saldoEl.value || 0);
        
        if (monto <= 0 || monto > saldo) {
            const msg = `El monto a procesar (${monto}) debe ser mayor a 0 y menor o igual al saldo pendiente (${saldo}).`;
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Monto Inválido', text: msg });
            else alert(msg);
            return false;
        }
        return true;
    };

    document.querySelectorAll('.js-form-confirm').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!validateMontoVsSaldo(form)) return;
            
            const go = () => {
                const btnSubmit = form.querySelector('button[type="submit"]');
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                }
                form.submit();
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Confirmar operación?',
                    text: 'Esta acción actualizará los saldos y quedará registrada en auditoría.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Sí, confirmar',
                    cancelButtonText: 'Cancelar'
                }).then((r) => { if (r.isConfirmed) go(); });
            } else if (window.confirm('¿Confirmar operación de tesorería?')) {
                go();
            }
        });
    });

    // ========================================================================
    // 3. FILTROS AUTOMÁTICOS CON AJAX UNIVERSAL (Omitido por brevedad, es el mismo que ya tenías)
    // ========================================================================
    // (MANTÉN TU CÓDIGO ACTUAL DEL BLOQUE 3 AQUÍ)


    // ========================================================================
    // 4. FORMULARIO DE CUENTAS: UX PROFESIONAL (Visibilidad y Autogeneración)
    // ========================================================================
    const tipoCuentaSelect = document.getElementById('cuentaTipo');
    const codigoInput = document.getElementById('cuentaCodigo');
    const tipoCuentaInput = document.getElementById('cuentaTipoCuenta');
    const entidadSelect = document.getElementById('cuentaEntidad');

    const bankFields = document.querySelectorAll('.js-bank-field');
    const tipoCuentaWrap = document.getElementById('cuentaTipoCuentaWrap');
    const cciWrap = document.getElementById('cuentaCciWrap');
    const cciInput = document.getElementById('cuentaCci');
    const numeroLabel = document.getElementById('cuentaNumeroLabel');
    const numeroInput = document.getElementById('cuentaNumero');

    const tipoCuentaOpciones = {
        BANCO: ['AHORROS', 'CORRIENTE', 'MAESTRA'],
        BILLETERA: ['YAPE', 'PLIN', 'LUKITA', 'OTRA']
    };

    const setTipoCuentaOptions = (tipo) => {
        if (!tipoCuentaInput) return;

        const selected = (tipoCuentaInput.dataset.selected || tipoCuentaInput.value || '').toUpperCase();
        tipoCuentaInput.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Seleccionar...';
        tipoCuentaInput.appendChild(placeholder);

        const opciones = tipoCuentaOpciones[tipo] || [];
        opciones.forEach((opcion) => {
            const opt = document.createElement('option');
            opt.value = opcion;
            opt.textContent = opcion.charAt(0) + opcion.slice(1).toLowerCase();
            if (selected === opcion) opt.selected = true;
            tipoCuentaInput.appendChild(opt);
        });

        tipoCuentaInput.dataset.selected = '';
    };

    const generarCodigoCliente = () => {
        if (!codigoInput || codigoInput.value.trim() !== '') return;
        const tipo = (tipoCuentaSelect?.value || 'CAJA').toUpperCase();
        const prefijo = tipo === 'CAJA' ? 'CJ-' : (tipo === 'BILLETERA' ? 'WL-' : 'BN-');
        const randomNum = Math.floor(Math.random() * 9000) + 1000;
        codigoInput.value = `${prefijo}${randomNum}`;
    };

    const syncEntidadOptions = () => {
        if (!entidadSelect || !tipoCuentaSelect) return;
        const tipo = tipoCuentaSelect.value.toUpperCase();

        Array.from(entidadSelect.options).forEach((opt, index) => {
            if (index === 0) {
                opt.hidden = false;
                return;
            }

            const optionTipo = (opt.dataset.tipo || '').toUpperCase();
            const mostrar = tipo === 'CAJA' || optionTipo === '' || optionTipo === tipo;
            opt.hidden = !mostrar;

            if (!mostrar && opt.selected) {
                entidadSelect.value = '';
            }
        });
    };

    const syncFormUX = () => {
        if (!tipoCuentaSelect) return;

        const tipo = tipoCuentaSelect.value.toUpperCase();
        const esCaja = tipo === 'CAJA';
        const esBilletera = tipo === 'BILLETERA';

        bankFields.forEach((field) => {
            field.style.display = esCaja ? 'none' : '';
        });

        if (tipoCuentaWrap) tipoCuentaWrap.style.display = esCaja ? 'none' : '';
        if (cciWrap) cciWrap.style.display = (esCaja || esBilletera) ? 'none' : '';

        if (numeroLabel) numeroLabel.textContent = esBilletera ? 'N° de Teléfono' : 'N° de cuenta';
        if (numeroInput) {
            numeroInput.placeholder = esBilletera ? '999999999' : '000-0000000';
            numeroInput.maxLength = esBilletera ? 9 : 80;
        }

        setTipoCuentaOptions(tipo);
        syncEntidadOptions();
    };

    if (tipoCuentaSelect) {
        tipoCuentaSelect.addEventListener('change', () => {
            const esEdicion = tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true';

            if (!esEdicion && codigoInput) {
                codigoInput.value = '';
            }

            if (tipoCuentaInput) {
                tipoCuentaInput.value = '';
                tipoCuentaInput.dataset.selected = '';
            }

            syncFormUX();
            generarCodigoCliente();
        });

        if (numeroInput) {
            numeroInput.addEventListener('input', () => {
                if (tipoCuentaSelect.value.toUpperCase() === 'BILLETERA') {
                    numeroInput.value = numeroInput.value.replace(/\D/g, '').slice(0, 9);
                }
            });
        }

        const formCuenta = document.getElementById('formCuentaTesoreria');
        if (formCuenta) {
            formCuenta.addEventListener('submit', () => {
                generarCodigoCliente();
                if (tipoCuentaSelect.value.toUpperCase() === 'BILLETERA' && cciInput) {
                    cciInput.value = '';
                }
            });
        }

        syncFormUX();
        generarCodigoCliente();
    }

    // ========================================================================
    // 5. LIMPIEZA AUTOMÁTICA DEL FORMULARIO AL CERRAR MODAL
    // ========================================================================
    const modalCreacionEl = document.getElementById('modalCuentaTesoreria');
    const formCreacionEl = document.getElementById('formCuentaTesoreria');

    if (modalCreacionEl && formCreacionEl) {
        modalCreacionEl.addEventListener('hidden.bs.modal', () => {
            
            // CORRECCIÓN: Evaluamos si es edición de forma segura.
            // Si tesoreriaApp no existe, esEdicion será falso y permitirá la limpieza.
            const esEdicion = tesoreriaApp && tesoreriaApp.dataset.esEdicion === 'true';
            
            if (!esEdicion) {
                // 1. Limpia inputs de texto, checkbox y radio
                formCreacionEl.reset(); 
                
                // 2. Limpiar validaciones previas de Bootstrap si existen
                formCreacionEl.classList.remove('was-validated'); 
                
                // 3. Limpiar el input de código manual para forzar uno nuevo
                if (codigoInput) {
                    codigoInput.value = '';
                }
                
                // 4. Disparar el evento change para que la interfaz (UX) vuelva a su estado original
                if(tipoCuentaSelect) {
                    tipoCuentaSelect.dispatchEvent(new Event('change')); 
                }
            }
        });
    }

});