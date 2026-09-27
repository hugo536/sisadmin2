/**
 * LÓGICA PARA ADELANTOS (TESORERÍA)
 * Archivo: public/assets/js/tesoreria/adelantos.js
 */

(function() {
    'use strict';

    // Función auxiliar para bloquear botones y evitar doble clic
    function bloquearBotonSubmit(form, textoCarga = "Procesando...") {
        const btnSubmit = form.querySelector('button[type="submit"]');
        if (btnSubmit) {
            if (!btnSubmit.dataset.originalHtml) btnSubmit.dataset.originalHtml = btnSubmit.innerHTML;
            btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${textoCarga}`;
            btnSubmit.classList.add('disabled');
            setTimeout(() => { btnSubmit.disabled = true; }, 10);
        }
    }

    function iniciarModuloAdelantos() {
        const appContenedor = document.getElementById('adelantosApp');
        if (!appContenedor || appContenedor.dataset.iniciado === '1') return;
        appContenedor.dataset.iniciado = '1';

        // ==============================================================
        // 1. BUSCADOR EN TIEMPO REAL
        // ==============================================================
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

        // ==============================================================
        // 2. MODAL: DEVOLUCIÓN DE DINERO
        // ==============================================================
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

            const formDevolver = document.getElementById('formDevolverAdelanto');
            if (formDevolver) {
                formDevolver.addEventListener('submit', function () {
                    if (this.checkValidity()) bloquearBotonSubmit(this, "Registrando...");
                });
            }
        }

        // ==============================================================
        // 3. MODAL: NUEVO ADELANTO (TomSelect + SweetAlert)
        // ==============================================================
        const modalNuevoAdelanto = document.getElementById('modalNuevoAdelanto');
        if (modalNuevoAdelanto) {
            // Inicializar TomSelect solo cuando el modal sea visible
            modalNuevoAdelanto.addEventListener('shown.bs.modal', function () {
                const selectEmpleado = document.getElementById('selectEmpleado');
                if (selectEmpleado && !selectEmpleado.tomselect) {
                    window.AppSelects.initLocal('#selectEmpleado', {
                        placeholder: 'Buscar y seleccionar trabajador...',
                        dropdownParent: 'body'
                    });
                }
            });

            const formNuevoAdelanto = document.getElementById('formNuevoAdelanto');
            if (formNuevoAdelanto) {
                formNuevoAdelanto.addEventListener('submit', function (e) {
                    e.preventDefault(); 
                    
                    if (!this.checkValidity()) {
                        this.reportValidity();
                        return;
                    }

                    const selectEl = document.getElementById('selectEmpleado');
                    const empleadoNombre = selectEl.options[selectEl.selectedIndex]?.text || 'el empleado';
                    const monto = this.querySelector('input[name="monto"]').value;

                    // SweetAlert de Confirmación
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: '¿Confirmar Desembolso?',
                            html: `Vas a entregar <b>S/ ${parseFloat(monto).toFixed(2)}</b> a <b>${empleadoNombre}</b>.<br><br>El dinero saldrá de Tesorería en este momento y se descontará en su próxima planilla.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#198754',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Sí, entregar dinero',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                bloquearBotonSubmit(formNuevoAdelanto, "Procesando...");
                                HTMLFormElement.prototype.submit.call(formNuevoAdelanto);
                            }
                        });
                    } else {
                        // Fallback nativo
                        if (confirm(`¿Entregar S/ ${monto} a ${empleadoNombre}?`)) {
                            bloquearBotonSubmit(formNuevoAdelanto, "Procesando...");
                            HTMLFormElement.prototype.submit.call(formNuevoAdelanto);
                        }
                    }
                });
            }
        }
        
        // ==============================================================
        // 4. HISTORIAL DE PAGOS (VER DETALLES AJAX)
        // ==============================================================
        const modalVerDetalle = document.getElementById('modalVerDetalle');
        if (modalVerDetalle) {
            let historialController = null;

            modalVerDetalle.addEventListener('show.bs.modal', async function (event) {
                const button = event.relatedTarget?.closest('.btn-detalles');
                const idAdelanto = button?.getAttribute('data-id');
                const nombreEmpleado = button?.getAttribute('data-empleado') || '--';

                const spanNombre = document.getElementById('detNombreEmpleado');
                if (spanNombre) spanNombre.textContent = nombreEmpleado;

                const tbody = document.getElementById('bodyHistorialAdelanto');
                if (!tbody) return;

                if (!button || !idAdelanto) {
                    tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>No se pudo identificar el adelanto.</td></tr>`;
                    return;
                }

                tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando historial...</td></tr>`;

                let timeoutId = null;
                try {
                    const baseUrl = window.BASE_URL || ''; 
                    const url = `${baseUrl}?ruta=tesoreria/adelantos&accion=historial&id=${idAdelanto}&_t=${new Date().getTime()}`;
                    
                    historialController?.abort();
                    historialController = new AbortController();
                    const requestController = historialController;
                    timeoutId = window.setTimeout(() => requestController.abort(), 10000);
                    
                    const resp = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        signal: requestController.signal
                    });
                    
                    if (!resp.ok) throw new Error(`Respuesta HTTP ${resp.status}`);
                    const data = await resp.json();

                    tbody.innerHTML = ''; 

                    if (data.ok && data.historial && data.historial.length > 0) {
                        data.historial.forEach(item => {
                            const tr = document.createElement('tr');
                            const isCaja = item.origen.toLowerCase().includes('caja') || item.origen.toLowerCase().includes('efectivo');
                            const colorBadge = isCaja ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-subtle text-primary border-primary-subtle';
                            
                            tr.innerHTML = `
                                <td class="ps-4 text-start fw-medium text-dark text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-calendar2-check text-muted me-2"></i>${item.fecha}
                                </td>
                                <td class="text-start py-3">
                                    <span class="badge ${colorBadge} border px-3 py-2 text-wrap text-start lh-sm" style="font-size: 0.75rem; max-width: 220px;">
                                        ${item.origen}
                                    </span>
                                </td>
                                <td class="pe-4 text-end fw-bold text-success text-nowrap" style="font-size: 0.9rem;">
                                    S/ ${parseFloat(item.monto).toFixed(2)}
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-1 text-light"></i>Aún no hay descuentos ni devoluciones registradas.</td></tr>`;
                    }
                } catch (error) {
                    console.error('Error al cargar historial:', error);
                    const mensaje = error.name === 'AbortError'
                        ? 'La consulta tardó demasiado. Vuelve a intentarlo.'
                        : 'Error de conexión al cargar los datos.';
                    tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>${mensaje}</td></tr>`;
                } finally {
                    if (timeoutId !== null) window.clearTimeout(timeoutId);
                }
            });

            modalVerDetalle.addEventListener('hidden.bs.modal', () => {
                historialController?.abort();
                historialController = null;
                const tbody = document.getElementById('bodyHistorialAdelanto');
                if (tbody) tbody.innerHTML = '';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarModuloAdelantos);
    } else {
        iniciarModuloAdelantos();
    }
})();