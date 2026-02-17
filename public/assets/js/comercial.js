/**
 * GESTIÓN COMERCIAL (Presentaciones, Listas, Asignaciones)
 * Este archivo maneja la lógica de las 3 vistas del módulo.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // =========================================================================
    // 1. LÓGICA PARA PRESENTACIONES (PACKS)
    // =========================================================================
    const tablaPresentaciones = document.getElementById('presentacionesTable');
    const inputBuscador = document.getElementById('presentacionSearch');
    const filtroProducto = document.getElementById('presentacionFiltroProducto');
    const filtroEstado = document.getElementById('presentacionFiltroEstado');
    const modalCrearPresentacion = document.getElementById('modalCrearPresentacion');
    const btnCrearPresentacion = document.querySelector('.js-crear-presentacion');

    if (tablaPresentaciones) {
        const filas = Array.from(tablaPresentaciones.querySelectorAll('tbody tr'));

        const actualizarTablaPresentaciones = function() {
            const termino = (inputBuscador?.value || '').toLowerCase().trim();
            const idProducto = filtroProducto?.value || '';
            const estado = filtroEstado?.value || '';

            filas.forEach((fila) => {
                const coincideTexto = (fila.getAttribute('data-search') || '').includes(termino);
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

        const resetFormPresentacion = function() {
            if (!modalCrearPresentacion) return;
            const form = modalCrearPresentacion.querySelector('form');
            if (!form) return;

            form.reset();
            form.querySelector('[name="id"]').value = '';
            form.querySelector('[name="accion"]').value = 'crear';
            modalCrearPresentacion.querySelector('.modal-title').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva Presentación';
        };

        const cargarEdicionPresentacion = function(btnEditar) {
            if (!modalCrearPresentacion || !btnEditar) return;
            const form = modalCrearPresentacion.querySelector('form');
            if (!form) return;

            modalCrearPresentacion.querySelector('.modal-title').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Presentación';
            form.querySelector('[name="id"]').value = btnEditar.dataset.id || '';
            form.querySelector('[name="accion"]').value = 'editar';
            form.querySelector('[name="id_item"]').value = btnEditar.dataset.idItem || '';
            form.querySelector('[name="nombre"]').value = btnEditar.dataset.nombre || '';
            form.querySelector('[name="factor"]').value = btnEditar.dataset.factor || '';
            form.querySelector('[name="precio_x_menor"]').value = btnEditar.dataset.precioMenor || '';
            form.querySelector('[name="precio_x_mayor"]').value = btnEditar.dataset.precioMayor || '';
            form.querySelector('[name="cantidad_minima_mayor"]').value = btnEditar.dataset.cantidadMinima || '';
        };

        // --- B. Lógica para EDITAR ---
        tablaPresentaciones.addEventListener('click', function(e) {
            const btnEditar = e.target.closest('.js-editar-presentacion');
            
            if (btnEditar) {
                cargarEdicionPresentacion(btnEditar);
            }

            const btnEstado = e.target.closest('.js-toggle-estado-presentacion');
            if (btnEstado) {
                const id = btnEstado.dataset.id;
                const estadoActual = btnEstado.checked ? 1 : 0;
                const nuevoEstado = estadoActual;
                const accionTexto = nuevoEstado === 1 ? 'activar' : 'desactivar';

                if (confirm(`¿Deseas ${accionTexto} esta presentación?`)) {
                    window.location.href = `?ruta=comercial/toggleEstadoPresentacion&id=${id}&estado=${nuevoEstado}`;
                } else {
                    btnEstado.checked = !btnEstado.checked;
                }
            }
        });

        // --- C. Lógica para ELIMINAR ---
        tablaPresentaciones.addEventListener('click', function(e) {
            const btnEliminar = e.target.closest('.js-eliminar-presentacion');
            if (btnEliminar) {
                if(confirm('¿Estás seguro de eliminar esta presentación?')) {
                    const id = btnEliminar.dataset.id;
                    window.location.href = `?ruta=comercial/eliminarPresentacion&id=${id}`;
                }
            }
        });

        modalCrearPresentacion?.addEventListener('hidden.bs.modal', function() {
            resetFormPresentacion();
        });

        modalCrearPresentacion?.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            if (!(trigger instanceof Element) || !trigger.classList.contains('js-editar-presentacion')) {
                resetFormPresentacion();
                return;
            }

            cargarEdicionPresentacion(trigger);
        });

        btnCrearPresentacion?.addEventListener('click', function() {
            resetFormPresentacion();
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
    // 3. LÓGICA PARA ASIGNACIÓN DE CLIENTES (AJAX)
    // =========================================================================
    const tablaAsignacion = document.getElementById('clientesAsignacionTable');

    if (tablaAsignacion) {
        const selects = tablaAsignacion.querySelectorAll('.js-cambiar-lista');

        selects.forEach(select => {
            select.addEventListener('change', function() {
                const idCliente = this.dataset.idCliente;
                const idLista = this.value;

                this.disabled = true;
                
                fetch('?ruta=comercial/guardarAsignacionAjax', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        id_cliente: idCliente,
                        id_lista: idLista
                    })
                })
                .then(response => response.json())
                .then(data => {
                    this.disabled = false;
                    
                    if (data.success) {
                        this.classList.add('is-valid', 'bg-success-subtle');
                        setTimeout(() => {
                            this.classList.remove('is-valid', 'bg-success-subtle');
                        }, 1500);
                    } else {
                        alert('Error al guardar: ' + (data.message || 'Error desconocido'));
                        this.classList.add('is-invalid');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.disabled = false;
                    alert('Error de conexión al intentar guardar.');
                });
            });
        });
    }
});
