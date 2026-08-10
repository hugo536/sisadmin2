/**
 * LÓGICA PARA ADELANTOS (TESORERÍA)
 * Archivo: public/assets/js/tesoreria/adelantos.js
 */

(function() {
    'use strict';

    function iniciarModuloAdelantos() {
        const appContenedor = document.getElementById('adelantosApp');
        if (!appContenedor || appContenedor.dataset.iniciado === '1') return;
        appContenedor.dataset.iniciado = '1';

        // 1. Buscador de tabla
        const searchInput = document.getElementById('searchAdelantos');
        const tablaAdelantos = document.getElementById('tablaAdelantos');

        if (searchInput && tablaAdelantos) {
            searchInput.addEventListener('keyup', function () {
                const searchTerm = this.value.toLowerCase().trim();
                const filas = tablaAdelantos.querySelectorAll('tbody tr:not(.empty-msg-row)');
                
                filas.forEach(fila => {
                    const dataSearch = fila.getAttribute('data-search') || '';
                    fila.style.display = dataSearch.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // 2. Llenar Modal de Devolución
        const modalDevolver = document.getElementById('modalDevolver');
        if (modalDevolver) {
            modalDevolver.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('devIdAdelanto').value = button.getAttribute('data-id');
                document.getElementById('devNombreEmpleado').textContent = button.getAttribute('data-empleado');
                
                const inputMonto = document.getElementById('devMonto');
                inputMonto.value = button.getAttribute('data-saldo');
                inputMonto.max = button.getAttribute('data-saldo');
            });
        }

        // 3. Bloquear envíos múltiples
        document.querySelectorAll('#adelantosApp form').forEach(form => {
            form.addEventListener('submit', function() {
                const btnSubmit = this.querySelector('button[type="submit"]');
                if (btnSubmit && this.checkValidity()) {
                    btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Procesando...`;
                    btnSubmit.classList.add('disabled');
                    setTimeout(() => { btnSubmit.disabled = true; }, 10);
                }
            });
        });

        // 4. Inicializar TomSelect en el modal de Nuevo Adelanto
        const modalNuevoAdelanto = document.getElementById('modalNuevoAdelanto');
        const selectEmpleado = document.getElementById('selectEmpleado');
        
        if (selectEmpleado && typeof TomSelect !== 'undefined') {
            const tomSelectInstance = new TomSelect(selectEmpleado, {
                create: false,
                placeholder: 'Buscar y seleccionar trabajador...',
                // Asegurar que el menú se vea por encima del modal
                dropdownParent: 'body'
            });

            // Truco para limpiar el select si se cierra y vuelve a abrir el modal
            if (modalNuevoAdelanto) {
                modalNuevoAdelanto.addEventListener('hidden.bs.modal', () => {
                    tomSelectInstance.clear();
                });
            }
        }
        
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarModuloAdelantos);
    } else {
        iniciarModuloAdelantos();
    }
})();