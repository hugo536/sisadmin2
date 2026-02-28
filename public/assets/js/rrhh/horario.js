(function () {
    function loadScript(src, onDone) {
        var tag = document.createElement('script');
        tag.src = src;
        tag.onload = onDone;
        document.head.appendChild(tag);
    }

    function initHorarioPage() {
        if (!document.getElementById('tablaTurnos') && !document.getElementById('horariosTable')) return;
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;

        var $ = window.jQuery;

        var empleadoSelect = $('#empleadoSelect2');
        if (empleadoSelect.length) {
            empleadoSelect.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar y seleccionar empleados...',
                closeOnSelect: false,
                allowClear: true
            });
        }

        $('#btnSeleccionarTodosEmpleados').on('click', function () {
            var options = $('#empleadoSelect2 > option').map(function () { return $(this).val(); }).get();
            $('#empleadoSelect2').val(options).trigger('change');
        });

        var btnTodosDias = document.getElementById('btnSeleccionarTodosDias');
        var diasMarcados = false;
        if (btnTodosDias) {
            btnTodosDias.addEventListener('click', function () {
                diasMarcados = !diasMarcados;
                document.querySelectorAll('.dia-checkbox').forEach(function (chk) {
                    chk.checked = diasMarcados;
                });
                this.innerText = diasMarcados ? 'Desmarcar Días' : 'Marcar Lun-Dom';
            });
        }

        var asignacionForm = document.getElementById('asignacionMasivaForm');
        if (asignacionForm) {
            asignacionForm.addEventListener('submit', function (event) {
                var empleados = $('#empleadoSelect2').val();
                var diasCheck = document.querySelectorAll('.dia-checkbox:checked');

                if (!empleados || empleados.length === 0) {
                    event.preventDefault();
                    alert('Por favor, selecciona al menos un empleado.');
                    return;
                }

                if (diasCheck.length === 0) {
                    event.preventDefault();
                    alert('Por favor, selecciona al menos un día de la semana.');
                }
            });
        }

        var idInput = document.getElementById('horarioId');
        var nombreInput = document.getElementById('horarioNombre');
        var entradaInput = document.getElementById('horarioEntrada');
        var salidaInput = document.getElementById('horarioSalida');
        var toleranciaInput = document.getElementById('horarioTolerancia');

        document.querySelectorAll('.js-editar-horario').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!idInput || !nombreInput || !entradaInput || !salidaInput || !toleranciaInput) return;
                idInput.value = this.dataset.id || '0';
                nombreInput.value = this.dataset.nombre || '';
                entradaInput.value = this.dataset.entrada || '';
                salidaInput.value = this.dataset.salida || '';
                toleranciaInput.value = this.dataset.tolerancia || '0';
                nombreInput.focus();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        var limpiar = document.getElementById('btnLimpiarHorario');
        if (limpiar && idInput && nombreInput && entradaInput && salidaInput && toleranciaInput) {
            limpiar.addEventListener('click', function () {
                idInput.value = '0';
                nombreInput.value = '';
                entradaInput.value = '';
                salidaInput.value = '';
                toleranciaInput.value = '0';
            });
        }
    }

    function boot() {
        if (!document.getElementById('tablaTurnos') && !document.getElementById('horariosTable')) return;

        var start = function () {
            if (!window.jQuery.fn.select2) {
                loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', initHorarioPage);
                return;
            }
            initHorarioPage();
        };

        if (!window.jQuery) {
            loadScript('https://code.jquery.com/jquery-3.7.1.min.js', start);
            return;
        }

        start();
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
