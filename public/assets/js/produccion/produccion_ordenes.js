/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes)
 * Archivo actualizado para manejo multi-almacén, división de filas, semáforos,
 * auto-generación de Código OP, UX de Acordeones (Master-Detail) y Reglas de Ítem.
 * Incluye: Planificador de Operaciones (Calendario).
 * Modo: Costeo Estándar por Centro de Trabajo (Tarifa de Planta Automática)
 * FIX: Compatible con Navegación SPA (Fetch)
 */

const initModuloProduccion = () => {
    const safeInit = (name, fn) => {
        try {
            fn();
        } catch (error) {
            console.error(`[Producción] Error inicializando ${name}:`, error);
        }
    };

    // 1. Inicializamos siempre (UI local de la vista actual)
    safeInit('filtros de órdenes', initFiltrosOrdenes);
    safeInit('tooltips', initTooltips);
    safeInit('modal de planificación', initModalPlanificacion);
    safeInit('planificador de operaciones', initPlanificadorOperaciones);

    // 2. Inicializamos SOLO UNA VEZ los eventos globales para evitar duplicados en SPA
    if (!window.produccionEventosGlobalesAtados) {
        safeInit('acciones de tabla', initAccionesTabla);
        safeInit('modal de ejecución', initModalEjecucion);
        window.produccionEventosGlobalesAtados = true;
    }
};

// =========================================================================
// LANZADOR INTELIGENTE (Detecta carga normal vs carga SPA)
// =========================================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModuloProduccion);
} else {
    initModuloProduccion();
}

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
                const idOrden = Number(btnDetalle.getAttribute('data-id') || 0);
                const real = parseFloat(btnDetalle.getAttribute('data-real') || '0') || 0;
                const totalRealVal = parseFloat(btnDetalle.getAttribute('data-total-real') || '0') || 0;
                
                document.getElementById('detalleCodigo').textContent = btnDetalle.getAttribute('data-codigo') || '-';
                document.getElementById('detalleProducto').textContent = btnDetalle.getAttribute('data-producto') || '-';
                document.getElementById('detallePlan').textContent = btnDetalle.getAttribute('data-plan') || '0.0000';
                document.getElementById('detalleReal').textContent = btnDetalle.getAttribute('data-real') || '0.0000';
                
                const mdReal = document.getElementById('detalleMdReal');
                const modReal = document.getElementById('detalleModReal');
                const cifReal = document.getElementById('detalleCifReal');
                const totalReal = document.getElementById('detalleTotalReal');
                const unitarioReal = document.getElementById('detalleUnitarioReal');
                
                if (mdReal) mdReal.textContent = btnDetalle.getAttribute('data-md-real') || '0.0000';
                if (modReal) modReal.textContent = btnDetalle.getAttribute('data-mod-real') || '0.0000';
                if (cifReal) cifReal.textContent = btnDetalle.getAttribute('data-cif-real') || '0.0000';
                if (totalReal) totalReal.textContent = btnDetalle.getAttribute('data-total-real') || '0.0000';
                if (unitarioReal) unitarioReal.textContent = (real > 0 ? (totalRealVal / real) : 0).toFixed(4);

                const badge = document.getElementById('detalleEstado');
                if (badge) {
                    badge.textContent = estado;
                    badge.className = `badge ${estado === 'Ejecutada' ? 'bg-success' : 'bg-danger'}`;
                }

                const fillTable = (id, rows, emptyCols, mapper) => {
                    const tbody = document.getElementById(id);
                    if (!tbody) return;
                    if (!Array.isArray(rows) || rows.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="${emptyCols}" class="text-center text-muted">Sin datos</td></tr>`;
                        return;
                    }
                    tbody.innerHTML = rows.map(mapper).join('');
                };

                const fd = new FormData();
                fd.append('accion', 'obtener_detalle_costos_ajax');
                fd.append('id_orden', String(idOrden));

                fetch(window.location.href, { method: 'POST', body: fd })
                    .then((r) => r.json())
                    .then((resp) => {
                        const data = (resp && resp.success && resp.data) ? resp.data : { materiales: [], mod: [], cif: [] };
                        fillTable('detalleTablaMd', data.materiales, 4, (r) => `<tr><td>${r.item_nombre || '-'}</td><td class="text-end"><div>${Number(r.cantidad || 0).toFixed(4)}</div><div class="text-muted small">Real: ${Number(r.cantidad_real || 0).toFixed(4)}</div></td><td class="text-end">S/ ${Number(r.costo_unitario || 0).toFixed(4)}</td><td class="text-end">S/ ${Number(r.costo_total || 0).toFixed(4)}</td></tr>`);
                        fillTable('detalleTablaMod', data.mod, 4, (r) => `<tr><td>${r.empleado || ('ID ' + (r.id_empleado || '-'))}</td><td class="text-end">${Number(r.horas_reales || 0).toFixed(4)}</td><td class="text-end">S/ ${Number(r.costo_hora_real || 0).toFixed(4)}</td><td class="text-end">S/ ${Number(r.costo_total_mod || 0).toFixed(4)}</td></tr>`);
                        fillTable('detalleTablaCif', data.cif, 3, (r) => `<tr><td>${r.concepto || '-'}</td><td>${r.base_distribucion || '-'}</td><td class="text-end">S/ ${Number(r.costo_aplicado || 0).toFixed(4)}</td></tr>`);
                    })
                    .catch(() => {
                        fillTable('detalleTablaMd', [], 4, () => '');
                        fillTable('detalleTablaMod', [], 4, () => '');
                        fillTable('detalleTablaCif', [], 3, () => '');
                    });

                modalDetalle.show();
            } else {
                console.error("El modal de detalle ('modalDetalleOP') no se encontró en el DOM.");
            }
        }
    });

    // Se asigna al document para que no se pierda al recargar la tabla en SPA
    document.addEventListener('show.bs.collapse', function (e) {
        if (e.target.classList.contains('collapse-faltantes')) {
            const tablaOrdenes = document.getElementById('tablaOrdenes');
            if(tablaOrdenes) {
                const abiertos = tablaOrdenes.querySelectorAll('.collapse-faltantes.show');
                abiertos.forEach(abierto => {
                    if (abierto !== e.target && typeof bootstrap !== 'undefined') {
                        const bsCollapse = bootstrap.Collapse.getInstance(abierto);
                        if (bsCollapse) bsCollapse.hide();
                    }
                });
            }
        }
    }, true);

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
// 3. MODAL DE EJECUCIÓN (Lógica AJAX y Eventos de Tiempo)
// =========================================================================
function initModalEjecucion() {
    let modalEjecutar;

    // Función global para calcular el tiempo
    window.calcularTiempoNetoOP = function() {
        const inputInicio = document.getElementById('execFechaInicio');
        const inputFin = document.getElementById('execFechaFin');
        const inputParada = document.getElementById('execHorasParada');
        const lblTiempoNeto = document.getElementById('lblTiempoNeto');
        
        if (!inputInicio || !inputFin || !lblTiempoNeto) return;
        
        if (!inputInicio.value || !inputFin.value) {
            lblTiempoNeto.innerHTML = `0.00 <small class="fs-6 text-muted fw-normal">Horas</small>`;
            return;
        }

        const f1 = new Date(inputInicio.value);
        const f2 = new Date(inputFin.value);
        
        if (f2 > f1) {
            let horas = (f2 - f1) / (1000 * 60 * 60);
            let paradas = parseFloat(inputParada ? inputParada.value : 0) || 0;
            let neto = Math.max(0, horas - paradas);
            lblTiempoNeto.innerHTML = `${neto.toFixed(2)} <small class="fs-6 text-muted fw-normal">Horas</small>`;
        } else {
            lblTiempoNeto.innerHTML = `0.00 <small class="fs-6 text-muted fw-normal">Horas</small>`;
        }
    };

    document.addEventListener('change', function(e) {
        if (e.target.id === 'execFechaInicio' || e.target.id === 'execFechaFin') {
            window.calcularTiempoNetoOP();
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.id === 'execHorasParada') {
            window.calcularTiempoNetoOP();
        }
    });

    document.addEventListener('click', async function(e) {
        
        const btnAbrir = e.target.closest('.js-abrir-ejecucion');
        if (btnAbrir) {
            const modalEl = document.getElementById('modalEjecutarOP');
            if (!modalEl || typeof bootstrap === 'undefined') return;

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

            // Setup Fechas
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const localDatetime = now.toISOString().slice(0, 16);
            
            const inputInicio = document.getElementById('execFechaInicio');
            const inputFin = document.getElementById('execFechaFin');
            const inputParada = document.getElementById('execHorasParada');

            if (inputInicio) inputInicio.value = localDatetime;
            if (inputFin) inputFin.value = localDatetime;
            if (inputParada) inputParada.value = '';
            window.calcularTiempoNetoOP();

            const tbodyConsumos = document.querySelector('#tablaConsumosDynamic tbody');
            const tbodyIngresos = document.querySelector('#tablaIngresosDynamic tbody');
            
            if(tbodyConsumos) tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-primary fw-bold"><div class="spinner-border spinner-border-sm me-2"></div>Buscando stock...</td></tr>';
            if(tbodyIngresos) tbodyIngresos.innerHTML = '';
            
            const precheckOk = btnAbrir.getAttribute('data-precheck-ok') === '1';

            if (!precheckOk && typeof Swal !== 'undefined') {
                const confirmacion = await Swal.fire({
                    icon: 'warning',
                    title: 'Insumos insuficientes en Planta',
                    text: 'Insumos insuficientes en Planta. Asegúrate de transferir o regularizar el stock en el sistema para poder guardar esta ejecución. ¿Desea abrir el formulario de todos modos?',
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
                if(tbodyConsumos) tbodyConsumos.innerHTML = ''; 

                if (result.success && result.data.length > 0) {
                    result.data.forEach(item => {
                        addConsumoRow(item, planificada);
                    });
                    recalcularSemaforos(); 
                } else {
                    if(tbodyConsumos) tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">No se cargaron insumos. Añádalos manualmente.</td></tr>';
                }
            } catch (error) {
                console.error("Error AJAX:", error);
                if(tbodyConsumos) tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de conexión al obtener receta.</td></tr>';
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

    // Delegamos el reset al document para el modal de ejecución
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target.id === 'modalEjecutarOP') {
            const form = e.target.querySelector('form');
            if (form) form.reset();
            const firstTab = e.target.querySelector('.nav-tabs .nav-link');
            if (firstTab && typeof bootstrap !== 'undefined') new bootstrap.Tab(firstTab).show();
            
            const boxJustificacion = document.getElementById('boxJustificacionFaltante');
            if (boxJustificacion) boxJustificacion.style.display = 'none';
            
            const lblTiempoNeto = document.getElementById('lblTiempoNeto');
            if (lblTiempoNeto) lblTiempoNeto.innerHTML = `0.00 <small class="fs-6 text-muted fw-normal">Horas</small>`;
        }
    });
}

// =========================================================================
// 4. GENERADORES DE INTERFAZ
// =========================================================================
function addConsumoRow(item = null, planificada = 1) {
    const tbody = document.querySelector('#tablaConsumosDynamic tbody');
    if (!tbody) return;
    const templateAlmacenes = document.getElementById('tplSelectAlmacenes')?.innerHTML || '';
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
    if (!tbody) return;
    const templateAlmacenes = document.getElementById('tplSelectAlmacenes')?.innerHTML || '';
    const tr = document.createElement('tr');

    const execReqLote = document.getElementById('execReqLote');
    const execReqVenc = document.getElementById('execReqVenc');
    const execUnidad = document.getElementById('execUnidad');

    const reqLote = execReqLote ? execReqLote.value === '1' : false;
    const reqVenc = execReqVenc ? execReqVenc.value === '1' : false;
    const unidad = execUnidad ? execUnidad.value : 'UND';

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
// 5. LÓGICA DE SEMÁFOROS
// =========================================================================
function recalcularSemaforos() {
    const filas = document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada');
    let faltaStockFisico = false;

    filas.forEach(tr => {
        const inputElement = tr.querySelector('input[name="consumo_cantidad[]"]');
        if (!inputElement) return;
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
                    <p class="small mb-0">La cantidad que deseas producir exige más insumos de los que existen actualmente en el almacén de planta seleccionado.</p>
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
// 6. MODAL DE PLANIFICACIÓN Y GUARDADO AJAX (MOTOR MRP - MULTINIVEL)
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
    
    // LÓGICA PARA AUTO-CALCULAR CANTIDAD VS HORAS
    const radioModo = document.querySelectorAll('input[name="modo_planificacion"]');
    const inputCant = document.getElementById('newCantPlan');
    const inputHoras = document.getElementById('newHorasPlan');
    const selectReceta = document.getElementById('newRecetaOP');

    function calcularPlanificacion() {
        if (!selectReceta || !selectReceta.value || !inputCant || !inputHoras) return;
        
        const option = selectReceta.options[selectReceta.selectedIndex];
        const rendimiento = parseFloat(option.getAttribute('data-rendimiento')) || 1;
        const tiempo = parseFloat(option.getAttribute('data-tiempo')) || 1;
        
        const modoSeleccionado = document.querySelector('input[name="modo_planificacion"]:checked');
        if (!modoSeleccionado) return;
        const modo = modoSeleccionado.value;
        
        if (modo === 'cantidad') {
            const cant = parseFloat(inputCant.value) || 0;
            const horasCalculadas = (cant / rendimiento) * tiempo;
            inputHoras.value = horasCalculadas > 0 ? horasCalculadas.toFixed(4) : '';
        } else {
            const horas = parseFloat(inputHoras.value) || 0;
            const cantCalculada = (horas / tiempo) * rendimiento;
            inputCant.value = cantCalculada > 0 ? cantCalculada.toFixed(4) : '';
        }
    }

    radioModo.forEach(radio => {
        radio.addEventListener('change', (e) => {
            const modo = e.target.value;
            if (modo === 'cantidad') {
                inputCant.readOnly = false;
                inputCant.classList.remove('bg-light');
                inputHoras.readOnly = true;
                inputHoras.classList.add('bg-light');
                inputCant.focus();
            } else {
                inputHoras.readOnly = false;
                inputHoras.classList.remove('bg-light');
                inputCant.readOnly = true;
                inputCant.classList.add('bg-light');
                inputHoras.focus();
            }
            calcularPlanificacion();
        });
    });

    if (inputCant) inputCant.addEventListener('input', calcularPlanificacion);
    if (inputHoras) inputHoras.addEventListener('input', calcularPlanificacion);
    if (selectReceta) selectReceta.addEventListener('change', calcularPlanificacion);

    // INTERCEPTAR EL FORMULARIO DE NUEVA OP (Motor de Explosión MRP)
    const form = modalPlanificar.querySelector('form');
    if (form) {
        // Removemos cualquier listener previo para evitar duplicados en SPA
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        newForm.addEventListener('submit', async function (e) {
            e.preventDefault(); 

            const btnSubmit = newForm.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando y analizando...';
            btnSubmit.disabled = true;

            try {
                // PASO 1: Guardar la Orden Principal (Padre)
                const formData = new FormData(newForm);
                formData.set('accion', 'crear_orden_ajax'); 

                const resCrear = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json());

                if (!resCrear.success) {
                    Swal.fire('Error', resCrear.message || 'No se pudo crear la orden.', 'error');
                    return;
                }

                const idOrdenPadre = resCrear.id_orden;

                // PASO 2: Preguntar al backend si faltan semielaborados
                const formDataAnalisis = new FormData();
                formDataAnalisis.append('accion', 'analizar_subordenes_ajax');
                formDataAnalisis.append('id_orden', idOrdenPadre);

                const resAnalisis = await fetch(window.location.href, {
                    method: 'POST',
                    body: formDataAnalisis,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json());

                // PASO 3: Evaluar la respuesta del Asistente
                if (resAnalisis.success && resAnalisis.data && resAnalisis.data.length > 0) {
                    
                    let listaHtml = '<ul class="text-start mt-3 mb-0 text-muted" style="font-size: 0.9rem;">';
                    resAnalisis.data.forEach(f => {
                        listaHtml += `<li class="mb-1"><i class="bi bi-box-seam me-2"></i><b>${f.insumo_nombre}</b>: Faltan <span class="text-danger fw-bold">${f.cantidad_faltante}</span></li>`;
                    });
                    listaHtml += '</ul>';

                    const confirmacion = await Swal.fire({
                        icon: 'info',
                        title: 'Faltan Semielaborados',
                        html: `<p class="mb-1">Para cumplir con esta orden, no hay stock suficiente de los siguientes productos intermedios:</p>
                               ${listaHtml}
                               <div class="mt-3 p-2 bg-light border rounded text-dark small fw-medium">¿Deseas que el sistema genere automáticamente los borradores de las sub-órdenes para estos faltantes?</div>`,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-magic me-1"></i> Sí, generar sub-órdenes',
                        cancelButtonText: 'No, lo haré manual',
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d'
                    });

                    // PASO 4: Si el jefe dice SÍ, generamos las hijas
                    if (confirmacion.isConfirmed) {
                        Swal.fire({ title: 'Generando cadena de producción...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        
                        const fdGenerar = new FormData();
                        fdGenerar.append('accion', 'generar_subordenes_ajax');
                        fdGenerar.append('id_orden_padre', idOrdenPadre);

                        const resGenerar = await fetch(window.location.href, {
                            method: 'POST',
                            body: fdGenerar,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(r => r.json());

                        if (resGenerar.success) {
                            await Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'La orden y sus sub-órdenes se generaron correctamente.', timer: 2000, showConfirmButton: false });
                        } else {
                            await Swal.fire('Error', 'Se creó la OP principal, pero hubo un problema al generar las sub-órdenes.', 'error');
                        }
                    } else {
                        await Swal.fire({ icon: 'success', title: '¡Guardado!', text: 'Orden principal planificada correctamente.', timer: 1500, showConfirmButton: false });
                    }
                } else {
                    await Swal.fire({ icon: 'success', title: '¡Guardado!', text: 'Orden planificada. Stock de semielaborados suficiente.', timer: 1500, showConfirmButton: false });
                }

                // FINAL: Refrescar la vista
                bootstrap.Modal.getInstance(modalPlanificar).hide();
                
                const modalPlanificadorEl = document.getElementById('modalPlanificadorProduccion');
                if (modalPlanificadorEl && modalPlanificadorEl.classList.contains('show')) {
                    if (typeof window.cargarGridPlanificador === 'function') {
                        window.cargarGridPlanificador(); 
                    }
                } else {
                    // Refrescamos la vista usando el sistema SPA si está disponible, sino reload clásico
                    if (window.fetch && window.DOMParser) {
                        const url = new URL(window.location.href);
                        // Esto fuerza a main.js a recargar la misma página suavemente
                        document.querySelector(`a[href="${url.pathname}"]`)?.click();
                    } else {
                        window.location.reload();
                    }
                }

            } catch (err) {
                console.error(err);
                Swal.fire('Error', 'Hubo un problema de conexión con el servidor.', 'error');
            } finally {
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
                newForm.reset(); 
            }
        });
    }

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

    const recargarPlanificador = () => {
        planFechaActual = new Date();
        window.cargarGridPlanificador();
    };

    modalPlanificador.addEventListener('show.bs.modal', recargarPlanificador);
    modalPlanificador.addEventListener('shown.bs.modal', () => {
        const lblPlan = document.getElementById('lblPlanActual');
        if (lblPlan && /cargando/i.test(lblPlan.textContent || '')) {
            recargarPlanificador();
        }
    });

    const btnAnterior = document.getElementById('btnPlanAnterior');
    if (btnAnterior) {
        btnAnterior.onclick = () => {
            if (vistaActual === 'mes') {
                planFechaActual.setMonth(planFechaActual.getMonth() - 1);
            } else {
                planFechaActual.setDate(planFechaActual.getDate() - 7);
            }
            window.cargarGridPlanificador();
        };
    }

    const btnSiguiente = document.getElementById('btnPlanSiguiente');
    if (btnSiguiente) {
        btnSiguiente.onclick = () => {
            if (vistaActual === 'mes') {
                planFechaActual.setMonth(planFechaActual.getMonth() + 1);
            } else {
                planFechaActual.setDate(planFechaActual.getDate() + 7);
            }
            window.cargarGridPlanificador();
        };
    }

    document.querySelectorAll('input[name="vistaPlanificador"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            vistaActual = e.target.value;
            window.cargarGridPlanificador();
        });
    });

    window.cargarGridPlanificador = async function() {
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
    };

    function dibujarGrid(primerDia, ultimoDia) {
        const grid = document.getElementById('planificadorGrid');
        let html = '';
        
        const minHeight = vistaActual === 'mes' ? '130px' : '350px';

        if (vistaActual === 'semana') {
            grid.classList.remove('vista-mes');
            grid.classList.add('vista-semana');
        } else {
            grid.classList.remove('vista-semana');
            grid.classList.add('vista-mes');
        }

        if (vistaActual === 'mes') {
            let inicioSemana = primerDia.getDay();
            inicioSemana = inicioSemana === 0 ? 6 : inicioSemana - 1; 
            for (let i = 0; i < inicioSemana; i++) {
                html += `<div class="bg-light rounded opacity-25 d-none d-md-block" style="min-height: ${minHeight}; border: 1px dashed #dee2e6;"></div>`;
            }
        }

        const currentDate = new Date(primerDia);
        while (currentDate <= ultimoDia) {
            const y = currentDate.getFullYear();
            const m = String(currentDate.getMonth() + 1).padStart(2, '0');
            const d = String(currentDate.getDate()).padStart(2, '0');
            const dateStr = `${y}-${m}-${d}`; 
            
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
                        const detalleHtml = registro.detalle.map((op) => {
                            const producto = op.producto || '-';
                            const codigoLimpio = op.codigo ? op.codigo.replace('OP-', '') : '-'; 
                            const cantidad = Number(op.cantidad || 0).toFixed(2);
                            const badgeColor = op.estado === 1 ? 'bg-warning text-dark' : 'bg-secondary text-white';
                            
                            return `<div class="mini-card-op small bg-white border rounded p-2 mt-2 text-start shadow-sm lh-sm border-start border-4 ${op.estado == 1 ? 'border-warning' : 'border-secondary'}" style="min-height: 60px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-primary" style="font-size:0.7rem;"><i class="bi bi-tag-fill me-1"></i>${codigoLimpio}</span>
                                            <span class="badge ${badgeColor} py-1 px-2" style="font-size:0.6rem;">${op.estado === 1 ? 'Proceso' : 'Borrador'}</span>
                                        </div>
                                        <div class="op-product-name" title="${producto}">${producto}</div>
                                        <div class="text-muted mt-1" style="font-size:0.75rem;"><i class="bi bi-box-seam me-1"></i>Plan: <span class="fw-bold text-dark">${cantidad}</span></div>
                                    </div>`;
                        }).join('');

                        htmlContenido += `<div class="w-100 overflow-auto px-1 pb-1 mt-1" style="max-height: 280px; scrollbar-width: thin;">${detalleHtml}</div>`;
                    } else {
                        let resumenHtml = '';
                        let tooltipText = []; 
                        
                        registro.detalle.forEach((op, index) => {
                            const codigoLimpio = op.codigo ? op.codigo.replace('OP-', '') : '-';
                            tooltipText.push(`• ${codigoLimpio}: ${op.producto} (${Number(op.cantidad||0).toFixed(2)})`);
                            
                            if (index < 2) {
                                const bgColor = op.estado === 1 ? 'bg-warning-subtle border-warning text-warning-emphasis' : 'bg-light border-secondary-subtle text-secondary';
                                
                                resumenHtml += `<div class="border rounded px-1 mb-1 text-start ${bgColor}" style="font-size: 0.65rem; padding: 4px 2px;" title="${op.producto}">
                                    <span class="d-block op-product-name-mes fw-bold opacity-100 text-center">${op.producto}</span>
                                </div>`;
                            }
                        });

                        if (registro.detalle.length > 2) {
                            resumenHtml += `<div class="text-muted fw-bold mt-1 text-center" style="font-size: 0.7rem;">+${registro.detalle.length - 2} más...</div>`;
                        }

                        htmlContenido += `<div class="w-100 mt-2 px-1">${resumenHtml}</div>`;
                        tooltipAttr = `data-bs-toggle="tooltip" data-bs-html="true" title="${tooltipText.join('<br>')}"`;
                    }
                } else if (registro.ops > 0) {
                    htmlContenido += `<div class="badge bg-dark mt-2 w-100"><i class="bi bi-gear-fill me-1"></i> ${registro.ops} OPs</div>`;
                }
            }

            html += `
                <div class="card shadow-sm position-relative ${colorClase} cursor-pointer hover-lift transition-all overflow-hidden plan-dia-card" 
                     style="min-height: ${minHeight};" 
                     onclick="accionProgramarOP('${dateStr}')" ${tooltipAttr}>
                    <div class="card-body p-1 p-md-2 d-flex flex-column align-items-center justify-content-start h-100">
                        <span class="fs-6 fw-bold ${textoClase} lh-1 mb-1 w-100 text-center">${diaNum}</span>
                        ${htmlContenido}
                    </div>
                </div>
            `;
            
            currentDate.setDate(currentDate.getDate() + 1);
        }

        grid.innerHTML = html;
        
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(grid.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }
}

window.accionProgramarOP = function(fechaStr) {
    const modalOPEl = document.getElementById('modalPlanificarOP');
    if (!modalOPEl || typeof bootstrap === 'undefined') {
        return;
    }

    const modalOP = bootstrap.Modal.getOrCreateInstance(modalOPEl);
    const inputFecha = document.getElementById('newFechaProgramada');

    if (inputFecha) {
        inputFecha.value = fechaStr;
    }

    modalOP.show();
};

// =========================================================================
// FIX DEFINITIVO PARA MODALES SUPERPUESTOS EN BOOTSTRAP (Z-INDEX)
// (Ejecutar solo una vez por entorno global)
// =========================================================================
if (!window.produccionZIndexFixAplicado) {
    document.addEventListener('show.bs.modal', function (event) {
        const modalsAbiertos = document.querySelectorAll('.modal.show').length;
        const nuevoZIndex = 1060 + (10 * modalsAbiertos);
        
        event.target.style.zIndex = nuevoZIndex;
        
        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop:not(.z-fixed)');
            backdrops.forEach(backdrop => {
                backdrop.style.zIndex = nuevoZIndex - 1;
                backdrop.classList.add('z-fixed');
            });
        }, 10);
    });

    // Fix body lock cuando se cierra un modal sobre otro modal
    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target.id === 'modalPlanificarOP') {
            if (document.getElementById('modalPlanificadorProduccion')?.classList.contains('show')) {
                document.body.classList.add('modal-open');
            }
        }
    });

    window.produccionZIndexFixAplicado = true;
}