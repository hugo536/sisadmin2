/**
 * LÓGICA PARA GESTIÓN DE ASISTENCIA (MODO EXCEL)
 * Archivo: public/assets/js/rrhh/gestion_asistencia.js
 * Compatible con arquitectura SPA (Single Page Application) y Hotwire/Turbo
 */

(function() {
    'use strict';

    function iniciarModuloExcelAsistencia() {
        const appContenedor = document.getElementById('gestionAsistenciaApp');
        
        // Prevención de doble inicialización en la misma vista
        if (!appContenedor || appContenedor.dataset.iniciado === '1') return;
        appContenedor.dataset.iniciado = '1';

        // ==========================================
        // VARIABLES GLOBALES Y ELEMENTOS DOM
        // ==========================================
        const baseUrl = window.BASE_URL || '/';
        let empleadoActualId = null;
        let fechaJustificacionActual = null;

        const DOM = {
            inputBuscar: document.getElementById('buscarEmpleado'),
            listaEmpleados: document.getElementById('listaEmpleados'),
            
            lblNombreActivo: document.getElementById('nombreEmpleadoActivo'),
            lblRangoActivo: document.getElementById('rangoActivoLabel'),
            
            lblTotalRegulares: document.getElementById('totalRegulares'),
            lblTotalExtras: document.getElementById('totalExtras'),
            
            selectPeriodo: document.getElementById('tipoPeriodo'),
            filtros: document.querySelectorAll('.filter-input'),
            
            gridCuerpo: document.getElementById('gridAsistenciaCuerpo'),
            
            modalJustificar: document.getElementById('modalJustificar'),
            lblModalFecha: document.getElementById('modalJustificarFecha'),
            selectEstado: document.getElementById('selectEstadoJustificacion'),
            txtObservacion: document.getElementById('txtObservacionJustificacion'),
            btnAplicarJust: document.getElementById('btnAplicarJustificacion')
        };

        // ==========================================
        // UTILIDADES DE CÁLCULO DE TIEMPO (FRONTEND)
        // ==========================================
        function calcularDiferenciaMinutos(horaEntrada, horaSalida) {
            if (!horaEntrada || !horaSalida) return 0;
            
            let [hIn, mIn] = horaEntrada.split(':').map(Number);
            let [hOut, mOut] = horaSalida.split(':').map(Number);
            
            let minIn = (hIn * 60) + mIn;
            let minOut = (hOut * 60) + mOut;
            
            // Manejo de turnos que cruzan la medianoche (ej: 22:00 a 06:00)
            if (minOut < minIn) {
                minOut += 24 * 60;
            }
            
            return minOut - minIn;
        }

        function formatoHoras(totalMinutos) {
            if (totalMinutos <= 0) return '0h';
            let h = Math.floor(totalMinutos / 60);
            let m = totalMinutos % 60;
            return m > 0 ? `${h}h ${m}m` : `${h}h`;
        }

        function actualizarTotalFilaUI(tr) {
            const getVal = (selector) => tr.querySelector(`[data-tipo="${selector}"]`)?.value || '';
            
            const t1_in = getVal('t1_in'), t1_out = getVal('t1_out');
            const t2_in = getVal('t2_in'), t2_out = getVal('t2_out');
            const t3_in = getVal('t3_in'), t3_out = getVal('t3_out');

            let total = 0;
            if (t1_in && t1_out) total += calcularDiferenciaMinutos(t1_in, t1_out);
            if (t2_in && t2_out) total += calcularDiferenciaMinutos(t2_in, t2_out);
            if (t3_in && t3_out) total += calcularDiferenciaMinutos(t3_in, t3_out);

            const celdaTotal = tr.querySelector('.celda-total-dia');
            if (celdaTotal) {
                celdaTotal.textContent = formatoHoras(total);
                if (total > 0) {
                    celdaTotal.classList.replace('text-muted', 'text-dark');
                    celdaTotal.classList.add('fw-bold');
                } else {
                    celdaTotal.classList.replace('text-dark', 'text-muted');
                    celdaTotal.classList.remove('fw-bold');
                }
            }
        }

        // ==========================================
        // ESTADO INICIAL (PANTALLA DE BIENVENIDA)
        // ==========================================
        function mostrarEstadoVacio() {
            if(DOM.lblNombreActivo) DOM.lblNombreActivo.innerHTML = '<i class="bi bi-person-fill text-muted me-2"></i>Esperando selección...';
            if(DOM.lblRangoActivo) DOM.lblRangoActivo.textContent = '--';
            if(DOM.lblTotalRegulares) DOM.lblTotalRegulares.textContent = '0h';
            if(DOM.lblTotalExtras) DOM.lblTotalExtras.textContent = '0h';
            
            if(DOM.gridCuerpo) {
                DOM.gridCuerpo.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5 bg-light border-bottom-0">
                            <i class="bi bi-person-lines-fill d-block text-muted opacity-25 mb-3" style="font-size: 4rem;"></i>
                            <h5 class="fw-bold text-dark">Selecciona un Empleado</h5>
                            <p class="text-muted small mb-0">Haz clic en un empleado del panel lateral izquierdo para cargar su cuadrícula de asistencia.</p>
                        </td>
                    </tr>
                `;
            }
        }

        // Auto-selección desde URL (Viene del botón "Corregir" en Planillas)
        const params = new URLSearchParams(window.location.search);
        const idTerceroUrl = params.get('id_tercero');

        if (idTerceroUrl && DOM.listaEmpleados) {
            let intentos = 0;
            const buscadorInterval = setInterval(() => {
                const tarjetaEmpleado = document.querySelector(`.empleado-item[data-id="${idTerceroUrl}"]`);
                if (tarjetaEmpleado) {
                    clearInterval(buscadorInterval);
                    tarjetaEmpleado.click();

                    if (DOM.inputBuscar) {
                        const nombreTexto = tarjetaEmpleado.querySelector('.fw-bold').textContent.trim();
                        DOM.inputBuscar.value = nombreTexto;
                        DOM.inputBuscar.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    // Limpiar URL para no repetir el proceso si recarga
                    const nuevaUrl = new URL(window.location.href);
                    nuevaUrl.searchParams.delete('id_tercero');
                    window.history.replaceState({}, document.title, nuevaUrl.toString());
                } else {
                    intentos++;
                    if (intentos >= 10) {
                        clearInterval(buscadorInterval);
                        mostrarEstadoVacio();
                    }
                }
            }, 300); // Reducido a 300ms para que sea más veloz
        } else {
            mostrarEstadoVacio();
        }

        // ==========================================
        // BUSCADOR Y FILTROS LATERALES
        // ==========================================
        if (DOM.inputBuscar) {
            DOM.inputBuscar.addEventListener('input', function() {
                const texto = this.value.toLowerCase().trim();
                const items = document.querySelectorAll('.empleado-item');

                items.forEach(item => {
                    const nombre = item.querySelector('.fw-bold')?.textContent.toLowerCase() || '';
                    item.classList.toggle('d-none', texto !== '' && !nombre.includes(texto));
                });
            });
        }

        // ==========================================
        // CAMBIO DE PERIODO (SEMANA/MES/RANGO)
        // ==========================================
        if (DOM.selectPeriodo) {
            DOM.selectPeriodo.addEventListener('change', function() {
                DOM.filtros.forEach(el => el.classList.add('d-none'));
                
                const showFilter = (id) => { const f = document.getElementById(id); if(f) f.classList.remove('d-none'); };
                
                if (this.value === 'semana') showFilter('filtroSemana');
                if (this.value === 'mes') showFilter('filtroMes');
                if (this.value === 'rango') showFilter('filtroRango');
                
                if (empleadoActualId) cargarDatosGrid(); 
            });
        }

        DOM.filtros.forEach(input => {
            input.addEventListener('change', () => {
                if (empleadoActualId) cargarDatosGrid();
            });
        });

        // ==========================================
        // SELECCIÓN DE EMPLEADO
        // ==========================================
        if (DOM.listaEmpleados) {
            DOM.listaEmpleados.addEventListener('click', function(e) {
                const item = e.target.closest('.empleado-item');
                if (!item) return;

                document.querySelectorAll('.empleado-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                
                empleadoActualId = item.dataset.id;
                DOM.lblNombreActivo.textContent = item.querySelector('.fw-bold').textContent;
                
                cargarDatosGrid();
            });
        }

        // ==========================================
        // CARGA DE DATOS AL GRID (FETCH)
        // ==========================================
        async function cargarDatosGrid() {
            if (!empleadoActualId || !DOM.gridCuerpo) return;

            DOM.gridCuerpo.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary"></div>Cargando registros...</td></tr>`;

            const fd = new FormData();
            fd.append('accion', 'obtener_grid_excel');
            fd.append('id_tercero', empleadoActualId);
            
            if (DOM.selectPeriodo) fd.append('periodo', DOM.selectPeriodo.value);
            
            const appendIfExist = (id, key) => { const el = document.getElementById(id); if(el && !el.classList.contains('d-none')) fd.append(key, el.value); };
            appendIfExist('filtroSemana', 'semana');
            appendIfExist('filtroMes', 'mes');
            appendIfExist('filtroDesde', 'fecha_inicio');
            appendIfExist('filtroHasta', 'fecha_fin');

            try {
                const res = await fetch(baseUrl + 'index.php?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    renderizarFilas(data.dias);
                    
                    if(DOM.lblTotalRegulares) DOM.lblTotalRegulares.textContent = data.total_regulares_str || '0h';
                    if(DOM.lblTotalExtras) DOM.lblTotalExtras.textContent = data.total_extras_str || '0h';
                    if(DOM.lblRangoActivo) DOM.lblRangoActivo.textContent = data.rango_label || 'Periodo seleccionado';
                } else {
                    DOM.gridCuerpo.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>${data.mensaje || 'Error al cargar datos.'}</td></tr>`;
                }
            } catch (error) {
                console.error(error);
                DOM.gridCuerpo.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger">Error de conexión con el servidor.</td></tr>`;
            }
        }

        // ==========================================
        // RENDERIZAR FILAS Y COLUMNAS
        // ==========================================
        function renderizarFilas(dias) {
            DOM.gridCuerpo.innerHTML = '';
            
            dias.forEach(dia => {
                const tr = document.createElement('tr');
                tr.dataset.fecha = dia.fecha;
                
                const esDescanso = dia.es_descanso === true; 
                const bgFila = esDescanso ? 'bg-light opacity-50' : '';
                const propDisabled = esDescanso ? 'disabled' : '';
                const msgTooltip = esDescanso ? 'title="Día de descanso (Sin horario asignado)"' : '';
                const bgInput = esDescanso ? 'bg-transparent text-muted' : '';
                const iconBtn = esDescanso ? 'bi-lock-fill' : 'bi-chat-left-text';
                
                const badgeEstado = esDescanso 
                    ? '<span class="badge bg-secondary-subtle text-secondary border-0 px-2 fw-semibold">Descanso</span>' 
                    : `<span class="badge ${dia.badge_class || 'bg-secondary-subtle text-secondary'} border-0 px-2 fw-semibold text-truncate" style="max-width: 90px;">${dia.estado_label || 'Sin datos'}</span>`;

                let totalMinutosDia = 0;
                if(dia.t1_in && dia.t1_out) totalMinutosDia += calcularDiferenciaMinutos(dia.t1_in, dia.t1_out);
                if(dia.t2_in && dia.t2_out) totalMinutosDia += calcularDiferenciaMinutos(dia.t2_in, dia.t2_out);
                if(dia.t3_in && dia.t3_out) totalMinutosDia += calcularDiferenciaMinutos(dia.t3_in, dia.t3_out);
                
                const textColorCls = totalMinutosDia > 0 ? 'fw-bold text-dark' : 'text-muted';
                const strTotalDia = dia.total_dia_formateado || formatoHoras(totalMinutosDia);

                tr.className = bgFila;
                tr.innerHTML = `
                    <td class="bg-light align-middle text-start ps-3 border-end">
                        <span class="fw-bold text-dark d-block" style="font-size: 0.85rem;">${dia.nombre_dia}</span>
                        <span class="text-muted fw-medium" style="font-size: 0.7rem;">${dia.fecha_formateada}</span>
                    </td>
                    <td><input type="time" class="cell-input ${bgInput}" data-tipo="t1_in" value="${dia.t1_in || ''}" ${propDisabled} ${msgTooltip}></td>
                    <td class="border-end"><input type="time" class="cell-input ${bgInput}" data-tipo="t1_out" value="${dia.t1_out || ''}" ${propDisabled} ${msgTooltip}></td>
                    <td><input type="time" class="cell-input ${bgInput}" data-tipo="t2_in" value="${dia.t2_in || ''}" ${propDisabled} ${msgTooltip}></td>
                    <td class="border-end"><input type="time" class="cell-input ${bgInput}" data-tipo="t2_out" value="${dia.t2_out || ''}" ${propDisabled} ${msgTooltip}></td>
                    <td><input type="time" class="cell-input ${bgInput}" data-tipo="t3_in" value="${dia.t3_in || ''}" ${propDisabled} ${msgTooltip}></td>
                    <td class="border-end"><input type="time" class="cell-input ${bgInput}" data-tipo="t3_out" value="${dia.t3_out || ''}" ${propDisabled} ${msgTooltip}></td>
                    
                    <td class="align-middle border-end bg-warning-subtle celda-total-dia ${textColorCls}" style="font-size: 0.85rem;">
                        ${strTotalDia}
                    </td>

                    <td class="align-middle px-2 text-start">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            ${badgeEstado}
                            <button type="button" class="btn btn-sm btn-light text-secondary border border-secondary-subtle p-1 rounded-2 transition-hover btn-justificar" ${propDisabled} title="${esDescanso ? 'No se puede justificar un día libre' : 'Justificar / Comentar'}">
                                <i class="bi ${iconBtn}" style="font-size: 0.8rem; pointer-events: none;"></i>
                            </button>
                        </div>
                    </td>
                `;
                DOM.gridCuerpo.appendChild(tr);
            });
        }

        // ==========================================
        // AUTOGUARDADO (EVENTO CHANGE)
        // ==========================================
        const Toast = typeof Swal !== 'undefined' ? Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            background: '#f8f9fa',
            color: '#198754'
        }) : null;

        if (DOM.gridCuerpo) {
            DOM.gridCuerpo.addEventListener('change', async function(e) {
                if (e.target.classList.contains('cell-input')) {
                    const input = e.target;
                    const tr = input.closest('tr');
                    const fecha = tr.dataset.fecha;
                    const campo = input.dataset.tipo;
                    const valor = input.value;

                    if (!empleadoActualId) return;

                    // Feedback visual inmediato local
                    actualizarTotalFilaUI(tr);

                    const syncStatus = document.getElementById('syncStatus');
                    if (syncStatus) syncStatus.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1"></span> Guardando...';

                    const fd = new FormData();
                    fd.append('accion', 'guardar_celda_excel');
                    fd.append('id_tercero', empleadoActualId);
                    fd.append('fecha', fecha);
                    fd.append('campo', campo);
                    fd.append('valor', valor);
                    
                    // Mantener contexto de filtros
                    if (DOM.selectPeriodo) fd.append('periodo', DOM.selectPeriodo.value);
                    const appendIfExist = (id, key) => { const el = document.getElementById(id); if(el && !el.classList.contains('d-none')) fd.append(key, el.value); };
                    appendIfExist('filtroSemana', 'semana');
                    appendIfExist('filtroMes', 'mes');
                    appendIfExist('filtroDesde', 'fecha_inicio');
                    appendIfExist('filtroHasta', 'fecha_fin');

                    try {
                        const res = await fetch(baseUrl + 'index.php?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                        const data = await res.json();

                        if (!data.ok) {
                            if(Toast) Swal.fire('Error', data.mensaje || 'No se pudo guardar la hora.', 'error');
                            input.classList.add('border-danger', 'text-danger');
                            if (syncStatus) syncStatus.innerHTML = '<i class="bi bi-cloud-slash text-danger fs-5 me-1"></i> Error';
                        } else {
                            if(Toast) Toast.fire({ icon: 'success', title: 'Guardado' });

                            input.classList.remove('border-danger', 'text-danger');
                            if (syncStatus) syncStatus.innerHTML = '<i class="bi bi-cloud-check text-success fs-5 me-1"></i> Sincronizado';

                            if(data.nuevo_estado_html || data.badge_class) {
                                const badgeContainer = tr.querySelector('.badge');
                                if (badgeContainer) {
                                    badgeContainer.className = `badge ${data.badge_class} border-0 px-2 fw-semibold text-truncate`;
                                    badgeContainer.textContent = data.nuevo_estado_label;
                                }
                            }
                            
                            if(data.total_dia_formateado) {
                                tr.querySelector('.celda-total-dia').textContent = data.total_dia_formateado;
                            }
                            
                            if(data.total_regulares_str && DOM.lblTotalRegulares) DOM.lblTotalRegulares.textContent = data.total_regulares_str;
                            if(data.total_extras_str && DOM.lblTotalExtras) DOM.lblTotalExtras.textContent = data.total_extras_str;
                        }
                    } catch (error) {
                        console.error(error);
                        if(Toast) Swal.fire('Error de Red', 'Revisa tu conexión a internet.', 'warning');
                        if (syncStatus) syncStatus.innerHTML = '<i class="bi bi-wifi-off text-warning fs-5 me-1"></i> Desconectado';
                    }
                }
            });

            // ==========================================
            // GESTIÓN DE JUSTIFICACIONES (MODAL)
            // ==========================================
            DOM.gridCuerpo.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-justificar');
                if (btn && !btn.disabled && typeof bootstrap !== 'undefined') {
                    const tr = btn.closest('tr');
                    fechaJustificacionActual = tr.dataset.fecha;
                    
                    const diaNombre = tr.querySelector('.fw-bold.text-dark').textContent;
                    if (DOM.lblModalFecha) DOM.lblModalFecha.textContent = `${diaNombre}, ${fechaJustificacionActual}`;
                    
                    if (DOM.selectEstado) DOM.selectEstado.value = 'ASISTENCIA';
                    if (DOM.txtObservacion) DOM.txtObservacion.value = '';

                    const modalInstance = new bootstrap.Modal(DOM.modalJustificar);
                    modalInstance.show();
                }
            });
        }

        if (DOM.btnAplicarJust) {
            DOM.btnAplicarJust.addEventListener('click', async function() {
                if (!empleadoActualId || !fechaJustificacionActual) return;

                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
                this.disabled = true;

                const fd = new FormData();
                fd.append('accion', 'guardar_justificacion_excel');
                fd.append('id_tercero', empleadoActualId);
                fd.append('fecha', fechaJustificacionActual);
                if (DOM.selectEstado) fd.append('estado', DOM.selectEstado.value);
                if (DOM.txtObservacion) fd.append('observacion', DOM.txtObservacion.value);

                try {
                    const res = await fetch(baseUrl + 'index.php?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.ok) {
                        if (typeof bootstrap !== 'undefined') {
                            const modal = bootstrap.Modal.getInstance(DOM.modalJustificar);
                            if (modal) modal.hide();
                        }
                        cargarDatosGrid(); 
                    } else {
                        if(Toast) Swal.fire('Error', data.mensaje || 'No se guardó la justificación.', 'error');
                    }
                } catch (error) {
                    console.error(error);
                    alert("Error de conexión");
                } finally {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            });
        }
    }

    // ==========================================
    // INICIALIZACIÓN (CANDADO ANTI-DUPLICADOS)
    // ==========================================
    if (!window._asistenciaModuloIniciado) {
        // Al usar Hotwire/Turbo
        document.addEventListener('turbo:load', iniciarModuloExcelAsistencia);
        // Carga normal clásica
        document.addEventListener('DOMContentLoaded', iniciarModuloExcelAsistencia);
        
        // Bloqueo para evitar que este archivo añada Listeners duplicados si se recarga el <script>
        window._asistenciaModuloIniciado = true;
    }
    
    // Ejecución forzada en caso de que el DOM ya esté listo cuando el script cargue dinámicamente
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        iniciarModuloExcelAsistencia();
    }

})();