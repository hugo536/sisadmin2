document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Instancia del Modal
    const modalElement = document.getElementById('modalActivoFijo');
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;

    // 2. Configurar e Inicializar Tom Select en las listas desplegables
    const tsOptions = {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: "Escriba para buscar..."
    };

    const tomActivo = new TomSelect('#af_cta_activo', tsOptions);
    const tomDep = new TomSelect('#af_cta_dep', tsOptions);
    const tomGasto = new TomSelect('#af_cta_gasto', tsOptions);
    const tomCentro = new TomSelect('#af_centro', tsOptions);

    // 3. Botón Nuevo Activo
    const btnNuevo = document.getElementById('btnNuevoActivo');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            const form = document.getElementById('formActivoFijo');
            if(form) form.reset();
            document.getElementById('af_dep_acumulada').value = '0';
            document.getElementById('af_id').value = '0';
            document.getElementById('tituloModalActivo').innerHTML = '<i class="bi bi-building-add me-2"></i>Registrar Activo Fijo';
            
            // Limpiar los buscadores de Tom Select
            tomActivo.clear();
            tomDep.clear();
            tomGasto.clear();
            tomCentro.setValue('0');
        });
    }

    // 4. Botones Editar en la Tabla
    document.querySelectorAll('.btn-editar-activo').forEach(btn => {
        btn.addEventListener('click', function() {
            // Llenar inputs de texto y fecha
            document.getElementById('af_dep_acumulada').value = this.dataset.depacum || '0';
            document.getElementById('af_id').value = this.dataset.id || '0';
            document.getElementById('af_codigo').value = this.dataset.codigo || '';
            document.getElementById('af_nombre').value = this.dataset.nombre || '';
            document.getElementById('af_fecha').value = this.dataset.fecha || '';
            document.getElementById('af_costo').value = this.dataset.costo || '';
            document.getElementById('af_residual').value = this.dataset.residual || '0';
            document.getElementById('af_vida').value = this.dataset.vida || '';
            document.getElementById('af_estado').value = this.dataset.estado || 'ACTIVO';
            
            // Llenar los menús desplegables controlados por Tom Select
            tomActivo.setValue(this.dataset.ctaActivo || '');
            tomDep.setValue(this.dataset.ctaDep || '');
            tomGasto.setValue(this.dataset.ctaGasto || '');
            tomCentro.setValue(this.dataset.centro || '0');
            
            document.getElementById('tituloModalActivo').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Activo Fijo';
            
            if(modal) modal.show();
        });
    });

    // 5. Buscador de la tabla
    const buscador = document.getElementById('searchActivos');
    if (buscador) {
        buscador.addEventListener('keyup', function() {
            const texto = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaActivosFijos tbody tr');
            
            filas.forEach(fila => {
                if (fila.querySelector('td[colspan]')) return; 
                const contenido = fila.textContent.toLowerCase();
                fila.style.display = contenido.includes(texto) ? '' : 'none';
            });
        });
    }
});