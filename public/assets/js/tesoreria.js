document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // ========================================================================
    // 0. INICIALIZACIÓN GLOBAL (Tooltips y Modales Auto-Open)
    // ========================================================================
    
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Lógica para auto-abrir el Modal de Cuentas si PHP inyectó el indicador en el HTML
    // Buscamos un elemento oculto o un dataset que la vista haya generado
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

    // Escuchamos clics en todo el documento (vital para botones generados por AJAX)
    document.addEventListener('click', (e) => {
        
        // --- Modal de Cobro (CXC) ---
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
                inputMonto.value = ''; // Limpiamos el monto al abrir
            }
            
            const modal = openModal('modalCobro');
            if (modal) modal.show();
        }

        // --- Modal de Pago (CXP) ---
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
                inputMonto.value = ''; // Limpiamos el monto al abrir
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
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Monto Inválido', text: msg });
            } else {
                alert(msg);
            }
            return false;
        }
        return true;
    };

    document.querySelectorAll('.js-form-confirm').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (!validateMontoVsSaldo(form)) return;
            
            const go = () => {
                // Prevenir doble clic cambiando el botón a estado "Cargando"
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
                }).then((r) => {
                    if (r.isConfirmed) go();
                });
            } else if (window.confirm('¿Confirmar operación de tesorería?')) {
                go();
            }
        });
    });

    // ========================================================================
    // 3. FILTROS AUTOMÁTICOS CON AJAX UNIVERSAL (CXC, CXP y Movimientos)
    // ========================================================================
    
    const formFiltros = document.getElementById('formFiltrosCxc') || 
                        document.getElementById('formFiltrosCxp') || 
                        document.getElementById('formFiltrosMovimientos');
    
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            triggerAutoSubmit();
        });

        let debounceTimer;

        const autoSubmitForm = async () => {
            let tableManager = null;
            let tableBodyId = '';
            
            if (document.getElementById('cxcTable')) {
                tableManager = window.cxcManager;
                tableBodyId = 'cxcTableBody';
            } else if (document.getElementById('cxpTable')) {
                tableManager = window.cxpManager;
                tableBodyId = 'cxpTableBody';
            } else if (document.getElementById('movimientosTable')) {
                tableManager = window.movimientosManager;
                tableBodyId = 'movimientosTableBody';
            }

            if (tableManager && typeof tableManager.showLoading === 'function') {
                tableManager.showLoading();
            }

            const url = new URL(window.location.href);
            const formData = new FormData(formFiltros);
            
            for (const [key, value] of formData.entries()) {
                if (key !== 'ruta') {
                    url.searchParams.set(key, value);
                }
            }

            try {
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if (!response.ok) throw new Error('Error al conectar con el servidor');
                const html = await response.text();

                const parser = new DOMParser();
                const docVirtual = parser.parseFromString(html, 'text/html');

                if (tableBodyId) {
                    const currentTbody = document.getElementById(tableBodyId);
                    const newTbody = docVirtual.getElementById(tableBodyId);
                    if (currentTbody && newTbody) {
                        currentTbody.innerHTML = newTbody.innerHTML;
                    }
                }

                const currentBadge = document.getElementById('badgeRegistros');
                const newBadge = docVirtual.getElementById('badgeRegistros');
                if (currentBadge && newBadge) {
                    currentBadge.innerHTML = newBadge.innerHTML;
                }

                if (tableManager && typeof tableManager.refresh === 'function') {
                    tableManager.refresh();
                }

                if (window.ERPTable && typeof window.ERPTable.initTooltips === 'function') {
                    window.ERPTable.initTooltips();
                }

                window.history.pushState({}, '', url);

            } catch (error) {
                console.error('Actualización AJAX Falló:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Aviso', 'No se pudo actualizar la tabla automáticamente.', 'warning');
                }
            } finally {
                if (tableManager && typeof tableManager.hideLoading === 'function') {
                    tableManager.hideLoading();
                }
            }
        };

        const triggerAutoSubmit = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                autoSubmitForm(); 
            }, 150);
        };

        formFiltros.addEventListener('change', triggerAutoSubmit);
        formFiltros.addEventListener('input', triggerAutoSubmit);
    }

    // ========================================================================
    // 4. FORMULARIO DE CUENTAS: CAMPOS BANCARIOS CONDICIONALES
    // ========================================================================
    const tipoCuentaEl = document.getElementById('cuentaTipo');
    if (tipoCuentaEl) {
        const syncBankFields = () => {
            const show = ['BANCO', 'BILLETERA'].includes((tipoCuentaEl.value || '').toUpperCase());
            document.querySelectorAll('.js-bank-field').forEach((el) => {
                el.style.display = show ? '' : 'none';
            });
        };

        tipoCuentaEl.addEventListener('change', syncBankFields);
        syncBankFields();
    }
});