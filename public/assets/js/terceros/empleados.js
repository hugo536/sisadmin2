(function () {
    'use strict';

    // Funciones auxiliares para lógica de empleados
    function toggleRegimenFields(regimenSelect) {
        if (!regimenSelect) return;
        const form = regimenSelect.closest('form');
        const prefix = regimenSelect.id.replace('Regimen', ''); // 'crear' o 'edit'
        
        const comisionSelect = document.getElementById(`${prefix}TipoComision`);
        const cusppInput = document.getElementById(`${prefix}Cuspp`);
        
        if (!comisionSelect || !cusppInput) return;

        const val = regimenSelect.value;
        // Si es AFP (no es vacío ni ONP), habilita campos
        const isAfp = val && val !== 'ONP';

        comisionSelect.disabled = !isAfp;
        cusppInput.disabled = !isAfp;

        if (!isAfp) {
            comisionSelect.value = '';
            cusppInput.value = '';
            // Limpiar errores visuales
            comisionSelect.classList.remove('is-invalid');
            cusppInput.classList.remove('is-invalid');
        }
    }

    function togglePagoFields(tipoPagoSelect) {
        if (!tipoPagoSelect) return;
        const prefix = tipoPagoSelect.id.replace('TipoPago', '');
        
        const sueldoGroup = document.getElementById(`${prefix}SueldoGroup`);
        const diarioGroup = document.getElementById(`${prefix}PagoDiarioGroup`);
        const sueldoInput = document.getElementById(`${prefix}SueldoBasico`);
        const diarioInput = document.getElementById(`${prefix}PagoDiario`);

        if (!sueldoGroup || !diarioGroup) return;

        const val = tipoPagoSelect.value;
        const isDiario = val === 'DIARIO';

        if (isDiario) {
            sueldoGroup.classList.add('d-none');
            diarioGroup.classList.remove('d-none');
            if(sueldoInput) sueldoInput.value = '';
        } else {
            sueldoGroup.classList.remove('d-none');
            diarioGroup.classList.add('d-none');
            if(diarioInput) diarioInput.value = '';
        }
    }

    // Exportar funciones si se necesitan fuera
    window.TercerosEmpleados = {
        init: function (prefix) {
            const regimen = document.getElementById(`${prefix}Regimen`);
            if (regimen) {
                regimen.addEventListener('change', () => toggleRegimenFields(regimen));
                // Estado inicial
                toggleRegimenFields(regimen);
            }

            const tipoPago = document.getElementById(`${prefix}TipoPago`);
            if (tipoPago) {
                tipoPago.addEventListener('change', () => togglePagoFields(tipoPago));
                // Estado inicial
                togglePagoFields(tipoPago);
            }
        }
    };

    // Inicializar listeners globales si el DOM ya tiene los elementos
    document.addEventListener('DOMContentLoaded', () => {
        window.TercerosEmpleados.init('crear');
        window.TercerosEmpleados.init('edit');
    });

})();