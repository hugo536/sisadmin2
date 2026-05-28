/**
 * ========================================================================
 * NÚCLEO (CORE) - MÓDULO DE TESORERÍA
 * Archivo: assets/js/tesoreria/tesoreria_core.js
 * Funciones globales, interceptores de formularios y UI compartida.
 * ========================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // ========================================================================
    // 1. INICIALIZACIÓN GLOBAL DE BOOTSTRAP TOOLTIPS
    // ========================================================================
    const initTooltips = () => {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
        }
    };
    initTooltips(); // Se ejecuta al cargar la página

    // Exponemos la función globalmente por si las vistas SPA (AJAX) necesitan 
    // re-inicializar los tooltips después de recargar una tabla.
    window.TesoreriaCore = {
        initTooltips: initTooltips
    };

    // ========================================================================
    // 2. INTERCEPTOR UNIVERSAL DE FORMULARIOS (SweetAlert2)
    // ========================================================================
    // Evita envíos accidentales y muestra un modal de confirmación elegante.
    document.querySelectorAll('.js-form-confirm').forEach(form => {
        // Clonamos y reemplazamos el nodo para evitar listeners duplicados (buenas prácticas)
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        newForm.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return; 
            e.preventDefault(); // Detenemos el submit inmediato
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Confirmar operación?',
                    text: 'Esta acción actualizará los saldos y se registrará en el sistema.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Sí, confirmar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Prevenir doble clic cambiando el botón a estado de carga
                        const btn = this.querySelector('button[type="submit"]');
                        if (btn) {
                            if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerHTML;
                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                        }
                        this.submit(); // Ahora sí, enviamos el formulario
                    }
                });
            } else {
                // Fallback por si SweetAlert falla en cargar
                if (confirm('¿Está seguro de realizar esta operación?')) {
                    this.submit();
                }
            }
        });
    });

    // ========================================================================
    // 3. INTERCEPTOR ESPECÍFICO: ELIMINAR CUENTAS
    // ========================================================================
    document.querySelectorAll('.js-form-delete-cuenta').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar cuenta?',
                    text: 'Esta cuenta no tiene movimientos. La eliminación no se podrá deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const btn = this.querySelector('button[type="submit"]');
                        if (btn) {
                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        }
                        this.submit();
                    }
                });
            }
        });
    });

    // ========================================================================
    // 4. AUTO-SUBMIT PARA SWITCHES DE ESTADO (Activar/Inactivar Cuentas)
    // ========================================================================
    document.querySelectorAll('.js-switch-estado-cuenta').forEach(sw => {
        sw.addEventListener('change', function () {
            const form = this.closest('form');
            if (form) form.submit();
        });
    });

    // ========================================================================
    // 5. LIMPIEZA DE URL AUTOMÁTICA (UX)
    // ========================================================================
    // Si la URL tiene un "?ok=1" o "?error=...", limpiamos la barra de direcciones 
    // después de que el PHP haya mostrado la alerta, para que si el usuario 
    // recarga la página con F5, no se vuelva a mostrar la alerta.
    const params = new URLSearchParams(window.location.search);
    
    if (params.has('ok') || params.has('error')) {
        // Damos un pequeño respiro de 500ms para que PHP/SweetAlert lean los params primero
        setTimeout(() => {
            params.delete('ok');
            params.delete('error');
            params.delete('action'); // Por si usas este parámetro también
            
            const newQuery = params.toString();
            const nextUrl = newQuery ? `${window.location.pathname}?${newQuery}` : window.location.pathname;
            
            window.history.replaceState({}, document.title, nextUrl);
        }, 500);
    }

});