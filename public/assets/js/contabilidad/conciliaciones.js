(function(){
    // =========================================================================
    // 1. Reservado para UX en importación / matching automático
    // (Aquí podrás agregar la lógica para hacer check automático a los 
    // movimientos que coincidan por monto y fecha)
    // =========================================================================
    
    
    // =========================================================================
    // 2. Confirmación crítica antes de cerrar la conciliación (Seguridad)
    // =========================================================================
    const btnCerrar = document.querySelector('.btn-cerrar-conciliacion');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function(e) {
            const confirmar = confirm("¿Está seguro de cerrar esta conciliación?\n\nAl hacerlo, el saldo se considerará cuadrado y no podrá deshacer esta acción ni marcar más movimientos.");
            if (!confirmar) {
                e.preventDefault(); // Evita que se envíe el formulario si el usuario cancela
            }
        });
    }

})();