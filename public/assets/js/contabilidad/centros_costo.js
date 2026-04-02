document.addEventListener('DOMContentLoaded', function() {
    // Pequeña validación de seguridad
    if (typeof bootstrap === 'undefined') {
        console.error('Error: La librería Bootstrap JS no está cargada en esta página.');
        return;
    }

    // 1. Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Modal
    const modalCCElement = document.getElementById('modalCentroCosto');
    const modalCC = modalCCElement ? bootstrap.Modal.getOrCreateInstance(modalCCElement) : null;

    // 3. Botones Editar
    document.querySelectorAll('.btn-editar-cc').forEach((btn) => {
        btn.addEventListener('click', function() {
            document.getElementById('cc_id').value = this.dataset.id || '0';
            document.getElementById('cc_codigo').value = this.dataset.codigo || '';
            document.getElementById('cc_nombre').value = this.dataset.nombre || '';
            document.getElementById('cc_estado').value = this.dataset.estado || '1';
            
            const tituloModal = document.getElementById('tituloModalCC');
            if (tituloModal) {
                tituloModal.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Centro de Costo';
            }
            
            if (modalCC) modalCC.show();
        });
    });

    // 4. Botón Nuevo
    const btnNuevo = document.getElementById('btnNuevoCentroCosto');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            const form = document.getElementById('formCentroCosto');
            if (form) form.reset();
            
            document.getElementById('cc_id').value = '0';
            
            const tituloModal = document.getElementById('tituloModalCC');
            if (tituloModal) {
                tituloModal.innerHTML = '<i class="bi bi-diagram-3 me-2"></i>Registrar Centro de Costo';
            }
        });
    }

    // 5. Buscador inteligente (¡Mejorado!)
    const inputBuscador = document.getElementById('searchCentrosCosto');
    const filaSinResultados = document.getElementById('filaSinResultados');
    
    if (inputBuscador) {
        inputBuscador.addEventListener('keyup', function() {
            const textoBusqueda = this.value.toLowerCase();
            // Solo buscamos en las filas que tienen datos reales (agregamos la clase .fila-datos en la vista)
            const filas = document.querySelectorAll('#tablaCentrosCosto tbody tr.fila-datos');
            let filasVisibles = 0;

            filas.forEach(fila => {
                const codigo = fila.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const nombre = fila.querySelector('td:nth-child(2)').textContent.toLowerCase();

                if (codigo.includes(textoBusqueda) || nombre.includes(textoBusqueda)) {
                    fila.style.display = '';
                    filasVisibles++;
                } else {
                    fila.style.display = 'none';
                }
            });

            // Si hay filas de datos, pero ninguna coincide con la búsqueda, mostramos el mensaje
            if (filaSinResultados) {
                if (filasVisibles === 0 && filas.length > 0 && textoBusqueda !== '') {
                    filaSinResultados.style.display = '';
                } else {
                    filaSinResultados.style.display = 'none';
                }
            }
        });
    }

    // 6. NUEVO: Envío del formulario por AJAX
    const formCC = document.getElementById('formCentroCosto');
    if (formCC) {
        formCC.addEventListener('submit', function(e) {
            e.preventDefault(); // Evitamos que la página se recargue a la antigua
            
            const url = this.getAttribute('data-url');
            const formData = new FormData(this);
            const btnGuardar = document.getElementById('btnGuardarCC');
            const btnOriginalHTML = btnGuardar.innerHTML;

            // Cambiamos el botón para que el usuario sepa que está cargando
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...';

            // Enviamos los datos
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // Le dice a PHP que es una petición AJAX
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Error en el servidor');
                return response.json(); // Esperamos que PHP nos devuelva un JSON
            })
            .then(data => {
                // Restauramos el botón
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = btnOriginalHTML;

                if (data.status === 'success') {
                    if (modalCC) modalCC.hide();
                    
                    // Verificamos si tienes SweetAlert en tu proyecto, si no, usamos un alert normal
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.message || 'Guardado correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload(); // Recargamos la tabla para ver el nuevo registro
                        });
                    } else {
                        alert(data.message || 'Guardado correctamente.');
                        window.location.reload();
                    }
                } else {
                    // Si hubo un error (como código duplicado)
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Atención', text: data.message });
                    } else {
                        alert('Error: ' + data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = btnOriginalHTML;
                alert('Ocurrió un error inesperado al intentar guardar.');
            });
        });
    }
});