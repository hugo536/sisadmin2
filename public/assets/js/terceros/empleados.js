(function () {
    'use strict';

    function edadDesdeFecha(fechaNacimiento) {
        if (!fechaNacimiento) return 0;
        const nacimiento = new Date(`${fechaNacimiento}T00:00:00`);
        const hoy = new Date();
        let edad = hoy.getFullYear() - nacimiento.getFullYear();
        const m = hoy.getMonth() - nacimiento.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
        return edad;
    }

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
            fechaInput.disabled = false;
            fechaInput.required = true;
            wrapper.classList.remove('empleado-input-disabled');
        } else {
            fechaInput.disabled = true;
            fechaInput.required = false;
            fechaInput.value = '';
            wrapper.classList.add('empleado-input-disabled');
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

    function renderRowsHijos(rows) {
        const body = document.getElementById('hijosAsignacionList');
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin registros.</td></tr>';
            return;
        }

        body.innerHTML = rows.map((row) => {
            const edad = edadDesdeFecha(row.fecha_nacimiento || '');
            return `
                <tr>
                    <td>${row.nombre_completo || ''}</td>
                    <td>${row.fecha_nacimiento || ''}</td>
                    <td>${edad}</td>
                    <td>${Number(row.esta_estudiando || 0) === 1 ? 'Sí' : 'No'}</td>
                    <td>${Number(row.discapacidad || 0) === 1 ? 'Sí' : 'No'}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 js-edit-hijo" data-hijo='${JSON.stringify(row)}'><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger js-del-hijo" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
        }).join('');
    }

    async function fetchHijos(idEmpleado, prefix) {
        if (!idEmpleado) return { rows: [], hasMayor: false };
        if (!idEmpleado) return;
        const fd = new FormData();
        fd.append('accion', 'listar_hijos_asignacion');
        fd.append('id_tercero', idEmpleado);

        const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar hijos.');

        const rows = data.data || [];
        renderRowsHijos(rows);

        const alertWrap = document.getElementById(`${prefix}HijosAlertWrapper`);
        if (alertWrap) alertWrap.classList.toggle('d-none', !data.has_mayor_sin_justificar);

        return { rows, hasMayor: !!data.has_mayor_sin_justificar };
        renderRowsHijos(data.data || []);

        const alertWrap = document.getElementById(`${prefix}HijosAlertWrapper`);
        if (alertWrap) alertWrap.classList.toggle('d-none', !data.has_mayor_sin_justificar);
    }

    function resetHijoForm() {
        document.getElementById('hijoAsignacionId').value = '';
        document.getElementById('hijoNombreCompleto').value = '';
        document.getElementById('hijoFechaNacimiento').value = '';
        document.getElementById('hijoEstaEstudiando').checked = false;
        document.getElementById('hijoDiscapacidad').checked = false;
        document.getElementById('formHijoAsignacion')?.classList.add('d-none');
    }

    function setupHijos(prefix) {
        const asignacionSwitch = document.getElementById(`${prefix}AsignacionFamiliar`);
        const gestionWrap = document.getElementById(`${prefix}GestionHijosWrapper`);
        const gestionarBtn = document.getElementById(`${prefix}GestionarHijosBtn`);
        const gestionarLabel = document.getElementById(`${prefix}GestionHijosLabel`);
        const revisarBtn = document.getElementById(`${prefix}RevisarHijosBtn`);
        const emptyWarn = document.getElementById(`${prefix}HijosEmptyWarningWrapper`);
        if (!asignacionSwitch || !gestionWrap) return;

        const getIdEmpleado = () => (prefix === 'edit' ? (document.getElementById('editId')?.value || '') : '');

        const refresh = async () => {
            const idEmpleado = getIdEmpleado();
            const active = asignacionSwitch.checked;
            const canManage = active && !!idEmpleado;

            gestionWrap.classList.toggle('d-none', !active);
            if (gestionarBtn) gestionarBtn.disabled = active && !idEmpleado;
            if (gestionarLabel) {
                gestionarLabel.textContent = idEmpleado
                    ? 'Gestionar hijos para asignación familiar'
                    : 'Guardar tercero para gestionar hijos';
            }

            if (!active) {
                document.getElementById(`${prefix}HijosAlertWrapper`)?.classList.add('d-none');
                emptyWarn?.classList.add('d-none');
                return;
            }

            if (!idEmpleado) {
                emptyWarn?.classList.remove('d-none');
                document.getElementById(`${prefix}HijosAlertWrapper`)?.classList.add('d-none');
                return;
            }

            try {
                const result = await fetchHijos(idEmpleado, prefix);
                emptyWarn?.classList.toggle('d-none', result.rows.length > 0);
            } catch (_) {
                emptyWarn?.classList.add('d-none');
                document.getElementById(`${prefix}HijosAlertWrapper`)?.classList.add('d-none');
            }
        };

        if (!asignacionSwitch.dataset.hijosBound) {
            asignacionSwitch.addEventListener('change', refresh);
            asignacionSwitch.dataset.hijosBound = '1';
        }

        const openModal = async () => {
            const idEmpleado = getIdEmpleado();
            if (!idEmpleado) {
                Swal.fire('Atención', 'Primero guarda el tercero como empleado. Luego podrás registrar hijos.', 'info');
        const revisarBtn = document.getElementById(`${prefix}RevisarHijosBtn`);
        if (!asignacionSwitch || !gestionWrap) return;

        const refresh = () => {
            const idEmpleado = (prefix === 'edit' ? document.getElementById('editId')?.value : '') || '';
            const isVisible = asignacionSwitch.checked && !!idEmpleado;
            gestionWrap.classList.toggle('d-none', !isVisible);

            if (!isVisible) {
                document.getElementById(`${prefix}HijosAlertWrapper`)?.classList.add('d-none');
                return;
            }

            fetchHijos(idEmpleado, prefix).catch(() => {
                document.getElementById(`${prefix}HijosAlertWrapper`)?.classList.add('d-none');
            });
        };

        asignacionSwitch.addEventListener('change', refresh);

        const openModal = async () => {
            const idEmpleado = document.getElementById('editId')?.value || '';
            if (!idEmpleado) {
                Swal.fire('Atención', 'Guarde el tercero como empleado para gestionar hijos.', 'warning');
                return;
            }
            document.getElementById('hijosAsignacionEmpleadoId').value = idEmpleado;
            await fetchHijos(idEmpleado, prefix);
            resetHijoForm();
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHijosAsignacion'));
            modal.show();
        };

        if (gestionarBtn && !gestionarBtn.dataset.hijosBound) {
            gestionarBtn.addEventListener('click', openModal);
            gestionarBtn.dataset.hijosBound = '1';
        }
        if (revisarBtn && !revisarBtn.dataset.hijosBound) {
            revisarBtn.addEventListener('click', openModal);
            revisarBtn.dataset.hijosBound = '1';
        }
        gestionarBtn?.addEventListener('click', openModal);
        revisarBtn?.addEventListener('click', openModal);
        if (!window.__hijosAsignacionBound) {
            window.__hijosAsignacionBound = true;

            document.getElementById('btnMostrarFormHijo')?.addEventListener('click', () => {
                document.getElementById('formHijoAsignacion')?.classList.remove('d-none');
            });
            document.getElementById('btnCancelarFormHijo')?.addEventListener('click', resetHijoForm);

            document.getElementById('hijosAsignacionList')?.addEventListener('click', async (e) => {
                const editBtn = e.target.closest('.js-edit-hijo');
                const delBtn = e.target.closest('.js-del-hijo');
                const idEmpleado = document.getElementById('hijosAsignacionEmpleadoId')?.value || '';
                if (editBtn) {
                    const hijo = JSON.parse(editBtn.dataset.hijo || '{}');
                    document.getElementById('hijoAsignacionId').value = hijo.id || '';
                    document.getElementById('hijoNombreCompleto').value = hijo.nombre_completo || '';
                    document.getElementById('hijoFechaNacimiento').value = hijo.fecha_nacimiento || '';
                    document.getElementById('hijoEstaEstudiando').checked = Number(hijo.esta_estudiando || 0) === 1;
                    document.getElementById('hijoDiscapacidad').checked = Number(hijo.discapacidad || 0) === 1;
                    document.getElementById('formHijoAsignacion')?.classList.remove('d-none');
                    return;
                }

                if (delBtn) {
                    const fd = new FormData();
                    fd.append('accion', 'eliminar_hijo_asignacion');
                    fd.append('id', delBtn.dataset.id || '0');
                    fd.append('id_tercero', idEmpleado);
                    const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    if (!data.ok) {
                        Swal.fire('Error', data.mensaje || 'No se pudo eliminar.', 'error');
                        return;
                    }
                    await fetchHijos(idEmpleado, 'edit');
                }
            });

            document.getElementById('formHijoAsignacion')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const idEmpleado = document.getElementById('hijosAsignacionEmpleadoId')?.value || '';
                const nombre = document.getElementById('hijoNombreCompleto')?.value?.trim() || '';
                const fecha = document.getElementById('hijoFechaNacimiento')?.value || '';
                if (!nombre || !fecha) {
                    Swal.fire('Atención', 'Nombre completo y fecha de nacimiento son obligatorios.', 'warning');
                    return;
                }

                const fd = new FormData();
                fd.append('accion', 'guardar_hijo_asignacion');
                fd.append('id_tercero', idEmpleado);
                fd.append('id', document.getElementById('hijoAsignacionId')?.value || '0');
                fd.append('nombre_completo', nombre);
                fd.append('fecha_nacimiento', fecha);
                fd.append('esta_estudiando', document.getElementById('hijoEstaEstudiando')?.checked ? '1' : '0');
                fd.append('discapacidad', document.getElementById('hijoDiscapacidad')?.checked ? '1' : '0');

                const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (!data.ok) {
                    Swal.fire('Error', data.mensaje || 'No se pudo guardar.', 'error');
                    return;
                }
                resetHijoForm();
                await fetchHijos(idEmpleado, 'edit');
            });
        }

        refresh();
    }

    function lockFechaIngreso(prefix) {
        const input = document.getElementById(`${prefix}FechaIngreso`);
        if (!input) return;

        if (prefix === 'crear') {
            input.readOnly = false;
            input.classList.remove('bg-light');
            return;
        }

        const editModal = document.getElementById('modalEditarTercero');
        if (!editModal) return;
        const trigger = editModal.__currentTriggerButton;
        const bloqueado = trigger && Number(trigger.dataset.empleadoRegistrado || 0) === 1;

        input.readOnly = !!bloqueado;
        input.classList.toggle('bg-light', !!bloqueado);
        input.title = bloqueado ? 'La fecha de ingreso no puede editarse una vez creado como empleado.' : '';
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

            setupHijos(prefix);
            lockFechaIngreso(prefix);
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
            setupHijos(prefix);
            lockFechaIngreso(prefix);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.TercerosEmpleados.init('crear');
        window.TercerosEmpleados.init('edit');
    });
})();
