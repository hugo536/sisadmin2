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
    
    // Contenedores a mostrar/ocultar
    const wrapBancoGral = document.getElementById('wrapBancarioGral'); // Envuelve todo lo bancario
    const wrapCci = document.getElementById('wrapCci');
    const wrapTipoCta = document.getElementById('wrapTipoCta'); // Ahorros/Corriente
    
    // Labels e inputs que cambian
    const labelNumCta = document.getElementById('labelNumCta');
    const inputNumCta = document.getElementById('inputNumCta');
    const selectEntidad = document.getElementById('cuentaEntidad'); // El TomSelect

    let tomEntidadInstance = null;

    if (tipoCuentaSelect) {
        
        // --- 4.1 Lógica de Visibilidad Condicional ---
        const syncFormUX = () => {
            const tipo = tipoCuentaSelect.value.toUpperCase();

            if (tipo === 'CAJA') {
                // Si es caja, ocultamos todo lo de bancos
                wrapBancoGral.style.display = 'none';
                
            } else if (tipo === 'BILLETERA') {
                // Mostrar bloque bancario
                wrapBancoGral.style.display = '';
                // Ocultar tipo de cuenta (No aplica a Yape/Plin) y CCI
                wrapTipoCta.style.display = 'none';
                wrapCci.style.display = 'none';
                
                // Cambiar Número de cuenta por Celular
                labelNumCta.textContent = 'N° de Celular';
                inputNumCta.placeholder = '999999999';
                inputNumCta.maxLength = 9;
                
            } else if (tipo === 'BANCO') {
                // Mostrar todo
                wrapBancoGral.style.display = '';
                wrapTipoCta.style.display = '';
                wrapCci.style.display = '';
                
                // Restaurar Número de Cuenta
                labelNumCta.textContent = 'N° de Cuenta';
                inputNumCta.placeholder = '000-00000000';
                inputNumCta.maxLength = 80;
            }

            // --- Filtrar TomSelect de Entidades (Bancos vs Billeteras) ---
            if (selectEntidad && tomEntidadInstance) {
                // Aquí asumiríamos que los options tienen data-tipo="BANCO" o "BILLETERA"
                // Como lo configuraste en tu código original.
                // ...
            }
        };

        // --- 4.2 Lógica de Autogeneración de Código ---
        const autogenerateCode = () => {
            // Solo autogenera si el campo está vacío (es decir, en creación nueva)
            if (codigoInput && codigoInput.value.trim() === '') {
                const prefix = tipoCuentaSelect.value === 'CAJA' ? 'CJ-' : (tipoCuentaSelect.value === 'BILLETERA' ? 'WL-' : 'BN-');
                const randomNum = Math.floor(Math.random() * 900) + 100; // Ej: BN-452
                codigoInput.value = `${prefix}${randomNum}`;
            }
        };

        // Eventos
        tipoCuentaSelect.addEventListener('change', () => {
            syncFormUX();
            
            // CORRECCIÓN: Guardamos la validación en una variable clara usando Optional Chaining (?.)
            const appEl = document.getElementById('tesoreriaCuentasApp');
            const esEdicion = appEl && appEl.dataset.esEdicion === 'true';
            
            // Si cambian el tipo, limpiamos el código para que genere uno nuevo acorde al prefijo
            if(codigoInput && !esEdicion) {
                 codigoInput.value = ''; 
                 autogenerateCode();
            }
        });

        // Limitar input a solo números si es billetera
        if (inputNumCta) {
            inputNumCta.addEventListener('input', () => {
                if (tipoCuentaSelect.value === 'BILLETERA') {
                    inputNumCta.value = inputNumCta.value.replace(/\D/g, '').slice(0, 9);
                }
            });
        }

        // Ejecutar al cargar
        syncFormUX();
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
                
                // 3. Reiniciar TomSelect si está activo (verificando que la variable exista)
                if (typeof tomEntidadInstance !== 'undefined' && tomEntidadInstance) {
                    tomEntidadInstance.clear();
                }

                // 4. Limpiar el input de código manual para forzar uno nuevo
                if (codigoInput) {
                    codigoInput.value = '';
                }
                
                // 5. Disparar el evento change para que la interfaz (UX) vuelva a su estado original
                if(tipoCuentaSelect) {
                    tipoCuentaSelect.dispatchEvent(new Event('change')); 
                }
            }
        });
    }

});