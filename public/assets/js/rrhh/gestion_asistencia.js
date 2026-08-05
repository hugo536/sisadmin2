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
        // BUSCADOR Y FILTROS LATERALES
        // ==========================================
        const filtrarLista = () => {
            const texto = DOM.inputBuscar.value.toLowerCase().trim();
            const grupoId = DOM.selectGrupo.value;
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
                
                cargarDatosGrid(); 
            });
        }

        DOM.filtros.forEach(input => {
            input.addEventListener('change', cargarDatosGrid);
        });

        // ==========================================
        // SELECCIÓN DE EMPLEADO (DELEGACIÓN DE EVENTOS)
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

            DOM.gridCuerpo.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando registros...</td></tr>`;

            const fd = new FormData();
            fd.append('accion', 'obtener_grid_excel');
            fd.append('id_tercero', empleadoActualId);
            fd.append('periodo', DOM.selectPeriodo.value);
            fd.append('semana', document.getElementById('filtroSemana').value);
            fd.append('mes', document.getElementById('filtroMes').value);
            fd.append('fecha_inicio', document.getElementById('filtroDesde').value);
            fd.append('fecha_fin', document.getElementById('filtroHasta').value);

            try {
                const res = await fetch(baseUrl + '?ruta=asistencia/gestion_asistencia', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    renderizarFilas(data.dias);
                    DOM.lblTotalHoras.textContent = data.total_horas_str || '0h 0m';
                    DOM.lblRangoActivo.textContent = data.rango_label || 'Periodo seleccionado';
                } else {
                    DOM.gridCuerpo.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>${data.mensaje || 'Error al cargar datos.'}</td></tr>`;
                }
            } catch (error) {
                console.error(error);
                DOM.gridCuerpo.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Error de conexión con el servidor.</td></tr>`;
            }
        }

        function renderizarFilas(dias) {
            DOM.gridCuerpo.innerHTML = '';
            
            dias.forEach(dia => {
                const tr = document.createElement('tr');
                tr.dataset.fecha = dia.fecha;
                
                tr.innerHTML = `
                    <td class="bg-light align-middle text-start ps-3 border-end">
                        <span class="fw-bold text-dark d-block" style="font-size: 0.85rem;">${dia.nombre_dia}</span>
                        <span class="text-muted fw-medium" style="font-size: 0.7rem;">${dia.fecha_formateada}</span>
                    </td>
                    <td><input type="time" class="cell-input" data-tipo="t1_in" value="${dia.t1_in || ''}"></td>
                    <td class="border-end"><input type="time" class="cell-input" data-tipo="t1_out" value="${dia.t1_out || ''}"></td>
                    <td><input type="time" class="cell-input" data-tipo="t2_in" value="${dia.t2_in || ''}"></td>
                    <td class="border-end"><input type="time" class="cell-input" data-tipo="t2_out" value="${dia.t2_out || ''}"></td>
                    <td><input type="time" class="cell-input" data-tipo="t3_in" value="${dia.t3_in || ''}"></td>
                    <td class="border-end"><input type="time" class="cell-input" data-tipo="t3_out" value="${dia.t3_out || ''}"></td>
                    <td class="align-middle px-2 text-start">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <span class="badge ${dia.badge_class || 'bg-secondary-subtle text-secondary'} border-0 px-2 fw-semibold text-truncate" style="font-size: 0.7rem; max-width: 90px;">${dia.estado_label || 'Sin datos'}</span>
                            <button type="button" class="btn btn-sm btn-light text-secondary border border-secondary-subtle p-1 rounded-2 transition-hover btn-justificar" title="Justificar / Comentar">
                                <i class="bi bi-chat-left-text" style="font-size: 0.8rem; pointer-events: none;"></i>
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
                    fd.append('periodo', DOM.selectPeriodo.value);
                    fd.append('semana', document.getElementById('filtroSemana').value);
                    fd.append('mes', document.getElementById('filtroMes').value);
                    fd.append('fecha_inicio', document.getElementById('filtroDesde').value);
                    fd.append('fecha_fin', document.getElementById('filtroHasta').value);

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

                            if(data.nuevo_estado_html) {
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
                if (btn) {
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

    // 1. Ejecutar inmediatamente al cargar el script (Funciona para navegación SPA)
    iniciarModuloExcelAsistencia();
    
    // 2. Ejecutar si la página hace una recarga dura (F5 tradicional)
    document.addEventListener('DOMContentLoaded', iniciarModuloExcelAsistencia);

})();