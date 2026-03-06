document.addEventListener('DOMContentLoaded', () => {

    const formFiltros = document.getElementById('formFiltrosAsistencia');
    const searchInput = document.getElementById('searchAsistencia');
    
    // VARIABLE GLOBAL PARA EL MÓDULO (Scope seguro para usar en clonar y crear)
    let empleadosSeleccionados = [];

    // 1. CANDADO AL BUSCADOR
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });
    }

    // 2. LÓGICA DE UI: Mostrar/Ocultar campos de fecha según el "Periodo"
    const selectPeriodo = document.querySelector('select[name="periodo"]');
    if (selectPeriodo) {
        selectPeriodo.addEventListener('change', (e) => {
            const val = e.target.value;
            document.querySelectorAll('[data-period-field]').forEach(el => {
                if (el.dataset.periodField === val) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });
        });
    }

    // 3. DELEGACIÓN DE EVENTOS GLOBAL (Para clics dinámicos como Clonar y Eliminar)
    document.addEventListener('click', async function(e) {
        
        // A) Botón Gestionar Asistencia (Modal de Justificación / Completar de cámaras)
        const btnGestion = e.target.closest('.js-gestionar-asistencia');
        if (btnGestion) {
            document.getElementById('gestIdAsistencia').value = btnGestion.dataset.id || '';
            document.getElementById('gestIdTercero').value = btnGestion.dataset.tercero || '';
            document.getElementById('gestFecha').value = btnGestion.dataset.fecha || '';
            document.getElementById('gestNombreEmpleado').innerText = btnGestion.dataset.nombre || '';
            document.getElementById('gestFechaDisplay').innerText = 'Día: ' + (btnGestion.dataset.fecha || '');

            const setTramosVisibles = (count) => {
                for (let i = 1; i <= 3; i++) {
                    const row = document.getElementById('gestTramo' + i);
                    const inInput = document.getElementById('gestHoraIngreso' + i);
                    const outInput = document.getElementById('gestHoraSalida' + i);
                    if (row) {
                        if (i <= count) row.classList.remove('d-none');
                        else row.classList.add('d-none');
                    }
                    if (inInput) inInput.value = '';
                    if (outInput) outInput.value = '';
                }
            };

            setTramosVisibles(1);

            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_marcaciones_dia');
                formData.append('id_tercero', btnGestion.dataset.tercero || '0');
                formData.append('fecha', btnGestion.dataset.fecha || '');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Error al consultar marcaciones');

                const payload = await response.json();
                if (payload && payload.ok && payload.detalle) {
                    const detalle = payload.detalle;
                    const activos = Math.max(1, Math.min(3, Number(detalle.tramos_activos || 1)));
                    setTramosVisibles(activos);

                    const ingresos = Array.isArray(detalle.ingresos_reales) ? detalle.ingresos_reales : [];
                    const salidas = Array.isArray(detalle.salidas_reales) ? detalle.salidas_reales : [];

                    for (let i = 1; i <= 3; i++) {
                        const inInput = document.getElementById('gestHoraIngreso' + i);
                        const outInput = document.getElementById('gestHoraSalida' + i);
                        if (inInput) inInput.value = ingresos[i - 1] || '';
                        if (outInput) outInput.value = salidas[i - 1] || '';
                    }
                }
            } catch (error) {
                const ingresosRaw = (btnGestion.dataset.ingresos || '').split('|').filter(Boolean).map(v => v.substring(11, 16));
                const salidasRaw = (btnGestion.dataset.salidas || '').split('|').filter(Boolean).map(v => v.substring(11, 16));
                const activos = Math.max(1, Math.min(3, Math.max(ingresosRaw.length, salidasRaw.length, 1)));
                setTramosVisibles(activos);

                for (let i = 1; i <= 3; i++) {
                    const inInput = document.getElementById('gestHoraIngreso' + i);
                    const outInput = document.getElementById('gestHoraSalida' + i);
                    if (inInput) inInput.value = ingresosRaw[i - 1] || '';
                    if (outInput) outInput.value = salidasRaw[i - 1] || '';
                }
            }
           
            const checkJustificar = document.getElementById('gestCheckJustificar');
            const boxJustificacion = document.getElementById('boxJustificacion');
            const obsInput = document.getElementById('gestObservacion');
           
            if (checkJustificar) checkJustificar.checked = false;
            if (boxJustificacion) boxJustificacion.classList.add('d-none');
            if (obsInput) {
                obsInput.value = '';
                obsInput.removeAttribute('required');
            }
        }

        // B) Botón Eliminar Grupo (SweetAlert o Confirmación normal)
        const btnEliminar = e.target.closest('.js-btn-eliminar-grupo');
        if (btnEliminar) {
            e.preventDefault();
            const form = btnEliminar.closest('form');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar Grupo?',
                    text: "Esta acción liberará a los empleados asignados y no se puede deshacer.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash"></i> Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            } else {
                if (confirm("¿Estás seguro de eliminar este grupo?")) form.submit();
            }
        }

        // C) Botón Clonar Plantilla (Llamada AJAX segura)
        const btnClonar = e.target.closest('.js-clonar-grupo');
        if (btnClonar) {
            e.preventDefault();
            const idGrupo = btnClonar.dataset.id;
            
            // Cambiar ícono a cargando...
            const originalHTML = btnClonar.innerHTML;
            btnClonar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            btnClonar.disabled = true;

            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_detalle_grupo');
                formData.append('id_grupo', idGrupo);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if(!response.ok) throw new Error("Error de red");
                const detalle = await response.json();
                
                if(detalle) {
                    const inputNombre = document.getElementById('inputNombreGrupo');
                    const inputTolerancia = document.querySelector('input[name="tolerancia_minutos"]');
                    const checkRangoDias = document.getElementById('checkRangoDias');
                    const fechaInicioInput = document.getElementById('fechaInicioGrupo');
                    const fechaFinInput = document.getElementById('fechaFinGrupo');
                    const btnAgregarTramo = document.getElementById('btnAgregarTramo');
                    const panelMiembros = document.getElementById('panelMiembros');
                    const contenedorInputs = document.getElementById('inputMiembrosContenedor');
                    const contadorMiembros = document.getElementById('contadorMiembros');
                    const hintVacio = document.getElementById('listaVaciaHint');

                    // 1. Llenar Nombre y Tolerancia
                    if(inputNombre) inputNombre.value = "Copia de " + detalle.nombre;
                    if(inputTolerancia) inputTolerancia.value = detalle.tolerancia_minutos || 0;
                    
                    // 2. Limpiar fechas para forzar selección manual
                    if (checkRangoDias) {
                        checkRangoDias.checked = false;
                        checkRangoDias.dispatchEvent(new Event('change'));
                    }
                    if (fechaInicioInput) fechaInicioInput.value = '';
                    if (fechaFinInput) fechaFinInput.value = '';

                    // 3. Resetear y rellenar tramos de horario
                    document.querySelectorAll('.js-quitar-tramo').forEach(b => b.click());
                    
                    if(detalle.t1_entrada) {
                        document.querySelector('input[name="t1_entrada"]').value = detalle.t1_entrada.substring(0,5);
                        document.querySelector('input[name="t1_salida"]').value = detalle.t1_salida.substring(0,5);
                    }
                    if(detalle.t2_entrada) {
                        if (btnAgregarTramo) btnAgregarTramo.click();
                        document.querySelector('input[name="t2_entrada"]').value = detalle.t2_entrada.substring(0,5);
                        document.querySelector('input[name="t2_salida"]').value = detalle.t2_salida.substring(0,5);
                    }
                    if(detalle.t3_entrada) {
                        if (btnAgregarTramo) btnAgregarTramo.click();
                        document.querySelector('input[name="t3_entrada"]').value = detalle.t3_entrada.substring(0,5);
                        document.querySelector('input[name="t3_salida"]').value = detalle.t3_salida.substring(0,5);
                    }

                    // 4. Rellenar Empleados (Badges dinámicos)
                    empleadosSeleccionados = [];
                    if (panelMiembros) panelMiembros.innerHTML = '';
                    if (contenedorInputs) contenedorInputs.innerHTML = '';
                    
                    if (detalle.empleados && Array.isArray(detalle.empleados)) {
                        detalle.empleados.forEach(emp => {
                            empleadosSeleccionados.push(emp.id.toString());
                            
                            const badge = document.createElement('div');
                            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center p-2 me-2 mb-2 shadow-sm';
                            badge.id = 'badge_grp_' + emp.id;
                            badge.innerHTML = `
                                ${emp.nombre_completo}
                                <button type="button" class="btn-close btn-close-white ms-2" aria-label="Remove" style="font-size: 0.5rem;" onclick="removerEmpleadoGrupo('${emp.id}')"></button>
                            `;
                            if (panelMiembros) panelMiembros.appendChild(badge);
                            
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'empleados[]';
                            input.value = emp.id;
                            input.id = 'input_grp_' + emp.id;
                            if (contenedorInputs) contenedorInputs.appendChild(input);
                        });
                    }
                    
                    if (contadorMiembros) contadorMiembros.textContent = empleadosSeleccionados.length;
                    if (hintVacio && empleadosSeleccionados.length > 0) hintVacio.style.display = 'none';

                    // Focalizar visualmente en el primer campo
                    if (inputNombre) inputNombre.focus();
                }
            } catch (error) {
                console.error("AJAX Falló:", error);
                if (typeof Swal !== 'undefined') Swal.fire('Aviso', 'No se pudo cargar la plantilla', 'error');
            } finally {
                btnClonar.innerHTML = originalHTML;
                btnClonar.disabled = false;
            }
        }
    });

    // Toggle para el Modal de Justificación
    const checkJustificar = document.getElementById('gestCheckJustificar');
    const boxJustificacion = document.getElementById('boxJustificacion');
    const obsInput = document.getElementById('gestObservacion');
   
    if (checkJustificar && boxJustificacion && obsInput) {
        checkJustificar.addEventListener('change', function() {
            if (this.checked) {
                boxJustificacion.classList.remove('d-none');
                obsInput.setAttribute('required', 'required');
            } else {
                boxJustificacion.classList.add('d-none');
                obsInput.removeAttribute('required');
            }
        });
    }

    // 4. EL DEBOUNCE Y AJAX (Filtros de Asistencia)
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            triggerAutoSubmit();
        });

        let debounceTimer;

        const autoSubmitForm = async () => {
            if (window.asistenciaManager && typeof window.asistenciaManager.showLoading === 'function') {
                window.asistenciaManager.showLoading();
            }

            const url = new URL(window.location.href);
            const formData = new FormData(formFiltros);
            for (const [key, value] of formData.entries()) {
                if (key !== 'ruta') url.searchParams.set(key, value);
            }

            try {
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
               
                if (!response.ok) throw new Error('Error de conexión');
                const html = await response.text();

                const parser = new DOMParser();
                const docVirtual = parser.parseFromString(html, 'text/html');

                const currentTbody = document.querySelector('#asistenciaTableBody');
                const newTbody = docVirtual.querySelector('#asistenciaTableBody');
                if (currentTbody && newTbody) currentTbody.innerHTML = newTbody.innerHTML;

                const currentBadge = document.getElementById('badgeRegistros');
                const newBadge = docVirtual.getElementById('badgeRegistros');
                if (currentBadge && newBadge) currentBadge.innerHTML = newBadge.innerHTML;

                const currentInfo = document.getElementById('infoPeriodoTexto');
                const newInfo = docVirtual.getElementById('infoPeriodoTexto');
                if (currentInfo && newInfo) currentInfo.innerHTML = newInfo.innerHTML;

                if (window.asistenciaManager && typeof window.asistenciaManager.refresh === 'function') {
                    window.asistenciaManager.refresh();
                }

                if (window.ERPTable && typeof window.ERPTable.initTooltips === 'function') {
                    window.ERPTable.initTooltips();
                }

                window.history.pushState({}, '', url);

            } catch (error) {
                console.error('AJAX Falló:', error);
                if (typeof Swal !== 'undefined') Swal.fire('Aviso', 'No se pudo actualizar. Intente nuevamente.', 'warning');
            } finally {
                if (window.asistenciaManager && typeof window.asistenciaManager.hideLoading === 'function') {
                    window.asistenciaManager.hideLoading();
                }
            }
        };

        const triggerAutoSubmit = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                autoSubmitForm();
            }, 150);
        };

        formFiltros.addEventListener('change', triggerAutoSubmit);
    }

    // =========================================================================
    // 5. LÓGICA DEL MODAL "REGISTRO MANUAL"
    // =========================================================================
    const formRegistroManual = document.getElementById('formRegistroManual');
    const modalRegistroManual = document.getElementById('modalRegistroManual');
    const selectEmpleadoManual = document.getElementById('selectEmpleadoManual');
   
    let tomSelectInstanceManual = null;

    if (selectEmpleadoManual && typeof TomSelect !== 'undefined') {
        if (selectEmpleadoManual.tomselect) {
            tomSelectInstanceManual = selectEmpleadoManual.tomselect;
        } else {
            tomSelectInstanceManual = new TomSelect(selectEmpleadoManual, {
                create: false,
                sortField: { field: "text", direction: "asc" },
                placeholder: "Buscar empleado por nombre..."
            });
        }
    }

    if (formRegistroManual) {
        formRegistroManual.addEventListener('submit', function(e) {
            const horaIngreso = this.querySelector('input[name="hora_ingreso"]').value;
            const horaSalida = this.querySelector('input[name="hora_salida"]').value;

            if (!horaIngreso && !horaSalida) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning', title: 'Atención',
                        text: 'Debe registrar al menos la Hora de Ingreso o la Hora de Salida.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert('Debe registrar al menos la Hora de Ingreso o la Hora de Salida.');
                }
            }
        });
    }

    if (modalRegistroManual && formRegistroManual) {
        modalRegistroManual.addEventListener('hidden.bs.modal', function () {
            formRegistroManual.reset();
            if (tomSelectInstanceManual) tomSelectInstanceManual.clear();
           
            const inputFecha = formRegistroManual.querySelector('input[name="fecha"]');
            if (inputFecha) {
                const hoy = new Date();
                hoy.setMinutes(hoy.getMinutes() - hoy.getTimezoneOffset());
                inputFecha.value = hoy.toISOString().split('T')[0];
            }
        });
    }

    // =========================================================================
    // 6. LÓGICA DEL MODAL "GESTIÓN DE GRUPOS"
    // =========================================================================
    const modalGestionGrupos = document.getElementById('modalGestionGrupos');
    const formCrearGrupo = document.getElementById('formCrearGrupo');
    
    if (modalGestionGrupos) {
        
        // --- A) Lógica del Switch de Fechas ---
        const checkRangoDias = document.getElementById('checkRangoDias');
        const labelPeriodo = document.getElementById('labelPeriodoExcepcion');
        const colFechaInicio = document.getElementById('colFechaInicio');
        const colFechaFin = document.getElementById('colFechaFin');
        const fechaInicioInput = document.getElementById('fechaInicioGrupo');
        const fechaFinInput = document.getElementById('fechaFinGrupo');
        const selectEmpleadosGrupo = document.getElementById('selectEmpleadosGrupo');

        if (checkRangoDias) {
            checkRangoDias.addEventListener('change', function() {
                if (this.checked) {
                    labelPeriodo.textContent = 'Rango de Fechas';
                    colFechaInicio.classList.remove('col-12');
                    colFechaInicio.classList.add('col-6');
                    colFechaFin.classList.remove('d-none');
                    fechaFinInput.removeAttribute('disabled');
                    fechaFinInput.setAttribute('required', 'required');
                } else {
                    labelPeriodo.textContent = 'Fecha Específica';
                    colFechaInicio.classList.remove('col-6');
                    colFechaInicio.classList.add('col-12');
                    colFechaFin.classList.add('d-none');
                    fechaFinInput.setAttribute('disabled', 'disabled');
                    fechaFinInput.removeAttribute('required');
                    fechaFinInput.value = fechaInicioInput.value; 
                }
            });
        }

        // --- Cargar Empleados Disponibles AJAX ---
        const cargarEmpleadosDisponibles = async (f_ini, f_fin) => {
            selectEmpleadosGrupo.setAttribute('disabled', 'disabled');
            selectEmpleadosGrupo.options[0].text = "Cargando empleados disponibles...";
            
            try {
                const formData = new FormData();
                formData.append('accion', 'buscar_disponibles');
                formData.append('f_ini', f_ini);
                formData.append('f_fin', f_fin);

                const response = await fetch(window.location.href, {
                    method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if(!response.ok) throw new Error("Error en red");
                const empleados = await response.json();
                
                while (selectEmpleadosGrupo.options.length > 1) { selectEmpleadosGrupo.remove(1); }
                
                if (empleados.length > 0) {
                    empleados.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.id;
                        option.text = emp.nombre_completo;
                        if (empleadosSeleccionados.includes(emp.id.toString())) option.disabled = true;
                        selectEmpleadosGrupo.appendChild(option);
                    });
                    selectEmpleadosGrupo.removeAttribute('disabled');
                    selectEmpleadosGrupo.options[0].text = "Buscar empleado...";
                } else {
                    selectEmpleadosGrupo.options[0].text = "No hay empleados libres en estas fechas";
                }
            } catch (error) {
                console.error("AJAX Error:", error);
                selectEmpleadosGrupo.options[0].text = "Error al cargar datos";
            }
        };

        const checkFechasLlenas = () => {
            if (checkRangoDias.checked) {
                if (fechaInicioInput.value && fechaFinInput.value) {
                    cargarEmpleadosDisponibles(fechaInicioInput.value, fechaFinInput.value);
                } else {
                    selectEmpleadosGrupo.setAttribute('disabled', 'disabled');
                    selectEmpleadosGrupo.options[0].text = "Seleccione fechas completas...";
                }
            } else {
                if (fechaInicioInput.value) {
                    fechaFinInput.value = fechaInicioInput.value; 
                    cargarEmpleadosDisponibles(fechaInicioInput.value, fechaFinInput.value);
                } else {
                    selectEmpleadosGrupo.setAttribute('disabled', 'disabled');
                    selectEmpleadosGrupo.options[0].text = "Seleccione primero la fecha...";
                }
            }
        };

        if (fechaInicioInput) fechaInicioInput.addEventListener('change', checkFechasLlenas);
        if (fechaFinInput) fechaFinInput.addEventListener('change', checkFechasLlenas);
        if (checkRangoDias) checkRangoDias.addEventListener('change', checkFechasLlenas);

        // --- B) Lógica de Tramos de Horario ---
        const btnAgregarTramo = document.getElementById('btnAgregarTramo');
        const inputCantidadTramos = document.getElementById('inputCantidadTramos');
        const tramo2 = document.getElementById('tramo2');
        const tramo3 = document.getElementById('tramo3');

        if (btnAgregarTramo) {
            btnAgregarTramo.addEventListener('click', () => {
                let tramosVisibles = parseInt(inputCantidadTramos.value);
                if (tramosVisibles === 1) {
                    tramo2.classList.remove('d-none');
                    inputCantidadTramos.value = 2;
                } else if (tramosVisibles === 2) {
                    tramo3.classList.remove('d-none');
                    inputCantidadTramos.value = 3;
                    btnAgregarTramo.classList.add('d-none'); 
                }
            });
        }

        document.querySelectorAll('.js-quitar-tramo').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const tramoDiv = document.getElementById(targetId);
                
                tramoDiv.querySelectorAll('input').forEach(inp => inp.value = '');
                tramoDiv.classList.add('d-none');
                
                let tramosVisibles = parseInt(inputCantidadTramos.value);
                inputCantidadTramos.value = tramosVisibles - 1;
                if (btnAgregarTramo) btnAgregarTramo.classList.remove('d-none');
            });
        });

        // --- C) Lógica de Seleccionar Empleados (Badges) ---
        const panelMiembros = document.getElementById('panelMiembros');
        const contenedorInputs = document.getElementById('inputMiembrosContenedor');
        const contadorMiembros = document.getElementById('contadorMiembros');
        const hintVacio = document.getElementById('listaVaciaHint');

        if (selectEmpleadosGrupo) {
            selectEmpleadosGrupo.addEventListener('change', function() {
                const id = this.value;
                const nombre = this.options[this.selectedIndex].text;
                
                if (!id) return;
                
                if (!empleadosSeleccionados.includes(id)) {
                    empleadosSeleccionados.push(id);
                    if(hintVacio) hintVacio.style.display = 'none';
                    
                    const badge = document.createElement('div');
                    badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center p-2 me-2 mb-2 shadow-sm';
                    badge.id = 'badge_grp_' + id;
                    badge.innerHTML = `
                        ${nombre}
                        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Remove" style="font-size: 0.5rem;" onclick="removerEmpleadoGrupo('${id}')"></button>
                    `;
                    panelMiembros.appendChild(badge);
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'empleados[]';
                    input.value = id;
                    input.id = 'input_grp_' + id;
                    contenedorInputs.appendChild(input);
                    
                    contadorMiembros.textContent = empleadosSeleccionados.length;
                    this.options[this.selectedIndex].disabled = true;
                }
                this.value = ''; 
            });
        }
        
        // Exportada global para que el botón "X" del badge funcione
        window.removerEmpleadoGrupo = function(id) {
            const badgeElement = document.getElementById('badge_grp_' + id);
            const inputElement = document.getElementById('input_grp_' + id);
            
            if(badgeElement) badgeElement.remove();
            if(inputElement) inputElement.remove();
            
            empleadosSeleccionados = empleadosSeleccionados.filter(item => item !== id);
            if(contadorMiembros) contadorMiembros.textContent = empleadosSeleccionados.length;
            if(empleadosSeleccionados.length === 0 && hintVacio) hintVacio.style.display = 'block';
            
            if(selectEmpleadosGrupo) {
                const option = Array.from(selectEmpleadosGrupo.options).find(opt => opt.value === id);
                if(option) option.disabled = false;
            }
        };

        // --- D) Evitar cierre brusco al guardar y generar color Random ---
        if (formCrearGrupo) {
            formCrearGrupo.addEventListener('submit', function(e) {
                if(empleadosSeleccionados.length === 0) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') Swal.fire('Atención', 'Debe agregar al menos un empleado.', 'warning');
                    else alert("Debe agregar al menos un empleado.");
                    return;
                }

                let inputColor = document.getElementById('inputColorGrupo');
                if (!inputColor) {
                    inputColor = document.createElement('input');
                    inputColor.type = 'hidden';
                    inputColor.name = 'color';
                    inputColor.id = 'inputColorGrupo';
                    this.appendChild(inputColor);
                }

                const hue = Math.floor(Math.random() * 360);
                inputColor.value = `hsl(${hue}, 70%, 85%)`; 

                const btnSubmit = document.getElementById('btnGuardarGrupoInterno');
                if(btnSubmit) {
                    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Guardando...';
                    btnSubmit.disabled = true;
                }
            });
        }
        
        // Reset al cerrar Modal
        modalGestionGrupos.addEventListener('hidden.bs.modal', function () {
            formCrearGrupo.reset();
            
            tramo2.classList.add('d-none');
            tramo3.classList.add('d-none');
            if(btnAgregarTramo) btnAgregarTramo.classList.remove('d-none');
            inputCantidadTramos.value = 1;
            
            labelPeriodo.textContent = 'Fecha Específica';
            colFechaInicio.classList.remove('col-6');
            colFechaInicio.classList.add('col-12');
            colFechaFin.classList.add('d-none');
            fechaFinInput.setAttribute('disabled', 'disabled');
            selectEmpleadosGrupo.setAttribute('disabled', 'disabled');
            
            empleadosSeleccionados = [];
            panelMiembros.innerHTML = `<div id="listaVaciaHint" class="text-center text-muted mt-2 opacity-50 small"><i class="bi bi-person-slash fs-5 d-block mb-1"></i>Vacío</div>`;
            contenedorInputs.innerHTML = '';
            contadorMiembros.textContent = '0';
            
            Array.from(selectEmpleadosGrupo.options).forEach(opt => opt.disabled = false);
            
            const btnSubmit = document.getElementById('btnGuardarGrupoInterno');
            if(btnSubmit) {
                btnSubmit.innerHTML = '<i class="bi bi-save me-2"></i>Guardar Grupo';
                btnSubmit.disabled = false;
            }
        });
    }
});
