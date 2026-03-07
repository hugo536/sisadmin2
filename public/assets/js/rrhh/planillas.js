document.addEventListener('DOMContentLoaded', () => {

    // --- VARIABLES GLOBALES ---
    const searchInput = document.getElementById('searchDetalles');
    const tablaDetalles = document.getElementById('tablaDetallesNomina'); 

    // ==============================================================
    // 1. GESTIÓN DEL MODAL DE AJUSTES (BONOS/DEDUCCIONES)
    // ==============================================================
    const modalAjustar = document.getElementById('modalAjustarNomina');
    if (modalAjustar) {
        modalAjustar.addEventListener('show.bs.modal', function (event) {
            // Botón (el lápiz amarillo) que disparó el modal
            const button = event.relatedTarget;
            
            // Extraer info de los atributos data-* que pusimos en el HTML
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            
            // Inyectar en los inputs ocultos y textos visuales del modal
            document.getElementById('ajusteIdDetalle').value = id;
            document.getElementById('ajusteNombreEmpleado').textContent = nombre;
            
            // Resetear los campos de monto y descripción cada vez que se abre
            const formAjuste = modalAjustar.querySelector('form');
            if(formAjuste) {
                formAjuste.querySelector('input[name="monto"]').value = '';
                formAjuste.querySelector('input[name="descripcion"]').value = '';
            }
        });
    }

    // ==============================================================
    // 2. BUSCADOR LOCAL EN LA TABLA DE DETALLES (RECIBOS)
    // ==============================================================
    if (searchInput && tablaDetalles) {
        
        // Prevenir que el "Enter" recargue la página en el buscador
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });

        // Filtrado en vivo (Keyup)
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const filas = tablaDetalles.querySelectorAll('tbody tr:not(.empty-msg-row)');
            
            let filasVisibles = 0;

            filas.forEach(fila => {
                // Recuperamos la cadena de búsqueda (data-search)
                const dataSearch = fila.getAttribute('data-search') || '';
                
                if (dataSearch.includes(searchTerm)) {
                    fila.style.display = ''; // Mostrar
                    filasVisibles++;
                } else {
                    fila.style.display = 'none'; // Ocultar
                }
            });

            // Lógica para mostrar/ocultar el mensaje de "No hay resultados"
            const tbody = tablaDetalles.querySelector('tbody');
            let emptyRow = tbody.querySelector('.empty-msg-row-search'); 
            
            if (filasVisibles === 0 && filas.length > 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.className = 'empty-msg-row-search border-bottom-0';
                    tbody.appendChild(emptyRow);
                }
                emptyRow.style.display = '';
                emptyRow.innerHTML = `
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>
                        No se encontraron empleados que coincidan con "<b>${searchTerm}</b>".
                    </td>
                `;
            } else if (emptyRow) {
                emptyRow.style.display = 'none';
            }
        });
    }

    // ==============================================================
    // 3. SEGURIDAD ADICIONAL (Prevenir doble envíos en pagos)
    // ==============================================================
    const formPagarLote = document.querySelector('#modalPagarLote form');
    if (formPagarLote) {
        formPagarLote.addEventListener('submit', function(e) {
            // Evitar que el usuario presione 2 veces el botón de pagar
            const btnSubmit = this.querySelector('button[type="submit"]');
            if (btnSubmit) {
                // Cambiamos el texto e ícono
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                // Agregamos una clase visual y deshabilitamos el botón físicamente
                btnSubmit.classList.add('disabled');
                btnSubmit.disabled = true; 
            }
        });
    }

});