(function () {
    function initDashboardPeriodoToggle() {
        const periodoSelect = document.querySelector('select[name="periodo"]');
        if (!periodoSelect) return;

        const camposPeriodo = Array.from(document.querySelectorAll('[data-period-field]'));
        const alternarCampos = function () {
            const seleccionado = periodoSelect.value;
            camposPeriodo.forEach(function (campo) {
                const visible = campo.getAttribute('data-period-field') === seleccionado;
                campo.classList.toggle('d-none', !visible);
                const inputs = campo.querySelectorAll('input');
                inputs.forEach(function (inp) {
                    if (!visible) inp.removeAttribute('required');
                    else if (campo.getAttribute('data-period-field') === 'dia') inp.setAttribute('required', 'required');
                });
            });
        };

        periodoSelect.addEventListener('change', alternarCampos);
        alternarCampos();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDashboardPeriodoToggle();
    });
})();
