(function () {
    'use strict';

    function toggleRegimenFields(regimenSelect) {
        if (!regimenSelect) return;
        const prefix = regimenSelect.id.replace('Regimen', '');

        const comisionSelect = document.getElementById(`${prefix}TipoComision`);
        const cusppInput = document.getElementById(`${prefix}Cuspp`);

        if (!comisionSelect || !cusppInput) return;

        const val = regimenSelect.value;
        const isAfp = val && val !== 'ONP';

        comisionSelect.disabled = !isAfp;
        cusppInput.disabled = !isAfp;

        if (!isAfp) {
            comisionSelect.value = '';
            cusppInput.value = '';
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

        const isDiario = tipoPagoSelect.value === 'DIARIO';

        if (isDiario) {
            sueldoGroup.classList.add('d-none');
            diarioGroup.classList.remove('d-none');
            if (sueldoInput) sueldoInput.value = '';
        } else {
            sueldoGroup.classList.remove('d-none');
            diarioGroup.classList.add('d-none');
            if (diarioInput) diarioInput.value = '';
        }
    }

    function toggleFechaNacimiento(recordarSwitch) {
        if (!recordarSwitch) return;
        const prefix = recordarSwitch.id.replace('RecordarCumpleanos', '');
        const wrapper = document.getElementById(`${prefix}FechaNacimientoWrapper`);
        const fechaInput = document.getElementById(`${prefix}FechaNacimiento`);

        if (!wrapper || !fechaInput) return;

        if (recordarSwitch.checked) {
            wrapper.classList.remove('d-none');
            fechaInput.disabled = false;
            fechaInput.required = true;
        } else {
            wrapper.classList.add('d-none');
            fechaInput.disabled = true;
            fechaInput.required = false;
            fechaInput.value = '';
        }
    }

    function toggleFechaCese(estadoLaboralSelect) {
        if (!estadoLaboralSelect) return;
        const prefix = estadoLaboralSelect.id.replace('EstadoLaboral', '');
        const fechaCeseInput = document.getElementById(`${prefix}FechaCese`);

        if (!fechaCeseInput) return;

        const requiereFechaCese = estadoLaboralSelect.value !== 'activo';
        fechaCeseInput.disabled = !requiereFechaCese;
        fechaCeseInput.required = requiereFechaCese;

        if (!requiereFechaCese) {
            fechaCeseInput.value = '';
        }
    }

    window.TercerosEmpleados = {
        init: function (prefix) {
            const regimen = document.getElementById(`${prefix}Regimen`);
            if (regimen) {
                regimen.addEventListener('change', () => toggleRegimenFields(regimen));
                toggleRegimenFields(regimen);
            }

            const tipoPago = document.getElementById(`${prefix}TipoPago`);
            if (tipoPago) {
                tipoPago.addEventListener('change', () => togglePagoFields(tipoPago));
                togglePagoFields(tipoPago);
            }

            const recordarCumpleanos = document.getElementById(`${prefix}RecordarCumpleanos`);
            if (recordarCumpleanos) {
                recordarCumpleanos.addEventListener('change', () => toggleFechaNacimiento(recordarCumpleanos));
                toggleFechaNacimiento(recordarCumpleanos);
            }

            const estadoLaboral = document.getElementById(`${prefix}EstadoLaboral`);
            if (estadoLaboral) {
                estadoLaboral.addEventListener('change', () => toggleFechaCese(estadoLaboral));
                toggleFechaCese(estadoLaboral);
            }
        },
        refreshState: function (prefix) {
            const recordarCumpleanos = document.getElementById(`${prefix}RecordarCumpleanos`);
            const estadoLaboral = document.getElementById(`${prefix}EstadoLaboral`);
            const tipoPago = document.getElementById(`${prefix}TipoPago`);
            const regimen = document.getElementById(`${prefix}Regimen`);

            toggleFechaNacimiento(recordarCumpleanos);
            toggleFechaCese(estadoLaboral);
            togglePagoFields(tipoPago);
            toggleRegimenFields(regimen);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.TercerosEmpleados.init('crear');
        window.TercerosEmpleados.init('edit');
    });
})();
