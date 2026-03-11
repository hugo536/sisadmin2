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

    // 6. AUTO-CÁLCULO DE DEPRECIACIÓN INICIAL (Solo para activos nuevos)
    function autoCalcularDepreciacion() {
        const id = parseInt(document.getElementById('af_id').value) || 0;
        
        // Si estamos editando un activo viejo, respetamos la base de datos y no recalculamos
        if (id > 0) return; 

        const fechaStr = document.getElementById('af_fecha').value;
        const costo = parseFloat(document.getElementById('af_costo').value) || 0;
        const residual = parseFloat(document.getElementById('af_residual').value) || 0;
        const vida = parseInt(document.getElementById('af_vida').value) || 0;
        const inputDep = document.getElementById('af_dep_acumulada');

        if (!fechaStr || costo <= 0 || vida <= 0) {
            inputDep.value = '0.0000';
            return;
        }

        const fechaAdq = new Date(fechaStr + 'T00:00:00'); // Evita desfase de zona horaria
        const hoy = new Date();
        
        // Calcular cuántos meses han pasado desde la compra hasta hoy
        let mesesPasados = (hoy.getFullYear() - fechaAdq.getFullYear()) * 12 + (hoy.getMonth() - fechaAdq.getMonth());
        
        if (mesesPasados < 0) mesesPasados = 0;
        if (mesesPasados > vida) mesesPasados = vida; // No se puede depreciar más de su vida útil

        const baseDepreciable = costo - residual;
        let depAcumulada = 0;
        
        if (baseDepreciable > 0) {
            const depMensual = baseDepreciable / vida;
            depAcumulada = depMensual * mesesPasados;
        }

        inputDep.value = depAcumulada.toFixed(4);
    }

    // Escuchar cada vez que el usuario escriba o cambie estos campos
    ['af_fecha', 'af_costo', 'af_residual', 'af_vida'].forEach(idCaja => {
        const caja = document.getElementById(idCaja);
        if(caja) caja.addEventListener('input', autoCalcularDepreciacion);
    });
});