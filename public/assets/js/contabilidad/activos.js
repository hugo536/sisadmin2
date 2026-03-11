document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Instancia segura del Modal (evita el fondo negro infinito)
    const modalElement = document.getElementById('modalActivoFijo');
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;

    // 2. Lógica para el botón "Nuevo Activo" (Limpia el formulario)
    const btnNuevo = document.getElementById('btnNuevoActivo');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            const form = document.getElementById('formActivoFijo');
            if(form) form.reset();
            
            document.getElementById('af_id').value = '0';
            document.getElementById('tituloModalActivo').innerHTML = '<i class="bi bi-building-add me-2"></i>Registrar Activo Fijo';
        });
    }

    // 3. Lógica para los botones "Editar" en la tabla
    document.querySelectorAll('.btn-editar-activo').forEach(btn => {
        btn.addEventListener('click', function() {
            // Llenar el formulario con los datos ocultos en el botón
            document.getElementById('af_id').value = this.dataset.id || '0';
            document.getElementById('af_codigo').value = this.dataset.codigo || '';
            document.getElementById('af_nombre').value = this.dataset.nombre || '';
            document.getElementById('af_fecha').value = this.dataset.fecha || '';
            document.getElementById('af_costo').value = this.dataset.costo || '';
            document.getElementById('af_residual').value = this.dataset.residual || '0';
            document.getElementById('af_vida').value = this.dataset.vida || '';
            document.getElementById('af_cta_activo').value = this.dataset.ctaActivo || '';
            document.getElementById('af_cta_dep').value = this.dataset.ctaDep || '';
            document.getElementById('af_centro').value = this.dataset.centro || '0';
            document.getElementById('af_estado').value = this.dataset.estado || 'ACTIVO';
            
            document.getElementById('tituloModalActivo').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Activo Fijo';
            
            // Abrir el modal
            if(modal) modal.show();
        });
    });

    // 4. Lógica del Buscador en tiempo real
    const buscador = document.getElementById('searchActivos');
    if (buscador) {
        buscador.addEventListener('keyup', function() {
            const texto = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaActivosFijos tbody tr');
            
            filas.forEach(fila => {
                if (fila.querySelector('td[colspan]')) return; // Ignorar la fila de "No hay registros"
                
                // Buscar en todo el texto de la fila
                const contenidoFila = fila.textContent.toLowerCase();
                fila.style.display = contenidoFila.includes(texto) ? '' : 'none';
            });
        });
    }
});