document.addEventListener('DOMContentLoaded', () => {

    const formFiltros = document.getElementById('formFiltrosPlanillas');
    const searchInput = document.getElementById('searchPlanilla');
    const tablaPlanillas = document.getElementById('planillasTable'); 

    // --- VARIABLES GLOBALES PARA EL CALENDARIO ---
    let calIdEmpleado = 0;
    let calFechaActual = new Date(); // Mes que se está visualizando en el modal
    let diccAsistenciaGlobal = {};   // Guardará los datos del mes para usarlos al hacer clic

    // --- DELEGACIÓN DE EVENTOS PARA MODALES (Pago y Calendario) ---
    if (tablaPlanillas) {
        tablaPlanillas.addEventListener('click', (e) => {
            // 1. Lógica para Modal de Pagar
            const botonPago = e.target.closest('button[data-bs-target="#modalPagarPlanilla"]');
            if (botonPago) {
                document.getElementById('pagoIdEmpleado').value = botonPago.getAttribute('data-id-empleado');
                document.getElementById('pagoMontoTotal').value = botonPago.getAttribute('data-monto-pagar');
                document.getElementById('pagoFechaDesde').value = botonPago.getAttribute('data-fecha-desde');
                document.getElementById('pagoFechaHasta').value = botonPago.getAttribute('data-fecha-hasta');
                
                document.getElementById('lblEmpleadoNombre').textContent = botonPago.getAttribute('data-nombre-empleado');
                document.getElementById('lblPeriodo').textContent = botonPago.getAttribute('data-fecha-desde') + ' al ' + botonPago.getAttribute('data-fecha-hasta');
                return;
            }

            // 2. Lógica para Modal de Calendario
            const botonCalendario = e.target.closest('.btn-ver-calendario');
            if (botonCalendario) {
                calIdEmpleado = botonCalendario.getAttribute('data-id-empleado');
                const nombreEmpleado = botonCalendario.getAttribute('data-nombre-empleado');
                const desdeOriginal = botonCalendario.getAttribute('data-fecha-desde');
                const hastaOriginal = botonCalendario.getAttribute('data-fecha-hasta');

                document.getElementById('calNombreEmpleado').textContent = nombreEmpleado;
                document.getElementById('calPeriodoOriginal').textContent = `Periodo filtrado: ${desdeOriginal} al ${hastaOriginal}`;
                
                // NUEVO: Iniciamos el calendario en el mes ACTUAL por defecto
                calFechaActual = new Date(); 
                cargarMesCalendario();
            }
        });
    }

    // --- LÓGICA DEL CALENDARIO MENSUAL ---

    // Eventos para botones de navegación del mes
    document.getElementById('btnMesAnterior')?.addEventListener('click', () => {
        calFechaActual.setMonth(calFechaActual.getMonth() - 1);
        cargarMesCalendario();
    });

    document.getElementById('btnMesSiguiente')?.addEventListener('click', () => {
        calFechaActual.setMonth(calFechaActual.getMonth() + 1);
        cargarMesCalendario();
    });

    async function cargarMesCalendario() {
        const loader = document.getElementById('calLoader');
        const grid = document.getElementById('calendarioGrid');
        const lblMes = document.getElementById('lblMesActual');

        if (!grid || !loader || !lblMes) return;

        // Calcular primer y último día del mes actual en visualización
        const year = calFechaActual.getFullYear();
        const month = calFechaActual.getMonth();
        
        const primerDia = new Date(year, month, 1);
        const ultimoDia = new Date(year, month + 1, 0);

        const formatDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        
        const strDesde = formatDate(primerDia);
        const strHasta = formatDate(ultimoDia);

        // Actualizar etiqueta del mes en el Modal
        const nombresMeses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        lblMes.textContent = `${nombresMeses[month]} ${year}`;

        loader.classList.remove('d-none');

        try {
            // Consultar el endpoint de Asistencia
            const url = `?ruta=planillas/api_asistencia_calendario&id_tercero=${calIdEmpleado}&desde=${strDesde}&hasta=${strHasta}`;
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            const resultado = await response.json();

            if (!resultado.ok) throw new Error(resultado.mensaje || 'Error desconocido.');

            dibujarGridMes(resultado.data || [], primerDia, ultimoDia);

        } catch (error) {
            grid.innerHTML = `<div class="alert alert-danger m-0" style="grid-column: span 7;">${error.message}</div>`;
        } finally {
            loader.classList.add('d-none');
        }
    }

    function dibujarGridMes(asistencias, primerDia, ultimoDia) {
        const grid = document.getElementById('calendarioGrid');
        
        // Limpiamos y llenamos el diccionario global
        diccAsistenciaGlobal = {};
        asistencias.forEach(a => { diccAsistenciaGlobal[a.fecha] = a; });

        let html = '';
        
        // Determinar qué día de la semana cae el día 1 (0=Dom, 1=Lun... 6=Sab)
        // Convertimos a formato Europeo para que la semana empiece en Lunes (0=Lun, 6=Dom)
        let inicioSemana = primerDia.getDay();
        inicioSemana = inicioSemana === 0 ? 6 : inicioSemana - 1; 

        // Rellenar espacios vacíos antes del día 1
        for (let i = 0; i < inicioSemana; i++) {
            html += `<div class="bg-light rounded opacity-25" style="min-height: 70px;"></div>`;
        }

        // Dibujar los días del mes
        const totalDias = ultimoDia.getDate();
        for (let dia = 1; dia <= totalDias; dia++) {
            const fechaActualLoop = new Date(primerDia.getFullYear(), primerDia.getMonth(), dia);
            const dateStr = `${fechaActualLoop.getFullYear()}-${String(fechaActualLoop.getMonth() + 1).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
            const esDomingo = fechaActualLoop.getDay() === 0;
            
            const registro = diccAsistenciaGlobal[dateStr];
            
            let colorClase = 'bg-white border-secondary-subtle border-dashed'; // Por defecto: Sin registro
            let textoClase = 'text-secondary';
            let cursorClase = 'cursor-pointer'; // Todos los días serán cliqueables

            if (esDomingo && !registro) {
                colorClase = 'bg-light border-light'; // Domingo vacío
            } else if (registro) {
                const estado = (registro.estado_asistencia || '').toUpperCase();
                textoClase = 'text-dark';
                
                if (estado === 'PUNTUAL') colorClase = 'bg-success-subtle border-success text-success';
                else if (estado === 'FALTA') colorClase = 'bg-danger-subtle border-danger text-danger';
                else if (estado.includes('TARDANZA') || estado === 'INCOMPLETO') colorClase = 'bg-warning-subtle border-warning text-warning-emphasis';
                else if (['PERMISO', 'VACACIONES', 'DESCANSO MEDICO', 'FALTA JUSTIFICADA'].includes(estado)) colorClase = 'bg-info-subtle border-info text-info-emphasis';
            }

            // Indicador visual si hubo horas extras
            const badgeExtra = (registro && registro.horas_extras > 0) ? `<div class="position-absolute top-0 end-0 mt-1 me-1 text-primary"><i class="bi bi-plus-circle-fill"></i></div>` : '';

            html += `
                <div class="card shadow-sm position-relative ${colorClase} ${cursorClase} hover-lift transition-all" 
                     style="min-height: 70px;" 
                     onclick="mostrarDetalleDia('${dateStr}')">
                    ${badgeExtra}
                    <div class="card-body p-2 d-flex flex-column align-items-center justify-content-center">
                        <span class="fs-5 fw-bold ${textoClase} lh-1">${dia}</span>
                    </div>
                </div>
            `;
        }

        grid.innerHTML = html;
    }

    // --- FUNCIÓN DEL CLIC: DIVULGACIÓN PROGRESIVA (SweetAlert2) ---
    window.mostrarDetalleDia = function(dateStr) {
        const registro = diccAsistenciaGlobal[dateStr];
        
        // Formatear la fecha para que se vea bonita (Ej. Jueves, 5 de Marzo de 2026)
        const [y, m, d] = dateStr.split('-');
        const fechaBonita = new Date(y, m - 1, d).toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        if (!registro) {
            Swal.fire({
                title: 'Sin Asistencia',
                text: `No hay datos registrados para el ${fechaBonita}.`,
                icon: 'info',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        // Construir el cuerpo del Pop-Up con los datos reales
        let htmlDetalle = `
            <div class="text-start mt-3">
                <p class="mb-2"><strong>Estado:</strong> <span class="badge bg-dark">${registro.estado_asistencia}</span></p>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light text-center">
                            <small class="text-muted d-block">Hora Ingreso</small>
                            <strong>${registro.hora_ingreso || '--:--'}</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light text-center">
                            <small class="text-muted d-block">Hora Salida</small>
                            <strong>${registro.hora_salida || '--:--'}</strong>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="p-2 border border-warning rounded bg-warning-subtle text-warning-emphasis text-center">
                            <small class="d-block">Tardanza</small>
                            <strong>${registro.minutos_tardanza || 0} min</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border border-success rounded bg-success-subtle text-success text-center">
                            <small class="d-block">Hrs Extras</small>
                            <strong>+${registro.horas_extras || 0} hrs</strong>
                        </div>
                    </div>
                </div>
                ${registro.observaciones ? `<p class="mt-3 mb-0 small text-muted"><strong>Obs:</strong> ${registro.observaciones}</p>` : ''}
            </div>
        `;

        Swal.fire({
            title: `<span class="fs-5 text-capitalize">${fechaBonita}</span>`,
            html: htmlDetalle,
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#6c757d',
            customClass: {
                popup: 'rounded-4'
            }
        });
    };


    // ==============================================================
    // LÓGICA DE FILTROS Y BÚSQUEDA AUTOMÁTICA (AJAX)
    // ==============================================================

    // 1. CANDADO AL BUSCADOR
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });
    }

    if (!formFiltros) return;

    // 2. CANDADO AL FORMULARIO
    formFiltros.addEventListener('submit', (e) => {
        e.preventDefault();
        triggerAutoSubmit();
    });

    // 3. CAPTURAMOS LOS FILTROS
    const inputDesde = formFiltros.querySelector('input[name="desde"]');
    const inputHasta = formFiltros.querySelector('input[name="hasta"]');
    const selectFrecuencia = formFiltros.querySelector('select[name="frecuencia_pago"]');
    const selectEmpleado = formFiltros.querySelector('select[name="id_tercero"]');
    const inputSemana = document.getElementById('filtroSemana');

    const obtenerRangoSemanaDesdeISO = (isoWeek) => {
        if (!isoWeek || !/^\d{4}-W\d{2}$/.test(isoWeek)) return null;

        const [yearText, weekText] = isoWeek.split('-W');
        const year = Number(yearText);
        const week = Number(weekText);
        if (!year || !week) return null;

        const jan4 = new Date(Date.UTC(year, 0, 4));
        const jan4Day = jan4.getUTCDay() || 7;
        const mondayWeek1 = new Date(jan4);
        mondayWeek1.setUTCDate(jan4.getUTCDate() - jan4Day + 1);

        const monday = new Date(mondayWeek1);
        monday.setUTCDate(mondayWeek1.getUTCDate() + (week - 1) * 7);

        const sunday = new Date(monday);
        sunday.setUTCDate(monday.getUTCDate() + 6);

        const format = (date) => date.toISOString().slice(0, 10);
        return { desde: format(monday), hasta: format(sunday) };
    };

    // --- EL DEBOUNCE ---
    let debounceTimer;

    const autoSubmitForm = async () => {
        
        const paginationInfo = document.getElementById('planillasPaginationInfo');
        if (paginationInfo) paginationInfo.textContent = 'Calculando nómina...';

        if (window.planillasManager) window.planillasManager.showLoading();

        const url = new URL(window.location.href);
        
        if (inputDesde) url.searchParams.set('desde', inputDesde.value);
        if (inputHasta) url.searchParams.set('hasta', inputHasta.value);
        
        if (selectFrecuencia) {
            if (selectFrecuencia.value === "") {
                url.searchParams.delete('frecuencia_pago');
            } else {
                url.searchParams.set('frecuencia_pago', selectFrecuencia.value);
            }
        }

        if (selectEmpleado) {
            if (selectEmpleado.value === "") {
                url.searchParams.delete('id_tercero');
            } else {
                url.searchParams.set('id_tercero', selectEmpleado.value);
            }
        }

        try {
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Error al conectar con el servidor');
            const html = await response.text();

            const parser = new DOMParser();
            const docVirtual = parser.parseFromString(html, 'text/html');

            ['totalPlanilla', 'totalExtras', 'totalDescuentos'].forEach(id => {
                const el = document.getElementById(id);
                const virtualEl = docVirtual.getElementById(id);
                if (el && virtualEl) el.innerHTML = virtualEl.innerHTML;
            });

            const currentTbody = document.querySelector('#planillasTable tbody');
            const newTbody = docVirtual.querySelector('#planillasTable tbody');
            if (currentTbody && newTbody) currentTbody.innerHTML = newTbody.innerHTML;

            const badge = document.querySelector('.badge.bg-primary-subtle');
            const newBadge = docVirtual.querySelector('.badge.bg-primary-subtle');
            if (badge && newBadge) badge.innerHTML = newBadge.innerHTML;

            if (window.planillasManager && typeof window.planillasManager.refresh === 'function') {
                window.planillasManager.refresh();
            }

            if (window.ERPTable && typeof window.ERPTable.initTooltips === 'function') {
                window.ERPTable.initTooltips();
            }

            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Error en la actualización AJAX:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Aviso', 'No se pudo actualizar en tiempo real.', 'warning');
            }
        } finally {
            if (window.planillasManager) window.planillasManager.hideLoading();
        }
    };

    const triggerAutoSubmit = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            autoSubmitForm(); 
        }, 150); 
    };

    // ESCUCHAMOS CAMBIOS EN LOS FILTROS
    if (inputDesde) inputDesde.addEventListener('change', () => {
        if (inputSemana) inputSemana.value = '';
        triggerAutoSubmit();
    });

    if (inputHasta) inputHasta.addEventListener('change', () => {
        if (inputSemana) inputSemana.value = '';
        triggerAutoSubmit();
    });
    
    if (selectFrecuencia) selectFrecuencia.addEventListener('change', triggerAutoSubmit); 
    if (selectEmpleado) selectEmpleado.addEventListener('change', triggerAutoSubmit);

    if (inputSemana) {
        inputSemana.addEventListener('change', () => {
            const rango = obtenerRangoSemanaDesdeISO(inputSemana.value);
            if (!rango) return;

            if (inputDesde) inputDesde.value = rango.desde;
            if (inputHasta) inputHasta.value = rango.hasta;
            triggerAutoSubmit();
        });
    }

});