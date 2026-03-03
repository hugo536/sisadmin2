(function () {
    // 1) Cambio de estado activo/inactivo con confirmación
    document.querySelectorAll('.form-cambiar-estado-cuenta').forEach(function (form) {
        const sw = form.querySelector('.switch-estado-cuenta');
        const toEstado = form.querySelector('input[name="estado"]');
        const badge = form.closest('tr') ? form.closest('tr').querySelector('[data-estado-badge]') : null;
        if (!sw || !toEstado) {
            return;
        }

        sw.addEventListener('change', function (e) {
            const estadoObjetivo = sw.checked ? 1 : 0;
            const activar = estadoObjetivo === 1;
            const codigo = form.getAttribute('data-codigo') || '';
            const nombre = form.getAttribute('data-nombre') || '';
            const accion = activar ? 'ACTIVAR' : 'INACTIVAR';

            const confirmar = confirm(
                '¿Está seguro de ' + accion + ' esta cuenta?\n\n' +
                (codigo ? ('Código: ' + codigo + '\n') : '') +
                (nombre ? ('Cuenta: ' + nombre + '\n\n') : '\n') +
                (activar
                    ? 'La cuenta volverá a estar disponible para operaciones.'
                    : 'La cuenta dejará de estar disponible para nuevos registros, pero su historial permanecerá intacto.')
            );

            if (!confirmar) {
                sw.checked = !sw.checked;
                e.preventDefault();
                return;
            }

            toEstado.value = String(estadoObjetivo);
            sw.disabled = true;
            if (badge) {
                badge.textContent = activar ? 'Activa' : 'Inactiva';
            }
            form.submit();
        });
    });

    // 2) Carga de datos en modal de edición de cuenta
    document.querySelectorAll('.btn-editar-cuenta').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const codigo = this.getAttribute('data-codigo');
            const nombre = this.getAttribute('data-nombre');
            const tipo = this.getAttribute('data-tipo');
            const nivel = this.getAttribute('data-nivel');
            const permiteMov = this.getAttribute('data-permite-movimiento');
            const estado = this.getAttribute('data-estado');

            const modalElement = document.getElementById('modalEditarCuenta');
            if (!modalElement) {
                return;
            }

            modalElement.querySelector('input[name="id"]').value = id;
            modalElement.querySelector('input[name="codigo"]').value = codigo;
            modalElement.querySelector('input[name="nombre"]').value = nombre;
            modalElement.querySelector('select[name="tipo"]').value = tipo;
            modalElement.querySelector('input[name="nivel"]').value = nivel;
            modalElement.querySelector('select[name="permite_movimiento"]').value = permiteMov;
            modalElement.querySelector('select[name="estado"]').value = estado;

            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    });

    // 3) Gestión de tabla de parámetros vigentes
    const filtroTexto = document.getElementById('buscarParametroVigente');
    const filtroClave = document.getElementById('filtroClaveParametroVigente');
    const filasParametros = Array.from(document.querySelectorAll('[data-param-row="true"]'));
    const filaVaciaFiltrada = document.getElementById('filaSinParametrosFiltrados');

    const aplicarFiltrosParametros = function () {
        if (!filasParametros.length) {
            return;
        }

        const texto = (filtroTexto ? filtroTexto.value : '').trim().toLowerCase();
        const clave = (filtroClave ? filtroClave.value : '').trim().toLowerCase();
        let visibles = 0;

        filasParametros.forEach(function (fila) {
            const filaTexto = (fila.getAttribute('data-search') || '').toLowerCase();
            const filaClave = (fila.getAttribute('data-clave') || '').toLowerCase();
            const coincideTexto = texto === '' || filaTexto.includes(texto);
            const coincideClave = clave === '' || filaClave === clave;
            const mostrar = coincideTexto && coincideClave;
            fila.classList.toggle('d-none', !mostrar);
            if (mostrar) {
                visibles += 1;
            }
        });

        if (filaVaciaFiltrada) {
            filaVaciaFiltrada.classList.toggle('d-none', visibles > 0);
        }
    };

    if (filtroTexto) {
        filtroTexto.addEventListener('input', aplicarFiltrosParametros);
    }
    if (filtroClave) {
        filtroClave.addEventListener('change', aplicarFiltrosParametros);
    }

    const claveSelect = document.getElementById('parametroClave');
    const cuentaSelect = document.getElementById('parametroCuenta');
    const modalParametros = document.getElementById('modalParametros');
    document.querySelectorAll('.btn-editar-parametro').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!claveSelect || !cuentaSelect || !modalParametros) {
                return;
            }

            claveSelect.value = this.getAttribute('data-clave') || '';
            cuentaSelect.value = this.getAttribute('data-id-cuenta') || '';
            bootstrap.Modal.getOrCreateInstance(modalParametros).show();
        });
    });
})();
