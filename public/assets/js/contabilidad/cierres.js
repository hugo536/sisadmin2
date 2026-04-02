(function(){
    // 1. Confirmación para Cierre Mensual
    document.querySelectorAll('.btn-confirmar-mensual').forEach((btn) => {
        btn.addEventListener('click', function(e) {
            const confirmar = confirm("¿Está seguro de cerrar este periodo mensual?\nYa no se podrán registrar ni modificar movimientos en este mes.");
            if (!confirmar) {
                e.preventDefault(); // Detiene el envío si el usuario cancela
            }
        });
    });

    // 2. Confirmación para Cierre Anual (Aún más estricta)
    document.querySelectorAll('.btn-confirmar-anual').forEach((btn) => {
        btn.addEventListener('click', function(e) {
            const anio = this.closest('form').querySelector('input[name="anio"]').value;
            const confirmar = confirm("¡ADVERTENCIA CRÍTICA!\n\nEstá a punto de ejecutar el Cierre Anual definitivo para el año " + anio + ".\nEsta operación calculará saldos finales, cerrará cuentas de resultados y bloqueará el año por completo.\n\n¿Está absolutamente seguro de proceder?");
            if (!confirmar) {
                e.preventDefault();
            }
        });
    });

    // 3. Confirmación para Depreciación
    document.querySelectorAll('.btn-confirmar-depreciacion').forEach((btn) => {
        btn.addEventListener('click', function(e) {
            const periodo = this.closest('form').querySelector('input[name="periodo"]').value;
            const confirmar = confirm(`Se ejecutará la depreciación automática para el periodo ${periodo}. ¿Desea continuar?`);
            if (!confirmar) {
                e.preventDefault();
            }
        });
    });
})();