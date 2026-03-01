document.addEventListener('DOMContentLoaded', () => {
    
    // Verificamos si estamos en la vista correcta (solo se ejecuta si existe la tabla o el form)
    if (!document.getElementById('tablaTurnos') && !document.getElementById('horariosTable')) return;

    // ========================================================================
    // 1. LÓGICA DE ASIGNACIÓN MASIVA (TomSelect + Panel Dinámico "Carrito")
    // ========================================================================
    
    const selectEl = document.getElementById('empleadoTomSelect');
    
    if (selectEl && typeof TomSelect !== 'undefined') {
        // Inicializamos TomSelect (Oculta las opciones al seleccionarlas)
        const ts = new TomSelect(selectEl, {
            create: false,
            placeholder: 'Escribe nombre o código...',
            dropdownParent: 'body',
            maxItems: 1 // Solo dejamos elegir uno a la vez porque lo vamos a mover al panel
        });

        // Referencias al DOM del panel derecho
        const panel = document.getElementById('panelSeleccionados');
        const contador = document.getElementById('contadorSeleccionados');
        const hint = document.getElementById('listaVaciaHint');
        const form = document.getElementById('asignacionMasivaForm');
        const inputIdsContenedor = document.getElementById('inputIdsContenedor');

        // Mapa en memoria para guardar quiénes están en el panel
        const seleccionados = new Map();

        // Función central que dibuja el panel basado en nuestro Mapa en memoria
        function actualizarPanel() {
            const ids = Array.from(seleccionados.keys());
            
            // Ocultar o mostrar el mensaje de "Busca empleados..."
            if (hint) {
                hint.style.display = ids.length > 0 ? 'none' : 'block';
            }
            
            // Actualizar el numerito (ej: Seleccionados (5))
            if (contador) {
                contador.innerText = ids.length;
            }

            // Destruir y volver a crear los inputs ocultos <input type="hidden">
            // Esto es lo que el servidor PHP recibirá al dar clic en Aplicar
            inputIdsContenedor.innerHTML = '';
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id_terceros[]'; // Nombre en arreglo para PHP
                input.value = id;
                inputIdsContenedor.appendChild(input);
            });
        }

        // EVENTO: Cuando el usuario elige alguien en el buscador
        ts.on('change', (value) => {
            if (!value) return; // Si limpió el input, no hacemos nada

            // Obtenemos el nombre del empleado seleccionado
            const data = ts.options[value];
            const nombre = data.text;

            // Verificamos que no esté ya en la lista (por seguridad)
            if (!seleccionados.has(value)) {
                // Lo guardamos en memoria
                seleccionados.set(value, nombre);

                // Dibujamos su tarjetita visual
                const item = document.createElement('div');
                item.className = 'd-flex justify-content-between align-items-center bg-white border rounded p-2 mb-2 shadow-sm fade-in';
                item.id = `item-emp-${value}`;
                item.innerHTML = `
                    <span class="small fw-semibold text-dark">${nombre}</span>
                    <button type="button" class="btn btn-sm btn-light text-danger py-0 px-2 border-0" onclick="quitarEmpleado('${value}')" title="Quitar de la lista">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;
                panel.appendChild(item);

                // MAGIA: Lo quitamos del desplegable para que no lo elija dos veces
                ts.removeOption(value);
            }

            // Vaciamos el input para que pueda buscar al siguiente rapidísimo
            ts.clear();
            actualizarPanel();
        });

        // Función Global atada a 'window' para que los botones "X" la puedan usar
        window.quitarEmpleado = function(id) {
            const nombre = seleccionados.get(id);
            if (!nombre) return;

            // Destruir tarjetita
            const item = document.getElementById(`item-emp-${id}`);
            if (item) item.remove();

            // Devolver al TomSelect original para que vuelva a ser busable
            ts.addOption({ value: id, text: nombre });
            
            // Borrar de la memoria
            seleccionados.delete(id);
            actualizarPanel();
        };

        // EVENTO: Botón Limpiar toda la lista a la vez
        const btnLimpiar = document.getElementById('btnLimpiarLista');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                // Ejecuta la función de quitar para cada empleado actualmente seleccionado
                Array.from(seleccionados.keys()).forEach(id => window.quitarEmpleado(id));
            });
        }

        // EVENTO: Botón Añadir a TODOS los empleados
        const btnTodos = document.getElementById('btnSeleccionarTodosEmp');
        if (btnTodos) {
            btnTodos.addEventListener('click', () => {
                // Dispara el evento 'change' del TomSelect por cada opción disponible
                Object.keys(ts.options).forEach(id => {
                    if (id) ts.setValue(id); 
                });
            });
        }

        // EVENTO: Marcar Lunes a Domingo
        let diasMarcados = false;
        const btnDias = document.getElementById('btnMarcarTodosDias');
        if (btnDias) {
            btnDias.addEventListener('click', function() {
                diasMarcados = !diasMarcados;
                document.querySelectorAll('.dia-checkbox').forEach(chk => chk.checked = diasMarcados);
                this.innerText = diasMarcados ? 'Desmarcar días' : 'Marcar Lunes a Domingo';
            });
        }

        // VALIDACIÓN: Evitar que guarde si no eligió nada
        if (form) {
            form.addEventListener('submit', (e) => {
                if (seleccionados.size === 0) {
                    e.preventDefault(); // Detiene el envío del form
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Atención', 'Añade al menos un empleado al panel derecho.', 'warning');
                    } else {
                        alert('Añade al menos un empleado al panel derecho.');
                    }
                    return;
                }
                
                const diasCheck = document.querySelectorAll('.dia-checkbox:checked');
                if (diasCheck.length === 0) {
                    e.preventDefault(); // Detiene el envío
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Atención', 'Marca al menos un día de la semana.', 'warning');
                    } else {
                        alert('Marca al menos un día de la semana.');
                    }
                }
            });
        }
    } else {
        console.warn('TomSelect no está definido. Asegúrate de incluir la librería en el header.');
    }

    // ========================================================================
    // 2. LÓGICA DE EDICIÓN DE TURNOS (Con Delegación de Eventos)
    // ========================================================================
    
    const idInput = document.getElementById('horarioId');
    const nombreInput = document.getElementById('horarioNombre');
    const entradaInput = document.getElementById('horarioEntrada');
    const salidaInput = document.getElementById('horarioSalida');
    const toleranciaInput = document.getElementById('horarioTolerancia');

    // Delegación de eventos: Escucha los clics en toda la página
    document.addEventListener('click', function (e) {
        // Busca si el clic fue en (o dentro de) un botón de edición
        const btnEditar = e.target.closest('.js-editar-horario');
        
        if (btnEditar) {
            if (!idInput || !nombreInput || !entradaInput || !salidaInput || !toleranciaInput) return;
            
            // Llena el formulario izquierdo con los datos de la fila (data-attributes)
            idInput.value = btnEditar.dataset.id || '0';
            nombreInput.value = btnEditar.dataset.nombre || '';
            entradaInput.value = btnEditar.dataset.entrada || '';
            salidaInput.value = btnEditar.dataset.salida || '';
            toleranciaInput.value = btnEditar.dataset.tolerancia || '0';
            
            // Sube suavemente la pantalla hasta el formulario y pone el cursor en el Nombre
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => nombreInput.focus(), 500); 
        }
    });

    // Limpiar el formulario de turnos si se arrepiente de editar
    const btnLimpiarHorario = document.getElementById('btnLimpiarHorario');
    if (btnLimpiarHorario && idInput && nombreInput && entradaInput && salidaInput && toleranciaInput) {
        btnLimpiarHorario.addEventListener('click', function () {
            idInput.value = '0';
            nombreInput.value = '';
            entradaInput.value = '';
            salidaInput.value = '';
            toleranciaInput.value = '0';
        });
    }

});