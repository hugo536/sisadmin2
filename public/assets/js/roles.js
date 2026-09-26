/**
 * public/assets/js/roles.js
 * Gestión de Roles y Permisos (Cliente)
 * V6 - Integración de Master Switch (Todo el módulo) y Cascada
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const TABLE_ID = 'rolesTable';
    const MY_ROLE_ID = (typeof window.MY_ROLE_ID !== 'undefined') ? parseInt(window.MY_ROLE_ID) : 0;
    let isSyncingRoleSwitch = false;

    // =========================================================
    // 1. LÓGICA DE CASCADA Y MASTER SWITCH (TODO EL MÓDULO)
    // =========================================================
    
    // Función A: Verifica si todos los checks hijos están marcados para encender/apagar el Master Switch
    function syncMasterSwitch(childClass) {
        const masterSwitch = document.querySelector(`.switch-master-modulo[data-target-class="${childClass}"]`);
        if (masterSwitch) {
            const allSiblings = document.querySelectorAll(`.${childClass}`);
            // Evalúa que existan siblings y que absolutamente todos estén en "checked = true"
            const allChecked = allSiblings.length > 0 && Array.from(allSiblings).every(chk => chk.checked);
            masterSwitch.checked = allChecked;
        }
    }

    // Función B: Controla la cascada dentro de un módulo (Apagar Permiso VER -> Apaga y bloquea Hijos)
    function updateModuleCascade(masterCheckbox) {
        const container = masterCheckbox.closest('.accordion-body');
        if (!container) return;

        const siblings = container.querySelectorAll('input[type="checkbox"].permiso-check');
        const isChecked = masterCheckbox.checked;
        const isMasterDisabled = masterCheckbox.disabled; 

        siblings.forEach(chk => {
            if (chk === masterCheckbox) return;

            const wrapper = chk.closest('.form-check'); 

            if (isChecked && !isMasterDisabled) {
                chk.disabled = false;
                if(wrapper) wrapper.style.opacity = '1';
            } else {
                chk.disabled = true;
                chk.checked = false; 
                if(wrapper) wrapper.style.opacity = '0.5';
            }
        });

        // Sincronizar master switch global del módulo por si la cascada apagó todo
        const classes = Array.from(masterCheckbox.classList);
        const childClass = classes.find(c => c.startsWith('child-perm-'));
        if (childClass) syncMasterSwitch(childClass);
    }

    // Función C: Controla el bloqueo total según el Switch general del Rol (Inactivar Rol)
    function updateRoleMatrixState(switchRol) {
        const rowMain = switchRol.closest('tr.role-row-main');
        if (!rowMain) return; 
        
        const rolId = rowMain.dataset.roleId;
        const rowDetail = document.querySelector(`tr.role-row-detail[data-detail-for="${rolId}"]`);
        
        if (!rowDetail) return;

        const allCheckboxes = rowDetail.querySelectorAll('input[type="checkbox"].permiso-check');
        const allMasterSwitches = rowDetail.querySelectorAll('input[type="checkbox"].switch-master-modulo');
        const isRoleActive = switchRol.checked;

        // Bloquea/Desbloquea Master Switches
        allMasterSwitches.forEach(master => {
            master.disabled = !isRoleActive;
        });

        allCheckboxes.forEach(chk => {
            const wrapper = chk.closest('.form-check');

            if (isRoleActive) {
                const slug = chk.dataset.slug || '';
                if (slug.endsWith('.ver')) {
                    chk.disabled = false;
                    if(wrapper) wrapper.style.opacity = '1';
                    updateModuleCascade(chk);
                }
            } else {
                chk.disabled = true;
                if(wrapper) wrapper.style.opacity = '0.5';
            }
        });
    }

    let cascadeListenersBound = false;

    // Inicializar listeners de Eventos
    function initCascadeListeners() {
        if (!cascadeListenersBound) {
            document.addEventListener('change', function(e) {
                
                // EVENTO 1: Lógica del Master Switch ("Todo")
                if (e.target.classList.contains('switch-master-modulo')) {
                    const targetClass = e.target.dataset.targetClass;
                    const isChecked = e.target.checked;
                    
                    if (targetClass) {
                        const childCheckboxes = document.querySelectorAll(`.${targetClass}`);
                        
                        // PASO 1: Encender primero los permisos "padre" (.ver) para desbloquear a los hijos
                        childCheckboxes.forEach(chk => {
                            const slug = chk.dataset.slug || '';
                            if (slug.endsWith('.ver') && !chk.disabled && chk.checked !== isChecked) {
                                chk.checked = isChecked;
                                // Disparamos el evento para que active la cascada
                                chk.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                        
                        // PASO 2: Encender el resto de los permisos hijos que ya están desbloqueados
                        childCheckboxes.forEach(chk => {
                            const slug = chk.dataset.slug || '';
                            if (!slug.endsWith('.ver') && !chk.disabled && chk.checked !== isChecked) {
                                chk.checked = isChecked;
                            }
                        });
                    }
                }

                // EVENTO 2: Lógica de checks individuales
                if (e.target.classList.contains('permiso-check')) {
                    const slug = e.target.dataset.slug || '';
                    if (slug.endsWith('.ver')) {
                        updateModuleCascade(e.target);
                    }
                    
                    // Sincronizar hacia arriba: El hijo le avisa al Master Switch
                    const classes = Array.from(e.target.classList);
                    const childClass = classes.find(c => c.startsWith('child-perm-'));
                    if (childClass) syncMasterSwitch(childClass);
                }
                
                // EVENTO 3: Lógica de switch general del Rol
                if (e.target.classList.contains('switch-estado-rol')) {
                    updateRoleMatrixState(e.target);
                }
            });
            cascadeListenersBound = true;
        }

        // Ejecución inicial: Sincroniza cascadas y estados al cargar la vista
        document.querySelectorAll('input[data-slug$=".ver"]').forEach(chk => {
            updateModuleCascade(chk);
        });

        document.querySelectorAll('.switch-estado-rol').forEach(sw => {
            updateRoleMatrixState(sw);
        });

        document.querySelectorAll('.switch-master-modulo').forEach(sw => {
            const targetClass = sw.dataset.targetClass;
            if (targetClass) syncMasterSwitch(targetClass);
        });
    }

    // =========================================================
    // 2. GESTIÓN DE TABLA (Renderizado y Filtros)
    // =========================================================
    const table = document.getElementById(TABLE_ID);

    function initTable() {
        if (!table || typeof ERPTable === 'undefined' || !ERPTable.createTableManager) return;

        const mainRows = Array.from(table.querySelectorAll('.role-row-main'));

        ERPTable.createTableManager({
            tableSelector: table,
            rowsSelector: '.role-row-main',
            searchInput: '#rolesSearch',
            filters: [
                { el: '#filtroEstadoRol', attr: 'data-estado', match: 'equals' }
            ],
            rowsPerPage: 25,
            paginationControls: '#rolesPaginationControls',
            paginationInfo: '#rolesPaginationInfo',
            infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} roles`,
            emptyText: 'No se encontraron roles',
            normalizeSearchText: (value) =>
                (value || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim(),
            onUpdate: () => {
                mainRows.forEach((row) => {
                    const detailRow = row.nextElementSibling;
                    if (detailRow && detailRow.classList.contains('role-row-detail')) {
                        detailRow.style.display = row.style.display === 'none' ? 'none' : '';
                    }
                });
                initCascadeListeners();
            }
        }).init();
    }

    // =========================================================
    // 3. SWITCH DE ESTADO DE ROL (Persistente + Seguridad)
    // =========================================================
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('switch-estado-rol')) return;
        if (isSyncingRoleSwitch) return;

        const checkbox = e.target;
        const rolId = parseInt(checkbox.dataset.id, 10) || 0;
        const estadoNuevo = checkbox.checked ? 1 : 0;
        const estadoAnterior = estadoNuevo === 1 ? 0 : 1;

        // Actualiza visual al instante mientras se confirma
        updateRoleMatrixState(checkbox);

        if (rolId === MY_ROLE_ID) {
            isSyncingRoleSwitch = true;
            checkbox.checked = true;
            updateRoleMatrixState(checkbox);
            isSyncingRoleSwitch = false;

            Swal.fire({
                icon: 'warning',
                title: 'Acción bloqueada',
                text: 'Por seguridad, no puedes desactivar tu propio rol.',
                confirmButtonText: 'OK'
            });
            return;
        }

        Swal.fire({
            title: '¿Cambiar estado del rol?',
            text: estadoNuevo === 1
                ? 'El rol quedará activo.'
                : 'El rol quedará inactivo y se bloqueará su uso.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (!result.isConfirmed) {
                isSyncingRoleSwitch = true;
                checkbox.checked = estadoAnterior === 1;
                updateRoleMatrixState(checkbox);
                isSyncingRoleSwitch = false;
                return;
            }

            const fd = new FormData();
            fd.append('accion', 'toggle');
            fd.append('id', String(rolId));
            fd.append('estado', String(estadoNuevo));

            try {
                await submitAction(fd);
                const parentRow = checkbox.closest('tr.role-row-main');
                if (parentRow) {
                    parentRow.dataset.estado = String(estadoNuevo);
                    const badge = parentRow.querySelector('.badge-status');
                    if (badge) {
                        badge.className = `badge-status ${estadoNuevo === 1 ? 'status-active' : 'status-inactive'}`;
                        badge.textContent = estadoNuevo === 1 ? 'Activo' : 'Inactivo';
                    }
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Actualizado',
                    text: 'Estado del rol guardado correctamente.',
                    confirmButtonText: 'OK'
                });
            } catch (error) {
                isSyncingRoleSwitch = true;
                checkbox.checked = estadoAnterior === 1;
                updateRoleMatrixState(checkbox);
                isSyncingRoleSwitch = false;
                Swal.fire('Error', error.message || 'No se pudo cambiar el estado del rol.', 'error');
            }
        });
    });

    // =========================================================
    // 4. GUARDAR TODO (Permisos + Switch Estado)
    // =========================================================
    document.querySelectorAll('.permiso-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const rolId = parseInt(this.querySelector('input[name="id_rol"]').value);

            if (rolId === MY_ROLE_ID) {
                Swal.fire('Acción Protegida', 'No puedes editar los permisos de tu propio rol.', 'error');
                return;
            }
            
            // Truco: Reactivamos los deshabilitados un milisegundo para que FormData los atrape si estaban checked
            const allDisabled = this.querySelectorAll('input:disabled');
            allDisabled.forEach(i => i.disabled = false);

            const formData = new FormData(this);

            allDisabled.forEach(i => i.disabled = true);

            const parentRow = document.querySelector(`tr.role-row-main[data-role-id="${rolId}"]`);
            if (parentRow) {
                const switchEstado = parentRow.querySelector('.switch-estado-rol');
                if (switchEstado) {
                    formData.append('estado_rol', switchEstado.checked ? 1 : 0);
                }
            }

            Swal.fire({
                title: '¿Guardar cambios?',
                text: "Se actualizarán los permisos y el estado del rol.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitAction(formData, () => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Guardado',
                            text: 'Rol y permisos actualizados.',
                            confirmButtonText: 'OK'
                        });
                        
                        if (parentRow) {
                            const newState = formData.get('estado_rol');
                            parentRow.dataset.estado = newState;
                            const badge = parentRow.querySelector('.badge-status');
                            if (badge) {
                                badge.className = `badge-status ${newState == 1 ? 'status-active' : 'status-inactive'}`;
                                badge.textContent = newState == 1 ? 'Activo' : 'Inactivo';
                            }
                            const switchEl = parentRow.querySelector('.switch-estado-rol');
                            if(switchEl) updateRoleMatrixState(switchEl);
                        }
                    });
                }
            });
        });
    });

    // =========================================================
    // 5. FORMULARIOS SECUNDARIOS (Crear, Editar, Eliminar)
    // =========================================================
    const formCrear = document.getElementById('formCrearRol');
    if (formCrear) {
        formCrear.addEventListener('submit', async function (e) {
            e.preventDefault();

            const confirm = await Swal.fire({
                title: '¿Crear rol?',
                text: 'Se creará el nuevo rol con permisos activos por defecto.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, crear',
                cancelButtonText: 'Cancelar'
            });
            if (!confirm.isConfirmed) return;

            try {
                await submitAction(new FormData(this), () => {
                    document.querySelector('#modalCrearRol .btn-close').click();
                    this.reset();
                    Swal.fire({
                        icon: 'success',
                        title: 'Creado',
                        text: 'Rol creado.',
                        confirmButtonText: 'OK'
                    }).then(() => window.location.reload());
                });
            } catch (error) {
                Swal.fire('Error', error.message || 'No se pudo crear el rol.', 'error');
            }
        });
    }

    const formEditar = document.getElementById('formEditarRol');
    if (formEditar) {
        formEditar.addEventListener('submit', async function (e) {
            e.preventDefault();

            const confirm = await Swal.fire({
                title: '¿Actualizar rol?',
                text: 'Se guardarán los cambios del rol seleccionado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar'
            });
            if (!confirm.isConfirmed) return;

            try {
                await submitAction(new FormData(this), () => {
                    document.querySelector('#modalEditarRol .btn-close').click();
                    Swal.fire({
                        icon: 'success',
                        title: 'Actualizado',
                        text: 'Rol actualizado.',
                        confirmButtonText: 'OK'
                    }).then(() => window.location.reload());
                });
            } catch (error) {
                Swal.fire('Error', error.message || 'No se pudo actualizar el rol.', 'error');
            }
        });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-editar-rol');
        if (btn) {
            document.getElementById('editRolId').value = btn.dataset.id;
            document.getElementById('editRolNombre').value = btn.dataset.nombre;
            document.getElementById('editRolEstado').value = btn.dataset.estado;
            new bootstrap.Modal(document.getElementById('modalEditarRol')).show();
        }
    });

    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('delete-form')) {
            e.preventDefault();
            const rolId = parseInt(new FormData(e.target).get('id'));
            if (rolId === MY_ROLE_ID) {
                Swal.fire('Error', 'No puedes eliminar tu propio rol.', 'error');
                return;
            }
            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Se eliminará este rol de forma irreversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (!result.isConfirmed) return;

                try {
                    const deleted = await submitAction(new FormData(e.target));
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Eliminado!',
                        text: deleted.mensaje || 'Rol eliminado.',
                        confirmButtonText: 'OK'
                    });
                    window.location.reload();
                } catch (error) {
                    Swal.fire('Error', error.message || 'No se pudo eliminar el rol.', 'error');
                }
            });
        }
    });

    async function submitAction(formData, onSuccess) {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.mensaje || 'No se pudo completar la acción.');
        }

        if (typeof onSuccess === 'function') {
            onSuccess(data);
            return data;
        }

        return data;
    }

    initTable();
    initCascadeListeners();
    [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(el => new bootstrap.Tooltip(el));
});