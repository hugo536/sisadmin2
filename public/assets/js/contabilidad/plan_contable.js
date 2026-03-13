(function () {
    "use strict";

    // --- 1) GESTIÓN DE ESTADO (SWITCH CON SWEETALERT2) ---
    document.querySelectorAll('.form-cambiar-estado-cuenta').forEach(function (form) {
        const sw = form.querySelector('.switch-estado-cuenta');
        const inputEstado = form.querySelector('input[name="estado"]');
        const row = form.closest('tr');
        const badge = row ? row.querySelector('[data-estado-badge]') : null;

        if (!sw || !inputEstado) return;

        sw.addEventListener('change', function (e) {
            // Detenemos el comportamiento por defecto para manejarlo con la alerta
            e.preventDefault();
            
            const estadoObjetivo = sw.checked ? 1 : 0;
            const activar = (estadoObjetivo === 1);
            const codigo = form.getAttribute('data-codigo') || '';
            const nombre = form.getAttribute('data-nombre') || '';
            const accion = activar ? 'ACTIVAR' : 'INACTIVAR';

            // Revertimos visualmente el switch momentáneamente hasta que confirme
            sw.checked = !activar;

            Swal.fire({
                title: `¿${activar ? 'Activar' : 'Inactivar'} cuenta?`,
                html: `¿Está seguro de cambiar el estado de la cuenta?<br><strong>${codigo} - ${nombre}</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: activar ? '#198754' : '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Si confirma: actualizamos inputs y enviamos
                    inputEstado.value = String(estadoObjetivo);
                    sw.checked = activar; // Ahora sí lo marcamos
                    sw.disabled = true;
                    
                    if (badge) {
                        badge.textContent = activar ? 'Activa' : 'Inactiva';
                        badge.className = activar 
                            ? 'badge rounded-pill bg-success-subtle text-success border border-success-subtle' 
                            : 'badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle';
                    }
                    form.submit();
                } else {
                    // Si cancela: el switch se mantiene en su posición original
                    sw.checked = !activar;
                }
            });
        });
    });

    // --- 2) EDICIÓN DE CUENTAS ---
    const modalEditar = document.getElementById('modalEditarCuenta');
    if (modalEditar) {
        document.querySelectorAll('.btn-editar-cuenta').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const dataMap = {
                    'id': 'data-id',
                    'codigo': 'data-codigo',
                    'nombre': 'data-nombre',
                    'tipo': 'data-tipo',
                    'nivel': 'data-nivel',
                    'permite_movimiento': 'data-permite-movimiento',
                    'estado': 'data-estado'
                };

                for (let key in dataMap) {
                    const input = modalEditar.querySelector(`[name="${key}"]`);
                    if (input) {
                        input.value = this.getAttribute(dataMap[key]);
                    }
                }

                bootstrap.Modal.getOrCreateInstance(modalEditar).show();
            });
        });
    }

    // --- 3) FILTROS DE PARÁMETROS VIGENTES ---
    const buscarParam = document.getElementById('buscarParametroVigente');
    const filtroClave = document.getElementById('filtroClaveParametroVigente');
    const filasParams = document.querySelectorAll('[data-param-row="true"]');
    const filaVacia = document.getElementById('filaSinParametrosFiltrados');

    const filtrarParametros = function () {
        const texto = (buscarParam ? buscarParam.value : '').toLowerCase().trim();
        const clave = (filtroClave ? filtroClave.value : '').toLowerCase().trim();
        let contador = 0;

        filasParams.forEach(fila => {
            const fTexto = (fila.getAttribute('data-search') || '').toLowerCase();
            const fClave = (fila.getAttribute('data-clave') || '').toLowerCase();
            
            const matchTexto = texto === '' || fTexto.includes(texto);
            const matchClave = clave === '' || fClave === clave;

            const visible = matchTexto && matchClave;
            fila.classList.toggle('d-none', !visible);
            if (visible) contador++;
        });

        if (filaVacia) filaVacia.classList.toggle('d-none', contador > 0);
    };

    if (buscarParam) buscarParam.addEventListener('input', filtrarParametros);
    if (filtroClave) filtroClave.addEventListener('change', filtrarParametros);

    // --- 4) GESTIÓN DE PARÁMETROS (NUEVO Y EDICIÓN) ---
    const modalParams = document.getElementById('modalParametros');
    const claveInput = modalParams ? modalParams.querySelector('#parametroClave') : null;
    const parametroCuentaEl = document.getElementById('parametroCuenta');
    
    // Inicializar TomSelect para las Cuentas Contables
    let tomSelectCuenta = null;
    if (parametroCuentaEl && typeof TomSelect !== 'undefined') {
        tomSelectCuenta = new TomSelect("#parametroCuenta", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "Buscar cuenta transaccional...",
            dropdownParent: 'body' // IMPORTANTE: Para que el menú no se corte dentro del modal
        });
    }

    // A. Lógica al crear un NUEVO parámetro
    const btnNuevoParametro = document.querySelector('button[data-bs-target="#modalParametros"]');
    if (btnNuevoParametro && claveInput) {
        btnNuevoParametro.addEventListener('click', function () {
            const form = modalParams.querySelector('form');
            if(form) form.reset(); // Forzamos a que vuelva a la opción por defecto
            
            // Limpiar el buscador TomSelect
            if (tomSelectCuenta) {
                tomSelectCuenta.clear();
            }
            
            let opcionesDisponibles = 0;

            // Recorremos las opciones y ocultamos las que ya están vinculadas
            Array.from(claveInput.options).forEach(opt => {
                // Ignorar el placeholder vacío o la opción de "no disponibles" que crearemos
                if (opt.value === '' || opt.classList.contains('opt-vacia')) return;

                if (opt.getAttribute('data-vinculado') === 'true') {
                    opt.style.display = 'none';
                    opt.disabled = true;
                } else {
                    opt.style.display = '';
                    opt.disabled = false;
                    opcionesDisponibles++; // Contamos cuántas opciones reales quedan
                }
            });

            // Manejar el mensaje de "No hay parámetros"
            let optVacia = claveInput.querySelector('.opt-vacia');
            if (!optVacia) {
                // Si no existe, la creamos la primera vez
                optVacia = document.createElement('option');
                optVacia.className = 'opt-vacia';
                optVacia.value = '';
                optVacia.disabled = true;
                optVacia.textContent = 'No hay parámetros disponibles por asignar';
                claveInput.appendChild(optVacia);
            }

            // Seleccionar el botón de guardar para bloquearlo si es necesario
            const btnSubmit = form.querySelector('button[type="submit"]');

            if (opcionesDisponibles === 0) {
                // Si no hay nada, mostramos el mensaje y bloqueamos el botón
                optVacia.style.display = '';
                optVacia.selected = true;
                if(btnSubmit) btnSubmit.disabled = true;
            } else {
                // Si hay opciones, ocultamos el mensaje y habilitamos el botón
                optVacia.style.display = 'none';
                claveInput.value = ''; // Regresamos al placeholder normal ("Seleccione un parámetro...")
                if(btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    // B. Lógica al EDITAR un parámetro
    if (modalParams) {
        document.querySelectorAll('.btn-editar-parametro').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const claveToEdit = this.getAttribute('data-clave') || '';
                const idCuentaToEdit = this.getAttribute('data-id-cuenta') || '';
                
                // Lógica del select de Parámetro (ocultar vinculados menos el actual)
                if (claveInput) {
                    Array.from(claveInput.options).forEach(opt => {
                        if (opt.value === claveToEdit) {
                            opt.style.display = '';
                            opt.disabled = false;
                        } else if (opt.getAttribute('data-vinculado') === 'true') {
                            opt.style.display = 'none';
                            opt.disabled = true;
                        } else {
                            opt.style.display = '';
                            opt.disabled = false;
                        }
                    });
                    claveInput.value = claveToEdit;
                }
                
                // Lógica para asignar el valor al TomSelect (Cuenta Contable)
                if (tomSelectCuenta) {
                    tomSelectCuenta.setValue(idCuentaToEdit);
                } else if (parametroCuentaEl) {
                    parametroCuentaEl.value = idCuentaToEdit;
                }
                
                // Cerramos la tabla anterior y abrimos el formulario
                const modalListaInstance = bootstrap.Modal.getInstance(document.getElementById('modalParametrosVigentes'));
                if (modalListaInstance) modalListaInstance.hide();
                
                bootstrap.Modal.getOrCreateInstance(modalParams).show();
            });
        });
    }

    // --- 5) ELIMINAR PARÁMETROS CON SWEETALERT ---
    const botonesEliminarParam = document.querySelectorAll('.btn-delete-param');
    botonesEliminarParam.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            
            Swal.fire({
                title: '¿Eliminar Parámetro?',
                html: 'Si elimina este vínculo, <b>las operaciones automáticas de tesorería podrían fallar</b> hasta que asigne una nueva cuenta.<br><br>¿Está seguro?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Muestra un pequeño estado de carga
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Eliminando parámetro contable.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit();
                }
            });
        });
    });
})();