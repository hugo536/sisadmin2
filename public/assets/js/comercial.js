/**
 * GESTIÓN COMERCIAL (Presentaciones, Listas, Asignaciones)
 * Este archivo maneja la lógica de las 3 vistas del módulo.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // =========================================================================
    // 1. LÓGICA PARA PRESENTACIONES (PACKS)
    // =========================================================================
    const tablaPresentaciones = document.getElementById('presentacionesTable');
    
    if (tablaPresentaciones) {
        // Lógica para el botón EDITAR
        tablaPresentaciones.addEventListener('click', function(e) {
            // Buscamos si el click fue dentro de un botón con clase .js-editar-presentacion
            const btnEditar = e.target.closest('.js-editar-presentacion');
            
            if (btnEditar) {
                const data = JSON.parse(btnEditar.dataset.json);
                const modal = document.getElementById('modalCrearPresentacion');
                
                // Cambiar título del modal
                modal.querySelector('.modal-title').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Presentación';
                
                // Llenar campos
                const form = modal.querySelector('form');
                form.querySelector('[name="id"]').value = data.id || ''; // Campo oculto para ID (asegurate de agregarlo en el HTML)
                form.querySelector('[name="id_item"]').value = data.id_item;
                form.querySelector('[name="nombre"]').value = data.nombre;
                form.querySelector('[name="factor"]').value = data.factor;
                form.querySelector('[name="precio_x_menor"]').value = data.precio_x_menor;
                form.querySelector('[name="precio_x_mayor"]').value = data.precio_x_mayor;
                form.querySelector('[name="cantidad_minima_mayor"]').value = data.cantidad_minima_mayor;
                
                // Nota: Idealmente deberías cambiar la acción del form o tener un input hidden 'accion'
            }
        });

        // Lógica para ELIMINAR (con SweetAlert2 si lo tienes, sino confirm nativo)
        tablaPresentaciones.addEventListener('click', function(e) {
            const btnEliminar = e.target.closest('.js-eliminar-presentacion');
            if (btnEliminar) {
                if(confirm('¿Estás seguro de eliminar esta presentación?')) {
                    const id = btnEliminar.dataset.id;
                    window.location.href = `?ruta=comercial/eliminarPresentacion&id=${id}`;
                }
            }
        });
    }

    // =========================================================================
    // 2. LÓGICA PARA LISTAS DE PRECIOS
    // =========================================================================
    const formPrecios = document.getElementById('formPrecios');
    
    if (formPrecios) {
        // Calcular cambios visuales cuando editas un precio
        const inputsPrecio = formPrecios.querySelectorAll('input[type="number"]');
        
        inputsPrecio.forEach(input => {
            input.addEventListener('change', function() {
                if(this.value !== '') {
                    this.classList.add('bg-warning-subtle'); // Resaltar cambio
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
                const originalColor = this.style.backgroundColor;

                // Feedback visual: Cargando...
                this.disabled = true;
                
                // Petición AJAX al controlador
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
                        // Éxito: Parpadeo verde
                        this.classList.add('is-valid', 'bg-success-subtle');
                        setTimeout(() => {
                            this.classList.remove('is-valid', 'bg-success-subtle');
                        }, 1500);
                        
                        // Opcional: Toast de notificación
                        // mostrarToast('Asignación actualizada correctamente');
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