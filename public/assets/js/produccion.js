/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes)
 * Archivo actualizado para manejo multi-almacén, división de filas, semáforos,
 * auto-generación de Código OP, UX de Acordeones (Master-Detail) y Reglas de Ítem.
 * Incluye: Planificador de Operaciones (Calendario Mes/Semana)
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
        initPlanificadorOperaciones(); 
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
            
            const mainRows = table.querySelectorAll('tbody tr[data-estado]');

            mainRows.forEach(row => {
                const matchText = (row.getAttribute('data-search') || '').toLowerCase().includes(term);
                const matchEstado = estado === '' || (row.getAttribute('data-estado') || '') === estado;
                const isVisible = matchText && matchEstado;

                row.style.display = isVisible ? '' : 'none';

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

        input.addEventListener('input', filterFn);
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
        let modalEditar = null;
        let modalDetalle = null;

        document.addEventListener('click', function(e) {
            const btnEditar = e.target.closest('.js-editar-op');
            if (btnEditar) {
                const modalEditarEl = document.getElementById('modalEditarOP');
                
                if (modalEditarEl && typeof bootstrap !== 'undefined') {
                    if (!modalEditar) {
                        modalEditar = new bootstrap.Modal(modalEditarEl);
                    }

                    document.getElementById('editIdOrden').value = btnEditar.getAttribute('data-id') || '';
                    document.getElementById('editCantPlan').value = btnEditar.getAttribute('data-cantidad') || '';
                    document.getElementById('editFechaProgramada').value = btnEditar.getAttribute('data-fecha') || '';
                    document.getElementById('editTurnoProgramado').value = btnEditar.getAttribute('data-turno') || '';
                    document.getElementById('editAlmacenPlanta').value = btnEditar.getAttribute('data-id-almacen') || '';
                    document.getElementById('editObsOP').value = btnEditar.getAttribute('data-observaciones') || '';
                    
                    modalEditar.show();
                } else {
                    console.error("El modal de edición ('modalEditarOP') no se encontró en el DOM.");
                }
                return;
            }

            const btnDetalle = e.target.closest('.js-ver-detalle');
            if (btnDetalle) {
                const modalDetalleEl = document.getElementById('modalDetalleOP');

                if (modalDetalleEl && typeof bootstrap !== 'undefined') {
                    if (!modalDetalle) {
                        modalDetalle = new bootstrap.Modal(modalDetalleEl);
                    }

                    const estado = btnDetalle.getAttribute('data-estado') || '-';
                    document.getElementById('detalleCodigo').textContent = btnDetalle.getAttribute('data-codigo') || '-';
                    document.getElementById('detalleProducto').textContent = btnDetalle.getAttribute('data-producto') || '-';
                    document.getElementById('detallePlan').textContent = btnDetalle.getAttribute('data-plan') || '0.0000';
                    document.getElementById('detalleReal').textContent = btnDetalle.getAttribute('data-real') || '0.0000';
                    
                    const badge = document.getElementById('detalleEstado');
                    if (badge) {
                        badge.textContent = estado;
                        badge.className = `badge ${estado === 'Ejecutada' ? 'bg-success' : 'bg-danger'}`;
                    }

                    modalDetalle.show();
                } else {
                    console.error("El modal de detalle ('modalDetalleOP') no se encontró en el DOM.");
                }
            }
        });

        const tablaOrdenes = document.getElementById('tablaOrdenes');
        
        if (tablaOrdenes) {
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

        document.addEventListener('click', async function(e) {
            
            const btnAbrir = e.target.closest('.js-abrir-ejecucion');
            if (btnAbrir) {
                const idOrden = btnAbrir.getAttribute('data-id') || '';
                const codigo = btnAbrir.getAttribute('data-codigo') || '';
                const idReceta = btnAbrir.getAttribute('data-receta') || '';
                const planificada = parseFloat(btnAbrir.getAttribute('data-planificada')) || 0;
                
                document.getElementById('execIdOrden').value = idOrden;
                document.getElementById('lblExecCodigo').textContent = codigo;

                const reqLote = btnAbrir.getAttribute('data-req-lote') || '0';
                const reqVenc = btnAbrir.getAttribute('data-req-venc') || '0';
                const unidad = btnAbrir.getAttribute('data-unidad') || 'UND';

                document.getElementById('execReqLote').value = reqLote;
                document.getElementById('execReqVenc').value = reqVenc;
                document.getElementById('execUnidad').value = unidad;

                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                const localDatetime = now.toISOString().slice(0, 16);
                
                document.getElementById('execFechaInicio').value = localDatetime;
                document.getElementById('execFechaFin').value = localDatetime;

                const tbodyConsumos = document.querySelector('#tablaConsumosDynamic tbody');
                const tbodyIngresos = document.querySelector('#tablaIngresosDynamic tbody');
                
                tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-primary fw-bold"><div class="spinner-border spinner-border-sm me-2"></div>Buscando stock...</td></tr>';
                tbodyIngresos.innerHTML = '';

                const precheckOk = btnAbrir.getAttribute('data-precheck-ok') === '1';
                const precheckMsg = btnAbrir.getAttribute('data-precheck-msg') || 'Falta stock en planta para ejecutar.';

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
                        recalcularSemaforos(); 
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

            if (e.target.closest('#btnAgregarConsumo')) { addConsumoRow(null); return; }
            if (e.target.closest('#btnAgregarIngreso')) { addIngresoRow(); return; }

            const btnRemove = e.target.closest('.js-remove-row');
            if (btnRemove) {
                const tr = btnRemove.closest('tr');
                if (tr) {
                    tr.remove();
                    dispararRecalculoTotal();
                }
                return;
            }

            const btnSplit = e.target.closest('.js-split-row');
            if (btnSplit) {
                const trOriginal = btnSplit.closest('tr');
                const trClon = trOriginal.cloneNode(true);
                
                trClon.querySelector('input[name="consumo_cantidad[]"]').value = '';
                trClon.querySelector('select[name="consumo_id_almacen[]"]').value = '';
                trClon.querySelector('input[name="consumo_id_lote[]"]').value = '';
                
                const badgeReq = trClon.querySelector('.badge-req');
                const btnDiv = trClon.querySelector('.js-split-row');
                if (badgeReq) badgeReq.style.display = 'none';
                if (btnDiv) btnDiv.style.display = 'none';

                trOriginal.parentNode.insertBefore(trClon, trOriginal.nextSibling);
                recalcularSemaforos();
                return;
            }
        });

        function dispararRecalculoTotal() {
            let totalProducido = 0;
            document.querySelectorAll('input[name="ingreso_cantidad[]"]').forEach(inp => {
                totalProducido += parseFloat(inp.value) || 0;
            });

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
            if (e.target.matches('input[name="ingreso_cantidad[]"]')) {
                dispararRecalculoTotal();
            }
        });

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
            tr.setAttribute('data-req-unitario', requerimientoUnitario); 
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

        const reqLote = document.getElementById('execReqLote').value === '1';
        const reqVenc = document.getElementById('execReqVenc').value === '1';
        const unidad = document.getElementById('execUnidad').value || 'UND';

        const autoLote = reqLote ? ('L' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' + Math.floor(Math.random() * 90 + 10)) : '';
        const inputLote = reqLote 
            ? `<input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm" value="${autoLote}" required>`
            : `<input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm bg-light text-muted" placeholder="N/A" readonly tabindex="-1">`;

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

            inputElement.classList.remove('border-danger', 'bg-danger-subtle', 'border-success', 'text-danger', 'text-success');
            inputElement.classList.add('bg-light');

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
                inputCodigo.value = `OP-${yy}${mm}${dd}-${hh}${min}${sec}`;
                inputCodigo.setAttribute('readonly', 'true');
                inputCodigo.classList.add('bg-light');
            }

            const inputFechaProgramada = document.getElementById('newFechaProgramada');
            if (inputFechaProgramada && !inputFechaProgramada.value) {
                inputFechaProgramada.value = `${now.getFullYear()}-${mm}-${dd}`;
            }
        });
        
        modalPlanificar.addEventListener('hidden.bs.modal', function () {
            const form = modalPlanificar.querySelector('form');
            if (form) form.reset();
        });
    }

    // =========================================================================
    // 7. PLANIFICADOR DE OPERACIONES (CALENDARIO MES / SEMANA)
    // =========================================================================
    function initPlanificadorOperaciones() {
        const modalPlanificador = document.getElementById('modalPlanificadorProduccion');
        if (!modalPlanificador) return; 

        let planFechaActual = new Date(); 
        let vistaActual = 'mes'; 
        let diccPlanificacion = {}; 

        modalPlanificador.addEventListener('show.bs.modal', () => {
            planFechaActual = new Date();
            cargarGridPlanificador();
        });

        document.getElementById('btnPlanAnterior').addEventListener('click', () => {
            if (vistaActual === 'mes') {
                planFechaActual.setMonth(planFechaActual.getMonth() - 1);
            } else {
                planFechaActual.setDate(planFechaActual.getDate() - 7);
            }
            cargarGridPlanificador();
        });

        document.getElementById('btnPlanSiguiente').addEventListener('click', () => {
            if (vistaActual === 'mes') {
                planFechaActual.setMonth(planFechaActual.getMonth() + 1);
            } else {
                planFechaActual.setDate(planFechaActual.getDate() + 7);
            }
            cargarGridPlanificador();
        });

        document.querySelectorAll('input[name="vistaPlanificador"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                vistaActual = e.target.value;
                cargarGridPlanificador();
            });
        });

        async function cargarGridPlanificador() {
            const loader = document.getElementById('planLoader');
            const grid = document.getElementById('planificadorGrid');
            const lblPlan = document.getElementById('lblPlanActual');
            if (!grid || !loader || !lblPlan) return;

            loader.classList.remove('d-none');
            
            let primerDia, ultimoDia;
            const nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

            if (vistaActual === 'mes') {
                const year = planFechaActual.getFullYear();
                const month = planFechaActual.getMonth();
                primerDia = new Date(year, month, 1);
                ultimoDia = new Date(year, month + 1, 0);
                lblPlan.textContent = `${nombresMeses[month]} ${year}`;
            } else {
                primerDia = new Date(planFechaActual);
                const day = primerDia.getDay() || 7; 
                if (day !== 1) primerDia.setHours(-24 * (day - 1)); 
                
                ultimoDia = new Date(primerDia);
                ultimoDia.setDate(ultimoDia.getDate() + 6); 

                const mes1 = nombresMeses[primerDia.getMonth()];
                const mes2 = nombresMeses[ultimoDia.getMonth()];
                lblPlan.innerHTML = `<span class="small">Semana:</span> <br> ${primerDia.getDate()} ${mes1} - ${ultimoDia.getDate()} ${mes2}`;
            }

            const formatDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            const strDesde = formatDate(primerDia);
            const strHasta = formatDate(ultimoDia);

            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_planificador_ajax');
                formData.append('desde', strDesde);
                formData.append('hasta', strHasta);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    diccPlanificacion = resultado.data || {};
                } else {
                    console.error("Error del servidor:", resultado.message);
                    diccPlanificacion = {};
                }
            } catch (e) { 
                console.error("Error crítico de red o PHP:", e); 
                diccPlanificacion = {};
            } 

            dibujarGrid(primerDia, ultimoDia);
            loader.classList.add('d-none');
        }

        // =========================================================
        // AQUI ESTÁ LA MAGIA DE DIBUJAR LAS TARJETAS (MES vs SEMANA)
        // =========================================================
        function dibujarGrid(primerDia, ultimoDia) {
            const grid = document.getElementById('planificadorGrid');
            let html = '';
            
            // Si es semana las celdas son altas, si es mes son más pequeñas
            const minHeight = vistaActual === 'mes' ? '130px' : '350px';

            if (vistaActual === 'mes') {
                let inicioSemana = primerDia.getDay();
                inicioSemana = inicioSemana === 0 ? 6 : inicioSemana - 1; 
                for (let i = 0; i < inicioSemana; i++) {
                    html += `<div class="bg-light rounded opacity-25" style="min-height: ${minHeight}; border: 1px dashed #dee2e6;"></div>`;
                }
            }

            const currentDate = new Date(primerDia);
            while (currentDate <= ultimoDia) {
                const dateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2,'0')}-${String(currentDate.getDate()).padStart(2,'0')}`;
                const diaNum = currentDate.getDate();
                const esDomingo = currentDate.getDay() === 0;
                
                const registro = diccPlanificacion[dateStr];
                
                let colorClase = 'bg-white border-secondary-subtle border-dashed'; 
                let textoClase = esDomingo ? 'text-danger' : 'text-secondary';
                let htmlContenido = '';
                let tooltipAttr = '';

                if (registro) {
                    if(registro.tipo === 'normal') colorClase = 'bg-primary-subtle border-primary text-primary-emphasis';
                    if(registro.tipo === 'excepcion') colorClase = 'bg-warning-subtle border-warning text-warning-emphasis';

                    if (Array.isArray(registro.detalle) && registro.detalle.length > 0) {
                        
                        if (vistaActual === 'semana') {
                            // --- VISTA DE SEMANA: TARJETAS DETALLADAS ---
                            const detalleHtml = registro.detalle.map((op) => {
                                const producto = op.producto || '-';
                                const codigo = op.codigo || '-';
                                const cantidad = Number(op.cantidad || 0).toFixed(2);
                                const badgeColor = op.estado === 1 ? 'bg-warning text-dark' : 'bg-secondary text-white';
                                
                                return `<div class="small bg-white border rounded p-2 mt-2 text-start shadow-sm lh-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-primary" style="font-size:0.75rem;">${codigo}</span>
                                                <span class="badge ${badgeColor} py-1" style="font-size:0.65rem;">${op.estado === 1 ? 'Proceso' : 'Borrador'}</span>
                                            </div>
                                            <div class="fw-semibold text-dark text-truncate" style="font-size:0.8rem;" title="${producto}">${producto}</div>
                                            <div class="text-muted mt-1" style="font-size:0.75rem;"><i class="bi bi-box-seam me-1"></i>Plan: ${cantidad}</div>
                                        </div>`;
                            }).join('');

                            // Agregamos scroll interno para que no se desborde el calendario hacia abajo
                            htmlContenido += `<div class="w-100 overflow-auto px-1 pb-1 mt-1" style="max-height: 280px;">${detalleHtml}</div>`;
                        
                        } else {
                            // --- VISTA DE MES: RESUMEN MINIATURA ---
                            let resumenHtml = '';
                            let tooltipText = []; // Aquí guardaremos los textos para mostrar al pasar el mouse
                            
                            registro.detalle.forEach((op, index) => {
                                tooltipText.push(`• ${op.codigo}: ${op.producto} (${Number(op.cantidad||0).toFixed(2)})`);
                                
                                // Solo mostramos las 2 primeras para no romper el calendario
                                if (index < 2) {
                                    const bgColor = op.estado === 1 ? 'bg-warning-subtle border-warning text-warning-emphasis' : 'bg-light border-secondary-subtle text-secondary';
                                    resumenHtml += `<div class="border rounded px-1 mb-1 text-truncate text-start ${bgColor}" style="font-size: 0.65rem; line-height:1.3;" title="${op.producto}">
                                        <span class="fw-bold">${op.codigo}</span> <span class="d-block text-truncate opacity-75">${op.producto}</span>
                                    </div>`;
                                }
                            });

                            if (registro.detalle.length > 2) {
                                resumenHtml += `<div class="text-muted fw-bold mt-1 text-center" style="font-size: 0.7rem;">+${registro.detalle.length - 2} más...</div>`;
                            }

                            htmlContenido += `<div class="w-100 mt-2 px-1">${resumenHtml}</div>`;
                            
                            // Se activa el Tooltip de Bootstrap
                            tooltipAttr = `data-bs-toggle="tooltip" data-bs-html="true" title="${tooltipText.join('<br>')}"`;
                        }
                    } else if (registro.ops > 0) {
                        htmlContenido += `<div class="badge bg-dark mt-2 w-100"><i class="bi bi-gear-fill me-1"></i> ${registro.ops} OPs</div>`;
                    }
                }

                html += `
                    <div class="card shadow-sm position-relative ${colorClase} cursor-pointer hover-lift transition-all overflow-hidden" 
                         style="min-height: ${minHeight};" 
                         onclick="abrirDiaPlanificador('${dateStr}')" ${tooltipAttr}>
                        <div class="card-body p-1 p-md-2 d-flex flex-column align-items-center justify-content-start h-100">
                            <span class="fs-6 fw-bold ${textoClase} lh-1 mb-1">${diaNum}</span>
                            ${htmlContenido}
                        </div>
                    </div>
                `;
                
                currentDate.setDate(currentDate.getDate() + 1);
            }

            grid.innerHTML = html;
            
            // Inicializar los tooltips que acabamos de crear en el HTML inyectado
            if (typeof bootstrap !== 'undefined') {
                const tooltipTriggerList = [].slice.call(grid.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        }
    }

    // --- CLIC EN UN DÍA DEL PLANIFICADOR ---
    window.abrirDiaPlanificador = function(dateStr) {
        const [y, m, d] = dateStr.split('-');
        const fechaBonita = new Date(y, m - 1, d).toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        Swal.fire({
            title: `<span class="fs-5 text-capitalize text-primary"><i class="bi bi-calendar-event me-2"></i>${fechaBonita}</span>`,
            html: `
                <div class="text-start mt-3">
                    <p class="text-muted small mb-3">¿Qué acción deseas realizar para el <strong>${d}/${m}/${y}</strong>?</p>
                    <div class="d-grid gap-2">
                        <button onclick="accionProgramarOP('${dateStr}')" class="btn btn-outline-dark text-start p-3 fw-semibold hover-lift">
                            <i class="bi bi-plus-circle me-2 text-primary"></i> Programar Nueva OP
                        </button>
                        
                        <button onclick="accionGestionarGrupos('${dateStr}', '${fechaBonita}')" class="btn btn-outline-dark text-start p-3 fw-semibold hover-lift">
                            <i class="bi bi-people me-2 text-info"></i> Asignar / Modificar Grupo de Trabajo
                        </button>
                        
                        <button onclick="accionConfigurarExcepcion('${dateStr}')" class="btn btn-outline-warning text-dark text-start p-3 fw-semibold border-warning hover-lift">
                            <i class="bi bi-clock-history me-2"></i> Configurar Excepción (Horas Extra)
                        </button>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { popup: 'rounded-4' }
        });
    };

    // =========================================================================
    // ACCIONES DE LOS BOTONES DEL SWEET ALERT (PLANIFICADOR)
    // =========================================================================
    
    window.accionProgramarOP = function(fechaStr) {
        Swal.close(); 
        
        const modalPlanificadorEl = document.getElementById('modalPlanificadorProduccion');
        if (modalPlanificadorEl && typeof bootstrap !== 'undefined') {
            const modalPlanificador = bootstrap.Modal.getInstance(modalPlanificadorEl);
            if (modalPlanificador) modalPlanificador.hide();
        }

        setTimeout(() => {
            const modalOPEl = document.getElementById('modalPlanificarOP');
            if (modalOPEl && typeof bootstrap !== 'undefined') {
                const modalOP = bootstrap.Modal.getOrCreateInstance(modalOPEl);
                
                // Limpiamos el valor de la fecha programada y seteamos la nueva
                const inputFecha = document.getElementById('newFechaProgramada');
                if(inputFecha) {
                    inputFecha.value = ''; // Limpiar
                    inputFecha.value = fechaStr; // Setear
                }

                modalOP.show();
            }
        }, 300); 
    };

    window.accionGestionarGrupos = function(fechaStr, fechaBonita) {
        Swal.close(); 
        
        const modalPlanificadorEl = document.getElementById('modalPlanificadorProduccion');
        if (modalPlanificadorEl && typeof bootstrap !== 'undefined') {
            const modalPlanificador = bootstrap.Modal.getInstance(modalPlanificadorEl);
            if (modalPlanificador) modalPlanificador.hide();
        }

        setTimeout(() => {
            const inputFechaVal = document.getElementById('grupoFechaVal');
            const lblFechaBonita = document.getElementById('lblGrupoFechaBonita');
            
            if(inputFechaVal) inputFechaVal.value = fechaStr;
            if(lblFechaBonita) lblFechaBonita.textContent = fechaBonita;
            
            const modalGruposEl = document.getElementById('modalAsignarGrupos');
            if (modalGruposEl && typeof bootstrap !== 'undefined') {
                const modalGrupos = bootstrap.Modal.getOrCreateInstance(modalGruposEl);
                modalGrupos.show();

                console.log("Cargando grupos para el día: " + fechaStr);
            }
        }, 300);
    };

    window.accionConfigurarExcepcion = function(fechaStr) {
        Swal.close();
        Swal.fire({
            icon: 'info',
            title: 'Módulo en proceso',
            text: 'La configuración de horas extras para el día ' + fechaStr + ' se implementará en el siguiente paso.',
            confirmButtonColor: '#0d6efd'
        });
    };

    document.getElementById('modalPlanificarOP')?.addEventListener('hidden.bs.modal', function () {
        if (document.getElementById('modalPlanificadorProduccion')?.classList.contains('show')) {
            document.body.classList.add('modal-open');
        }
    });
    
    document.getElementById('modalAsignarGrupos')?.addEventListener('hidden.bs.modal', function () {
        if (document.getElementById('modalPlanificadorProduccion')?.classList.contains('show')) {
            document.body.classList.add('modal-open');
        }
    });
}