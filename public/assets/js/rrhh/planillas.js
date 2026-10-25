/**
 * LÓGICA PARA EL MÓDULO DE PLANILLAS Y PAGOS
 * Archivo: public/assets/js/rrhh/planillas.js
 * Compatible con arquitectura SPA y Hotwire/Turbo
 */

(function() {
    'use strict';

    // 1. CAPTURADOR GLOBAL DE ALERTAS PHP (URL)
    function procesarAlertasUrl() {
        const url = new URL(window.location.href);
        const msgOk = url.searchParams.get('ok');
        const msgError = url.searchParams.get('error');

        if (msgOk || msgError) {
            const mensaje = msgOk || msgError;
            const icono = msgOk ? 'success' : 'error';
            const titulo = msgOk ? 'Éxito' : 'Atención';
            const color = msgOk ? '#198754' : '#dc3545';

            let intentosSwal = 0;
            const checkSwal = setInterval(() => {
                if (typeof Swal !== 'undefined') {
                    clearInterval(checkSwal);
                    Swal.fire({
                        icon: icono,
                        title: titulo,
                        text: mensaje,
                        confirmButtonColor: color
                    });
                    
                    // Limpiar la URL sin recargar
                    url.searchParams.delete('ok');
                    url.searchParams.delete('error');
                    window.history.replaceState(null, '', url.href);
                } else {
                    intentosSwal++;
                    if (intentosSwal > 15) { // Espera máxima de 1.5s
                        clearInterval(checkSwal);
                        alert(mensaje);
                        url.searchParams.delete('ok');
                        url.searchParams.delete('error');
                        window.history.replaceState(null, '', url.href);
                    }
                }
            }, 100);
        }
    }

    function iniciarModuloPlanillas() {
        // Disparar captura de alertas de PHP inmediatamente
        procesarAlertasUrl();

        const appContenedor = document.getElementById('planillasApp');
        
        if (!appContenedor || appContenedor.dataset.iniciado === '1') return;
        appContenedor.dataset.iniciado = '1';

        const modalAjustar = document.getElementById('modalAjustarNomina');
        const contenedorMovimientos = document.getElementById('contenedorMovimientosNomina');
        const tplMovimiento = document.getElementById('tplMovimientoNomina');
        const btnAgregarMovimiento = document.getElementById('btnAgregarMovimientoNomina');

        function renombrarCamposMovimientos() {
            if (!contenedorMovimientos) return;
            const items = contenedorMovimientos.querySelectorAll('.movimiento-nomina-item');
            items.forEach((item, idx) => {
                const lbl = item.querySelector('.js-mov-index');
                if (lbl) lbl.textContent = `#${idx + 1}`;
                item.querySelectorAll('[data-name]').forEach((field) => {
                    const key = field.getAttribute('data-name');
                    field.setAttribute('name', `movimientos[${idx}][${key}]`);
                });
                const btnRemove = item.querySelector('.js-remove-movimiento');
                if (btnRemove) btnRemove.disabled = items.length === 1;
            });
        }

        function crearItemMovimiento(data = {}) {
            if (!contenedorMovimientos || !tplMovimiento) return null;
            const nodo = tplMovimiento.content.firstElementChild.cloneNode(true);
            
            const tipo = (data.tipo || '').toString().trim().toUpperCase();
            const categoria = (data.categoria || '').toString().trim();
            const descripcion = (data.descripcion || '').toString().trim();
            const monto = Number.parseFloat(data.monto ?? 0);

            const inputTipo = nodo.querySelector('[data-name="tipo_concepto"]');
            const inputCategoria = nodo.querySelector('[data-name="categoria_concepto"]');
            const inputDescripcion = nodo.querySelector('[data-name="descripcion"]');
            const inputMonto = nodo.querySelector('[data-name="monto"]');
            
            const categoriasDisponibles = {
                'PERCEPCION': ['Mérito / Productividad', 'Movilidad / Viáticos', 'Reintegro de Gastos', 'Bono Extra', 'Otros'],
                'DEDUCCION': ['Adelanto de Sueldo', 'Penalidad / Multa', 'Falta no justificada', 'Otros']
            };

            const actualizarDependencias = (tipoSeleccionado, categoriaActual) => {
                inputCategoria.innerHTML = '<option value="" disabled selected>Seleccione categoría...</option>';
                
                if (categoriasDisponibles[tipoSeleccionado]) {
                    categoriasDisponibles[tipoSeleccionado].forEach(cat => {
                        inputCategoria.add(new Option(cat, cat));
                    });
                }
                
                if (categoriaActual && categoriasDisponibles[tipoSeleccionado]?.includes(categoriaActual)) {
                    inputCategoria.value = categoriaActual;
                } else if (categoriaActual) {
                    inputCategoria.add(new Option(categoriaActual, categoriaActual));
                    inputCategoria.value = categoriaActual;
                }

                inputMonto.classList.remove('text-primary', 'text-success', 'text-danger', 'text-dark');
                if (tipoSeleccionado === 'PERCEPCION') {
                    inputMonto.classList.add('text-primary'); 
                } else if (tipoSeleccionado === 'DEDUCCION') {
                    inputMonto.classList.add('text-danger'); 
                } else {
                    inputMonto.classList.add('text-dark'); 
                }
            };

            if (inputTipo) {
                inputTipo.addEventListener('change', (e) => {
                    actualizarDependencias(e.target.value, '');
                });
            }

            if (inputTipo && (tipo === 'PERCEPCION' || tipo === 'DEDUCCION')) {
                inputTipo.value = tipo;
                actualizarDependencias(tipo, categoria);
            } else {
                actualizarDependencias('', '');
            }

            if (inputDescripcion) inputDescripcion.value = descripcion;
            if (inputMonto && Number.isFinite(monto) && monto > 0) inputMonto.value = monto.toFixed(2);

            contenedorMovimientos.appendChild(nodo);
            return nodo;
        }

        function agregarMovimientoInicial() {
            if (!contenedorMovimientos || !tplMovimiento) return;
            crearItemMovimiento();
            renombrarCamposMovimientos();
        }

        async function cargarMovimientosGuardados(idDetalle) {
            if (!contenedorMovimientos || !idDetalle) return;
            const url = `${window.BASE_URL}?ruta=planillas&accion=movimientos_detalle&id_detalle=${encodeURIComponent(idDetalle)}`;

            try {
                const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                const movimientos = Array.isArray(data?.movimientos) ? data.movimientos : [];

                contenedorMovimientos.innerHTML = '';
                if (movimientos.length === 0) {
                    agregarMovimientoInicial();
                    return;
                }

                movimientos.forEach((mov) => {
                    crearItemMovimiento({
                        id_concepto: mov.id || '',
                        id_adelanto_ref: mov.id_adelanto_ref || '',
                        tipo: mov.tipo,
                        categoria: mov.categoria,
                        descripcion: mov.descripcion,
                        monto: mov.monto,
                    });
                });
                renombrarCamposMovimientos();
            } catch (error) {
                console.error('No se pudieron cargar movimientos guardados', error);
                contenedorMovimientos.innerHTML = '';
                agregarMovimientoInicial();
            }
        }

        function validarDuplicadosMovimientos() {
            if (!contenedorMovimientos) return true;
            const vistos = new Set();
            const items = contenedorMovimientos.querySelectorAll('.movimiento-nomina-item');
            for (const item of items) {
                const tipo = (item.querySelector('[data-name="tipo_concepto"]')?.value || '').trim().toUpperCase();
                const categoria = (item.querySelector('[data-name="categoria_concepto"]')?.value || '').trim().toLowerCase();
                const descripcion = (item.querySelector('[data-name="descripcion"]')?.value || '').trim().toLowerCase();
                const llave = `${tipo}::${categoria}::${descripcion}`;
                if (vistos.has(llave)) return false;
                vistos.add(llave);
            }
            return true;
        }

        if (modalAjustar) {
            modalAjustar.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const nombre = button.getAttribute('data-nombre');

                const inputAjusteIdDetalle = document.getElementById('ajusteIdDetalle');
                const spanAjusteNombre = document.getElementById('ajusteNombreEmpleado');

                if (inputAjusteIdDetalle) inputAjusteIdDetalle.value = id;
                if (spanAjusteNombre) spanAjusteNombre.textContent = nombre;

                const formAjuste = modalAjustar.querySelector('form');
                if (formAjuste) {
                    if (contenedorMovimientos) {
                        contenedorMovimientos.innerHTML = '<div class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando ajustes...</div>';
                        cargarMovimientosGuardados(id);
                    }
                    restaurarBotonSubmit(formAjuste);
                }
            });
        }

        if (btnAgregarMovimiento) {
            btnAgregarMovimiento.addEventListener('click', (e) => {
                e.preventDefault();
                agregarMovimientoInicial();
            });
        }

        if (contenedorMovimientos) {
            contenedorMovimientos.addEventListener('click', (e) => {
                const btn = e.target.closest('.js-remove-movimiento');
                if (!btn) return;
                const item = btn.closest('.movimiento-nomina-item');
                if (item) {
                    item.remove();
                    if (!contenedorMovimientos.querySelector('.movimiento-nomina-item')) {
                        agregarMovimientoInicial();
                    } else {
                        renombrarCamposMovimientos();
                    }
                }
            });
        }

        const formAjustar = document.querySelector('#modalAjustarNomina form');
        if (formAjustar) {
            formAjustar.addEventListener('submit', function (e) {
                e.preventDefault(); 

                if (!validarDuplicadosMovimientos()) {
                    e.stopPropagation();
                    if(typeof Swal !== 'undefined') {
                        Swal.fire('Atención', 'Hay movimientos repetidos. Ajusta tipo/categoría/descripción para continuar.', 'warning');
                    } else {
                        alert('Hay movimientos repetidos. Ajusta tipo/categoría/descripción para continuar.');
                    }
                    return;
                }

                if (this.checkValidity()) {
                    bloquearBotonSubmit(this, "Guardando...");
                    const formData = new FormData(this);

                    fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'error') {
                            restaurarBotonSubmit(this);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Atención',
                                    text: data.message,
                                    confirmButtonColor: '#dc3545'
                                });
                            } else {
                                alert(data.message);
                            }
                        } else if (data.status === 'success') {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Éxito',
                                    text: data.message,
                                    confirmButtonColor: '#198754'
                                }).then(() => { window.location.reload(); });
                            } else {
                                alert(data.message);
                                window.location.reload();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        restaurarBotonSubmit(this);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', 'Hubo un problema de conexión con el servidor.', 'error');
                        }
                    });
                } else {
                    this.reportValidity();
                }
            });
        }

        // ==============================================================
        // LÓGICA DE GENERACIÓN DE LOTES (CON SOPORTE SEMANAL ISO 8601)
        // ==============================================================
        const modalGenerarLote = document.getElementById('modalGenerarLote');
        const selectFrecuenciaLote = document.getElementById('frecuenciaLote');
        const inputFechaInicioLote = document.getElementById('fechaInicioLote');
        const inputFechaFinLote = document.getElementById('fechaFinLote');
        const ayudaFrecuenciaLote = document.getElementById('ayudaFrecuenciaLote');
        const inputNombreGenerado = document.getElementById('nombreGeneradoLote');

        const divRangoNormal = document.getElementById('rangoFechasNormal');
        const divRangoSemanal = document.getElementById('rangoFechasSemanal');
        const inputSemana = document.getElementById('semanaLoteInput');

        const PERIODOS_DIAS = { TODOS: 30, SEMANAL: 7, QUINCENAL: 15, MENSUAL: 30 };
        const MENSAJES_PERIODO = {
            TODOS: 'Rango libre recomendado hasta 30 días. Se calcularán todos los empleados.',
            SEMANAL: 'Se ha seleccionado una semana exacta (Rango estricto de Lunes a Domingo).',
            QUINCENAL: 'Se configuró un rango de 15 días para empleados con frecuencia quincenal.',
            MENSUAL: 'Se configuró un rango de 30 días para empleados con frecuencia mensual.'
        };

        function formatDateISO(date) { return date.toISOString().slice(0, 10); }
        function formatLatino(dateStr) { if (!dateStr) return ''; const [year, month, day] = dateStr.split('-'); return `${day}/${month}/${year}`; }
        function addDays(dateValue, days) { const date = new Date(dateValue + 'T00:00:00'); if (isNaN(date.getTime())) return null; date.setDate(date.getDate() + days); return date; }

        function obtenerSemanaISOActual() {
            const hoy = new Date();
            const d = new Date(Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()));
            const diaSemana = d.getUTCDay() || 7; 
            d.setUTCDate(d.getUTCDate() + 4 - diaSemana);
            const inicioAnio = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            const numSemana = Math.ceil((((d - inicioAnio) / 86400000) + 1) / 7);
            return `${d.getUTCFullYear()}-W${numSemana.toString().padStart(2, '0')}`;
        }

        function calcularRangoDesdeSemanaISO(semanaISO) {
            if (!semanaISO) return null;
            const [anioStr, semanaStr] = semanaISO.split('-W');
            const anio = parseInt(anioStr, 10);
            const semana = parseInt(semanaStr, 10);
            
            const simple = new Date(anio, 0, 1 + (semana - 1) * 7);
            const inicioISO = new Date(simple);
            
            if (simple.getDay() <= 4) {
                inicioISO.setDate(simple.getDate() - simple.getDay() + 1);
            } else {
                inicioISO.setDate(simple.getDate() + 8 - simple.getDay());
            }
            
            const lunes = new Date(inicioISO);
            const domingo = new Date(inicioISO);
            domingo.setDate(domingo.getDate() + 6);
            
            return { inicio: formatDateISO(lunes), fin: formatDateISO(domingo) };
        }

        function actualizarNombreLote() {
            if (inputNombreGenerado && inputFechaInicioLote.value && inputFechaFinLote.value) {
                inputNombreGenerado.value = `NOM - ${formatLatino(inputFechaInicioLote.value)} al ${formatLatino(inputFechaFinLote.value)}`;
            }
        }

        function ajustarRangoPorFrecuencia() {
            if (!selectFrecuenciaLote || !inputFechaInicioLote || !inputFechaFinLote) return;
            const frecuencia = (selectFrecuenciaLote.value || 'TODOS').toUpperCase();
            
            if (ayudaFrecuenciaLote) {
                ayudaFrecuenciaLote.innerHTML = `<i class="bi bi-info-circle text-primary me-1"></i> ${MENSAJES_PERIODO[frecuencia] ?? MENSAJES_PERIODO.TODOS}`;
            }

            if (frecuencia === 'SEMANAL') {
                if (divRangoNormal) divRangoNormal.classList.add('d-none');
                if (divRangoSemanal) divRangoSemanal.classList.remove('d-none');
                
                if (inputSemana && !inputSemana.value) {
                    inputSemana.value = obtenerSemanaISOActual();
                }
                
                if (inputSemana) {
                    const rango = calcularRangoDesdeSemanaISO(inputSemana.value);
                    if (rango) {
                        inputFechaInicioLote.value = rango.inicio;
                        inputFechaFinLote.value = rango.fin;
                        inputFechaFinLote.min = rango.inicio;
                        inputFechaFinLote.max = rango.fin;
                    }
                }
            } else {
                if (divRangoNormal) divRangoNormal.classList.remove('d-none');
                if (divRangoSemanal) divRangoSemanal.classList.add('d-none');
                
                const diasPeriodo = PERIODOS_DIAS[frecuencia] ?? 30;

                if (!inputFechaInicioLote.value) {
                    const hoy = new Date(); hoy.setHours(0,0,0,0);
                    inputFechaInicioLote.value = formatDateISO(hoy);
                }

                const fechaFinCalculada = addDays(inputFechaInicioLote.value, diasPeriodo - 1);
                if (fechaFinCalculada) {
                    inputFechaFinLote.value = formatDateISO(fechaFinCalculada);
                    inputFechaFinLote.min = inputFechaInicioLote.value;
                    inputFechaFinLote.max = formatDateISO(addDays(inputFechaInicioLote.value, diasPeriodo - 1));
                }
            }

            actualizarNombreLote();
        }

        function validarRangoSegunFrecuencia() {
            if (!selectFrecuenciaLote || !inputFechaInicioLote || !inputFechaFinLote || !inputFechaInicioLote.value || !inputFechaFinLote.value) return;

            const frecuencia = (selectFrecuenciaLote.value || 'TODOS').toUpperCase();
            const diasPeriodo = PERIODOS_DIAS[frecuencia] ?? 30;
            const inicio = new Date(inputFechaInicioLote.value + 'T00:00:00');
            const fin = new Date(inputFechaFinLote.value + 'T00:00:00');
            const diferenciaDias = Math.floor((fin.getTime() - inicio.getTime()) / 86400000) + 1;

            if (diferenciaDias <= 0) {
                inputFechaFinLote.setCustomValidity('La fecha fin debe ser mayor o igual a la fecha de inicio.');
                return;
            }

            if (frecuencia !== 'SEMANAL' && diferenciaDias !== diasPeriodo) {
                inputFechaFinLote.setCustomValidity(`Para frecuencia ${frecuencia.toLowerCase()} el rango debe ser de ${diasPeriodo} días.`);
                return;
            }

            inputFechaFinLote.setCustomValidity('');
            actualizarNombreLote();
        }

        if (modalGenerarLote) {
            modalGenerarLote.addEventListener('shown.bs.modal', () => {
                ajustarRangoPorFrecuencia();
                const formGenerarLote = modalGenerarLote.querySelector('form');
                if (formGenerarLote) restaurarBotonSubmit(formGenerarLote);
            });
        }
        
        if (selectFrecuenciaLote) selectFrecuenciaLote.addEventListener('change', () => { inputFechaFinLote?.setCustomValidity(''); ajustarRangoPorFrecuencia(); });
        if (inputFechaInicioLote) inputFechaInicioLote.addEventListener('change', () => { inputFechaFinLote?.setCustomValidity(''); ajustarRangoPorFrecuencia(); });
        if (inputFechaFinLote) inputFechaFinLote.addEventListener('change', validarRangoSegunFrecuencia);
        
        if (inputSemana) {
            inputSemana.addEventListener('change', () => {
                ajustarRangoPorFrecuencia();
                validarRangoSegunFrecuencia();
            });
        }

        const formGenerarLote = modalGenerarLote?.querySelector('form');
        if (formGenerarLote) {
            formGenerarLote.addEventListener('submit', (e) => {
                validarRangoSegunFrecuencia();
                if (!formGenerarLote.checkValidity()) {
                    e.preventDefault(); e.stopPropagation();
                    formGenerarLote.reportValidity();
                } else {
                    bloquearBotonSubmit(formGenerarLote, "Calculando Nómina...");
                }
            });
        }

        const searchInput = document.getElementById('searchDetalles');
        const tablaDetalles = document.getElementById('tablaDetallesNomina');

        if (searchInput && tablaDetalles) {
            searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
            searchInput.addEventListener('keyup', function () {
                const searchTerm = this.value.toLowerCase().trim();
                const filas = tablaDetalles.querySelectorAll('tbody tr:not(.empty-msg-row)');
                let filasVisibles = 0;

                filas.forEach(fila => {
                    const dataSearch = fila.getAttribute('data-search') || '';
                    if (dataSearch.includes(searchTerm)) { fila.style.display = ''; filasVisibles++; }
                    else fila.style.display = 'none';
                });

                const tbody = tablaDetalles.querySelector('tbody');
                let emptyRow = tbody.querySelector('.empty-msg-row-search');
                
                if (filasVisibles === 0 && filas.length > 0) {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'empty-msg-row-search border-bottom-0';
                        tbody.appendChild(emptyRow);
                    }
                    emptyRow.style.display = '';
                    emptyRow.innerHTML = `
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>
                            No se encontraron empleados que coincidan con "<b>${searchTerm}</b>".
                        </td>
                    `;
                } else if (emptyRow) {
                    emptyRow.style.display = 'none';
                }
            });
        }

        function bloquearBotonSubmit(form, textoCarga = "Procesando...") {
            const btnSubmit = form.querySelector('button[type="submit"]');
            if (btnSubmit) {
                if (!btnSubmit.dataset.originalHtml) btnSubmit.dataset.originalHtml = btnSubmit.innerHTML;
                btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${textoCarga}`;
                btnSubmit.classList.add('disabled');
                setTimeout(() => { btnSubmit.disabled = true; }, 10);
            }
        }

        function restaurarBotonSubmit(form) {
            const btnSubmit = form.querySelector('button[type="submit"]');
            if (btnSubmit && btnSubmit.dataset.originalHtml) {
                btnSubmit.innerHTML = btnSubmit.dataset.originalHtml;
                btnSubmit.classList.remove('disabled');
                btnSubmit.disabled = false;
            }
        }

        const formCerrar = document.getElementById('formCerrarLote');
        if (formCerrar) {
            formCerrar.addEventListener('submit', function (e) {
                e.preventDefault();

                const hayConflictos = tablaDetalles && tablaDetalles.querySelector('.bi-exclamation-triangle-fill') !== null;

                if (hayConflictos) {
                    if (!window.Swal) {
                        alert('No se puede cerrar la planilla. Existen empleados con asistencia incompleta.');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede cerrar la planilla',
                            html: 'Existen empleados con asistencia incompleta.<br>Corrige los registros marcados en rojo antes de continuar.',
                            confirmButtonText: 'Entendido'
                        });
                    }
                    return;
                }

                if (!window.Swal) {
                    if (confirm("¿Estás seguro? Cerrarás la planilla y ya no podrás agregar bonos ni descuentos.")) {
                        bloquearBotonSubmit(formCerrar, "Cerrando...");
                        HTMLFormElement.prototype.submit.call(formCerrar);
                    }
                } else {
                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: "Cerrarás la planilla y ya no podrás agregar bonos ni descuentos manuales.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-lock-fill me-1"></i> Sí, cerrar planilla',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            bloquearBotonSubmit(formCerrar, "Cerrando...");
                            HTMLFormElement.prototype.submit.call(formCerrar);
                        }
                    });
                }
            });
        }

        // ==========================================
        // ELIMINAR LOTE BORRADOR
        // ==========================================
        const formEliminar = document.getElementById('formEliminarLote');
        if (formEliminar) {
            formEliminar.addEventListener('submit', function (e) {
                e.preventDefault();

                if (!window.Swal) {
                    if (confirm("¿Estás seguro? Se borrará todo el cálculo de este borrador y no se puede deshacer.")) {
                        bloquearBotonSubmit(formEliminar, "Eliminando...");
                        HTMLFormElement.prototype.submit.call(formEliminar);
                    }
                } else {
                    Swal.fire({
                        title: '¿Eliminar borrador?',
                        text: "Se borrará todo el cálculo de esta planilla. Esta acción no se puede deshacer.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Sí, eliminar lote',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            bloquearBotonSubmit(formEliminar, "Eliminando...");
                            HTMLFormElement.prototype.submit.call(formEliminar);
                        }
                    });
                }
            });
        }

        // ==========================================
        // REABRIR LOTE (DEVOLVER A BORRADOR)
        // ==========================================
        const formReabrir = document.getElementById('formReabrirLote') || document.querySelector('form[action*="planillas/reabrir"]');
        if (formReabrir) {
            formReabrir.addEventListener('submit', function (e) {
                e.preventDefault();

                if (!window.Swal) {
                    if (confirm("¿Estás seguro de regresar esta planilla a borrador? Podrás volver a editar los montos y agregar conceptos.")) {
                        bloquearBotonSubmit(formReabrir, "Reabriendo...");
                        HTMLFormElement.prototype.submit.call(formReabrir);
                    }
                } else {
                    Swal.fire({
                        title: '¿Reabrir Planilla?',
                        text: "El lote volverá a estado BORRADOR. Podrás modificar asistencias, agregar bonos y registrar descuentos manualmente nuevamente.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Sí, reabrir borrador',
                        cancelButtonText: 'Cancelar',
                        customClass: { confirmButton: 'text-dark fw-bold shadow-sm' }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            bloquearBotonSubmit(formReabrir, "Reabriendo...");
                            HTMLFormElement.prototype.submit.call(formReabrir);
                        }
                    });
                }
            });
        }

        // ==========================================
        // LLENAR MODAL DE DETALLE EMPLEADO (RRHH)
        // ==========================================
        const modalInfoEmpleado = document.getElementById('modalInfoEmpleado');
        if (modalInfoEmpleado) {
            modalInfoEmpleado.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const dataRaw = button.getAttribute('data-info');
                
                if (dataRaw) {
                    try {
                        const data = JSON.parse(dataRaw);
                        
                        document.getElementById('infoEmpNombre').textContent = data.nombre;
                        document.getElementById('infoEmpCargo').textContent = `${data.cargo} | Pago: ${data.frecuencia}`;
                        document.getElementById('infoEmpDias').textContent = data.dias;
                        document.getElementById('infoEmpHoras').textContent = `${data.horas_normales}h`;
                        document.getElementById('infoEmpExtras').textContent = `${data.horas_extras}h`;
                        document.getElementById('infoEmpSueldoBase').textContent = `S/ ${data.sueldo_base.toFixed(2)}`;
                        document.getElementById('infoEmpPagoExtras').textContent = `S/ ${data.pago_extras.toFixed(2)}`;
                        document.getElementById('infoEmpPercepciones').textContent = `S/ ${data.percepciones.toFixed(2)}`;
                        document.getElementById('infoEmpDeducciones').textContent = `S/ ${data.deducciones.toFixed(2)}`;
                        document.getElementById('infoEmpNeto').textContent = `S/ ${data.neto.toFixed(2)}`;
                        
                        const btnBoleta = document.getElementById('btnImprimirBoletaModal');
                        if (btnBoleta && data.id_detalle) {
                            const baseUrl = window.location.origin + window.location.pathname;
                            btnBoleta.href = `${baseUrl}?ruta=planillas/imprimir_boleta&id=${data.id_detalle}`;
                        }
                    } catch (e) {
                        console.error('Error parseando datos del empleado:', e);
                    }
                }
            });
        }

        // ==============================================================
        // LÓGICA PARA EL MODAL DE PAGO MASIVO (MÚLTIPLES CUENTAS)
        // ==============================================================
        const formPagarLote = document.getElementById('formPagarLote');
        const btnAgregarCuentaPlanilla = document.getElementById('btnAgregarCuentaPlanilla');
        const contenedorCuentasPlanilla = document.getElementById('contenedorCuentasPlanilla');
        const totalPagadoPlanilla = document.getElementById('totalPagadoPlanilla');
        const inputTotalNetoLote = document.getElementById('inputTotalNetoLote');
        const templateFilaPagoPlanilla = document.getElementById('templateFilaPagoPlanilla');
        const modalPagarPlanilla = document.getElementById('modalPagarPlanilla');

        function recalcularTotalPagoPlanilla() {
            if (!contenedorCuentasPlanilla || !totalPagadoPlanilla || !inputTotalNetoLote) return;
            
            let total = 0;
            const inputsMonto = contenedorCuentasPlanilla.querySelectorAll('.input-monto-planilla');
            
            inputsMonto.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            totalPagadoPlanilla.textContent = `S/ ${total.toFixed(2)}`;
            
            const montoNeto = parseFloat(inputTotalNetoLote.value) || 0;
            
            if (total === montoNeto) {
                totalPagadoPlanilla.classList.remove('text-dark', 'text-danger');
                totalPagadoPlanilla.classList.add('text-success');
            } else {
                totalPagadoPlanilla.classList.remove('text-success', 'text-dark');
                totalPagadoPlanilla.classList.add('text-danger');
            }
        }

        function agregarFilaPagoPlanilla(montoInicial = '') {
            if (!templateFilaPagoPlanilla || !contenedorCuentasPlanilla) return;
            
            const clone = templateFilaPagoPlanilla.content.cloneNode(true);
            
            if (montoInicial !== '') {
                const inputMonto = clone.querySelector('.input-monto-planilla');
                if (inputMonto) inputMonto.value = montoInicial;
            }

            contenedorCuentasPlanilla.appendChild(clone);
            recalcularTotalPagoPlanilla();
        }

        if (modalPagarPlanilla) {
            modalPagarPlanilla.addEventListener('shown.bs.modal', function () {
                if (contenedorCuentasPlanilla && contenedorCuentasPlanilla.children.length === 0) {
                    const montoNeto = parseFloat(inputTotalNetoLote?.value || 0).toFixed(2);
                    agregarFilaPagoPlanilla(montoNeto > 0 ? montoNeto : '');
                }
            });

            modalPagarPlanilla.addEventListener('hidden.bs.modal', function () {
                if (contenedorCuentasPlanilla) {
                    contenedorCuentasPlanilla.innerHTML = '';
                    recalcularTotalPagoPlanilla();
                }
            });
        }

        if (btnAgregarCuentaPlanilla) {
            btnAgregarCuentaPlanilla.addEventListener('click', (e) => {
                e.preventDefault();
                agregarFilaPagoPlanilla();
            });
        }

        if (contenedorCuentasPlanilla) {
            contenedorCuentasPlanilla.addEventListener('input', (e) => {
                if (e.target.classList.contains('input-monto-planilla')) {
                    recalcularTotalPagoPlanilla();
                }
            });

            contenedorCuentasPlanilla.addEventListener('click', (e) => {
                const btnEliminar = e.target.closest('.btn-quitar-pago');
                if (btnEliminar) {
                    e.preventDefault();
                    if (contenedorCuentasPlanilla.querySelectorAll('.fila-pago-planilla').length > 1) {
                        btnEliminar.closest('.fila-pago-planilla').remove();
                        recalcularTotalPagoPlanilla();
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Atención', 'Debe mantener al menos una cuenta para el pago.', 'warning');
                        }
                    }
                }
            });
        }

        if (formPagarLote) {
            formPagarLote.addEventListener('submit', function (e) {
                e.preventDefault();

                const montoNeto = parseFloat(inputTotalNetoLote?.value || 0).toFixed(2);
                let totalDistribuido = 0;
                let selectVacios = false;
                
                this.querySelectorAll('.input-monto-planilla').forEach(input => {
                    totalDistribuido += parseFloat(input.value) || 0;
                });
                
                this.querySelectorAll('.pago-cuenta').forEach(select => {
                    if (!select.value) selectVacios = true;
                });

                totalDistribuido = totalDistribuido.toFixed(2);

                if (selectVacios) {
                    if(typeof Swal !== 'undefined') {
                        Swal.fire('Atención', 'Debe seleccionar una cuenta de origen en todas las filas.', 'warning');
                    }
                    return;
                }

                if (montoNeto !== totalDistribuido) {
                    if(typeof Swal !== 'undefined') {
                        Swal.fire('Error de cuadre', `El total distribuido (S/ ${totalDistribuido}) debe ser exactamente igual al monto a pagar (S/ ${montoNeto}).`, 'error');
                    }
                    return; 
                }

                if (this.checkValidity()) {
                    bloquearBotonSubmit(this, "Emitiendo Pagos...");
                    HTMLFormElement.prototype.submit.call(this);
                } else {
                    this.reportValidity();
                }
            });
        }

        // ==============================================================
        // LÓGICA PARA AUTO-SELECCIONAR EMPLEADO AL VENIR DE "CORREGIR"
        // ==============================================================
        const urlParams = new URLSearchParams(window.location.search);
        const idEmpleadoFiltro = urlParams.get('id_empleado');
        const fechaInicioFiltro = urlParams.get('fecha_inicio');

        function convertirFechaASemanaISO(fechaString) {
            const date = new Date(fechaString + 'T00:00:00'); 
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
            return d.getUTCFullYear() + '-W' + weekNo.toString().padStart(2, '0');
        }

        if (fechaInicioFiltro) {
            setTimeout(() => {
                const inputFecha = document.getElementById('inputFechaAsistencia') || document.querySelector('input[type="week"]') || document.querySelector('input[type="date"]');
                if (inputFecha) {
                    if (inputFecha.type === 'week') {
                        inputFecha.value = convertirFechaASemanaISO(fechaInicioFiltro);
                    } else {
                        inputFecha.value = fechaInicioFiltro;
                    }
                    inputFecha.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, 300);
        }

        if (idEmpleadoFiltro) {
            setTimeout(() => {
                const empleadoItem = document.querySelector(`[data-id="${idEmpleadoFiltro}"]`) || document.querySelector(`.empleado-item[data-id="${idEmpleadoFiltro}"]`);
                if (empleadoItem) {
                    empleadoItem.click();
                    empleadoItem.scrollIntoView({ behavior: 'smooth', block: 'center' }); 
                }
            }, 600);
        }

    } // Fin de la función iniciarModuloPlanillas

    // ==============================================================
    // INICIALIZACIÓN COMPATIBLE CON TURBO/SPA Y ANTI-DUPLICADOS
    // ==============================================================
    if (!window._planillasModuloIniciado) {
        document.addEventListener('turbo:load', iniciarModuloPlanillas);
        document.addEventListener('DOMContentLoaded', iniciarModuloPlanillas);
        window._planillasModuloIniciado = true;
    }
    
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        iniciarModuloPlanillas();
    }

})();

// ==============================================================
// FUNCIONES GLOBALES (EXPORTACIÓN)
// ==============================================================
window.exportarSiEsValido = function(formato, idLote, esBorrador, tienePagos) {
    if (esBorrador) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Planilla en Edición',
                text: 'Debes "Cerrar Planilla" (botón verde) para guardar los cálculos antes de poder exportar los reportes.',
                confirmButtonColor: '#198754'
            });
        } else {
            alert('Debes "Cerrar Planilla" para guardar los cálculos antes de exportar.');
        }
        return;
    }
    
    if (!tienePagos) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Lote sin pagos',
                text: 'No se encontraron empleados con montos a pagar mayores a S/ 0.00 en este lote.',
                confirmButtonColor: '#0d6efd'
            });
        } else {
            alert('No se encontraron pagos mayores a S/ 0.00.');
        }
        return; 
    }

    const baseUrl = window.BASE_URL || '';
    let url = '';

    if (formato === 'pdf') {
        url = baseUrl + 'index.php?ruta=planillas/imprimir_reporte_planilla&id_lote=' + idLote;
    } else if (formato === 'excel') {
        url = baseUrl + 'index.php?ruta=planillas/exportar_excel&id_lote=' + idLote;
    } else if (formato === 'csv') {
        url = baseUrl + 'index.php?ruta=planillas/exportar_csv&id_lote=' + idLote;
    }

    if (url !== '') {
        window.open(url, '_blank');
    }
};