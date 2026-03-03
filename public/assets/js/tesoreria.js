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
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: isUpdate ? 'Cuenta actualizada' : 'Cuenta guardada',
                    text: isUpdate ? 'La cuenta se actualizó correctamente.' : 'La cuenta se guardó correctamente.',
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
    // 2. RESTRICCIÓN DE MÉTODOS DE PAGO (Lógica para Cobros/Pagos)
    // ========================================================================
    // 
    const accountSelectors = document.querySelectorAll('select[name="id_cuenta"]');
    
    accountSelectors.forEach(select => {
        select.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tipoCuenta = (selectedOption.dataset.tipo || '').toUpperCase();
            
            // Buscamos el selector de método de pago en el mismo modal/formulario
            const form = this.closest('form');
            const metodoPagoSelect = form.querySelector('select[name="id_metodo_pago"]');
            
            if (!metodoPagoSelect) return;

            if (tipoCuenta === 'CAJA') {
                // Si es CAJA, forzamos a EFECTIVO (asumimos que ID 1 es Efectivo, ajusta según tu BD)
                Array.from(metodoPagoSelect.options).forEach(opt => {
                    const texto = opt.textContent.toUpperCase();
                    // Deshabilitamos todo lo que no diga "EFECTIVO"
                    if (!texto.includes('EFECTIVO') && opt.value !== "") {
                        opt.disabled = true;
                    } else if (texto.includes('EFECTIVO')) {
                        opt.selected = true;
                    }
                });
            } else {
                // Si es BANCO o BILLETERA, habilitamos todo
                Array.from(metodoPagoSelect.options).forEach(opt => opt.disabled = false);
            }
        });
    });

    // ========================================================================
    // 3. CONFIRMACIÓN DE OPERACIONES (SweetAlert2)
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

    // ========================================================================
    // 4. LIMPIEZA AL CERRAR MODAL
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
});