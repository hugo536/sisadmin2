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

})();