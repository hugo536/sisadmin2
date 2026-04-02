document.addEventListener('DOMContentLoaded', () => {
    
    // Verificamos si estamos en la vista correcta
    if (!document.getElementById('horariosTable')) return;

    // ========================================================================
    // 1. LÓGICA DE ASIGNACIÓN MASIVA (TomSelect + Panel Dinámico "Carrito")
    // ========================================================================
    
    const selectEl = document.getElementById('empleadoTomSelect');
    
    if (selectEl && typeof TomSelect !== 'undefined') {
        let ts;
        
        if (selectEl.tomselect) {
            ts = selectEl.tomselect; 
        } else {
            ts = new TomSelect(selectEl, {
                create: false,
                placeholder: 'Escribe nombre o código...',
                dropdownParent: 'body',
                maxItems: 1
            });
        }

        const panel = document.getElementById('panelSeleccionados');
        const contador = document.getElementById('contadorSeleccionados');
        const hint = document.getElementById('listaVaciaHint');
        const form = document.getElementById('asignacionMasivaForm');
        const inputIdsContenedor = document.getElementById('inputIdsContenedor');

        const seleccionados = new Map();

        function actualizarPanel() {
            const ids = Array.from(seleccionados.keys());
            
            if (hint) hint.style.display = ids.length > 0 ? 'none' : 'block';
            if (contador) contador.innerText = ids.length;

            inputIdsContenedor.innerHTML = '';
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id_terceros[]'; 
                input.value = id;
                inputIdsContenedor.appendChild(input);
            });
        }

        ts.on('change', (value) => {
            if (!value) return;

            const data = ts.options[value];
            const nombre = data.text;

            if (!seleccionados.has(value)) {
                seleccionados.set(value, nombre);

                const item = document.createElement('div');
                item.className = 'd-flex justify-content-between align-items-center bg-light border border-secondary-subtle rounded p-2 mb-2 shadow-sm fade-in';
                item.id = `item-emp-${value}`;
                item.innerHTML = `
                    <span class="small fw-semibold text-dark">${nombre}</span>
                    <button type="button" class="btn btn-sm btn-white text-danger py-0 px-2 border-0" onclick="quitarEmpleado('${value}')" title="Quitar de la lista">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;
                panel.appendChild(item);

                ts.removeOption(value);
            }

            ts.clear();
            actualizarPanel();
        });

        window.quitarEmpleado = function(id) {
            const nombre = seleccionados.get(id);
            if (!nombre) return;

            const item = document.getElementById(`item-emp-${id}`);
            if (item) item.remove();

            ts.addOption({ value: id, text: nombre });
            seleccionados.delete(id);
            actualizarPanel();
        };

        const btnLimpiar = document.getElementById('btnLimpiarLista');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                Array.from(seleccionados.keys()).forEach(id => window.quitarEmpleado(id));
            });
        }

        const btnTodos = document.getElementById('btnSeleccionarTodosEmp');
        if (btnTodos) {
            btnTodos.addEventListener('click', () => {
                Object.keys(ts.options).forEach(id => {
                    if (id) ts.setValue(id); 
                });
            });
        }

        let diasMarcados = false;
        const btnDias = document.getElementById('btnMarcarTodosDias');
        if (btnDias) {
            btnDias.addEventListener('click', function() {
                diasMarcados = !diasMarcados;
                document.querySelectorAll('.dia-checkbox').forEach(chk => chk.checked = diasMarcados);
                this.innerText = diasMarcados ? 'Desmarcar días' : 'Marcar Lun a Dom';
            });
        }

        if (form) {
            form.addEventListener('submit', (e) => {
                if (seleccionados.size === 0) {
                    e.preventDefault(); 
                    if (typeof Swal !== 'undefined') Swal.fire('Atención', 'Añade al menos un empleado al panel derecho.', 'warning');
                    else alert('Añade al menos un empleado al panel derecho.');
                    return;
                }
                
                const diasCheck = document.querySelectorAll('.dia-checkbox:checked');
                if (diasCheck.length === 0) {
                    e.preventDefault(); 
                    if (typeof Swal !== 'undefined') Swal.fire('Atención', 'Marca al menos un día de la semana.', 'warning');
                    else alert('Marca al menos un día de la semana.');
                }
            });
        }
        
        const modalAsignacionMasiva = document.getElementById('modalAsignacionMasiva');
        if (modalAsignacionMasiva) {
            modalAsignacionMasiva.addEventListener('hidden.bs.modal', () => {
                if (btnLimpiar) btnLimpiar.click(); 
                document.querySelectorAll('.dia-checkbox').forEach(chk => chk.checked = false); 
                if (btnDias) {
                    diasMarcados = false;
                    btnDias.innerText = 'Marcar Lun a Dom';
                }
                const selectTurno = form.querySelector('select[name="id_horario"]');
                if (selectTurno) selectTurno.value = '';
            });
        }
        
    }

    // ========================================================================
    // 2. LÓGICA DE EDICIÓN DE TURNOS (TRAMOS) - ADAPTADO AL MODAL
    // ========================================================================
    
    const idInput = document.getElementById('horarioId');
    const nombreInput = document.getElementById('horarioNombre');
    const toleranciaInput = document.getElementById('horarioTolerancia');
    
    const t1EntradaInput = document.getElementById('t1Entrada');
    const t1SalidaInput = document.getElementById('t1Salida');
    const t2EntradaInput = document.getElementById('t2Entrada');
    const t2SalidaInput = document.getElementById('t2Salida');
    const t3EntradaInput = document.getElementById('t3Entrada');
    const t3SalidaInput = document.getElementById('t3Salida');

    const limpiarModalTurno = () => {
        if(idInput) idInput.value = '0';
        if(nombreInput) nombreInput.value = '';
        if(toleranciaInput) toleranciaInput.value = '0';
        
        if(t1EntradaInput) t1EntradaInput.value = '';
        if(t1SalidaInput) t1SalidaInput.value = '';
        
        if(t2EntradaInput) t2EntradaInput.value = '';
        if(t2SalidaInput) t2SalidaInput.value = '';
        
        if(t3EntradaInput) t3EntradaInput.value = '';
        if(t3SalidaInput) t3SalidaInput.value = '';
    };

    const btnNuevoTurno = document.getElementById('btnNuevoTurno');
    if (btnNuevoTurno) {
        btnNuevoTurno.addEventListener('click', limpiarModalTurno);
    }

    const btnLimpiarHorario = document.getElementById('btnLimpiarHorario');
    if (btnLimpiarHorario) {
        btnLimpiarHorario.addEventListener('click', limpiarModalTurno);
    }

    document.addEventListener('click', function (e) {
        const btnEditar = e.target.closest('.js-editar-horario');
        
        if (btnEditar) {
            if (!idInput || !nombreInput || !t1EntradaInput || !t1SalidaInput) return;
            
            idInput.value = btnEditar.dataset.id || '0';
            nombreInput.value = btnEditar.dataset.nombre || '';
            toleranciaInput.value = btnEditar.dataset.tolerancia || '0';

            t1EntradaInput.value = btnEditar.dataset.t1Entrada && btnEditar.dataset.t1Entrada !== '00:00' ? btnEditar.dataset.t1Entrada : '';
            t1SalidaInput.value  = btnEditar.dataset.t1Salida && btnEditar.dataset.t1Salida !== '00:00' ? btnEditar.dataset.t1Salida : '';
            
            if (t2EntradaInput && t2SalidaInput) {
                t2EntradaInput.value = btnEditar.dataset.t2Entrada && btnEditar.dataset.t2Entrada !== '00:00' ? btnEditar.dataset.t2Entrada : '';
                t2SalidaInput.value  = btnEditar.dataset.t2Salida && btnEditar.dataset.t2Salida !== '00:00' ? btnEditar.dataset.t2Salida : '';
            }

            if (t3EntradaInput && t3SalidaInput) {
                t3EntradaInput.value = btnEditar.dataset.t3Entrada && btnEditar.dataset.t3Entrada !== '00:00' ? btnEditar.dataset.t3Entrada : '';
                t3SalidaInput.value  = btnEditar.dataset.t3Salida && btnEditar.dataset.t3Salida !== '00:00' ? btnEditar.dataset.t3Salida : '';
            }
            
            // Enfoca suavemente el nombre para que el usuario sepa que puede editar
            setTimeout(() => nombreInput.focus(), 100); 
        }
    });

});