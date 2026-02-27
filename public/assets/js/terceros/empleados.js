(function () {
    'use strict';

    // ==========================================
    // 1. UTILIDADES Y LÓGICA DE CAMPOS (Mantenido)
    // ==========================================

    function toggleRegimenFields(regimenSelect) {
        if (!regimenSelect) return;
        const prefix = regimenSelect.id.replace('Regimen', '');
        const comisionSelect = document.getElementById(`${prefix}TipoComision`);
        const cusppInput = document.getElementById(`${prefix}Cuspp`);

        if (!comisionSelect || !cusppInput) return;

        const val = regimenSelect.value;
        const isAfp = val && val !== 'ONP' && val !== '';

        comisionSelect.disabled = !isAfp;
        cusppInput.disabled = !isAfp;

        if (!isAfp) {
            comisionSelect.value = '';
            cusppInput.value = '';
        }
    }

    function togglePagoFields(tipoPagoSelect) {
        if (!tipoPagoSelect) return;
        const prefix = tipoPagoSelect.id.replace('TipoPago', '');
        const sueldoGroup = document.getElementById(`${prefix}SueldoGroup`);
        const diarioGroup = document.getElementById(`${prefix}PagoDiarioGroup`);
        const sueldoInput = document.getElementById(`${prefix}SueldoBasico`);
        const diarioInput = document.getElementById(`${prefix}PagoDiario`);
        const empleadoCheckbox = document.getElementById(`${prefix}EsEmpleado`);

        if (!sueldoGroup || !diarioGroup) return;

        const isDiario = tipoPagoSelect.value === 'DIARIO';
        const isEmpleado = !!empleadoCheckbox?.checked;

        if (isDiario) {
            sueldoGroup.classList.add('d-none');
            diarioGroup.classList.remove('d-none');
            if (sueldoInput) sueldoInput.value = '';
        } else {
            sueldoGroup.classList.remove('d-none');
            diarioGroup.classList.add('d-none');
            if (diarioInput) diarioInput.value = '';
        }

        if (sueldoInput) sueldoInput.required = isEmpleado && !isDiario;
        if (diarioInput) diarioInput.required = isEmpleado && isDiario;
    }

    function toggleFechaNacimiento(recordarSwitch) {
        if (!recordarSwitch) return;
        const prefix = recordarSwitch.id.replace('RecordarCumpleanos', '');
        const wrapper = document.getElementById(`${prefix}FechaNacimientoWrapper`);
        const fechaInput = document.getElementById(`${prefix}FechaNacimiento`);

        if (!wrapper || !fechaInput) return;

        if (recordarSwitch.checked) {
            fechaInput.disabled = false;
        } else {
            fechaInput.disabled = true;
            fechaInput.value = '';
        }
    }

    function updateFechaCeseRules(prefix) {
        if (!prefix) return;

        const estadoLaboralSelect = document.getElementById(`${prefix}EstadoLaboral`);
        const tipoContratoSelect = document.getElementById(`${prefix}TipoContrato`);
        const fechaCeseInput = document.getElementById(`${prefix}FechaCese`);
        const requiredMark = document.getElementById(`${prefix}FechaCeseRequired`);

        if (!estadoLaboralSelect || !tipoContratoSelect || !fechaCeseInput) return;

        const estadoLaboral = estadoLaboralSelect.value;
        const tipoContrato = tipoContratoSelect.value;

        const contratosConFechaCeseObligatoria = ['PLAZO_FIJO', 'LOCACION', 'PRACTICANTE'];
        const contratoRequiereFechaSiempre = contratosConFechaCeseObligatoria.includes(tipoContrato);
        const contratoEsSinFechaFija = tipoContrato === 'INDETERMINADO' || tipoContrato === 'PART_TIME';
        const estadoNoActivo = estadoLaboral !== 'activo';

        const debeHabilitar = contratoRequiereFechaSiempre || (contratoEsSinFechaFija && estadoNoActivo);
        const esObligatoria = contratoRequiereFechaSiempre;

        fechaCeseInput.disabled = !debeHabilitar;
        fechaCeseInput.required = esObligatoria;

        if (!debeHabilitar) {
            fechaCeseInput.value = '';
        }

        if (requiredMark) {
            requiredMark.classList.toggle('d-none', !esObligatoria);
        }
    }

    function toggleFechaCese(estadoLaboralSelect) {
        if (!estadoLaboralSelect) return;
        const prefix = estadoLaboralSelect.id.replace('EstadoLaboral', '');
        updateFechaCeseRules(prefix);
    }

    // ==========================================
    // 2. NUEVA LÓGICA DE HIJOS (Array Dinámico)
    // ==========================================

    /**
     * Genera el HTML de una fila para un hijo
     */
    function crearFilaHijoHTML(data = {}) {
        const id = data.id || '0';
        const nombre = data.nombre_completo || '';
        const fecha = data.fecha_nacimiento || '';
        const estudia = Number(data.esta_estudiando || 0);
        const discapacidad = Number(data.discapacidad || 0);

        return `
            <div class="row g-2 align-items-center mb-2 border-bottom pb-2 hijo-row bg-white">
                <input type="hidden" name="hijo_id[]" value="${id}">
                
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" name="hijo_nombre[]" placeholder="Nombre completo" value="${nombre}" required>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control form-control-sm" name="hijo_fecha_nacimiento[]" value="${fecha}" required>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="hijo_esta_estudiando[]">
                        <option value="0" ${estudia === 0 ? 'selected' : ''}>No</option>
                        <option value="1" ${estudia === 1 ? 'selected' : ''}>Sí</option>
                    </select>
                    <div class="form-text d-md-none small">¿Estudia?</div>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="hijo_discapacidad[]">
                        <option value="0" ${discapacidad === 0 ? 'selected' : ''}>No</option>
                        <option value="1" ${discapacidad === 1 ? 'selected' : ''}>Sí</option>
                    </select>
                    <div class="form-text d-md-none small">Discapacidad</div>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-hijo" title="Eliminar fila">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Agrega una fila al contenedor
     */
    function agregarHijo(prefix, data = {}) {
        const container = document.getElementById(`${prefix}ListaHijos`);
        const emptyState = document.getElementById(`${prefix}HijosEmptyState`);
        
        if (!container) return;

        // Ocultar mensaje de vacío
        if (emptyState) emptyState.classList.add('d-none');

        // Insertar HTML
        const html = crearFilaHijoHTML(data);
        container.insertAdjacentHTML('beforeend', html);
    }

    /**
     * Inicializa los eventos para la sección de hijos
     */
    function setupHijosDinamic(prefix) {
        const switchAsig = document.getElementById(`${prefix}AsignacionFamiliar`);
        const wrapper = document.getElementById(`${prefix}WrapperHijos`);
        const btnAdd = document.getElementById(`${prefix}BtnAgregarHijo`);
        const container = document.getElementById(`${prefix}ListaHijos`);
        const emptyState = document.getElementById(`${prefix}HijosEmptyState`);

        if (!switchAsig || !wrapper) return;

        // 1. Toggle Visibilidad
        const toggleVisibility = () => {
            const isChecked = switchAsig.checked;
            wrapper.classList.toggle('d-none', !isChecked);
            
            // Si se desactiva, podríamos limpiar los inputs para que no se envíen,
            // pero el controlador ya maneja eso ignorándolos si asignacion_familiar es 0.
        };

        switchAsig.addEventListener('change', toggleVisibility);
        // Ejecutar al inicio para establecer estado correcto
        toggleVisibility(); 

        // 2. Agregar Fila
        if (btnAdd) {
            btnAdd.addEventListener('click', () => {
                agregarHijo(prefix);
            });
        }

        // 3. Eliminar Fila (Delegación de eventos)
        if (container) {
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-remove-hijo');
                if (btn) {
                    const row = btn.closest('.hijo-row');
                    if (row) {
                        row.remove();
                        // Verificar si quedó vacío
                        if (container.querySelectorAll('.hijo-row').length === 0 && emptyState) {
                            emptyState.classList.remove('d-none');
                        }
                    }
                }
            });
        }
    }

    // ==========================================
    // 3. API PÚBLICA E INICIALIZACIÓN
    // ==========================================

    window.TercerosEmpleados = {
        init: function (prefix) {
            const regimen = document.getElementById(`${prefix}Regimen`);
            if (regimen) {
                regimen.addEventListener('change', () => toggleRegimenFields(regimen));
                toggleRegimenFields(regimen); // Estado inicial
            }

            const tipoPago = document.getElementById(`${prefix}TipoPago`);
            if (tipoPago) {
                tipoPago.addEventListener('change', () => togglePagoFields(tipoPago));
                togglePagoFields(tipoPago);
            }

            const recordarCumpleanos = document.getElementById(`${prefix}RecordarCumpleanos`);
            if (recordarCumpleanos) {
                recordarCumpleanos.addEventListener('change', () => toggleFechaNacimiento(recordarCumpleanos));
                toggleFechaNacimiento(recordarCumpleanos);
            }

            const estadoLaboral = document.getElementById(`${prefix}EstadoLaboral`);
            if (estadoLaboral) {
                estadoLaboral.addEventListener('change', () => toggleFechaCese(estadoLaboral));
                toggleFechaCese(estadoLaboral);
            }

            const tipoContrato = document.getElementById(`${prefix}TipoContrato`);
            if (tipoContrato) {
                tipoContrato.addEventListener('change', () => updateFechaCeseRules(prefix));
                updateFechaCeseRules(prefix);
            }

            // Inicializar lógica de hijos
            setupHijosDinamic(prefix);
        },

        /**
         * Método para llenar la tabla de hijos desde AJAX (usar en Edit)
         * @param {string} prefix 'crear' o 'edit'
         * @param {Array} hijosArray Array de objetos {id, nombre_completo, ...}
         */
        setHijos: function(prefix, hijosArray) {
            const container = document.getElementById(`${prefix}ListaHijos`);
            const emptyState = document.getElementById(`${prefix}HijosEmptyState`);
            
            if (!container) return;

            // Limpiar actuales
            // Mantenemos el emptyState pero removemos las filas .hijo-row
            const filas = container.querySelectorAll('.hijo-row');
            filas.forEach(f => f.remove());

            if (!Array.isArray(hijosArray) || hijosArray.length === 0) {
                if (emptyState) emptyState.classList.remove('d-none');
                return;
            }

            // Agregar cada hijo
            hijosArray.forEach(hijo => {
                agregarHijo(prefix, hijo);
            });
        },

        refreshState: function(prefix) {
            const recordarCumpleanos = document.getElementById(`${prefix}RecordarCumpleanos`);
            if (recordarCumpleanos) {
                toggleFechaNacimiento(recordarCumpleanos);
            }
            updateFechaCeseRules(prefix);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.TercerosEmpleados.init('crear');
        // Si usas un modal para editar, llama a init('edit') cuando se abra el modal
        // o si los elementos existen en el DOM al carga.
        if(document.getElementById('editRegimen')) {
             window.TercerosEmpleados.init('edit');
        }
    });

})();
