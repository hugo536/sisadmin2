/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Órdenes)
 * VERSIÓN OPTIMIZADA (Zero-Reload, Debounce, Eventos Acotados)
 */

// =========================================================================
// UTILIDADES GLOBALES (Reglas de Optimización)
// =========================================================================
const AppUtils = {
    // Evita la sobrecarga de eventos (tecleo, scroll)
    debounce: function(func, wait = 300) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    },
    // Actualiza la tabla (Temporalmente usa reload clásico hasta ajustar PHP)
    recargarTablaSilencioso: function() {
        // Ponemos un pequeño delay para dar tiempo a que se lean los SweetAlerts
        setTimeout(() => {
            window.location.reload(); 
        }, 300);
    }
};

window.planFechaActual = window.planFechaActual || new Date();
window.vistaActualPlan = window.vistaActualPlan || 'mes';
window.diccPlanificacion = window.diccPlanificacion || {};

window.initModuloProduccion = window.initModuloProduccion || function initModuloProduccion() {
    // Limpieza SPA
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
    document.body.style.overflow = '';

    const safeInit = (name, fn) => {
        try { fn(); } catch (error) { console.error(`[Producción] Error inicializando ${name}:`, error); }
    };

    safeInit('filtros de órdenes', initFiltrosOrdenes);
    safeInit('tooltips', initTooltips);
    safeInit('tom selects', initTomSelects); 
    safeInit('planificador base', initPlanificadorOperaciones); 

    const modalPlanificar = document.getElementById('modalPlanificarOP');
    if (modalPlanificar) {
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
            const form = document.getElementById('formCrearOP');
            if (form) form.reset();
            
            const inputHoras = document.getElementById('newHorasPlan');
            if (inputHoras) inputHoras.value = '';
            
            const tsReceta = document.getElementById('newRecetaOP');
            const tsAlmacen = document.getElementById('newAlmacenPlanta');
            
            if (tsReceta && tsReceta.tomselect) tsReceta.tomselect.clear();
            if (tsAlmacen && tsAlmacen.tomselect) tsAlmacen.tomselect.clear();
            
            if (typeof window.calcularPlanificacionOP === 'function') {
                window.calcularPlanificacionOP();
            }
        });
    }

    if (!window.produccionEventosGlobalesAtados) {
        safeInit('acciones de tabla', initAccionesTabla);
        safeInit('modal de ejecución', initModalEjecucion);
        safeInit('eventos planificador', initEventosPlanificadorGlobales); 
        safeInit('motor calculo op', initMotorCalculoYGuardadoGlobal); 
        window.produccionEventosGlobalesAtados = true;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initModuloProduccion);
} else {
    window.initModuloProduccion();
}

// =========================================================================
// 0. INICIALIZADOR DE TOM SELECT
// =========================================================================
function initTomSelects() {
    if (typeof TomSelect === 'undefined') return;
    const tsConfig = { create: false, dropdownParent: 'body' };
    
    const initTS = (id) => {
        const el = document.getElementById(id);
        if (!el || el.tomselect) return;

        if (id === 'newRecetaOP') {
            new TomSelect(el, {
                ...tsConfig,
                searchField: ['text', 'searchTokens'], 
                maxOptions: 200
            });
            return;
        }
        new TomSelect(el, tsConfig);
    };
    
    initTS('newRecetaOP');
    initTS('newAlmacenPlanta');
    initTS('editAlmacenPlanta');
}

// =========================================================================
// 1. FILTROS DE TABLA DE ÓRDENES (OPTIMIZADO CON DEBOUNCE)
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

    // Aplicación del Debounce para no colgar el navegador al escribir rápido
    const filterFnDebounced = AppUtils.debounce(filterFn, 300);

    input.addEventListener('input', filterFnDebounced);
    if (select) select.addEventListener('change', filterFn);
}

function initTooltips() {
    if (typeof bootstrap === 'undefined') return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
}

// =========================================================================
// 2. ACCIONES DE TABLA (EVENTOS ACOTADOS A #tablaOrdenes)
// =========================================================================
function initAccionesTabla() {
    let modalEditar = null;
    let modalDetalle = null;
    const tabla = document.getElementById('tablaOrdenes');
    
    if (!tabla) return;

    // Solo escuchamos clics dentro de la tabla
    tabla.addEventListener('click', function(e) {
        
        // BOTÓN EDITAR
        const btnEditar = e.target.closest('.js-editar-op');
        if (btnEditar) {
            const modalEditarEl = document.getElementById('modalEditarOP');
            if (modalEditarEl && typeof bootstrap !== 'undefined') {
                if (!modalEditar) modalEditar = new bootstrap.Modal(modalEditarEl);

                document.getElementById('editIdOrden').value = btnEditar.getAttribute('data-id') || '';
                document.getElementById('editCantPlan').value = btnEditar.getAttribute('data-cantidad') || '';
                document.getElementById('editFechaProgramada').value = btnEditar.getAttribute('data-fecha') || '';
                
                const editAlmacen = document.getElementById('editAlmacenPlanta');
                if (editAlmacen) {
                    editAlmacen.value = btnEditar.getAttribute('data-id-almacen') || '';
                    if (editAlmacen.tomselect) editAlmacen.tomselect.sync();
                }

                document.getElementById('editObsOP').value = btnEditar.getAttribute('data-observaciones') || '';
                modalEditar.show();
            }
            return;
        }

        // BOTÓN DIAGNÓSTICO
        const btnDiagnostico = e.target.closest('.js-ver-diagnostico');
        if (btnDiagnostico) {
            const modalDiagEl = document.getElementById('modalDiagnosticoOP');
            if (!modalDiagEl || typeof bootstrap === 'undefined') return;

            const modalDiag = bootstrap.Modal.getOrCreateInstance(modalDiagEl);
            const codigo = btnDiagnostico.getAttribute('data-codigo');
            const destino = btnDiagnostico.getAttribute('data-destino');
            const estaOk = btnDiagnostico.getAttribute('data-ok') === '1';
            const resumen = btnDiagnostico.getAttribute('data-resumen') || '';

            document.getElementById('diagCodigoOP').textContent = codigo;
            document.getElementById('diagDestino').textContent = destino;
            const contenedor = document.getElementById('diagContenido');
            
            if (estaOk) {
                contenedor.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 fw-bold text-dark">Todo en orden</h5>
                        <p class="text-muted mb-0">La planta cuenta con stock suficiente.</p>
                    </div>`;
            } else {
                const listaFaltantes = resumen.replace('Faltantes: ', '').split(';').filter(i => i.trim() !== '');
                let htmlFaltantes = `<div class="alert alert-warning shadow-sm mb-0">
                                        <p class="small text-dark mb-2">Insumos Faltantes:</p>
                                        <ul class="list-group list-group-flush shadow-sm rounded">`;
                listaFaltantes.forEach(item => {
                    htmlFaltantes += `<li class="list-group-item list-group-item-warning py-1 px-3 border-warning-subtle">
                                        <span class="fw-medium text-dark">${item.trim()}</span>
                                      </li>`;
                });
                htmlFaltantes += `</ul></div>`;
                contenedor.innerHTML = htmlFaltantes;
            }
            modalDiag.show();
            return;
        }

        // BOTÓN DETALLE DE COSTOS
        const btnDetalle = e.target.closest('.js-ver-detalle');
        if (btnDetalle) {
            const modalDetalleEl = document.getElementById('modalDetalleOP');
            if (modalDetalleEl && typeof bootstrap !== 'undefined') {
                if (!modalDetalle) modalDetalle = new bootstrap.Modal(modalDetalleEl);
                const fmt = (num, dec = 4) => Number(num).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });

                document.getElementById('detalleCodigo').textContent = btnDetalle.getAttribute('data-codigo') || '-';
                document.getElementById('detalleProducto').textContent = btnDetalle.getAttribute('data-producto') || '-';
                
                document.getElementById('detallePlan').textContent = fmt(parseFloat(btnDetalle.getAttribute('data-plan') || 0));
                document.getElementById('detalleReal').textContent = fmt(parseFloat(btnDetalle.getAttribute('data-real') || 0));
                document.getElementById('detalleMdReal').textContent = fmt(parseFloat(btnDetalle.getAttribute('data-md-real') || 0));
                document.getElementById('detalleModReal').textContent = fmt(parseFloat(btnDetalle.getAttribute('data-mod-real') || 0));
                document.getElementById('detalleCifReal').textContent = fmt(parseFloat(btnDetalle.getAttribute('data-cif-real') || 0));
                document.getElementById('detalleTotalReal').textContent = fmt(parseFloat(btnDetalle.getAttribute('data-total-real') || 0));

                const real = parseFloat(btnDetalle.getAttribute('data-real') || 0);
                const total = parseFloat(btnDetalle.getAttribute('data-total-real') || 0);
                document.getElementById('detalleUnitarioReal').textContent = fmt(real > 0 ? (total / real) : 0);

                const estado = btnDetalle.getAttribute('data-estado') || '-';
                const badge = document.getElementById('detalleEstado');
                if (badge) {
                    badge.textContent = estado;
                    badge.className = `badge ${estado === 'Ejecutada' ? 'bg-success' : 'bg-danger'} px-3 py-2 fs-6 rounded-pill`;
                }

                document.getElementById('detalleTablaMd').innerHTML = `<tr><td colspan="4" class="text-center text-primary py-4">Cargando datos...</td></tr>`;

                const fd = new FormData();
                fd.append('accion', 'obtener_detalle_costos_ajax');
                fd.append('id_orden', btnDetalle.getAttribute('data-id'));

                fetch(window.location.href, { method: 'POST', body: fd })
                    .then((r) => r.json())
                    .then((resp) => {
                        const fillTable = (id, rows, emptyCols, mapper) => {
                            const tbody = document.getElementById(id);
                            if (!tbody) return;
                            if (!Array.isArray(rows) || rows.length === 0) {
                                tbody.innerHTML = `<tr><td colspan="${emptyCols}" class="text-center text-muted py-2">Sin datos</td></tr>`;
                                return;
                            }
                            tbody.innerHTML = rows.map(mapper).join('');
                        };

                        if (!resp.success) {
                            document.getElementById('detalleTablaMd').innerHTML = `<tr><td colspan="4" class="text-danger text-center">Error SQL: ${resp.message}</td></tr>`;
                            return;
                        }

                        const data = resp.data || { materiales: [], mod: [], cif: [] };
                        fillTable('detalleTablaMd', data.materiales, 4, (r) => `<tr><td class="ps-3">${r.item_nombre}</td><td class="text-end">${fmt(r.cantidad)}</td><td class="text-end">S/ ${fmt(r.costo_unitario)}</td><td class="text-end pe-3 fw-bold">S/ ${fmt(r.costo_total)}</td></tr>`);
                        fillTable('detalleTablaMod', data.mod, 4, (r) => `<tr><td class="ps-3">${r.empleado}</td><td class="text-end">${fmt(r.horas_reales)}</td><td class="text-end">S/ ${fmt(r.costo_hora_real)}</td><td class="text-end pe-3 fw-bold">S/ ${fmt(r.costo_total_mod)}</td></tr>`);
                        fillTable('detalleTablaCif', data.cif, 3, (r) => `<tr><td class="ps-3">${r.concepto}</td><td class="text-muted">${r.base_distribucion}</td><td class="text-end pe-3 fw-bold">S/ ${fmt(r.costo_aplicado)}</td></tr>`);
                    }).catch(() => {
                        document.getElementById('detalleTablaMd').innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4">Error de red</td></tr>`;
                    });

                modalDetalle.show();
            }
        }
    });
}

// =========================================================================
// 6. MOTOR GLOBAL DE CÁLCULO DUAL Y GUARDADO DE OP (ZERO RELOAD)
// =========================================================================
function initMotorCalculoYGuardadoGlobal() {
    
    window.calcularPlanificacionOP = function() {
        const inputCant = document.getElementById('newCantPlan');
        const inputHoras = document.getElementById('newHorasPlan');
        const selectReceta = document.getElementById('newRecetaOP');
        const modoSeleccionado = document.querySelector('input[name="modo_planificacion"]:checked');
        const radiosModo = document.querySelectorAll('input[name="modo_planificacion"]'); 

        if (!selectReceta || !inputCant || !inputHoras || !modoSeleccionado || !radiosModo) return;
        
        const valReceta = selectReceta.value;
        if (!valReceta) {
            radiosModo.forEach(r => r.disabled = true);
            inputCant.readOnly = true; inputCant.classList.add('bg-light', 'text-muted'); inputCant.value = ''; 
            inputHoras.readOnly = true; inputHoras.classList.add('bg-light', 'text-muted'); inputHoras.value = ''; 
            return; 
        } else {
            radiosModo.forEach(r => r.disabled = false);
            if (modoSeleccionado.value === 'cantidad') {
                inputCant.readOnly = false; inputCant.classList.remove('bg-light', 'text-muted');
                inputHoras.readOnly = true; inputHoras.classList.add('bg-light', 'text-muted');
            } else {
                inputHoras.readOnly = false; inputHoras.classList.remove('bg-light', 'text-muted');
                inputCant.readOnly = true; inputCant.classList.add('bg-light', 'text-muted');
            }
        }

        const option = selectReceta.querySelector(`option[value="${valReceta}"]`);
        if (!option) return;

        const rendimiento = parseFloat(option.getAttribute('data-rendimiento')) || 1;
        const tiempo = parseFloat(option.getAttribute('data-tiempo')) || 1;

        if (modoSeleccionado.value === 'cantidad') {
            const cant = parseFloat(inputCant.value) || 0;
            if(cant > 0) inputHoras.value = ((cant * tiempo) / rendimiento).toFixed(4);
            else inputHoras.value = '';
        } else {
            const horas = parseFloat(inputHoras.value) || 0;
            if(horas > 0) inputCant.value = ((horas * rendimiento) / tiempo).toFixed(4);
            else inputCant.value = '';
        }
    };

    const containerPlanificar = document.getElementById('modalPlanificarOP');
    if(containerPlanificar) {
        containerPlanificar.addEventListener('input', function(e) {
            if (e.target.id === 'newCantPlan' || e.target.id === 'newHorasPlan') window.calcularPlanificacionOP();
        });
        containerPlanificar.addEventListener('change', function(e) {
            if (e.target.id === 'newRecetaOP') {
                const selectReceta = e.target;
                const option = selectReceta.options[selectReceta.selectedIndex];
                const idAlmacenPlanta = option ? String(option.getAttribute('data-id-almacen-planta') || '') : '';
                const selectPlanta = document.getElementById('newAlmacenPlanta');
                if (selectPlanta && idAlmacenPlanta) {
                    if (selectPlanta.tomselect) selectPlanta.tomselect.setValue(idAlmacenPlanta, true);
                    else selectPlanta.value = idAlmacenPlanta;
                }
                window.calcularPlanificacionOP();
            }
            if (e.target.name === 'modo_planificacion') window.calcularPlanificacionOP();
        });
    }

    // FORMULARIO CREAR OP - OPTIMIZADO PARA NO RECARGAR
    const formCrear = document.getElementById('formCrearOP');
    if (formCrear) {
        formCrear.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = this.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            btnSubmit.disabled = true;

            try {
                const formData = new FormData(this);
                formData.set('accion', 'crear_orden_ajax'); 

                // Idealmente el backend aquí ya calcula faltantes y los devuelve.
                // Usamos la arquitectura actual de 2 requests por compatibilidad.
                const resCrear = await fetch(window.location.href, {
                    method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json());

                if (!resCrear.success) {
                    Swal.fire('Error', resCrear.message || 'No se pudo crear.', 'error');
                    return;
                }

                const formDataAnalisis = new FormData();
                formDataAnalisis.append('accion', 'analizar_subordenes_ajax');
                formDataAnalisis.append('id_orden', resCrear.id_orden);

                const resAnalisis = await fetch(window.location.href, {
                    method: 'POST', body: formDataAnalisis, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json());

                if (resAnalisis.success && resAnalisis.data && resAnalisis.data.length > 0) {
                    let listaHtml = '<ul class="text-start mt-3 mb-0 text-muted">';
                    resAnalisis.data.forEach(f => {
                        listaHtml += `<li><b>${f.insumo_nombre}</b>: Faltan <span class="text-danger">${f.cantidad_faltante}</span></li>`;
                    });
                    listaHtml += '</ul>';

                    const confirmacion = await Swal.fire({
                        icon: 'info',
                        title: 'Faltan Semielaborados',
                        html: `Para cumplir esta orden faltan insumos: ${listaHtml} <br>¿Generar sub-órdenes?`,
                        showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'No, manual'
                    });

                    if (confirmacion.isConfirmed) {
                        Swal.fire({ title: 'Generando...', didOpen: () => Swal.showLoading() });
                        const fdGenerar = new FormData();
                        fdGenerar.append('accion', 'generar_subordenes_ajax');
                        fdGenerar.append('id_orden_padre', resCrear.id_orden);
                        await fetch(window.location.href, { method: 'POST', body: fdGenerar, headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                        Swal.fire({ icon: 'success', title: 'Generadas', timer: 1500, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'success', title: 'Guardado', timer: 1500, showConfirmButton: false });
                    }
                } else {
                    Swal.fire({ icon: 'success', title: 'Guardado sin faltantes', timer: 1500, showConfirmButton: false });
                }

                // Cierre y Limpieza sin Recargar
                const modalEl = document.getElementById('modalPlanificarOP');
                if(modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                
                const plannerModal = document.getElementById('modalPlanificadorProduccion');
                if (plannerModal && plannerModal.classList.contains('show')) {
                    if (typeof window.cargarGridPlanificador === 'function') window.cargarGridPlanificador(); 
                } else {
                    // ZERO-RELOAD: Actualizar la tabla local en vez de recargar la página entera
                    AppUtils.recargarTablaSilencioso();
                }

            } catch (err) {
                console.error(err);
                Swal.fire('Error', 'Hubo un problema con el servidor.', 'error');
            } finally {
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
                this.reset(); 
            }
        });
    }
}


// =========================================================================
// 3. MODAL DE EJECUCIÓN (EVENTOS ACOTADOS A #modalEjecutarOP)
// =========================================================================
function initModalEjecucion() {
    let modalEjecutar;
    const modalEl = document.getElementById('modalEjecutarOP');
    if (!modalEl) return;

    window.calcularTiempoNetoOP = function() {
        const dateInicio = document.getElementById('execFechaInicioDate');
        const timeInicio = document.getElementById('execFechaInicioTime');
        const dateFin = document.getElementById('execFechaFinDate');
        const timeFin = document.getElementById('execFechaFinTime');
        
        const inputParadaHoras = document.getElementById('execParadaHoras');
        const inputParadaMinutos = document.getElementById('execParadaMinutos');
        const hiddenInicio = document.getElementById('execFechaInicio');
        const hiddenFin = document.getElementById('execFechaFin');
        const hiddenParada = document.getElementById('execHorasParada');
        
        const lblTiempoNeto = document.getElementById('lblTiempoNeto');
        const btnGuardar = document.querySelector('#formEjecutarOrden button[type="submit"]');

        if (!dateInicio || !lblTiempoNeto) return;
        
        const bloquearParadas = () => {
            if (inputParadaHoras) { inputParadaHoras.disabled = true; inputParadaHoras.value = ''; }
            if (inputParadaMinutos) { inputParadaMinutos.disabled = true; inputParadaMinutos.value = ''; }
            if (hiddenParada) hiddenParada.value = '0';
        };

        if (!dateInicio.value || !timeInicio.value || !dateFin.value || !timeFin.value) {
            lblTiempoNeto.innerHTML = `0h 0m <small class="text-muted fw-normal ms-2 opacity-75">(0.00 Hrs)</small>`;
            if (hiddenInicio) hiddenInicio.value = '';
            if (hiddenFin) hiddenFin.value = '';
            if (btnGuardar) btnGuardar.removeAttribute('data-error-tiempo');
            bloquearParadas();
            return;
        }

        const strInicio = `${dateInicio.value}T${timeInicio.value}`;
        const strFin = `${dateFin.value}T${timeFin.value}`;
        if (hiddenInicio) hiddenInicio.value = strInicio;
        if (hiddenFin) hiddenFin.value = strFin;

        const f1 = new Date(strInicio);
        const f2 = new Date(strFin);
        
        if (f2 > f1) {
            if (inputParadaHoras) inputParadaHoras.disabled = false;
            if (inputParadaMinutos) inputParadaMinutos.disabled = false;

            let horasBrutas = (f2 - f1) / (1000 * 60 * 60);
            const paradaHoras = parseInt(inputParadaHoras ? inputParadaHoras.value : 0, 10) || 0;
            let paradaMinutos = parseInt(inputParadaMinutos ? inputParadaMinutos.value : 0, 10) || 0;
            paradaMinutos = Math.min(59, Math.max(0, paradaMinutos));
            if (inputParadaMinutos && inputParadaMinutos.value !== '') inputParadaMinutos.value = paradaMinutos;
            
            let paradasDecimal = paradaHoras + (paradaMinutos / 60);
            
            if (paradasDecimal >= horasBrutas) {
                lblTiempoNeto.innerHTML = `<span class="text-danger fw-bold" style="font-size: 0.8rem;">Parada mayor a duración</span>`;
                if (btnGuardar) { btnGuardar.setAttribute('data-error-tiempo', '1'); btnGuardar.disabled = true; }
                return;
            } else {
                if (btnGuardar) btnGuardar.removeAttribute('data-error-tiempo');
                if (typeof recalcularSemaforos === 'function') recalcularSemaforos(); 
            }

            if (hiddenParada) hiddenParada.value = paradasDecimal.toFixed(4);
            let netoDecimal = Math.max(0, horasBrutas - paradasDecimal);
            let totalMinutosNetos = Math.round(netoDecimal * 60);
            let hHuman = Math.floor(totalMinutosNetos / 60);
            let mHuman = totalMinutosNetos % 60;

            lblTiempoNeto.innerHTML = `${hHuman}h ${mHuman}m <small class="text-light fw-normal opacity-75 ms-2" style="font-size: 0.7rem;">(${netoDecimal.toFixed(2)} Hrs)</small>`;
        } else {
            lblTiempoNeto.innerHTML = `<span class="text-danger fw-bold" style="font-size: 0.8rem;">Inicio mayor al fin</span>`;
            if (btnGuardar) { btnGuardar.setAttribute('data-error-tiempo', '1'); btnGuardar.disabled = true; }
            bloquearParadas();
        }
    };

    // Listeners Acotados a Modal Ejecución
    modalEl.addEventListener('change', function(e) {
        if (['execFechaInicioDate', 'execFechaInicioTime', 'execFechaFinDate', 'execFechaFinTime'].includes(e.target.id)) {
            window.calcularTiempoNetoOP();
        }
        if (e.target.matches('select[name="consumo_id_almacen[]"]')) recalcularSemaforos(true);
    });

    modalEl.addEventListener('input', function(e) {
        if (e.target.id === 'execParadaHoras' || e.target.id === 'execParadaMinutos') window.calcularTiempoNetoOP();
        if (e.target.matches('input[name="ingreso_cantidad[]"]')) dispararRecalculoTotal();
        if (e.target.matches('#tablaConsumosDynamic tbody tr.fila-fraccion-extra input[name="consumo_cantidad[]"]')) {
            const tr = e.target.closest('tr');
            if (tr && tr.getAttribute('data-id-insumo')) {
                distribuirConsumoPorInsumo(tr.getAttribute('data-id-insumo'));
                recalcularSemaforos(true);
            }
        }
    });

    modalEl.addEventListener('click', async function(e) {
        // Táctiles +-
        const btnMinus = e.target.closest('.js-btn-minus');
        const btnPlus = e.target.closest('.js-btn-plus');
        if (btnMinus || btnPlus) {
            const input = (btnMinus || btnPlus).closest('.input-group').querySelector('input[type="number"]');
            if (!input || input.readOnly) return;
            let val = parseFloat(input.value) || 0;
            let step = parseFloat(input.getAttribute('step')) || 1; 
            if (btnMinus) val = Math.max(0, val - step);
            if (btnPlus) val += step;
            input.value = val.toFixed(step < 1 ? 4 : 0);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // Botón Mágico
        const btnMagico = e.target.closest('#btnMagicoEjecucion');
        if (btnMagico) {
            const magiaAplicada = btnMagico.getAttribute('data-magia-aplicada') === '1';
            if (magiaAplicada) {
                document.getElementById('execFechaInicioTime').value = '';
                document.getElementById('execFechaFinTime').value = '';
                document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-fraccion-extra').forEach(tr => tr.remove());
                document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada select').forEach(sel => { if (sel.tomselect) sel.tomselect.setValue('', true); else sel.value = ''; });
                document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada').forEach(tr => distribuirConsumoPorInsumo(tr.getAttribute('data-id-insumo')));
                recalcularSemaforos(false); window.calcularTiempoNetoOP();
                btnMagico.setAttribute('data-magia-aplicada', '0');
                btnMagico.innerHTML = '<i class="bi bi-calculator me-1"></i> Proponer Consumos';
                btnMagico.classList.replace('btn-warning', 'btn-outline-secondary');
            } else {
                let faltantesGlobales = false;
                const horasEst = parseFloat(modalEl.getAttribute('data-horas-estimadas')) || 0;
                const end = new Date();
                const start = new Date(end.getTime() - (horasEst * 60 * 60 * 1000));
                
                const formatDate = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
                const formatTime = d => `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;

                document.getElementById('execFechaInicioDate').value = formatDate(start);
                document.getElementById('execFechaInicioTime').value = formatTime(start);
                document.getElementById('execFechaFinDate').value = formatDate(end);
                document.getElementById('execFechaFinTime').value = formatTime(end);

                document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada').forEach(trOriginal => {
                    const idInsumo = trOriginal.getAttribute('data-id-insumo');
                    let cantidadRestante = calcularCantidadRequeridaPorInsumo(idInsumo);
                    const selectEl = trOriginal.querySelector('select[name="consumo_id_almacen[]"]');
                    if (!selectEl) return;

                    let opcionesStock = Array.from(selectEl.options).filter(opt => opt.hasAttribute('data-stock')).map(opt => ({ value: opt.value, stock: parseFloat(opt.getAttribute('data-stock')) }));
                    opcionesStock.sort((a, b) => b.stock - a.stock);

                    if (opcionesStock.length === 0 || opcionesStock[0].stock === 0) { faltantesGlobales = true; return; }

                    let filaActual = trOriginal;
                    for (let i = 0; i < opcionesStock.length && cantidadRestante > 0; i++) {
                        let almacen = opcionesStock[i];
                        let cantidadAsignar = Math.min(almacen.stock, cantidadRestante);
                        const currentSelect = filaActual.querySelector('select[name="consumo_id_almacen[]"]');
                        if (currentSelect.tomselect) currentSelect.tomselect.setValue(almacen.value, true); else currentSelect.value = almacen.value;
                        filaActual.querySelector('input[name="consumo_cantidad[]"]').value = cantidadAsignar.toFixed(4);
                        cantidadRestante -= cantidadAsignar;

                        if (cantidadRestante > 0 && i < opcionesStock.length - 1) {
                            const trClon = trOriginal.cloneNode(true);
                            trClon.classList.add('fila-fraccion-extra');
                            trClon.querySelector('input[name="consumo_cantidad[]"]').value = '';
                            trClon.querySelector('td:last-child').innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="bi bi-trash-fill fs-5"></i></button>';
                            filaActual.parentNode.insertBefore(trClon, filaActual.nextSibling);
                            filaActual = trClon;
                        }
                    }
                    if (cantidadRestante > 0) faltantesGlobales = true;
                    distribuirConsumoPorInsumo(idInsumo);
                });
                recalcularSemaforos(true); window.calcularTiempoNetoOP();
                btnMagico.setAttribute('data-magia-aplicada', '1');
                btnMagico.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar';
                btnMagico.classList.replace('btn-outline-secondary', 'btn-warning');
            }
            return;
        }

        const btnRemove = e.target.closest('.js-remove-row');
        if (btnRemove) {
            const tr = btnRemove.closest('tr');
            if (tr) { tr.remove(); dispararRecalculoTotal(); }
            return;
        }

        const btnSplit = e.target.closest('.js-split-row');
        if (btnSplit) {
            const trOriginal = btnSplit.closest('tr');
            if (!trOriginal) return;
            const trClon = trOriginal.cloneNode(true);
            const idInsumo = trOriginal.getAttribute('data-id-insumo') || '';
            const nombreItem = trClon.querySelector('.fw-bold');
            if (nombreItem && !nombreItem.innerHTML.includes('bi-arrow-return-right')) nombreItem.innerHTML = '<i class="bi bi-arrow-return-right text-muted me-2"></i>' + nombreItem.innerHTML;
            const inputCantClon = trClon.querySelector('input[name="consumo_cantidad[]"]');
            if (inputCantClon) { inputCantClon.value = ''; inputCantClon.readOnly = false; inputCantClon.classList.remove('bg-light'); }
            const selectAlmClon = trClon.querySelector('select[name="consumo_id_almacen[]"]');
            if (selectAlmClon) selectAlmClon.selectedIndex = 0;
            trClon.classList.add('fila-fraccion-extra');
            const celdaAccion = trClon.querySelector('td:last-child');
            if (celdaAccion) celdaAccion.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="bi bi-trash-fill fs-5"></i></button>';
            const inputOriginal = trOriginal.querySelector('input[name="consumo_cantidad[]"]');
            if (inputOriginal) { inputOriginal.readOnly = true; inputOriginal.classList.add('bg-light'); }
            trOriginal.parentNode.insertBefore(trClon, trOriginal.nextSibling);
            if (idInsumo) distribuirConsumoPorInsumo(idInsumo);
            recalcularSemaforos(true);
            return;
        }

        const btnSplitIngreso = e.target.closest('.js-split-ingreso-row');
        if (btnSplitIngreso) {
            const trOriginal = btnSplitIngreso.closest('tr');
            const trClon = trOriginal.cloneNode(true);
            const inputCant = trClon.querySelector('input[name="ingreso_cantidad[]"]');
            if (inputCant) inputCant.value = '';
            const selectAlm = trClon.querySelector('select[name="ingreso_id_almacen[]"]');
            if (selectAlm) selectAlm.selectedIndex = 0;
            const celdaAccion = trClon.querySelector('td:last-child');
            if (celdaAccion) celdaAccion.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="bi bi-trash-fill fs-5"></i></button>';
            trOriginal.parentNode.insertBefore(trClon, trOriginal.nextSibling);
            return;
        }
    });

    // Delegación del botón "Abrir Ejecución" (Este vive en la tabla, por lo que va al contenedor tabla)
    const tabla = document.getElementById('tablaOrdenes');
    if (tabla) {
        tabla.addEventListener('click', async function(e) {
            const btnAbrir = e.target.closest('.js-abrir-ejecucion');
            if (btnAbrir) {
                if (!modalEl || typeof bootstrap === 'undefined') return;

                const idOrden = btnAbrir.getAttribute('data-id') || '';
                const planificada = parseFloat(btnAbrir.getAttribute('data-planificada')) || 0;
                
                document.getElementById('execIdOrden').value = idOrden;
                document.getElementById('lblExecCodigo').textContent = btnAbrir.getAttribute('data-codigo') || '';
                document.getElementById('lblExecRecetaInfo').textContent = btnAbrir.getAttribute('data-producto-nombre') ? `Receta - ${btnAbrir.getAttribute('data-producto-nombre')}` : '';

                document.getElementById('execReqLote').value = btnAbrir.getAttribute('data-req-lote') || '0';
                document.getElementById('execReqVenc').value = btnAbrir.getAttribute('data-req-venc') || '0';
                document.getElementById('execUnidad').value = btnAbrir.getAttribute('data-unidad') || 'UND';

                const recetaRendimiento = parseFloat(btnAbrir.getAttribute('data-receta-rendimiento')) || 0;
                const recetaTiempoHoras = parseFloat(btnAbrir.getAttribute('data-receta-tiempo')) || 0;
                const horasPlanificadas = (recetaRendimiento > 0 && recetaTiempoHoras > 0 && planificada > 0) ? ((planificada * recetaTiempoHoras) / recetaRendimiento) : 0;
                modalEl.setAttribute('data-horas-estimadas', horasPlanificadas);

                const dateInicio = document.getElementById('execFechaInicioDate');
                const timeInicio = document.getElementById('execFechaInicioTime');
                const dateFin = document.getElementById('execFechaFinDate');
                const timeFin = document.getElementById('execFechaFinTime');
                const ahora = new Date();
                const fechaStr = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;

                if (dateInicio) dateInicio.value = fechaStr;
                if (dateFin) dateFin.value = fechaStr;
                if (timeInicio) timeInicio.value = '';
                if (timeFin) timeFin.value = '';
                
                const selectCentroCosto = document.getElementById('execCentroCosto');
                if (selectCentroCosto) selectCentroCosto.value = parseInt(btnAbrir.getAttribute('data-receta-centro-costo')) || localStorage.getItem(`op_cc_${idOrden}`) || '';
                
                window.calcularTiempoNetoOP();

                const tbodyConsumos = document.querySelector('#tablaConsumosDynamic tbody');
                const tbodyIngresos = document.querySelector('#tablaIngresosDynamic tbody');
                if(tbodyConsumos) tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-primary fw-bold">Cargando receta...</td></tr>';
                if(tbodyIngresos) tbodyIngresos.innerHTML = '';
                
                if (!modalEjecutar) modalEjecutar = new bootstrap.Modal(modalEl);
                modalEjecutar.show();

                try {
                    const formData = new FormData();
                    formData.append('accion', 'obtener_receta_ajax');
                    formData.append('id_receta', btnAbrir.getAttribute('data-receta'));
                    formData.append('cantidad', planificada);
                    formData.append('id_orden', idOrden);
                    formData.append('id_almacen_planta', btnAbrir.getAttribute('data-id-almacen-planta'));

                    const result = await fetch(window.location.href, { method: 'POST', body: formData }).then(r=>r.json());
                    if(tbodyConsumos) tbodyConsumos.innerHTML = ''; 

                    if (result.success && result.data.length > 0) {
                        result.data.forEach(item => { addConsumoRow(item, planificada, btnAbrir.getAttribute('data-id-almacen-planta')); });
                        recalcularSemaforos(false); 
                    } else {
                        if(tbodyConsumos) tbodyConsumos.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Añade consumos manuales</td></tr>`;
                    }
                } catch (error) {
                    if(tbodyConsumos) tbodyConsumos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de red.</td></tr>';
                }
                addIngresoRow(planificada);
            }
        });
    }

    function dispararRecalculoTotal() {
        const insumos = new Set();
        document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada').forEach((tr) => {
            const id = tr.getAttribute('data-id-insumo');
            if (id) insumos.add(id);
        });
        insumos.forEach((id) => distribuirConsumoPorInsumo(id));
        recalcularSemaforos();
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        const form = this.querySelector('form');
        if (form) form.reset();
        this.removeAttribute('data-magia-activa');
        const btnMag = document.getElementById('btnMagicoEjecucion');
        if (btnMag) {
            btnMag.setAttribute('data-magia-aplicada', '0');
            btnMag.innerHTML = '<i class="bi bi-calculator me-1"></i> Proponer Consumos';
            btnMag.className = 'btn btn-sm btn-outline-secondary shadow-sm rounded-pill fw-bold px-3 py-1 transition-hover';
        }
        const firstTab = this.querySelector('.nav-tabs .nav-link');
        if (firstTab && typeof bootstrap !== 'undefined') new bootstrap.Tab(firstTab).show();
        const lblTiempoNeto = document.getElementById('lblTiempoNeto');
        if (lblTiempoNeto) lblTiempoNeto.innerHTML = `0h 0m`;
    });

    const formEjecucion = document.getElementById('formEjecutarOrden');
    if (formEjecucion) {
        formEjecucion.addEventListener('submit', function () {
            const idOrden = document.getElementById('execIdOrden')?.value || '';
            const centro = document.getElementById('execCentroCosto')?.value || '';
            if (idOrden) localStorage.setItem(`op_cc_${idOrden}`, centro);
        });
    }
}

// =========================================================================
// 4. GENERADORES DE INTERFAZ & CALCULO (IGUAL, SOLO LIMPIOS)
// =========================================================================
function calcularCantidadRequeridaPorInsumo(idInsumo) {
    const filaBase = document.querySelector(`#tablaConsumosDynamic tbody tr.fila-calculada[data-id-insumo="${idInsumo}"]`);
    if (!filaBase) return 0;
    const reqUnitario = parseFloat(filaBase.getAttribute('data-req-unitario')) || 0;
    let totalProducido = 0;
    document.querySelectorAll('input[name="ingreso_cantidad[]"]').forEach((inp) => { totalProducido += parseFloat(inp.value) || 0; });
    return reqUnitario * totalProducido;
}

function distribuirConsumoPorInsumo(idInsumo) {
    const filas = Array.from(document.querySelectorAll(`#tablaConsumosDynamic tbody tr.fila-calculada[data-id-insumo="${idInsumo}"]`));
    if (!filas.length) return;
    const inputPrincipal = filas[0].querySelector('input[name="consumo_cantidad[]"]');
    if (!inputPrincipal) return;
    const requerimiento = calcularCantidadRequeridaPorInsumo(idInsumo);
    let sumaFraccionada = 0;
    filas.slice(1).forEach((f) => {
        const iExt = f.querySelector('input[name="consumo_cantidad[]"]');
        if (!iExt) return;
        const valor = Math.max(0, parseFloat(iExt.value) || 0);
        if (String(iExt.value) !== String(valor)) iExt.value = valor ? String(valor) : '';
        sumaFraccionada += valor;
    });
    const restante = Math.max(0, requerimiento - sumaFraccionada);
    inputPrincipal.value = restante.toFixed(4);
}

function addConsumoRow(item = null, planificada = 1, idAlmacenPlanta = 0) {
    const tbody = document.querySelector('#tablaConsumosDynamic tbody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    
    // (Lógica de inyección HTML original conservada)
    if (item) {
        const reqU = (parseFloat(item.cantidad_calculada) / planificada) || 0;
        tr.setAttribute('data-id-insumo', item.id_insumo); tr.setAttribute('data-req-unitario', reqU); tr.classList.add('fila-calculada');
        let optionsHtml = '<option value="">Seleccione almacén...</option>';
        const almacenes = Array.isArray(item.almacenes) ? item.almacenes : [];
        const almacenPlanta = almacenes.find(a => Number(a.id) === Number(idAlmacenPlanta));
        const otros = almacenes.filter(a => Number(a.id) !== Number(idAlmacenPlanta));

        if (almacenPlanta) { optionsHtml += `<optgroup label="Recomendado"><option value="${almacenPlanta.id}" data-stock="${almacenPlanta.stock_actual}" selected>${almacenPlanta.nombre} (Stock: ${almacenPlanta.stock_actual})</option></optgroup>`; }
        if (otros.length > 0) {
            optionsHtml += `<optgroup label="Otros">`;
            otros.forEach(a => { optionsHtml += `<option value="${a.id}" data-stock="${a.stock_actual}">${a.nombre} (Stock: ${a.stock_actual})</option>`; });
            optionsHtml += `</optgroup>`;
        }
        
        tr.innerHTML = `<td class="align-middle bg-light"><input type="hidden" name="consumo_id_insumo[]" value="${item.id_insumo}"><div class="fw-bold">${item.insumo_nombre}</div></td>
            <td class="align-middle"><select name="consumo_id_almacen[]" class="form-select form-select-sm" required>${optionsHtml}</select></td>
            <td class="align-middle"><input type="number" step="0.0001" name="consumo_cantidad[]" class="form-control form-control-sm text-end bg-light" value="${item.cantidad_calculada}" readonly></td>
            <td class="align-middle d-none"><input type="text" name="consumo_id_lote[]" class="form-control form-control-sm"></td>
            <td class="text-end align-middle">${almacenes.length > 1 ? `<button type="button" class="btn btn-sm btn-outline-primary js-split-row"><i class="bi bi-plus-circle fs-5"></i></button>` : ''}</td>`;
    } else {
        const tmpl = document.getElementById('tplSelectAlmacenes')?.innerHTML || '';
        tr.innerHTML = `<td class="align-middle"><input type="number" name="consumo_id_insumo[]" class="form-control form-control-sm" required></td>
            <td class="align-middle"><select name="consumo_id_almacen[]" class="form-select form-select-sm" required>${tmpl}</select></td>
            <td class="align-middle"><input type="number" step="0.0001" name="consumo_cantidad[]" class="form-control form-control-sm text-end" required></td>
            <td class="align-middle d-none"><input type="text" name="consumo_id_lote[]" class="form-control form-control-sm"></td>
            <td class="text-end align-middle"><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="bi bi-trash-fill fs-5"></i></button></td>`;
    }
    tbody.appendChild(tr);
}

function addIngresoRow(cantidadDefecto = '') {
    const tbody = document.querySelector('#tablaIngresosDynamic tbody');
    if (!tbody) return;
    const tmpl = document.getElementById('tplSelectAlmacenes')?.innerHTML || '';
    const reqLote = document.getElementById('execReqLote')?.value === '1';
    const unidad = document.getElementById('execUnidad')?.value || 'UND';
    const autoLote = reqLote ? ('L' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' + Math.floor(Math.random() * 90 + 10)) : '';

    const tr = document.createElement('tr');
    tr.innerHTML = `<td class="align-middle"><select name="ingreso_id_almacen[]" class="form-select form-select-sm" required>${tmpl}</select></td>
        <td class="align-middle" style="width: 160px;"><div class="input-group input-group-sm justify-content-end"><input type="number" step="0.0001" name="ingreso_cantidad[]" class="form-control fw-bold border-success text-end" required value="${cantidadDefecto}"><span class="input-group-text">${unidad}</span></div></td>
        <td class="align-middle d-none"><input type="text" name="ingreso_id_lote[]" class="form-control form-control-sm" ${reqLote ? `value="${autoLote}" required` : `readonly`}></td>
        <td class="align-middle d-none"><input type="date" name="ingresos_fecha_vencimiento[]" class="form-control form-control-sm"></td>
        <td class="text-end align-middle">${(tmpl.match(/<option/g) || []).length > 1 ? `<button type="button" class="btn btn-sm btn-outline-primary js-split-ingreso-row"><i class="bi bi-plus-circle fs-5"></i></button>` : ''}</td>`;
    tbody.appendChild(tr);
}

// =========================================================================
// 5. SEMÁFOROS 
// =========================================================================
function recalcularSemaforos(desdeBotonMagico = false) {
    const modalEl = document.getElementById('modalEjecutarOP');
    if (desdeBotonMagico && modalEl) modalEl.setAttribute('data-magia-activa', '1');
    const magiaActiva = modalEl ? modalEl.getAttribute('data-magia-activa') === '1' : false;

    let faltaStockFisico = false;
    document.querySelectorAll('#tablaConsumosDynamic tbody tr.fila-calculada, #tablaConsumosDynamic tbody tr.fila-fraccion-extra').forEach(tr => {
        const inputElement = tr.querySelector('input[name="consumo_cantidad[]"]');
        const selectEl = tr.querySelector('select[name="consumo_id_almacen[]"]');
        if (!inputElement || !selectEl) return;
        const cantRequerida = parseFloat(inputElement.value) || 0;
        const optionSel = selectEl.options[selectEl.selectedIndex];
        const stockDisp = (optionSel && optionSel.hasAttribute('data-stock')) ? parseFloat(optionSel.getAttribute('data-stock')) : null;

        if (stockDisp === null || cantRequerida > stockDisp || selectEl.value === "") faltaStockFisico = true;

        inputElement.style.removeProperty('background-color'); inputElement.style.removeProperty('border-color'); inputElement.style.removeProperty('color');
        inputElement.classList.remove('text-danger', 'text-success');

        if (magiaActiva) {
            inputElement.classList.remove('bg-light');
            inputElement.style.setProperty('background-color', '#ffffff', 'important'); 
            if (stockDisp === null || cantRequerida > stockDisp || selectEl.value === "") {
                inputElement.style.setProperty('border-color', '#dc3545', 'important'); inputElement.style.setProperty('color', '#dc3545', 'important');
            } else {
                inputElement.style.setProperty('border-color', '#198754', 'important'); inputElement.style.setProperty('color', '#198754', 'important');
            }
        } else {
            inputElement.classList.add('bg-light');
        }
    });

    const btnGuardar = document.querySelector('#formEjecutarOrden button[type="submit"]');
    if (btnGuardar) btnGuardar.disabled = faltaStockFisico || btnGuardar.getAttribute('data-error-tiempo') === '1';
}

// =========================================================================
// 7. PLANIFICADOR DE OPERACIONES (Acotado)
// =========================================================================
function initEventosPlanificadorGlobales() {
    const modalPlanificador = document.getElementById('modalPlanificadorProduccion');
    if (!modalPlanificador) return;

    modalPlanificador.addEventListener('show.bs.modal', function() {
        window.planFechaActual = new Date(); 
        if (typeof window.cargarGridPlanificador === 'function') window.cargarGridPlanificador();
    });

    modalPlanificador.addEventListener('click', function(e) {
        if (e.target.closest('#btnPlanAnterior')) {
            if (window.vistaActualPlan === 'mes') window.planFechaActual.setMonth(window.planFechaActual.getMonth() - 1);
            else window.planFechaActual.setDate(window.planFechaActual.getDate() - 7);
            if (typeof window.cargarGridPlanificador === 'function') window.cargarGridPlanificador();
        }
        if (e.target.closest('#btnPlanSiguiente')) {
            if (window.vistaActualPlan === 'mes') window.planFechaActual.setMonth(window.planFechaActual.getMonth() + 1);
            else window.planFechaActual.setDate(window.planFechaActual.getDate() + 7);
            if (typeof window.cargarGridPlanificador === 'function') window.cargarGridPlanificador();
        }
    });

    modalPlanificador.addEventListener('change', function(e) {
        if (e.target.matches('input[name="vistaPlanificador"]')) {
            window.vistaActualPlan = e.target.value;
            if (typeof window.cargarGridPlanificador === 'function') window.cargarGridPlanificador();
        }
    });
}

// =========================================================================
// 7. PLANIFICADOR DE OPERACIONES (Core - Faltante)
// =========================================================================
function initPlanificadorOperaciones() {
    window.cargarGridPlanificador = async function() {
        const loader = document.getElementById('planLoader');
        const grid = document.getElementById('planificadorGrid');
        const lblPlan = document.getElementById('lblPlanActual');
        
        if (!grid || !lblPlan) return; 
        if (loader) loader.classList.remove('d-none');
        
        let primerDia, ultimoDia;
        const nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        if (window.vistaActualPlan === 'mes') {
            const year = window.planFechaActual.getFullYear();
            const month = window.planFechaActual.getMonth();
            primerDia = new Date(year, month, 1);
            ultimoDia = new Date(year, month + 1, 0);
            lblPlan.textContent = `${nombresMeses[month]} ${year}`;
        } else {
            primerDia = new Date(window.planFechaActual);
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

            const response = await fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const resultado = await response.json();
            
            if (resultado.success) window.diccPlanificacion = resultado.data || {};
            else window.diccPlanificacion = {};
        } catch (e) { 
            window.diccPlanificacion = {};
        } 

        dibujarGrid(primerDia, ultimoDia);
        if (loader) loader.classList.add('d-none');
    };

    function dibujarGrid(primerDia, ultimoDia) {
        const grid = document.getElementById('planificadorGrid');
        if (!grid) return;
        
        let html = '';
        const minHeight = window.vistaActualPlan === 'mes' ? '130px' : '350px';

        if (window.vistaActualPlan === 'semana') {
            grid.classList.remove('vista-mes');
            grid.classList.add('vista-semana');
        } else {
            grid.classList.remove('vista-semana');
            grid.classList.add('vista-mes');
        }

        if (window.vistaActualPlan === 'mes') {
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
            const registro = window.diccPlanificacion[dateStr];
            
            let colorClase = 'bg-white border-secondary-subtle border-dashed'; 
            let textoClase = esDomingo ? 'text-danger' : 'text-secondary';
            let htmlContenido = '';
            let tooltipAttr = '';

            if (registro) {
                if(registro.tipo === 'normal') colorClase = 'bg-primary-subtle border-primary text-primary-emphasis';
                if(registro.tipo === 'excepcion') colorClase = 'bg-warning-subtle border-warning text-warning-emphasis';

                if (Array.isArray(registro.detalle) && registro.detalle.length > 0) {
                    if (window.vistaActualPlan === 'semana') {
                        const detalleHtml = registro.detalle.map((op) => {
                            const producto = op.producto || '-';
                            const codigoLimpio = op.codigo ? op.codigo.replace('OP-', '') : '-'; 
                            const cantidad = Number(op.cantidad || 0).toFixed(2);
                            const badgeColor = op.estado === 2 ? 'bg-success text-white' : 'bg-secondary text-white';
                            const estadoTexto = op.estado === 2 ? 'Ejecutada' : 'Borrador';
                            const bordeEstado = op.estado === 2 ? 'border-success' : 'border-secondary';
                            
                            return `<div class="mini-card-op small bg-white border rounded p-2 mt-2 text-start shadow-sm lh-sm border-start border-4 ${bordeEstado}" style="min-height: 60px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-primary" style="font-size:0.7rem;"><i class="bi bi-tag-fill me-1"></i>${codigoLimpio}</span>
                                            <span class="badge ${badgeColor} py-1 px-2" style="font-size:0.6rem;">${estadoTexto}</span>
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
                                const bgColor = op.estado === 2
                                    ? 'bg-success-subtle border-success text-success-emphasis'
                                    : 'bg-light border-secondary-subtle text-secondary';
                                
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
            tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
        }
    }
}

window.accionProgramarOP = function(fechaStr) {
    const modalOPEl = document.getElementById('modalPlanificarOP');
    if (!modalOPEl || typeof bootstrap === 'undefined') return;

    const modalOP = bootstrap.Modal.getOrCreateInstance(modalOPEl);
    const inputFecha = document.getElementById('newFechaProgramada');
    if (inputFecha) inputFecha.value = fechaStr;

    modalOP.show();
};
