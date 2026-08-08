document.addEventListener('DOMContentLoaded', function() {
    
    const formConfig = document.getElementById('formConfigRRHH');
    const selectBloque = document.querySelector('[name="bloque_minutos"]');
    const inputTolerancia = document.querySelector('[name="minutos_tolerancia"]');

    // --- VALIDACIÓN ANTES DE GUARDAR ---
    if (formConfig && selectBloque && inputTolerancia) {
        formConfig.addEventListener('submit', function(e) {
            const bloque = parseInt(selectBloque.value) || 0;
            const umbral = parseInt(inputTolerancia.value) || 0;
            
            // La tolerancia no puede ser igual o mayor al bloque, 
            // sino el sistema no sabría hacia dónde redondear.
            if (umbral >= bloque) {
                e.preventDefault(); // Detiene el guardado
                
                // Si tienes SweetAlert2 cargado en tu layout, lo usamos para que se vea elegante
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Configuración Inválida',
                        text: 'El umbral de corte (tolerancia) debe ser estrictamente menor al tamaño del bloque.',
                        confirmButtonText: 'Entendido'
                    });
                } else {
                    alert('El umbral de corte (tolerancia) debe ser estrictamente menor al tamaño del bloque.');
                }
                
                inputTolerancia.focus();
            }
        });
    }

});