(function(){
    // Confirmación para inactivar una cuenta contable
    document.querySelectorAll('.form-inactivar-cuenta').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const confirmar = confirm("¿Está seguro de INACTIVAR esta cuenta?\n\nLa cuenta dejará de estar disponible para nuevos registros, pero su historial permanecerá intacto.");
            
            if (!confirmar) {
                e.preventDefault(); // Detiene la inactivación si el usuario cancela
            }
        });
    });
})();