(function(){
    /**
     * =========================================================================
     * 1. Confirmación de Seguridad: Cerrar Periodo
     * =========================================================================
     * Intercepta el envío del formulario cuando el usuario intenta cerrar un mes.
     * Es crucial porque un mes cerrado bloquea la contabilidad.
     */
    const formulariosCerrar = document.querySelectorAll('.form-cerrar-periodo');
    
    formulariosCerrar.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const mensaje = '¿Está seguro de CERRAR este periodo?\n\nYa no se podrán registrar ni modificar asientos contables en este mes.';
            const confirmar = confirm(mensaje);
            
            // Si el usuario presiona "Cancelar", detenemos el envío de los datos al servidor
            if (!confirmar) {
                e.preventDefault(); 
            }
        });
    });

    /**
     * =========================================================================
     * 2. Confirmación de Seguridad: Reabrir Periodo
     * =========================================================================
     * Intercepta el envío del formulario cuando se intenta abrir un mes pasado.
     * Modificar meses anteriores puede alterar los Estados Financieros.
     */
    const formulariosAbrir = document.querySelectorAll('.form-abrir-periodo');
    
    formulariosAbrir.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const mensaje = '¿Está seguro de REABRIR este periodo?\n\nEsto permitirá registrar movimientos pasados y podría alterar los reportes financieros que ya fueron emitidos.';
            const confirmar = confirm(mensaje);
            
            // Si el usuario presiona "Cancelar", detenemos el envío
            if (!confirmar) {
                e.preventDefault();
            }
        });
    });

})();