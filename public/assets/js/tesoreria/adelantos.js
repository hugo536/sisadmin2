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
        
        // ==============================================================
        // 5. HISTORIAL DE PAGOS (VER DETALLES) - NUEVO
        // ==============================================================
        const modalVerDetalle = document.getElementById('modalVerDetalle');
        if (modalVerDetalle) {
            modalVerDetalle.addEventListener('show.bs.modal', async function (event) {
                const button = event.relatedTarget;
                const idAdelanto = button.getAttribute('data-id');
                const nombreEmpleado = button.getAttribute('data-empleado');

                // 5.1 Asignar el nombre al UI
                const spanNombre = document.getElementById('detNombreEmpleado');
                if (spanNombre) spanNombre.textContent = nombreEmpleado;

                const tbody = document.getElementById('bodyHistorialAdelanto');
                if (!tbody) return;

                // 5.2 Mostrar estado de carga
                tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando historial...</td></tr>`;

                try {
                    // 5.3 Hacer petición AJAX al backend
                    // Asume que tienes una ruta así. El fallback a '' es por si window.BASE_URL no está definido.
                    const baseUrl = window.BASE_URL || ''; 
                    const url = `${baseUrl}?ruta=tesoreria/adelantos&accion=historial&id=${idAdelanto}`;
                    
                    const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await resp.json();

                    tbody.innerHTML = ''; // Limpiar tabla

                    // 5.4 Procesar respuesta y dibujar filas
                    if (data.ok && data.historial && data.historial.length > 0) {
                        data.historial.forEach(item => {
                            const tr = document.createElement('tr');
                            // Identificar color según origen
                            const isCaja = item.origen.toLowerCase().includes('caja') || item.origen.toLowerCase().includes('efectivo');
                            const colorBadge = isCaja ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-subtle text-primary border-primary-subtle';
                            
                            tr.innerHTML = `
                                <td class="ps-4 text-start fw-medium text-dark" style="font-size: 0.9rem;">
                                    <i class="bi bi-calendar2-check text-muted me-1"></i> ${item.fecha}
                                </td>
                                <td><span class="badge ${colorBadge} border">${item.origen}</span></td>
                                <td class="pe-4 text-end fw-bold text-success">S/ ${parseFloat(item.monto).toFixed(2)}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-1 text-light"></i>Aún no hay descuentos ni devoluciones registradas.</td></tr>`;
                    }
                } catch (error) {
                    console.error('Error al cargar historial:', error);
                    tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error de conexión al cargar los datos.</td></tr>`;
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarModuloAdelantos);
    } else {
        iniciarModuloAdelantos();
    }
})();