/**
 * public/assets/js/roles.js
 * Gestión de Roles y Permisos (Cliente)
 * V5 - Fix: Error de estilos (Null Style) corregido
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const TABLE_ID = 'rolesTable';
    const ROWS_PER_PAGE = 25;
    let currentPage = 1;
    const MY_ROLE_ID = (typeof window.MY_ROLE_ID !== 'undefined') ? parseInt(window.MY_ROLE_ID) : 0;

    // =========================================================
    // 1. LÓGICA DE CASCADA (VER -> HIJOS) Y BLOQUEO DE ROL
    // =========================================================
    
    // Función A: Controla la cascada dentro de un módulo
    function updateModuleCascade(masterCheckbox) {
        const container = masterCheckbox.closest('.accordion-body');
        if (!container) return;

        const siblings = container.querySelectorAll('input[type="checkbox"].permiso-check');
        const isChecked = masterCheckbox.checked;
        const isMasterDisabled = masterCheckbox.disabled; 

        siblings.forEach(chk => {
            if (chk === masterCheckbox) return;

            // CORRECCIÓN AQUÍ: Usamos .closest('.form-check') en lugar de label
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
    }

    // Función B: Controla el bloqueo total según el Switch del Rol
    function updateRoleMatrixState(switchRol) {
        const rowMain = switchRol.closest('tr.role-row-main');
        if (!rowMain) return; // Validación extra
        
        const rolId = rowMain.dataset.roleId;
        const rowDetail = document.querySelector(`tr.role-row-detail[data-detail-for="${rolId}"]`);
        
        if (!rowDetail) return;

        const allCheckboxes = rowDetail.querySelectorAll('input[type="checkbox"].permiso-check');
        const isRoleActive = switchRol.checked;

        allCheckboxes.forEach(chk => {
            // CORRECCIÓN AQUÍ TAMBIÉN
            const wrapper = chk.closest('.form-check');

            if (isRoleActive) {
                const slug = chk.dataset.slug || '';
                
                if (slug.endsWith('.ver')) {
                    chk.disabled = false;
                    if(wrapper) wrapper.style.opacity = '1';
                    // Disparamos la actualización para sus hijos
                    updateModuleCascade(chk);
                }
            } else {
                // Rol inactivo -> Todo deshabilitado
                chk.disabled = true;
                if(wrapper) wrapper.style.opacity = '0.5';
            }
        });
    }

    // Inicializar listeners de Cascada
    function initCascadeListeners() {
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('permiso-check')) {
                const slug = e.target.dataset.slug || '';
                if (slug.endsWith('.ver')) {
                    updateModuleCascade(e.target);
                }
            }
            if (e.target.classList.contains('switch-estado-rol')) {
                updateRoleMatrixState(e.target);
            }
        });

        // Ejecución inicial
        document.querySelectorAll('input[data-slug$=".ver"]').forEach(chk => {
            updateModuleCascade(chk);
        });

        document.querySelectorAll('.switch-estado-rol').forEach(sw => {
            updateRoleMatrixState(sw);
        });
    }


    // =========================================================
    // 2. GESTIÓN DE TABLA (Renderizado y Filtros)
    // =========================================================
    const table = document.getElementById(TABLE_ID);
    const searchInput = document.getElementById('rolesSearch');
    const statusFilter = document.getElementById('filtroEstadoRol');
    const paginationInfo = document.getElementById('rolesPaginationInfo');
    const paginationControls = document.getElementById('rolesPaginationControls');

    function initTable() {
        if (!table) return;
        const mainRows = Array.from(table.querySelectorAll('.role-row-main'));

        function renderTable() {
            const searchText = (searchInput?.value || '').toLowerCase().trim();
            const statusValue = (statusFilter?.value || '').toString();

            const filteredRows = mainRows.filter(row => {
                const searchData = (row.dataset.search || '').toLowerCase();
                const statusData = (row.dataset.estado || '').toString();
                return (!searchText || searchData.includes(searchText)) &&
                       (!statusValue || statusData === statusValue);
            });

            const totalItems = filteredRows.length;
            const totalPages = Math.ceil(totalItems / ROWS_PER_PAGE) || 1;
            if (currentPage > totalPages) currentPage = 1;
            
            const startIndex = (currentPage - 1) * ROWS_PER_PAGE;
            const endIndex = startIndex + ROWS_PER_PAGE;
            const visibleRows = filteredRows.slice(startIndex, endIndex);

            mainRows.forEach(row => {
                row.style.display = 'none';
                const detailRow = row.nextElementSibling;
                if (detailRow?.classList.contains('role-row-detail')) {
                    detailRow.style.display = 'none';
                }
            });

            visibleRows.forEach(row => {
                row.style.display = '';
                const detailRow = row.nextElementSibling;
                if (detailRow?.classList.contains('role-row-detail')) {
                    detailRow.style.display = '';
                }
            });

            if (paginationInfo) {
                paginationInfo.textContent = totalItems > 0 
                    ? `Mostrando ${startIndex + 1}-${Math.min(endIndex, totalItems)} de ${totalItems} roles`
                    : 'No se encontraron roles';
            }
            renderPaginationControls(totalPages);
            
            setTimeout(() => {
                initCascadeListeners(); 
            }, 50);
        }

        function renderPaginationControls(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return;

            const createLi = (text, page, isActive, isDisabled) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${text}</a>`;
                li.onclick = (e) => { 
                    e.preventDefault(); 
                    if (!isDisabled && !isActive) { currentPage = page; renderTable(); }
                };
                return li;
            };

            paginationControls.appendChild(createLi('Anterior', currentPage - 1, false, currentPage === 1));
            for (let i = 1; i <= totalPages; i++) {
                paginationControls.appendChild(createLi(i, i, i === currentPage, false));
            }
            paginationControls.appendChild(createLi('Siguiente', currentPage + 1, false, currentPage === totalPages));
        }

        searchInput?.addEventListener('input', () => { currentPage = 1; renderTable(); });
        statusFilter?.addEventListener('change', () => { currentPage = 1; renderTable(); });
        renderTable();
    }

    // =========================================================
    // 3. LOGICA ANTISUICIDIO (Switch)
    // =========================================================
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('switch-estado-rol')) {
            const checkbox = e.target;
            const rolId = parseInt(checkbox.dataset.id);

            if (rolId === MY_ROLE_ID) {
                e.preventDefault(); 
                e.stopPropagation();
                // Fix visual + Recálculo de estado matriz
                setTimeout(() => { 
                    checkbox.checked = true; 
                    updateRoleMatrixState(checkbox); 
                }, 10); 

                Swal.fire({
                    icon: 'warning', title: 'Acción Bloqueada',
                    text: 'Por seguridad, no puedes desactivar tu propio rol.',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
        }
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
                        Swal.fire('Guardado', 'Rol y permisos actualizados.', 'success');
                        
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
    // 5. OTROS FORMULARIOS
    // =========================================================
    const formCrear = document.getElementById('formCrearRol');
    if (formCrear) {
        formCrear.addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
                await submitAction(new FormData(this), () => {
                    document.querySelector('#modalCrearRol .btn-close').click();
                    this.reset();
                    Swal.fire('Creado', 'Rol creado.', 'success').then(() => window.location.reload());
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
            try {
                await submitAction(new FormData(this), () => {
                    document.querySelector('#modalEditarRol .btn-close').click();
                    Swal.fire('Actualizado', 'Rol actualizado.', 'success').then(() => window.location.reload());
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
                    await Swal.fire('¡Eliminado!', deleted.mensaje || 'Rol eliminado.', 'success');
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