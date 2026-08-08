/**
 * LÓGICA PARA GESTIÓN DE ASISTENCIA (MODO EXCEL)
 * Archivo: public/assets/js/rrhh/gestion_asistencia.js
 * Compatible con arquitectura SPA (Single Page Application)
 */

(function() {
    'use strict';

    function iniciarModuloExcelAsistencia() {
        const appContenedor = document.getElementById('gestionAsistenciaApp');
        
        // 1. Si no existe la vista en el DOM, o si ya la inicializamos, abortamos.
        if (!appContenedor || appContenedor.dataset.iniciado === '1') return;
        
        // Marcamos la vista como inicializada para no duplicar eventos en la SPA
        appContenedor.dataset.iniciado = '1';

        // ==========================================
        // VARIABLES GLOBALES Y ELEMENTOS DOM
        // ==========================================
        const baseUrl = window.BASE_URL || '/';
        let empleadoActualId = null;
        let fechaJustificacionActual = null;

        const DOM = {
            inputBuscar: document.getElementById('buscarEmpleado'),
            selectGrupo: document.getElementById('filtroGrupo'),
            listaEmpleados: document.getElementById('listaEmpleados'),
            
            lblNombreActivo: document.getElementById('nombreEmpleadoActivo'),
            lblRangoActivo: document.getElementById('rangoActivoLabel'),
            lblTotalHoras: document.getElementById('totalHorasCalculadas'),
            
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
        // ESTADO INICIAL (PANTALLA DE BIENVENIDA)
        // ==========================================
        function mostrarEstadoVacio() {
            DOM.lblNombreActivo.innerHTML = '<i class="bi bi-person-fill text-muted me-2"></i>Esperando selección...';
            DOM.lblRangoActivo.textContent = '--';
            DOM.lblTotalHoras.textContent = '0h 0m';
            
            DOM.gridCuerpo.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 bg-light border-bottom-0">
                        <i class="bi bi-person-lines-fill d-block text-muted opacity-25 mb-3" style="font-size: 4rem;"></i>
                        <h5 class="fw-bold text-dark">Selecciona un Empleado</h5>
                        <p class="text-muted small mb-0">Haz clic en un empleado del panel lateral izquierdo para cargar su cuadrícula de asistencia.</p>
                    </td>
                </tr>
            `;
        }

        // ==========================================
        // AUTO-SELECCIÓN DE EMPLEADO DESDE LA URL 
        // (Viene desde el botón "Corregir" en Planillas)
        // ==========================================
        const params = new URLSearchParams(window.location.search);
        const idTerceroUrl = params.get('id_tercero');

        if (idTerceroUrl) {
            let intentos = 0;
            const buscadorInterval = setInterval(() => {
                // Busca la tarjeta del empleado (usando data-id)
                const tarjetaEmpleado = document.querySelector(`.empleado-item[data-id="${idTerceroUrl}"]`);

                if (tarjetaEmpleado) {
                    clearInterval(buscadorInterval);
                    
                    // Simula el clic en la tarjeta
                    tarjetaEmpleado.click();

                    // Escribe en el buscador para filtrar visualmente
                    if (DOM.inputBuscar) {
                        const nombreTexto = tarjetaEmpleado.querySelector('.fw-bold').textContent.trim();
                        DOM.inputBuscar.value = nombreTexto;
                        DOM.inputBuscar.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    // Limpia la URL sin recargar la página
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
            }, 500);
        } else {
            mostrarEstadoVacio();
        }

        // ==========================================
        // BUSCADOR Y FILTROS LATERALES
        // ==========================================
        const filtrarLista = () => {
            const texto = DOM.inputBuscar.value.toLowerCase().trim();
            const grupoId = DOM.selectGrupo ? DOM.selectGrupo.value : '';
            const items = document.querySelectorAll('.empleado-item');

            items.forEach(item => {
                const nombre = item.querySelector('.fw-bold').textContent.toLowerCase();
                const idGrupoEmpleado = item.dataset.grupo || ''; 

                const coincideTexto = texto === '' || nombre.includes(texto);
                const coincideGrupo = grupoId === '' || idGrupoEmpleado === grupoId;

                if (coincideTexto && coincideGrupo) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        };

        if (DOM.inputBuscar) DOM.inputBuscar.addEventListener('input', filtrarLista);
        if (DOM.selectGrupo) DOM.selectGrupo.addEventListener('change', filtrarLista);

        // ==========================================
        // CAMBIO DE PERIODO (SEMANA/MES/RANGO)
        // ==========================================
        if (DOM.selectPeriodo) {
            DOM.selectPeriodo.addEventListener('change', function() {
                DOM.filtros.forEach(el => el.classList.add('d-none'));
                
                if (this.value === 'semana') document.getElementById('filtroSemana').classList.remove('d-none');
                if (this.value === 'mes') document.getElementById('filtroMes').classList.remove('d-none');
                if (this.value === 'rango') document.getElementById('filtroRango').classList.remove('d-none');
                
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
            if (!empleadoActualId) return;

            DOM.gridCuerpo.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary"></div>Cargando registros...</td></tr>`;

            const fd = new FormData();
            fd.append('accion', 'obtener_grid_excel');
            fd.append('id_tercero', empleadoActualId);
            
            if (DOM.selectPeriodo) fd.append('periodo', DOM.selectPeriodo.value);
            if (document.getElementById('filtroSemana')) fd.append('semana', document.getElementById('filtroSemana').value);
            if (document.getElementById('filtroMes')) fd.append('mes', document.getElementById('filtroMes').value);
            if (document.getElementById('filtroDesde')) fd.append('fecha_inicio', document.getElementById('filtroDesde').value);
            if (document.getElementById('filtroHasta')) fd.append('fecha_fin', document.getElementById('filtroHasta').value);

            try {
                const res = await fetch(baseUrl + '?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    renderizarFilas(data.dias);
                    DOM.lblTotalHoras.textContent = data.total_horas_str || '0h 0m';
                    DOM.lblRangoActivo.textContent = data.rango_label || 'Periodo seleccionado';

                    // --- ALERTA VISUAL DE EMPLEADO SIN HORARIO ---
                    const nombreContainer = DOM.lblNombreActivo;
                    const nombreTexto = nombreContainer.getAttribute('data-nombre-original') || nombreContainer.textContent.replace(/<[^>]*>?/gm, '').trim();
                    nombreContainer.setAttribute('data-nombre-original', nombreTexto);

                    if (data.empleado_sin_horario) {
                        nombreContainer.innerHTML = `
                            ${nombreTexto} 
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-2 fs-6 py-1 px-2" data-bs-toggle="tooltip" title="Este empleado no tiene turnos asignados en este periodo. No se puede registrar asistencia.">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Sin Horario Asignado
                            </span>
                        `;
                    } else {
                        nombreContainer.innerHTML = nombreTexto;
                    }
                } else {
                    DOM.gridCuerpo.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>${data.mensaje || 'Error al cargar datos.'}</td></tr>`;
                }
            } catch (error) {
                console.error(error);
                DOM.gridCuerpo.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Error de conexión con el servidor.</td></tr>`;
            }
        }

        // ==========================================
        // RENDERIZAR FILAS CON BLOQUEO INTELIGENTE
        // ==========================================
        function renderizarFilas(dias) {
            DOM.gridCuerpo.innerHTML = '';
            
            dias.forEach(dia => {
                const tr = document.createElement('tr');
                tr.dataset.fecha = dia.fecha;
                
                // Lógica de Bloqueo por Día de Descanso
                const esDescanso = dia.es_descanso === true; 
                
                const bgFila = esDescanso ? 'bg-light opacity-50' : '';
                const propDisabled = esDescanso ? 'disabled' : '';
                const msgTooltip = esDescanso ? 'title="Día de descanso (Sin horario asignado)"' : '';
                const bgInput = esDescanso ? 'bg-transparent text-muted' : '';
                const iconBtn = esDescanso ? 'bi-lock-fill' : 'bi-chat-left-text';
                
                const badgeEstado = esDescanso 
                    ? '<span class="badge bg-secondary-subtle text-secondary border-0 px-2 fw-semibold">Descanso</span>' 
                    : `<span class="badge ${dia.badge_class || 'bg-secondary-subtle text-secondary'} border-0 px-2 fw-semibold text-truncate" style="max-width: 90px;">${dia.estado_label || 'Sin datos'}</span>`;

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
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            background: '#f8f9fa',
            color: '#198754'
        });

        if (DOM.gridCuerpo) {
            DOM.gridCuerpo.addEventListener('change', async function(e) {
                if (e.target.classList.contains('cell-input')) {
                    const input = e.target;
                    const tr = input.closest('tr');
                    const fecha = tr.dataset.fecha;
                    const campo = input.dataset.tipo;
                    const valor = input.value;

                    if (!empleadoActualId) return;

                    const syncStatus = document.getElementById('syncStatus');
                    if (syncStatus) syncStatus.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1"></span> Guardando...';

                    const fd = new FormData();
                    fd.append('accion', 'guardar_celda_excel');
                    fd.append('id_tercero', empleadoActualId);
                    fd.append('fecha', fecha);
                    fd.append('campo', campo);
                    fd.append('valor', valor);
                    
                    if (DOM.selectPeriodo) fd.append('periodo', DOM.selectPeriodo.value);
                    if (document.getElementById('filtroSemana')) fd.append('semana', document.getElementById('filtroSemana').value);
                    if (document.getElementById('filtroMes')) fd.append('mes', document.getElementById('filtroMes').value);
                    if (document.getElementById('filtroDesde')) fd.append('fecha_inicio', document.getElementById('filtroDesde').value);
                    if (document.getElementById('filtroHasta')) fd.append('fecha_fin', document.getElementById('filtroHasta').value);

                    try {
                        const res = await fetch(baseUrl + '?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                        const data = await res.json();

                        if (!data.ok) {
                            if(typeof Swal !== 'undefined') Swal.fire('Error', data.mensaje || 'No se pudo guardar la hora.', 'error');
                            input.classList.add('border-danger', 'text-danger');
                            if (syncStatus) syncStatus.innerHTML = '<i class="bi bi-cloud-slash text-danger fs-5 me-1"></i> Error';
                        } else {
                            Toast.fire({ icon: 'success', title: 'Dato actualizado' });

                            input.classList.remove('border-danger', 'text-danger');
                            if (syncStatus) syncStatus.innerHTML = '<i class="bi bi-cloud-check text-success fs-5 me-1"></i> Sincronizado';

                            if(data.nuevo_estado_html || data.badge_class) {
                                const badgeContainer = tr.querySelector('.badge');
                                badgeContainer.className = `badge ${data.badge_class} border-0 px-2 fw-semibold text-truncate`;
                                badgeContainer.textContent = data.nuevo_estado_label;
                            }
                            if(data.total_horas_str) {
                                DOM.lblTotalHoras.textContent = data.total_horas_str;
                            }
                        }
                    } catch (error) {
                        console.error(error);
                        if(typeof Swal !== 'undefined') Swal.fire('Error de Red', 'Revisa tu conexión a internet.', 'warning');
                        if (syncStatus) syncStatus.innerHTML = '<i class="bi bi-wifi-off text-warning fs-5 me-1"></i> Desconectado';
                    }
                }
            });

            // ==========================================
            // GESTIÓN DE JUSTIFICACIONES (MODAL)
            // ==========================================
            DOM.gridCuerpo.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-justificar');
                if (btn && !btn.disabled) {
                    const tr = btn.closest('tr');
                    fechaJustificacionActual = tr.dataset.fecha;
                    
                    const diaNombre = tr.querySelector('.fw-bold.text-dark').textContent;
                    DOM.lblModalFecha.textContent = `${diaNombre}, ${fechaJustificacionActual}`;
                    
                    DOM.selectEstado.value = 'ASISTENCIA';
                    DOM.txtObservacion.value = '';

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
                fd.append('estado', DOM.selectEstado.value);
                fd.append('observacion', DOM.txtObservacion.value);

                try {
                    const res = await fetch(baseUrl + '?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.ok) {
                        bootstrap.Modal.getInstance(DOM.modalJustificar).hide();
                        cargarDatosGrid(); 
                    } else {
                        if(typeof Swal !== 'undefined') Swal.fire('Error', data.mensaje || 'No se guardó la justificación.', 'error');
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

    // 1. Ejecutar inmediatamente al cargar el script
    iniciarModuloExcelAsistencia();
    
    // 2. Ejecutar si la página hace una recarga dura
    document.addEventListener('DOMContentLoaded', iniciarModuloExcelAsistencia);

})();