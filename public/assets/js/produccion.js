/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes)
 * Archivo actualizado para manejo multi-almacén, división de filas, semáforos,
 * auto-generación de Código OP, UX de Acordeones (Master-Detail) y Reglas de Ítem.
 * Modo: Consumo Teórico Estricto (Strict Backflushing)
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
            
            // Solo seleccionamos las filas principales (las que tienen data-estado)
            const mainRows = table.querySelectorAll('tbody tr[data-estado]');

            mainRows.forEach(row => {
                const matchText = (row.getAttribute('data-search') || '').toLowerCase().includes(term);
                const matchEstado = estado === '' || (row.getAttribute('data-estado') || '') === estado;
                const isVisible = matchText && matchEstado;

                // Mostramos u ocultamos la fila principal
                row.style.display = isVisible ? '' : 'none';

                // Manejo de la fila hija (Acordeón de Faltantes)
                const nextRow = row.nextElementSibling;
                if (nextRow && nextRow.classList.contains('collapse-faltantes')) {
                    if (!isVisible) {
                        nextRow.style.display = 'none';
                        nextRow.classList.remove('show');
                    } else {
                        nextRow.style.display = '';
                    }
                }
            });
        };

        input.addEventListener('input', filterFn); // Detecta escritura y borrado rápido
        if (select) select.addEventListener('change', filterFn);
    }

    function initTooltips() {
        if (typeof bootstrap === 'undefined') return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
    }

    // =========================================================================
    // 2. ACCIONES DE TABLA Y UX DE ACORDEONES
    // =========================================================================
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

        // --- LÓGICA DE UX PARA LOS ACORDEONES DE FALTANTES ---
        const tablaOrdenes = document.getElementById('tablaOrdenes');
        
        if (tablaOrdenes) {
            // 1. Que al abrir uno, se cierren los demás
            tablaOrdenes.addEventListener('show.bs.collapse', function (e) {
                const abiertos = tablaOrdenes.querySelectorAll('.collapse-faltantes.show');
                abiertos.forEach(abierto => {
                    if (abierto !== e.target && typeof bootstrap !== 'undefined') {
                        const bsCollapse = bootstrap.Collapse.getInstance(abierto);
                        if (bsCollapse) bsCollapse.hide();
                    }
                });
            });
        }

        // 2. Que al hacer clic fuera de la tabla, se cierre el acordeón abierto
        document.addEventListener('click', function(e) {
            const isClickInsideTable = e.target.closest('#tablaOrdenes');
            if (!isClickInsideTable) {
                const acordeonAbierto = document.querySelector('.collapse-faltantes.show');
                if (acordeonAbierto && typeof bootstrap !== 'undefined') {
                    const bsCollapse = bootstrap.Collapse.getInstance(acordeonAbierto);
                    if (bsCollapse) bsCollapse.hide();
                }
            }
        });
    }

    // =========================================================================
    // 3. MODAL DE EJECUCIÓN (Lógica AJAX y Eventos)
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

                // --- NUEVO: Extraer atributos del Item ---
                const reqLote = btnAbrir.getAttribute('data-req-lote') || '0';
                const reqVenc = btnAbrir.getAttribute('data-req-venc') || '0';
                const unidad = btnAbrir.getAttribute('data-unidad') || 'UND';

                // Guardar en inputs ocultos
                document.getElementById('execReqLote').value = reqLote;
                document.getElementById('execReqVenc').value = reqVenc;
                document.getElementById('execUnidad').value = unidad;

                // Establecer la fecha y hora actual por defecto
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                const localDatetime = now.toISOString().slice(0, 16);
                
                document.getElementById('execFechaInicio').value = localDatetime;
                document.getElementById('execFechaFin').value = localDatetime;
                // -------------------------------------------

                const tbodyConsumos = document.querySelector('#tablaConsumosDynamic tbody');
                const tbodyIngresos = document.querySelector('#tablaIngresosDynamic tbody');
                
                tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-primary fw-bold"><div class="spinner-border spinner-border-sm me-2"></div>Buscando stock...</td></tr>';
                tbodyIngresos.innerHTML = '';

                const precheckOk = btnAbrir.getAttribute('data-precheck-ok') === '1';
                const precheckMsg = btnAbrir.getAttribute('data-precheck-msg') || 'Falta stock en planta para ejecutar.';

                // Validamos si hay faltantes antes de abrir
                if (!precheckOk && typeof Swal !== 'undefined') {
                    const confirmacion = await Swal.fire({
                        icon: 'warning',
                        title: 'Insumos insuficientes en Planta',
                        text: 'Insumos insuficientes en Planta. Asegúrate de transferir o regularizar el stock en el sistema para poder guardar esta ejecución. ¿Desea abrir el formulario de todos modos?',
                        footer: 'Revisa el detalle de faltantes en la tabla antes de continuar.',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, abrir formulario',
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
                            addConsumoRow(item, planificada);
                        });
                        recalcularSemaforos(); // Primera evaluación al cargar
                    } else {
                        tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">No se cargaron insumos. Añádalos manualmente.</td></tr>';
                    }
                } catch (error) {
                    console.error("Error AJAX:", error);
                    tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de conexión al obtener receta.</td></tr>';
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
                    // Si eliminan un ingreso, hay que recalcular el total
                    dispararRecalculoTotal();
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

        // --- MAGIA DEL CONSUMO TEÓRICO: RECALCULAR RECETA SI CAMBIA LO PRODUCIDO ---
        function dispararRecalculoTotal() {
            let totalProducido = 0;
            document.querySelectorAll('input[name="ingreso_cantidad[]"]').forEach(inp => {
                totalProducido += parseFloat(inp.value) || 0;
            });

            // Actualizar todas las filas de la Pestaña 1 (Consumos)
            document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada').forEach(tr => {
                const reqUnitario = parseFloat(tr.getAttribute('data-req-unitario')) || 0;
                const inputConsumo = tr.querySelector('input[name="consumo_cantidad[]"]');
                if (inputConsumo) {
                    inputConsumo.value = (reqUnitario * totalProducido).toFixed(4);
                }
            });

            recalcularSemaforos();
        }

        document.addEventListener('input', function(e) {
            // Solo escuchamos cambios en la Pestaña 2 (Ingresos)
            if (e.target.matches('input[name="ingreso_cantidad[]"]')) {
                dispararRecalculoTotal();
            }
        });

        // Si cambia el almacén origen en la Pestaña 1, verificar stock
        document.addEventListener('change', function(e) {
            if (e.target.matches('select[name="consumo_id_almacen[]"]')) {
                recalcularSemaforos();
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            const form = modalEl.querySelector('form');
            if (form) form.reset();
            const firstTab = modalEl.querySelector('.nav-tabs .nav-link');
            if (firstTab && typeof bootstrap !== 'undefined') new bootstrap.Tab(firstTab).show();
            
            const boxJustificacion = document.getElementById('boxJustificacionFaltante');
            if (boxJustificacion) boxJustificacion.style.display = 'none';
        });
    }

    // =========================================================================
    // 4. GENERADORES DE INTERFAZ
    // =========================================================================

    function addConsumoRow(item = null, planificada = 1) {
        const tbody = document.querySelector('#tablaConsumosDynamic tbody');
        const templateAlmacenes = document.getElementById('tplSelectAlmacenes').innerHTML;
        const tr = document.createElement('tr');

        if (item) {
            const requerimientoUnitario = (parseFloat(item.cantidad_calculada) / planificada) || 0;

            tr.setAttribute('data-id-insumo', item.id_insumo);
            tr.setAttribute('data-req-unitario', requerimientoUnitario); // Guardamos la receta unitaria
            tr.classList.add('fila-calculada');

            let optionsHtml = '<option value="">Seleccione almacén...</option>';
            if (item.almacenes && item.almacenes.length > 0) {
                optionsHtml += `<optgroup label="Recomendados (Con Stock)">`;
                item.almacenes.forEach(a => {
                    optionsHtml += `<option value="${a.id}" data-stock="${a.stock_actual}">${a.nombre} (Stock: ${a.stock_actual})</option>`;
                });
                optionsHtml += `</optgroup><optgroup label="Otros (Sin Stock - Forzar)">${templateAlmacenes}</optgroup>`;
            } else {
                optionsHtml += `<optgroup label="⚠ Sin Stock Registrado">${templateAlmacenes}</optgroup>`;
            }

            tr.innerHTML = `
                <td class="align-middle bg-light">
                    <input type="hidden" name="consumo_id_insumo[]" value="${item.id_insumo}">
                    <div class="fw-bold text-dark mb-1">${item.insumo_nombre}</div>
                    <div class="small text-muted"><i class="bi bi-lock-fill"></i> Teórico (Bloqueado)</div>
                </td>
                <td class="align-middle">
                    <select name="consumo_id_almacen[]" class="form-select form-select-sm" required>
                        ${optionsHtml}
                    </select>
                </td>
                <td class="align-middle">
                    <input type="number" step="0.0001" name="consumo_cantidad[]" class="form-control form-control-sm border-2 fw-bold text-center bg-light" value="${item.cantidad_calculada}" readonly tabindex="-1">
                </td>
                <td class="align-middle">
                    <input type="text" name="consumo_id_lote[]" class="form-control form-control-sm" placeholder="Lote (Opc)">
                </td>
                <td class="text-center align-middle">
                    </td>
            `;
        } else {
            // Fila Libre (Por si necesitan meter algo adicional)
            tr.innerHTML = `
                <td class="align-middle">
                    <input type="number" name="consumo_id_insumo[]" class="form-control form-control-sm" placeholder="ID insumo" required>
                </td>
                <td class="align-middle">
                    <select name="consumo_id_almacen[]" class="form-select form-select-sm" required>${templateAlmacenes}</select>
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
        const templateAlmacenes = document.getElementById('tplSelectAlmacenes').innerHTML;
        const tr = document.createElement('tr');

        // Leer reglas del producto de los inputs ocultos
        const reqLote = document.getElementById('execReqLote').value === '1';
        const reqVenc = document.getElementById('execReqVenc').value === '1';
        const unidad = document.getElementById('execUnidad').value || 'UND';

        // Lógica de Lote
        const autoLote = reqLote ? ('L' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' + Math.floor(Math.random() * 90 + 10)) : '';
        const inputLote = reqLote 
            ? `<input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm" value="${autoLote}" required>`
            : `<input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm bg-light text-muted" placeholder="N/A" readonly tabindex="-1">`;

        // Lógica de Vencimiento
        const inputVenc = reqVenc
            ? `<input type="date" name="ingresos_fecha_vencimiento[]" class="form-control form-control-sm border-warning" required>`
            : `<input type="date" name="ingresos_fecha_vencimiento[]" class="form-control form-control-sm bg-light text-muted" readonly tabindex="-1">`;

        tr.innerHTML = `
            <td class="align-middle">
                <select name="ingreso_id_almacen[]" class="form-select form-select-sm" required>
                    ${templateAlmacenes}
                </select>
            </td>
            <td class="align-middle" style="width: 160px;">
                <div class="input-group input-group-sm">
                    <input type="number" step="0.0001" name="ingreso_cantidad[]" class="form-control fw-bold border-success text-end" required value="${cantidadDefecto}">
                    <span class="input-group-text bg-light text-muted fw-bold">${unidad}</span>
                </div>
            </td>
            <td class="align-middle">
                ${inputLote}
            </td>
            <td class="align-middle">
                ${inputVenc}
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm text-danger border-0 js-remove-row"><i class="bi bi-trash fs-5"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    // =========================================================================
    // 5. LÓGICA DE SEMÁFOROS (Tráfico Light - Bloqueo Estricto)
    // =========================================================================
    function recalcularSemaforos() {
        const filas = document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada');
        let faltaStockFisico = false;

        filas.forEach(tr => {
            const inputElement = tr.querySelector('input[name="consumo_cantidad[]"]');
            const cantRequerida = parseFloat(inputElement.value) || 0;
            
            const selectEl = tr.querySelector('select[name="consumo_id_almacen[]"]');
            const optionSel = selectEl.options[selectEl.selectedIndex];
            const stockDisp = (optionSel && optionSel.hasAttribute('data-stock')) ? parseFloat(optionSel.getAttribute('data-stock')) : null;

            // Limpiamos estilos previos
            inputElement.classList.remove('border-danger', 'bg-danger-subtle', 'border-success', 'text-danger', 'text-success');
            inputElement.classList.add('bg-light');

            // BLOQUEO ROJO: Si intenta sacar más de lo que físicamente hay en ese almacén
            if (stockDisp !== null && cantRequerida > stockDisp) {
                faltaStockFisico = true;
                inputElement.classList.add('border-danger', 'bg-danger-subtle', 'text-danger');
                inputElement.classList.remove('bg-light');
            } else if (stockDisp !== null) {
                inputElement.classList.add('border-success', 'text-success');
            }
        });

        const boxJustificacion = document.getElementById('boxJustificacionFaltante');
        const btnGuardar = document.querySelector('#formEjecutarOrden button[type="submit"]');
        
        if (!boxJustificacion || !btnGuardar) return;

        if (faltaStockFisico) {
            // BLOQUEO ESTRICTO
            btnGuardar.disabled = true;
            boxJustificacion.className = 'alert alert-danger mt-3 mb-0';
            boxJustificacion.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="bi bi-x-circle-fill fs-4 me-3 mt-1"></i>
                    <div class="w-100">
                        <h6 class="fw-bold mb-1">Producción Bloqueada: Stock Insuficiente</h6>
                        <p class="small mb-0">La cantidad que deseas producir exige más insumos de los que existen actualmente en el almacén de planta seleccionado. <strong>Transfiere stock a la planta</strong> o ajusta lo producido para poder guardar.</p>
                    </div>
                </div>
            `;
            boxJustificacion.style.display = 'block';
        } else {
            // TODO OK
            btnGuardar.disabled = false;
            boxJustificacion.style.display = 'none';
        }
    }

    // =========================================================================
    // 6. MODAL DE PLANIFICACIÓN (Generador de Código OP)
    // =========================================================================
    function initModalPlanificacion() {
        const modalPlanificar = document.getElementById('modalPlanificarOP'); 
        if (!modalPlanificar) return;

        modalPlanificar.addEventListener('show.bs.modal', function () {
            const inputCodigo = document.getElementById('newCodigoOP'); 
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