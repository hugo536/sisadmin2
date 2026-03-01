document.addEventListener('DOMContentLoaded', () => {

    const formFiltros = document.getElementById('formFiltrosAsistencia');
    const searchInput = document.getElementById('searchAsistencia');

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
            // Busca todos los contenedores que tengan el atributo "data-period-field"
            document.querySelectorAll('[data-period-field]').forEach(el => {
                if (el.dataset.periodField === val) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });
        });
    }

    // 3. LÓGICA DEL MODAL DE JUSTIFICACIÓN (Con Delegación de Eventos)
    // Escuchamos los clics en todo el documento, no solo en los botones actuales
    document.addEventListener('click', function(e) {
        const btnGestion = e.target.closest('.js-gestionar-asistencia');
        if (btnGestion) {
            // Llenar campos ocultos
            document.getElementById('gestIdAsistencia').value = btnGestion.dataset.id || '';
            document.getElementById('gestIdTercero').value = btnGestion.dataset.tercero || '';
            document.getElementById('gestFecha').value = btnGestion.dataset.fecha || '';
            
            // Llenar textos visuales
            document.getElementById('gestNombreEmpleado').innerText = btnGestion.dataset.nombre || '';
            document.getElementById('gestFechaDisplay').innerText = 'Día: ' + (btnGestion.dataset.fecha || '');
            
            // Llenar inputs de hora
            document.getElementById('gestHoraEntrada').value = btnGestion.dataset.entrada !== '-' ? btnGestion.dataset.entrada : '';
            document.getElementById('gestHoraSalida').value = btnGestion.dataset.salida !== '-' ? btnGestion.dataset.salida : '';
            
            // Resetear justificación al abrir
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
    });

    // Toggle de la caja de justificación en el modal
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

    // 4. EL DEBOUNCE Y AJAX (Exactamente igual que planillas)
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            triggerAutoSubmit();
        });

        let debounceTimer;

        const autoSubmitForm = async () => {
            
            // Llamamos al Spinner Global que creamos antes
            if (window.asistenciaManager && typeof window.asistenciaManager.showLoading === 'function') {
                window.asistenciaManager.showLoading();
            }

            // Construimos la URL con todos los campos del formulario dinámicamente
            const url = new URL(window.location.href);
            const formData = new FormData(formFiltros);
            for (const [key, value] of formData.entries()) {
                if (key !== 'ruta') { // Evitamos sobreescribir la ruta principal si es necesaria
                    url.searchParams.set(key, value);
                }
            }

            try {
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if (!response.ok) throw new Error('Error de conexión');
                const html = await response.text();

                const parser = new DOMParser();
                const docVirtual = parser.parseFromString(html, 'text/html');

                // 1. Reemplazamos la Tabla
                const currentTbody = document.querySelector('#asistenciaTableBody');
                const newTbody = docVirtual.querySelector('#asistenciaTableBody');
                if (currentTbody && newTbody) currentTbody.innerHTML = newTbody.innerHTML;

                // 2. Reemplazamos el Badge de "X Registros"
                const currentBadge = document.getElementById('badgeRegistros');
                const newBadge = docVirtual.getElementById('badgeRegistros');
                if (currentBadge && newBadge) currentBadge.innerHTML = newBadge.innerHTML;

                // 3. Reemplazamos el texto informativo del periodo
                const currentInfo = document.getElementById('infoPeriodoTexto');
                const newInfo = docVirtual.getElementById('infoPeriodoTexto');
                if (currentInfo && newInfo) currentInfo.innerHTML = newInfo.innerHTML;

                // 4. Refrescamos la paginación global
                if (window.asistenciaManager && typeof window.asistenciaManager.refresh === 'function') {
                    window.asistenciaManager.refresh();
                }

                // 5. Refrescamos tooltips
                if (window.ERPTable && typeof window.ERPTable.initTooltips === 'function') {
                    window.ERPTable.initTooltips();
                }

                // Actualizamos la URL silenciosamente
                window.history.pushState({}, '', url);

            } catch (error) {
                console.error('AJAX Falló:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Aviso', 'No se pudo actualizar. Intente nuevamente.', 'warning');
                }
            } finally {
                // Quitamos el Spinner
                if (window.asistenciaManager && typeof window.asistenciaManager.hideLoading === 'function') {
                    window.asistenciaManager.hideLoading();
                }
            }
        };

        const triggerAutoSubmit = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                autoSubmitForm(); 
            }, 150); // Tu punto dulce de 150ms
        };

        // Escuchamos cualquier cambio dentro del formulario (inputs, selects, datepickers)
        formFiltros.addEventListener('change', triggerAutoSubmit);
    }

    // =========================================================================
    // 5. LÓGICA DEL MODAL "REGISTRO MANUAL"
    // =========================================================================
    const formRegistroManual = document.getElementById('formRegistroManual');
    const modalRegistroManual = document.getElementById('modalRegistroManual');
    const selectEmpleadoManual = document.getElementById('selectEmpleadoManual');
    
    let tomSelectInstanceManual = null;

    // Inicializar Tom Select para el buscador de empleados
    if (selectEmpleadoManual && typeof TomSelect !== 'undefined') {
        tomSelectInstanceManual = new TomSelect(selectEmpleadoManual, {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            },
            placeholder: "Buscar empleado por nombre..."
        });
    }

    // Validación antes de enviar el registro manual
    if (formRegistroManual) {
        formRegistroManual.addEventListener('submit', function(e) {
            const horaIngreso = this.querySelector('input[name="hora_ingreso"]').value;
            const horaSalida = this.querySelector('input[name="hora_salida"]').value;

            if (!horaIngreso && !horaSalida) {
                e.preventDefault(); 
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'Debe registrar al menos la Hora de Ingreso o la Hora de Salida.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert('Debe registrar al menos la Hora de Ingreso o la Hora de Salida.');
                }
            }
        });
    }

    // Limpiar el formulario y el buscador cuando se cierra el modal
    if (modalRegistroManual && formRegistroManual) {
        modalRegistroManual.addEventListener('hidden.bs.modal', function () {
            formRegistroManual.reset(); 
            
            // Limpiar Tom Select
            if (tomSelectInstanceManual) {
                tomSelectInstanceManual.clear();
            }
            
            // Restablecer la fecha a hoy
            const inputFecha = formRegistroManual.querySelector('input[name="fecha"]');
            if (inputFecha) {
                const hoy = new Date();
                hoy.setMinutes(hoy.getMinutes() - hoy.getTimezoneOffset());
                inputFecha.value = hoy.toISOString().split('T')[0];
            }
        });
    }

});