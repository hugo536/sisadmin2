(function () {
    'use strict';

    // ==========================================
    // 1. UTILIDADES Y LÓGICA DE CAMPOS
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

    // --- NUEVA LÓGICA FINANCIERA LIMPIA ---
    function togglePagoFields(tipoPagoSelect) {
        if (!tipoPagoSelect) return;
        const prefix = tipoPagoSelect.id.replace('TipoPago', '');
        
        const sueldoInput = document.getElementById(`${prefix}SueldoBasico`);
        const labelSueldo = document.getElementById(`${prefix}LabelSueldo`);
        const helpSueldo = document.getElementById(`${prefix}HelpSueldo`);
        const empleadoCheckbox = document.getElementById(`${prefix}EsEmpleado`);

        if (!sueldoInput || !labelSueldo || !helpSueldo) return;

        const valorPago = tipoPagoSelect.value;
        // Si existe el checkbox de empleado, tomamos su valor, si no, asumimos que es true en esta vista
        const isEmpleado = empleadoCheckbox ? empleadoCheckbox.checked : true; 

        if (valorPago === 'SEMANAL') {
            // Configuración visual para modo Jornalero
            labelSueldo.innerHTML = 'Pago por Día (Jornal) <span class="text-danger">*</span>';
            helpSueldo.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Poner el valor de 1 solo día (Ej: 50.00)</span>';
            sueldoInput.classList.add('text-primary');
            
        } else if (valorPago === 'QUINCENAL') {
            // Configuración visual para modo Quincenal
            labelSueldo.innerHTML = 'Sueldo Mensual Base <span class="text-danger">*</span>';
            helpSueldo.innerHTML = 'Monto total del mes (El sistema pagará la mitad cada quincena).';
            sueldoInput.classList.remove('text-primary');
            
        } else {
            // Configuración visual para modo Mensual
            labelSueldo.innerHTML = 'Sueldo Básico <span class="text-danger">*</span>';
            helpSueldo.innerHTML = 'Monto fijo mensual.';
            sueldoInput.classList.remove('text-primary');
        }

        // El campo de sueldo ahora siempre es obligatorio si es empleado
        sueldoInput.required = isEmpleado;
    }
    // ----------------------------------------

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
    // 2. LÓGICA DE HIJOS (Array Dinámico)
    // ==========================================

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

    function agregarHijo(prefix, data = {}) {
        const container = document.getElementById(`${prefix}ListaHijos`);
        const emptyState = document.getElementById(`${prefix}HijosEmptyState`);
        
        if (!container) return;

        if (emptyState) emptyState.classList.add('d-none');

        const html = crearFilaHijoHTML(data);
        container.insertAdjacentHTML('beforeend', html);
    }

    function setupHijosDinamic(prefix) {
        const switchAsig = document.getElementById(`${prefix}AsignacionFamiliar`);
        const wrapper = document.getElementById(`${prefix}WrapperHijos`);
        const btnAdd = document.getElementById(`${prefix}BtnAgregarHijo`);
        const container = document.getElementById(`${prefix}ListaHijos`);
        const emptyState = document.getElementById(`${prefix}HijosEmptyState`);

        if (!switchAsig || !wrapper) return;

        const toggleVisibility = () => {
            const isChecked = switchAsig.checked;
            wrapper.classList.toggle('d-none', !isChecked);
        };

        switchAsig.addEventListener('change', toggleVisibility);
        toggleVisibility(); 

        if (btnAdd) {
            btnAdd.addEventListener('click', () => {
                agregarHijo(prefix);
            });
        }

        if (container) {
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-remove-hijo');
                if (btn) {
                    const row = btn.closest('.hijo-row');
                    if (row) {
                        row.remove();
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
                toggleRegimenFields(regimen); 
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

            setupHijosDinamic(prefix);
        },

        setHijos: function(prefix, hijosArray) {
            const container = document.getElementById(`${prefix}ListaHijos`);
            const emptyState = document.getElementById(`${prefix}HijosEmptyState`);
            
            if (!container) return;

            const filas = container.querySelectorAll('.hijo-row');
            filas.forEach(f => f.remove());

            if (!Array.isArray(hijosArray) || hijosArray.length === 0) {
                if (emptyState) emptyState.classList.remove('d-none');
                return;
            }

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
        if(document.getElementById('editRegimen')) {
             window.TercerosEmpleados.init('edit');
        }
    });

})();