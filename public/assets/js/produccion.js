/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes)
 * Archivo actualizado para manejo multi-almacén, división de filas, semáforos
 * y auto-generación de Código OP.
 */

if (!window.produccionJsInitialized) {
    window.produccionJsInitialized = true;

    document.addEventListener('DOMContentLoaded', function() {
        initFiltrosOrdenes();
        initTooltips();
        initAccionesTabla();
        initModalEjecucion();
        initModalPlanificacion();
    });

    // =========================================================================
    // 1. FILTROS DE TABLA DE ÓRDENES
    // =========================================================================
    function initFiltrosOrdenes() {
        const input = document.getElementById('opSearch');
        const select = document.getElementById('opFiltroEstado');
        const table = document.getElementById('tablaOrdenes');
        
        if (!input || !table) return;

        const filterFn = () => {
            const term = input.value.toLowerCase();
            const estado = select ? select.value : '';
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const matchText = (row.getAttribute('data-search') || '').toLowerCase().includes(term);
                const matchEstado = estado === '' || (row.getAttribute('data-estado') || '') === estado;
                row.style.display = (matchText && matchEstado) ? '' : 'none';
            });
        };

        input.addEventListener('keyup', filterFn);
        if (select) select.addEventListener('change', filterFn);
    }


    function initTooltips() {
        if (typeof bootstrap === 'undefined') return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
    }

    function initAccionesTabla() {
        const modalEditarEl = document.getElementById('modalEditarOP');
        const modalDetalleEl = document.getElementById('modalDetalleOP');
        const modalEditar = (modalEditarEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalEditarEl) : null;
        const modalDetalle = (modalDetalleEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalDetalleEl) : null;

        document.addEventListener('click', function(e) {
            const btnEditar = e.target.closest('.js-editar-op');
            if (btnEditar && modalEditar) {
                document.getElementById('editIdOrden').value = btnEditar.getAttribute('data-id') || '';
                document.getElementById('editCantPlan').value = btnEditar.getAttribute('data-cantidad') || '';
                document.getElementById('editFechaProgramada').value = btnEditar.getAttribute('data-fecha') || '';
                document.getElementById('editTurnoProgramado').value = btnEditar.getAttribute('data-turno') || '';
                document.getElementById('editAlmacenPlanta').value = btnEditar.getAttribute('data-id-almacen') || '';
                document.getElementById('editObsOP').value = btnEditar.getAttribute('data-observaciones') || '';
                modalEditar.show();
                return;
            }

            const btnDetalle = e.target.closest('.js-ver-detalle');
            if (btnDetalle && modalDetalle) {
                const estado = btnDetalle.getAttribute('data-estado') || '-';
                document.getElementById('detalleCodigo').textContent = btnDetalle.getAttribute('data-codigo') || '-';
                document.getElementById('detalleProducto').textContent = btnDetalle.getAttribute('data-producto') || '-';
                document.getElementById('detallePlan').textContent = btnDetalle.getAttribute('data-plan') || '0.0000';
                document.getElementById('detalleReal').textContent = btnDetalle.getAttribute('data-real') || '0.0000';
                const badge = document.getElementById('detalleEstado');
                badge.textContent = estado;
                badge.className = `badge ${estado === 'Ejecutada' ? 'bg-success' : 'bg-danger'}`;
                modalDetalle.show();
            }
        });
    }

    // =========================================================================
    // 2. MODAL DE EJECUCIÓN (Lógica AJAX y Eventos)
    // =========================================================================
    function initModalEjecucion() {
        const modalEl = document.getElementById('modalEjecutarOP');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        let modalEjecutar;

        // --- DELEGACIÓN GLOBAL DE CLICS ---
        document.addEventListener('click', async function(e) {
            
            // A. Abrir Modal y Cargar AJAX
            const btnAbrir = e.target.closest('.js-abrir-ejecucion');
            if (btnAbrir) {
                const idOrden = btnAbrir.getAttribute('data-id') || '';
                const codigo = btnAbrir.getAttribute('data-codigo') || '';
                const idReceta = btnAbrir.getAttribute('data-receta') || '';
                const planificada = parseFloat(btnAbrir.getAttribute('data-planificada')) || 0;
                
                document.getElementById('execIdOrden').value = idOrden;
                document.getElementById('lblExecCodigo').textContent = codigo;

                const tbodyConsumos = document.querySelector('#tablaConsumosDynamic tbody');
                const tbodyIngresos = document.querySelector('#tablaIngresosDynamic tbody');
                
                tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-primary fw-bold"><i class="bi bi-arrow-repeat spin"></i> Buscando stock en almacenes...</td></tr>';
                tbodyIngresos.innerHTML = '';

                const precheckOk = btnAbrir.getAttribute('data-precheck-ok') === '1';
                const precheckMsg = btnAbrir.getAttribute('data-precheck-msg') || 'Falta stock en planta para ejecutar.';

                if (!precheckOk && typeof Swal !== 'undefined') {
                    const confirmacion = await Swal.fire({
                        icon: 'warning',
                        title: 'Insumos insuficientes en Planta',
                        text: 'Insumos insuficientes en Planta. La ejecución podría generar stock negativo en la ubicación actual. ¿Desea continuar?',
                        footer: precheckMsg,
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar'
                    });
                    if (!confirmacion.isConfirmed) return;
                }

                try {
                    const formInicio = new FormData();
                    formInicio.append('accion', 'iniciar_ejecucion_ajax');
                    formInicio.append('id_orden', idOrden);
                    const inicioResponse = await fetch(window.location.href, { method: 'POST', body: formInicio });
                    const inicioJson = await inicioResponse.json();
                    if (!inicioJson.success) throw new Error(inicioJson.message || 'No se pudo iniciar la ejecución');
                } catch (errorInicio) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: errorInicio.message || 'No se pudo iniciar la ejecución.' });
                    }
                    return;
                }

                if (!modalEjecutar) modalEjecutar = new bootstrap.Modal(modalEl);
                modalEjecutar.show();

                try {
                    const formData = new FormData();
                    formData.append('accion', 'obtener_receta_ajax');
                    formData.append('id_receta', idReceta);
                    formData.append('cantidad', planificada);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    tbodyConsumos.innerHTML = ''; 

                    if (result.success && result.data.length > 0) {
                        result.data.forEach(item => {
                            addConsumoRow(item);
                        });
                        recalcularSemaforos(); // Primera evaluación al cargar
                    } else {
                        tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">No se cargaron insumos. Añádalos manualmente.</td></tr>';
                    }
                } catch (error) {
                    console.error("Error AJAX:", error);
                    tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de conexión. Añada insumos manualmente.</td></tr>';
                }

                addIngresoRow(planificada);
                return;
            }

            // B. Agregar Fila Extra (Manual)
            if (e.target.closest('#btnAgregarConsumo')) { addConsumoRow(null); return; }
            if (e.target.closest('#btnAgregarIngreso')) { addIngresoRow(); return; }

            // C. Eliminar Fila
            const btnRemove = e.target.closest('.js-remove-row');
            if (btnRemove) {
                const tr = btnRemove.closest('tr');
                if (tr) {
                    tr.remove();
                    recalcularSemaforos(); // Recalcular si se borra una fila dividida
                }
                return;
            }

            // D. Dividir Insumo en múltiples almacenes
            const btnSplit = e.target.closest('.js-split-row');
            if (btnSplit) {
                const trOriginal = btnSplit.closest('tr');
                const trClon = trOriginal.cloneNode(true);
                
                // Limpiar valores del clon para que el usuario los llene
                trClon.querySelector('input[name="consumo_cantidad[]"]').value = '';
                trClon.querySelector('select[name="consumo_id_almacen[]"]').value = '';
                trClon.querySelector('input[name="consumo_id_lote[]"]').value = '';
                
                // Ocultar la insignia de "Req:" y el botón "Dividir" en la fila hija para que se vea más limpio
                const badgeReq = trClon.querySelector('.badge-req');
                const btnDiv = trClon.querySelector('.js-split-row');
                if (badgeReq) badgeReq.style.display = 'none';
                if (btnDiv) btnDiv.style.display = 'none';

                // Insertar justo debajo de la original
                trOriginal.parentNode.insertBefore(trClon, trOriginal.nextSibling);
                recalcularSemaforos();
                return;
            }
        });

        // --- CÁLCULO EN TIEMPO REAL AL ESCRIBIR ---
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name="consumo_cantidad[]"]')) {
                recalcularSemaforos();
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            const form = modalEl.querySelector('form');
            if (form) form.reset();
            const firstTab = modalEl.querySelector('.nav-tabs .nav-link');
            if (firstTab && typeof bootstrap !== 'undefined') new bootstrap.Tab(firstTab).show();
            
            // Ocultar caja de justificación si quedó abierta
            const boxJustificacion = document.getElementById('boxJustificacionFaltante');
            if (boxJustificacion) boxJustificacion.style.display = 'none';
        });
    }

    // =========================================================================
    // 3. GENERADORES DE INTERFAZ
    // =========================================================================

    function addConsumoRow(item = null) {
        const tbody = document.querySelector('#tablaConsumosDynamic tbody');
        const templateAlmacenes = document.getElementById('tplSelectAlmacenes').innerHTML;
        const tr = document.createElement('tr');

        if (item) {
            // Fila proveniente de la Base de Datos (AJAX)
            tr.setAttribute('data-id-insumo', item.id_insumo);
            tr.setAttribute('data-req', item.cantidad_calculada);
            tr.classList.add('fila-calculada');

            // Lógica inteligente de Almacenes
            let optionsHtml = '<option value="">Seleccione almacén...</option>';
            
            if (item.almacenes && item.almacenes.length > 0) {
                optionsHtml += `<optgroup label="Recomendados (Con Stock)">`;
                item.almacenes.forEach(a => {
                    optionsHtml += `<option value="${a.id}">${a.nombre} (Stock: ${a.stock_actual})</option>`;
                });
                optionsHtml += `</optgroup>`;
                optionsHtml += `<optgroup label="Otros (Sin Stock - Forzar)">${templateAlmacenes}</optgroup>`;
            } else {
                optionsHtml += `<optgroup label="⚠ Sin Stock Registrado">${templateAlmacenes}</optgroup>`;
            }

            tr.innerHTML = `
                <td class="align-middle bg-light">
                    <input type="hidden" name="consumo_id_insumo[]" value="${item.id_insumo}">
                    <div class="fw-bold text-dark mb-1">${item.insumo_nombre}</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge-req badge bg-secondary" title="Requerido por Receta">Req: ${item.cantidad_calculada}</span>
                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 js-split-row" title="Extraer de otro almacén adicional"><i class="bi bi-diagram-2"></i> Dividir</button>
                    </div>
                </td>
                <td class="align-middle">
                    <select name="consumo_id_almacen[]" class="form-select form-select-sm" required>
                        ${optionsHtml}
                    </select>
                </td>
                <td class="align-middle">
                    <input type="number" step="0.0001" name="consumo_cantidad[]" class="form-control form-control-sm border-2 fw-bold" placeholder="Ej. 100.5" required value="${item.cantidad_calculada}">
                </td>
                <td class="align-middle">
                    <input type="text" name="consumo_id_lote[]" class="form-control form-control-sm" placeholder="Lote (Opc)">
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm text-danger border-0 js-remove-row" title="Quitar"><i class="bi bi-trash fs-5"></i></button>
                </td>
            `;
        } else {
            // Fila Libre (Agregada manualmente)
            tr.innerHTML = `
                <td class="align-middle">
                    <input type="number" name="consumo_id_insumo[]" class="form-control form-control-sm" placeholder="Escriba ID de insumo" required>
                </td>
                <td class="align-middle">
                    <select name="consumo_id_almacen[]" class="form-select form-select-sm" required>
                        ${templateAlmacenes}
                    </select>
                </td>
                <td class="align-middle">
                    <input type="number" step="0.0001" name="consumo_cantidad[]" class="form-control form-control-sm border-2 fw-bold" required>
                </td>
                <td class="align-middle">
                    <input type="text" name="consumo_id_lote[]" class="form-control form-control-sm" placeholder="Lote (Opc)">
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm text-danger border-0 js-remove-row"><i class="bi bi-trash fs-5"></i></button>
                </td>
            `;
        }

        tbody.appendChild(tr);
    }

    function addIngresoRow(cantidadDefecto = '') {
        const tbody = document.querySelector('#tablaIngresosDynamic tbody');
        // Generamos un número de lote aleatorio amigable por defecto
        const autoLote = 'L' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' + Math.floor(Math.random() * 90 + 10);
        const templateAlmacenes = document.getElementById('tplSelectAlmacenes').innerHTML;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="align-middle">
                <select name="ingreso_id_almacen[]" class="form-select form-select-sm" required>
                    ${templateAlmacenes}
                </select>
            </td>
            <td class="align-middle">
                <input type="number" step="0.0001" name="ingreso_cantidad[]" class="form-control form-control-sm fw-bold border-success" required value="${cantidadDefecto}">
            </td>
            <td class="align-middle">
                <input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm" value="${autoLote}">
            </td>
            <td class="align-middle">
                <input type="date" name="ingresos_fecha_vencimiento[]" class="form-control form-control-sm text-muted">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm text-danger border-0 js-remove-row"><i class="bi bi-trash fs-5"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    // =========================================================================
    // 4. LÓGICA DE SEMÁFOROS (Tráfico Light)
    // =========================================================================
    function recalcularSemaforos() {
        const filas = document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada');
        let agrupado = {};
        let faltaMaterialEnProduccion = false;

        // Tolerancia para evitar errores por decimales ínfimos
        const toleranciaError = 0.001;

        // Agrupar cantidades por ID de insumo (por si dividieron la fila)
        filas.forEach(tr => {
            const idInsumo = tr.getAttribute('data-id-insumo');
            const req = parseFloat(tr.getAttribute('data-req')) || 0;
            const cantInput = parseFloat(tr.querySelector('input[name="consumo_cantidad[]"]').value) || 0;

            if (!agrupado[idInsumo]) {
                agrupado[idInsumo] = { requerida: req, ingresada: 0, elementosTr: [] };
            }
            agrupado[idInsumo].ingresada += cantInput;
            agrupado[idInsumo].elementosTr.push(tr);
        });

        // Evaluar cada insumo
        for (const id in agrupado) {
            const data = agrupado[id];
            const diferencia = data.requerida - data.ingresada;
            
            // Tomamos la primera fila del grupo para actualizar su "Insignia"
            const badge = data.elementosTr[0].querySelector('.badge-req');

            if (diferencia > toleranciaError) {
                // FALTAN INSUMOS (Naranja)
                data.elementosTr.forEach(tr => {
                    tr.querySelector('input[name="consumo_cantidad[]"]').classList.replace('border-success', 'border-warning');
                    tr.querySelector('input[name="consumo_cantidad[]"]').classList.add('bg-warning-subtle');
                });
                if (badge) {
                    badge.className = 'badge-req badge bg-warning text-dark border border-warning';
                    badge.innerHTML = `⚠ Falta declarar: ${diferencia.toFixed(2)}`;
                }
                faltaMaterialEnProduccion = true;
            } else {
                // COMPLETO O EXCEDIDO (Verde)
                data.elementosTr.forEach(tr => {
                    tr.querySelector('input[name="consumo_cantidad[]"]').classList.replace('border-warning', 'border-success');
                    tr.querySelector('input[name="consumo_cantidad[]"]').classList.remove('bg-warning-subtle');
                });
                if (badge) {
                    badge.className = 'badge-req badge bg-success';
                    badge.innerHTML = `✔ Ok (${data.ingresada.toFixed(2)})`;
                }
            }
        }

        // Mostrar / Ocultar caja de justificación en la vista
        const boxJustificacion = document.getElementById('boxJustificacionFaltante');
        const inputJustificacion = document.getElementById('inputJustificacionFaltante');
        
        if (boxJustificacion && inputJustificacion) {
            if (faltaMaterialEnProduccion) {
                boxJustificacion.style.display = 'block';
                inputJustificacion.required = true; // Volverlo obligatorio
            } else {
                boxJustificacion.style.display = 'none';
                inputJustificacion.required = false;
                inputJustificacion.value = '';
            }
        }
    }

    // =========================================================================
    // 5. MODAL DE PLANIFICACIÓN (Generador de Código OP)
    // =========================================================================
    function initModalPlanificacion() {
        const modalPlanificar = document.getElementById('modalPlanificarOP'); // Verifica este ID en tu HTML
        if (!modalPlanificar) return;

        modalPlanificar.addEventListener('show.bs.modal', function () {
            const inputCodigo = document.getElementById('newCodigoOP'); // Verifica este ID en tu HTML
            const now = new Date();
            const yy = String(now.getFullYear()).slice(-2);
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const hh = String(now.getHours()).padStart(2, '0');
            const min = String(now.getMinutes()).padStart(2, '0');
            const sec = String(now.getSeconds()).padStart(2, '0');

            if (inputCodigo) {
                // Generar código único: OP-AAMMDD-HHMMSS
                inputCodigo.value = `OP-${yy}${mm}${dd}-${hh}${min}${sec}`;
                
                // Aseguramos que esté bloqueado (readonly) y tenga fondo gris (bg-light)
                inputCodigo.setAttribute('readonly', 'true');
                inputCodigo.classList.add('bg-light');
            }

            const inputFechaProgramada = document.getElementById('newFechaProgramada');
            if (inputFechaProgramada) {
                inputFechaProgramada.value = `${now.getFullYear()}-${mm}-${dd}`;
            }
        });
        
        // Limpiar el formulario al cerrar el modal (si el usuario cancela)
        modalPlanificar.addEventListener('hidden.bs.modal', function () {
            const form = modalPlanificar.querySelector('form');
            if (form) form.reset();
        });
    }
}