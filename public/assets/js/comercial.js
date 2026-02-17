/**
 * GESTIÓN COMERCIAL (Presentaciones, Listas, Asignaciones)
 * Versión Final: Corrige la apertura del modal y la carga de datos.
 */

document.addEventListener('DOMContentLoaded', function() {
    const routeUrl = (ruta, params = {}) => {
        const base = (window.BASE_URL || '/').replace(/\/?$/, '/');
        const url = new URL('index.php', window.location.origin + base);
        url.searchParams.set('ruta', ruta);
        Object.entries(params).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '') return;
            url.searchParams.set(key, String(value));
        });
        return url.toString();
    };
    
    // =========================================================================
    // 1. LÓGICA PARA PRESENTACIONES (PACKS)
    // =========================================================================
    const tablaPresentaciones = document.getElementById('presentacionesTable');
    const inputBuscador = document.getElementById('presentacionSearch');
    const filtroProducto = document.getElementById('presentacionFiltroProducto');
    const filtroEstado = document.getElementById('presentacionFiltroEstado');
    
    // Elementos del Modal
    const modalElement = document.getElementById('modalCrearPresentacion');
    const formPresentacion = document.getElementById('formPresentacion');
    const btnCrearPresentacion = document.querySelector('.js-crear-presentacion');
    const modalTitle = document.getElementById('modalTitle');

    // IMPORTANTE: Inicializar el Modal de Bootstrap manualmente para poder abrirlo desde JS
    const modalBootstrap = modalElement ? new bootstrap.Modal(modalElement) : null;

    // Inputs específicos
    const inputId = document.getElementById('presentacionId');
    const inputItem = document.getElementById('inputItem'); // Select de productos
    const inputFactor = document.getElementById('inputFactor');
    const inputPrecioMenor = document.getElementById('inputPrecioMenor');
    const inputPrecioMayor = document.getElementById('inputPrecioMayor');
    const inputMinMayor = document.getElementById('inputMinMayor');

    if (tablaPresentaciones && modalBootstrap) {
        const filas = Array.from(tablaPresentaciones.querySelectorAll('tbody tr'));

        // --- A. Filtrado en tiempo real ---
        const actualizarTablaPresentaciones = function() {
            const termino = (inputBuscador?.value || '').toLowerCase().trim();
            const idProducto = filtroProducto?.value || '';
            const estado = filtroEstado?.value || '';

            filas.forEach((fila) => {
                const searchData = fila.getAttribute('data-search') || '';
                const coincideTexto = searchData.includes(termino);
                const coincideProducto = idProducto === '' || (fila.getAttribute('data-id-item') || '') === idProducto;
                const coincideEstado = estado === '' || (fila.getAttribute('data-estado') || '') === estado;

                fila.style.display = coincideTexto && coincideProducto && coincideEstado ? '' : 'none';
            });
        };

        [inputBuscador, filtroProducto, filtroEstado].forEach((control) => {
            if (!control) return;
            control.addEventListener('input', actualizarTablaPresentaciones);
            control.addEventListener('change', actualizarTablaPresentaciones);
        });

        // --- B. Resetear Formulario (Limpiar) ---
        const resetFormPresentacion = function() {
            if (!formPresentacion) return;
            formPresentacion.reset();
            
            // Limpiar ID para indicar creación
            if(inputId) inputId.value = '';
            
            // Limpiar select (soporte para TomSelect si lo usas)
            if (inputItem && inputItem.tomselect) {
                inputItem.tomselect.clear();
            }

            // Restaurar título
            if(modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva Presentación';
        };

        // --- C. Cargar Datos para Edición (AJAX) ---
        const cargarDatosEdicionAjax = function(id) {
            if (!modalTitle) return;

            // 1. ABRIR EL MODAL PRIMERO (Esto faltaba en tu código anterior)
            modalBootstrap.show();

            // Feedback visual
            modalTitle.innerHTML = '<i class="bi bi-arrow-clockwise me-2 fa-spin"></i>Cargando datos...';

            // Petición al servidor
            fetch(routeUrl('comercial/obtenerPresentacion', { id }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        
                        // Llenar campos
                        if(inputId) inputId.value = data.id;
                        
                        // Llenar Select (Soporte TomSelect + Normal)
                        if(inputItem) {
                            if (inputItem.tomselect) {
                                inputItem.tomselect.setValue(data.id_item);
                            } else {
                                inputItem.value = data.id_item;
                            }
                        }

                        if(inputFactor) inputFactor.value = parseFloat(data.factor); 
                        if(inputPrecioMenor) inputPrecioMenor.value = data.precio_x_menor;
                        if(inputPrecioMayor) inputPrecioMayor.value = data.precio_x_mayor;
                        if(inputMinMayor) inputMinMayor.value = data.cantidad_minima_mayor;

                        modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Presentación';
                    } else {
                        alert('Error: No se pudo cargar la información.');
                        modalBootstrap.hide();
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error de conexión.');
                    modalBootstrap.hide();
                });
        };

        // --- D. Delegación de Eventos (Clicks en la tabla) ---
        tablaPresentaciones.addEventListener('click', function(e) {
            
            // 1. Click en EDITAR
            const btnEditar = e.target.closest('.js-editar-presentacion');
            if (btnEditar) {
                e.preventDefault(); // Evita saltos de página
                const id = btnEditar.dataset.id;
                cargarDatosEdicionAjax(id);
            }

            // 2. Click en TOGGLE ESTADO
            const btnEstado = e.target.closest('.js-toggle-estado-presentacion');
            if (btnEstado) {
                const id = btnEstado.dataset.id;
                const estadoActual = btnEstado.checked ? 1 : 0;
                window.location.href = routeUrl('comercial/toggleEstadoPresentacion', { id, estado: estadoActual });
            }

            // 3. Click en ELIMINAR
            const btnEliminar = e.target.closest('.js-eliminar-presentacion');
            if (btnEliminar) {
                if(confirm('¿Estás seguro de eliminar esta presentación?')) {
                    const id = btnEliminar.dataset.id;
                    window.location.href = routeUrl('comercial/eliminarPresentacion', { id });
                }
            }
        });

        // --- E. Botón "Nueva Presentación" ---
        btnCrearPresentacion?.addEventListener('click', function() {
            resetFormPresentacion();
            // Aquí NO necesitamos .show() porque el botón HTML ya tiene data-bs-toggle="modal"
            // Pero si decidiste quitarlo, descomenta la siguiente línea:
            // modalBootstrap.show(); 
        });

        actualizarTablaPresentaciones();
    }

    // =========================================================================
    // 2. LÓGICA PARA LISTAS DE PRECIOS
    // =========================================================================
    const formPrecios = document.getElementById('formPrecios');
    if (formPrecios) {
        const inputsPrecio = formPrecios.querySelectorAll('input[type="number"]');
        inputsPrecio.forEach(input => {
            input.addEventListener('change', function() {
                if(this.value !== '') {
                    this.classList.add('bg-warning-subtle');
                } else {
                    this.classList.remove('bg-warning-subtle');
                }
            });
        });
    }

    // =========================================================================
    // 3. LÓGICA PARA ASIGNACIÓN DE CLIENTES
    // =========================================================================
    const tablaAsignacion = document.getElementById('clientesAsignacionTable');
    if (tablaAsignacion) {
        const selects = tablaAsignacion.querySelectorAll('.js-cambiar-lista');
        selects.forEach(select => {
            select.addEventListener('change', function() {
                const idCliente = this.dataset.idCliente;
                const idLista = this.value;
                this.disabled = true;
                
                fetch(routeUrl('comercial/guardarAsignacionAjax'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id_cliente: idCliente, id_lista: idLista })
                })
                .then(response => response.json())
                .then(data => {
                    this.disabled = false;
                    if (data.success) {
                        this.classList.add('is-valid', 'bg-success-subtle');
                        setTimeout(() => this.classList.remove('is-valid', 'bg-success-subtle'), 1500);
                    } else {
                        alert('Error: ' + (data.message || 'Desconocido'));
                        this.classList.add('is-invalid');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.disabled = false;
                    alert('Error de conexión.');
                });
            });
        });
    }
});
