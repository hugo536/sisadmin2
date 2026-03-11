document.addEventListener('DOMContentLoaded', function() {
    // Pequeña validación de seguridad para asegurarnos de que Bootstrap cargó
    if (typeof bootstrap === 'undefined') {
        console.error('Error: La librería Bootstrap JS no está cargada en esta página.');
        return;
    }

    // 1. Inicializar los tooltips de Bootstrap para los iconos de la tabla
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Crear la instancia del Modal de Bootstrap para poder controlarlo por JS
    const modalCCElement = document.getElementById('modalCentroCosto');
    const modalCC = modalCCElement ? bootstrap.Modal.getOrCreateInstance(modalCCElement) : null;

    // 3. Lógica para los botones de Editar
    document.querySelectorAll('.btn-editar-cc').forEach((btn) => {
        btn.addEventListener('click', function() {
            // Llenar el formulario con los atributos data-* del botón
            document.getElementById('cc_id').value = this.dataset.id || '0';
            document.getElementById('cc_codigo').value = this.dataset.codigo || '';
            document.getElementById('cc_nombre').value = this.dataset.nombre || '';
            document.getElementById('cc_estado').value = this.dataset.estado || '1';
            
            // Cambiar dinámicamente el título del modal
            const tituloModal = document.getElementById('tituloModalCC');
            if (tituloModal) {
                tituloModal.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Centro de Costo';
            }
            
            // Abrir el modal
            if (modalCC) modalCC.show();
        });
    });

    // 4. Lógica para el botón de Nuevo (para limpiar el formulario)
    const btnNuevo = document.getElementById('btnNuevoCentroCosto');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            // Limpiar los campos del formulario
            const form = document.getElementById('formCentroCosto');
            if (form) form.reset();
            
            // Asegurar que el ID oculto vuelva a 0
            document.getElementById('cc_id').value = '0';
            
            // Restaurar el título original
            const tituloModal = document.getElementById('tituloModalCC');
            if (tituloModal) {
                tituloModal.innerHTML = '<i class="bi bi-diagram-3 me-2"></i>Registrar Centro de Costo';
            }
        });
    }

    // 5. Lógica para el buscador en tiempo real
    const inputBuscador = document.getElementById('searchCentrosCosto');
    if (inputBuscador) {
        inputBuscador.addEventListener('keyup', function() {
            const textoBusqueda = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaCentrosCosto tbody tr');

            filas.forEach(fila => {
                // Evitar ocultar la fila de "No hay centros de costo registrados" si aparece
                if (fila.querySelector('td[colspan]')) return;

                // Capturamos el texto de la columna Código (1) y Nombre (2)
                const codigo = fila.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const nombre = fila.querySelector('td:nth-child(2)').textContent.toLowerCase();

                // Si el texto buscado está en el código o en el nombre, mostramos la fila, si no, la ocultamos
                if (codigo.includes(textoBusqueda) || nombre.includes(textoBusqueda)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });
    }
});