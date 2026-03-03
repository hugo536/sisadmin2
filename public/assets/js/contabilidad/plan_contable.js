(function(){
    // 1. Confirmación para inactivar una cuenta contable
    document.querySelectorAll('.form-inactivar-cuenta').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const confirmar = confirm("¿Está seguro de INACTIVAR esta cuenta?\n\nLa cuenta dejará de estar disponible para nuevos registros, pero su historial permanecerá intacto.");
            
            if (!confirmar) {
                e.preventDefault(); // Detiene la inactivación si el usuario cancela
            }
        });
    });

    // 2. Lógica para cargar datos en el Modal de Edición
    document.querySelectorAll('.btn-editar-cuenta').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // Extraer la información de la cuenta desde los atributos 'data-' del botón
            const id = this.getAttribute('data-id');
            const codigo = this.getAttribute('data-codigo');
            const nombre = this.getAttribute('data-nombre');
            const tipo = this.getAttribute('data-tipo');
            const nivel = this.getAttribute('data-nivel');
            const permiteMov = this.getAttribute('data-permite-movimiento');
            const estado = this.getAttribute('data-estado'); // Capturamos el estado
            
            // Seleccionar el modal de edición
            const modalElement = document.getElementById('modalEditarCuenta');
            if (modalElement) {
                // Rellenar los campos del formulario dentro del modal
                // IMPORTANTE: Cambiamos 'id_cuenta' por 'id' para que coincida con el Modelo PHP
                modalElement.querySelector('input[name="id"]').value = id; 
                modalElement.querySelector('input[name="codigo"]').value = codigo;
                modalElement.querySelector('input[name="nombre"]').value = nombre;
                modalElement.querySelector('select[name="tipo"]').value = tipo;
                modalElement.querySelector('input[name="nivel"]').value = nivel;
                modalElement.querySelector('select[name="permite_movimiento"]').value = permiteMov;
                modalElement.querySelector('select[name="estado"]').value = estado; // Rellenamos el estado

                // Usar getOrCreateInstance para evitar crear múltiples instancias del mismo modal
                const bsModal = bootstrap.Modal.getOrCreateInstance(modalElement);
                bsModal.show();
            } else {
                console.error("No se encontró el modal con ID 'modalEditarCuenta'");
            }
        });
    });
})();