(function(){
    // 1) Cambio de estado activo/inactivo con confirmación
    document.querySelectorAll('.form-cambiar-estado-cuenta').forEach(function(form) {
        const sw = form.querySelector('.switch-estado-cuenta');
        if (!sw) {
            return;
        }

        sw.addEventListener('change', function(e) {
            const toEstado = form.querySelector('input[name="estado"]');
            const activar = toEstado && String(toEstado.value) === '1';
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

            form.submit();
        });
    });

    // 2) Carga de datos en modal de edición
    document.querySelectorAll('.btn-editar-cuenta').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const codigo = this.getAttribute('data-codigo');
            const nombre = this.getAttribute('data-nombre');
            const tipo = this.getAttribute('data-tipo');
            const nivel = this.getAttribute('data-nivel');
            const permiteMov = this.getAttribute('data-permite-movimiento');
            const estado = this.getAttribute('data-estado');

            const modalElement = document.getElementById('modalEditarCuenta');
            if (!modalElement) {
                console.error("No se encontró el modal con ID 'modalEditarCuenta'");
                return;
            }

            modalElement.querySelector('input[name="id"]').value = id;
            modalElement.querySelector('input[name="codigo"]').value = codigo;
            modalElement.querySelector('input[name="nombre"]').value = nombre;
            modalElement.querySelector('select[name="tipo"]').value = tipo;
            modalElement.querySelector('input[name="nivel"]').value = nivel;
            modalElement.querySelector('select[name="permite_movimiento"]').value = permiteMov;
            modalElement.querySelector('select[name="estado"]').value = estado;

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalElement);
            bsModal.show();
        });
    });

    // 3) Modal de parámetros vinculados por cuenta
    const modalParametrosCuenta = document.getElementById('modalParametrosCuenta');
    const titulo = document.getElementById('parametrosCuentaTitulo');
    const lista = document.getElementById('parametrosCuentaLista');

    document.querySelectorAll('.btn-parametros-cuenta').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!modalParametrosCuenta || !titulo || !lista) {
                return;
            }

            const codigo = this.getAttribute('data-codigo') || '';
            const nombre = this.getAttribute('data-nombre') || '';
            const raw = this.getAttribute('data-parametros') || '[]';
            let parametros = [];

            try {
                parametros = JSON.parse(raw);
            } catch (err) {
                parametros = [];
            }

            titulo.textContent = (codigo ? codigo + ' - ' : '') + nombre;
            lista.innerHTML = '';

            if (!Array.isArray(parametros) || parametros.length === 0) {
                const li = document.createElement('li');
                li.className = 'list-group-item text-muted';
                li.textContent = 'Esta cuenta no está vinculada a ningún parámetro contable.';
                lista.appendChild(li);
            } else {
                parametros.forEach(function(parametro) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';

                    const clave = document.createElement('span');
                    clave.className = 'font-monospace text-primary fw-semibold';
                    clave.textContent = parametro.clave || '-';

                    const cuenta = document.createElement('span');
                    cuenta.className = 'text-muted small';
                    cuenta.textContent = (parametro.cuenta_codigo || '') + ' - ' + (parametro.cuenta_nombre || '');

                    li.appendChild(clave);
                    li.appendChild(cuenta);
                    lista.appendChild(li);
                });
            }

            bootstrap.Modal.getOrCreateInstance(modalParametrosCuenta).show();
        });
    });
})();
