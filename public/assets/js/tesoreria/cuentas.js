/**
 * Lógica específica para la vista de Tesorería - Cuentas
 * Archivo: assets/js/tesoreria/cuentas.js
 */
(function arrancarCuentasTesoreria() {
    'use strict';

    // 1. Elementos del DOM
    const tesoreriaApp = document.getElementById('tesoreriaCuentasApp');
    if (!tesoreriaApp) return;

    const modalCuentaEl = document.getElementById('modalCuentaTesoreria');
    const formCuenta = document.getElementById('formCuentaTesoreria');
    
    // ========================================================================
    // 2. INICIALIZACIÓN DE TABLAS Y ESTADO DE EDICIÓN
    // ========================================================================
    
    // Auto-inicializar la tabla de Cuentas
    if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
        window.ERPTable.autoInitFromDataset(tesoreriaApp);
    }

    // Si la URL indica que estamos editando, abrir el modal automáticamente
    if (tesoreriaApp.dataset.esEdicion === 'true' && modalCuentaEl) {
        if (typeof bootstrap !== 'undefined') {
            setTimeout(() => {
                bootstrap.Modal.getOrCreateInstance(modalCuentaEl).show();
            }, 100); // Pequeño retraso para asegurar que el DOM cargó
        }
    }

    // ========================================================================
    // 3. LIMPIEZA DE MODAL AL CERRAR
    // ========================================================================
    if (modalCuentaEl && formCuenta) {
        modalCuentaEl.addEventListener('hidden.bs.modal', () => {
            // Solo reseteamos si NO estamos en modo edición 
            const esEdicion = tesoreriaApp.dataset.esEdicion === 'true';
            
            if (!esEdicion) {
                formCuenta.reset();
            }
        });
    }

    // ========================================================================
    // 4. EDICIÓN SIN RECARGA DE PÁGINA (VÍA DATA-ATTRIBUTES)
    // ========================================================================
    const botonesEditar = document.querySelectorAll('.js-editar-cuenta-btn');

    botonesEditar.forEach(boton => {
        boton.addEventListener('click', function() {
            // 1. Obtener la información oculta en el botón
            const cuenta = JSON.parse(this.dataset.cuenta);

            // 2. Rellenar los campos de texto y selects
            formCuenta.querySelector('[name="id"]').value = cuenta.id || '0';
            formCuenta.querySelector('[name="codigo"]').value = cuenta.codigo || '';
            formCuenta.querySelector('[name="nombre"]').value = cuenta.nombre || '';
            formCuenta.querySelector('[name="tipo"]').value = cuenta.tipo || 'CAJA';
            formCuenta.querySelector('[name="moneda"]').value = cuenta.moneda || 'PEN';

            // 3. Rellenar los switches de permisos
            formCuenta.querySelector('[name="permite_cobros"]').checked = parseInt(cuenta.permite_cobros) === 1;
            formCuenta.querySelector('[name="permite_pagos"]').checked = parseInt(cuenta.permite_pagos) === 1;

            // 4. Rellenar los checkboxes de métodos de pago
            formCuenta.querySelectorAll('[name="metodos_pago[]"]').forEach(cb => cb.checked = false); // Limpiar primero
            
            if (cuenta.metodos_pago) {
                // Si viene de BD como string JSON, lo parseamos. Si ya es array, lo usamos.
                let metodos = typeof cuenta.metodos_pago === 'string' ? JSON.parse(cuenta.metodos_pago) : cuenta.metodos_pago;
                
                if (Array.isArray(metodos)) {
                    metodos.forEach(metodo => {
                        const cb = formCuenta.querySelector(`[name="metodos_pago[]"][value="${metodo}"]`);
                        if (cb) cb.checked = true;
                    });
                }
            }

            // 5. Cambiar el título y botón del modal visualmente a "Edición"
            modalCuentaEl.querySelector('.modal-title').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Cuenta';
            formCuenta.querySelector('button[type="submit"]').innerHTML = '<i class="bi bi-save me-2"></i>Actualizar Cuenta';

            // 6. Abrir el modal
            const modal = bootstrap.Modal.getOrCreateInstance(modalCuentaEl);
            modal.show();
        });
    });

    // Asegurar que al hacer clic en "Nueva Cuenta" el formulario esté limpio
    const btnNuevaCuenta = document.querySelector('[data-bs-target="#modalCuentaTesoreria"]');
    if (btnNuevaCuenta) {
        btnNuevaCuenta.addEventListener('click', () => {
            formCuenta.reset();
            formCuenta.querySelector('[name="id"]').value = '0';
            modalCuentaEl.querySelector('.modal-title').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva Cuenta';
            formCuenta.querySelector('button[type="submit"]').innerHTML = '<i class="bi bi-save me-2"></i>Guardar Cuenta';
        });
    }

    // ========================================================================
    // 5. ALERTAS SWEETALERT2 (GUARDAR/ACTUALIZAR, ELIMINAR, ESTADO)
    // ========================================================================

    // A. Interceptar el formulario de Guardar / Actualizar Cuenta
    if (formCuenta) {
        formCuenta.addEventListener('submit', function(e) {
            e.preventDefault(); // Pausamos el envío para hacer validaciones
            
            // --- NUEVA VALIDACIÓN: Exigir al menos un check de método de pago ---
            const metodosSeleccionados = this.querySelectorAll('input[name="metodos_pago[]"]:checked').length;
            
            if (metodosSeleccionados === 0) {
                Swal.fire({
                    title: 'Información incompleta',
                    text: 'Debes seleccionar como mínimo un Método de Pago Vinculado.',
                    icon: 'warning',
                    confirmButtonColor: '#0d6efd',
                    confirmButtonText: 'Entendido',
                    customClass: { popup: 'rounded-4 shadow-lg' }
                });
                return; // Detenemos la ejecución aquí, no mostramos la confirmación
            }
            // ----------------------------------------------------------------------

            // Verificamos si es creación o edición leyendo el ID
            const idCuenta = this.querySelector('[name="id"]').value;
            const esEdicion = idCuenta !== '0' && idCuenta !== '';
            const accionTexto = esEdicion ? 'actualizar' : 'guardar';

            Swal.fire({
                title: `¿Confirmas ${accionTexto} esta cuenta?`,
                text: "Verifica que los datos y los métodos de pago vinculados sean correctos.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd', // Color azul Bootstrap
                cancelButtonColor: '#6c757d', // Color gris Bootstrap
                confirmButtonText: `<i class="bi bi-check-circle me-1"></i> Sí, ${accionTexto}`,
                cancelButtonText: 'Cancelar',
                customClass: {
                    popup: 'rounded-4 shadow-lg' // Bordes redondeados más modernos
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar un loader mientras el backend procesa
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Por favor, espera un momento.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    this.submit(); // Ahora sí, enviamos el formulario al PHP
                }
            });
        });
    }

    // B. Interceptar el formulario de Eliminar Cuenta
    const formsEliminar = document.querySelectorAll('.js-form-delete-cuenta');
    formsEliminar.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Pausamos la eliminación

            Swal.fire({
                title: '¿Estás completamente seguro?',
                text: "Esta acción eliminará la cuenta de tesorería y no se puede revertir.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Color rojo peligro
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    popup: 'rounded-4 shadow-lg'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Eliminando...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    this.submit();
                }
            });
        });
    });

    // C. Interceptar el switch de Cambio de Estado (Activar/Inactivar)
    const switchesEstado = document.querySelectorAll('.js-switch-estado-cuenta');
    switchesEstado.forEach(switchEl => {
        switchEl.addEventListener('change', function(e) {
            const isChecked = this.checked;
            const form = this.closest('form'); // Buscamos el form padre del switch
            
            Swal.fire({
                title: isChecked ? '¿Activar esta cuenta?' : '¿Inactivar esta cuenta?',
                text: isChecked 
                    ? "La cuenta volverá a estar disponible para realizar cobros y pagos." 
                    : "La cuenta se ocultará y ya no se podrá usar en nuevas transacciones.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isChecked ? '#198754' : '#ffc107', // Verde para activar, amarillo para pausar
                cancelButtonColor: '#6c757d',
                confirmButtonText: isChecked ? 'Sí, activar' : 'Sí, inactivar',
                cancelButtonText: 'Cancelar',
                customClass: { popup: 'rounded-4 shadow-lg' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Actualizando estado...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    form.submit();
                } else {
                    // Si el usuario cancela, revertimos el switch visualmente a como estaba
                    this.checked = !isChecked;
                }
            });
        });
    });

    // D. Interceptar el formulario de Transferencia Interna (Solo para mostrar el loader)
    const formTransferencia = document.querySelector('#modalTransferenciaInterna form');
    if (formTransferencia) {
        formTransferencia.addEventListener('submit', function(e) {
            // No usamos e.preventDefault() porque queremos que se envíe directo,
            // solo queremos mostrar la pantalla de carga visualmente.
            Swal.fire({
                title: 'Procesando transferencia...',
                text: 'Por favor, espera un momento.',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
    }

})();