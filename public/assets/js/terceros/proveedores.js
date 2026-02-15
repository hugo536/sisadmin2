(function () {
    'use strict';

    function initProveedorLogic(prefix) {
        const condicionSelect = document.getElementById(`${prefix}ProvCondicion`);
        const diasInput = document.getElementById(`${prefix}ProvDiasCredito`);

        if (!condicionSelect || !diasInput) return;

        // Función para bloquear/desbloquear días según condición
        const toggleDias = () => {
            const val = condicionSelect.value;
            const esCredito = val === 'CREDITO';
            
            diasInput.disabled = !esCredito;
            
            if (!esCredito) {
                diasInput.value = '0';
                diasInput.classList.remove('is-invalid');
            } else if (diasInput.value === '0' || diasInput.value === '') {
                // Opcional: Sugerir días por defecto
                // diasInput.value = '30'; 
            }
        };

        condicionSelect.addEventListener('change', toggleDias);
        
        // Ejecutar al inicio por si es edición
        toggleDias();
    }

    // Inicializar para Crear y Editar
    document.addEventListener('DOMContentLoaded', function () {
        initProveedorLogic('crear');
        // Para 'edit' se ejecutará cuando el modal cargue los datos, 
        // pero añadimos el listener aquí para que reaccione a cambios posteriores.
        initProveedorLogic('edit');
    });

    // Hook para cuando se abre el modal de editar (para refrescar el estado disabled)
    const modalEdit = document.getElementById('modalEditarTercero');
    if (modalEdit) {
        modalEdit.addEventListener('shown.bs.modal', () => initProveedorLogic('edit'));
    }

    window.TercerosProveedores = {};
})();