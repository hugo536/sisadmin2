(function(){
    // 1. Inicializar los tooltips de Bootstrap para los iconos de la tabla
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Crear la instancia del Modal de Bootstrap para poder controlarlo por JS
    const modalCCElement = document.getElementById('modalCentroCosto');
    const modalCC = modalCCElement ? new bootstrap.Modal(modalCCElement) : null;

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
})();