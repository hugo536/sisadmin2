(function () {
    "use strict";

    // --- 1) GESTIÓN DE ESTADO (SWITCH CON SWEETALERT2) ---
    document.querySelectorAll('.form-cambiar-estado-cuenta').forEach(function (form) {
        const sw = form.querySelector('.switch-estado-cuenta');
        const inputEstado = form.querySelector('input[name="estado"]');
        const row = form.closest('tr');
        const badge = row ? row.querySelector('[data-estado-badge]') : null;

        if (!sw || !inputEstado) return;

        sw.addEventListener('change', function (e) {
            // Detenemos el comportamiento por defecto para manejarlo con la alerta
            e.preventDefault();
            
            const estadoObjetivo = sw.checked ? 1 : 0;
            const activar = (estadoObjetivo === 1);
            const codigo = form.getAttribute('data-codigo') || '';
            const nombre = form.getAttribute('data-nombre') || '';
            const accion = activar ? 'ACTIVAR' : 'INACTIVAR';

            // Revertimos visualmente el switch momentáneamente hasta que confirme
            sw.checked = !activar;

            Swal.fire({
                title: `¿${activar ? 'Activar' : 'Inactivar'} cuenta?`,
                html: `¿Está seguro de cambiar el estado de la cuenta?<br><strong>${codigo} - ${nombre}</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: activar ? '#198754' : '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Si confirma: actualizamos inputs y enviamos
                    inputEstado.value = String(estadoObjetivo);
                    sw.checked = activar; // Ahora sí lo marcamos
                    sw.disabled = true;
                    
                    if (badge) {
                        badge.textContent = activar ? 'Activa' : 'Inactiva';
                        badge.className = activar 
                            ? 'badge rounded-pill bg-success-subtle text-success border border-success-subtle' 
                            : 'badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle';
                    }
                    form.submit();
                } else {
                    // Si cancela: el switch se mantiene en su posición original
                    sw.checked = !activar;
                }
            });
        });
    });

    // --- 2) EDICIÓN DE CUENTAS ---
    const modalEditar = document.getElementById('modalEditarCuenta');
    if (modalEditar) {
        document.querySelectorAll('.btn-editar-cuenta').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const dataMap = {
                    'id': 'data-id',
                    'codigo': 'data-codigo',
                    'nombre': 'data-nombre',
                    'tipo': 'data-tipo',
                    'nivel': 'data-nivel',
                    'permite_movimiento': 'data-permite-movimiento',
                    'estado': 'data-estado'
                };

                for (let key in dataMap) {
                    const input = modalEditar.querySelector(`[name="${key}"]`);
                    if (input) {
                        input.value = this.getAttribute(dataMap[key]);
                    }
                }

                bootstrap.Modal.getOrCreateInstance(modalEditar).show();
            });
        });
    }

    // --- 3) FILTROS DE PARÁMETROS VIGENTES ---
    const buscarParam = document.getElementById('buscarParametroVigente');
    const filtroClave = document.getElementById('filtroClaveParametroVigente');
    const filasParams = document.querySelectorAll('[data-param-row="true"]');
    const filaVacia = document.getElementById('filaSinParametrosFiltrados');

    const filtrarParametros = function () {
        const texto = (buscarParam ? buscarParam.value : '').toLowerCase().trim();
        const clave = (filtroClave ? filtroClave.value : '').toLowerCase().trim();
        let contador = 0;

        filasParams.forEach(fila => {
            const fTexto = (fila.getAttribute('data-search') || '').toLowerCase();
            const fClave = (fila.getAttribute('data-clave') || '').toLowerCase();
            
            const matchTexto = texto === '' || fTexto.includes(texto);
            const matchClave = clave === '' || fClave === clave;

            const visible = matchTexto && matchClave;
            fila.classList.toggle('d-none', !visible);
            if (visible) contador++;
        });

        if (filaVacia) filaVacia.classList.toggle('d-none', contador > 0);
    };

    if (buscarParam) buscarParam.addEventListener('input', filtrarParametros);
    if (filtroClave) filtroClave.addEventListener('change', filtrarParametros);

    // --- 4) EDICIÓN DE PARÁMETROS ---
    const modalParams = document.getElementById('modalParametros');
    if (modalParams) {
        document.querySelectorAll('.btn-editar-parametro').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const claveInput = modalParams.querySelector('#parametroClave');
                const cuentaInput = modalParams.querySelector('#parametroCuenta');
                
                if (claveInput) claveInput.value = this.getAttribute('data-clave') || '';
                if (cuentaInput) cuentaInput.value = this.getAttribute('data-id-cuenta') || '';
                
                const modalListaInstance = bootstrap.Modal.getInstance(document.getElementById('modalParametrosVigentes'));
                if (modalListaInstance) modalListaInstance.hide();
                
                bootstrap.Modal.getOrCreateInstance(modalParams).show();
            });
        });
    }
})();