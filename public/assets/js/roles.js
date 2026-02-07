/**
 * public/assets/js/roles.js
 * Gestión de Roles y Permisos (Cliente)
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // =========================================================
    // CONFIGURACIÓN Y CONSTANTES
    // =========================================================
    const BASE_URL = (document.querySelector('base') || {}).href || window.location.origin; // Ajustar según tu <base> tag si existe
    const TABLE_ID = 'rolesTable';
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    // =========================================================
    // 1. GESTIÓN DE TABLA (Buscador + Paginación)
    // =========================================================
    const table = document.getElementById(TABLE_ID);
    const searchInput = document.getElementById('rolesSearch');
    const statusFilter = document.getElementById('filtroEstadoRol');
    const paginationInfo = document.getElementById('rolesPaginationInfo');
    const paginationControls = document.getElementById('rolesPaginationControls');

    function initTable() {
        if (!table) return;

        // Selección robusta: Solo filas principales, ignorando detalles
        const mainRows = Array.from(table.querySelectorAll('.role-row-main'));

        function renderTable() {
            const searchText = (searchInput?.value || '').toLowerCase().trim();
            const statusValue = (statusFilter?.value || '').toString();

            // Filtrado
            const filteredRows = mainRows.filter(row => {
                const searchData = (row.dataset.search || '').toLowerCase();
                const statusData = (row.dataset.estado || '').toString();
                
                const matchText = !searchText || searchData.includes(searchText);
                const matchStatus = !statusValue || statusData === statusValue;

                return matchText && matchStatus;
            });

            // Paginación
            const totalItems = filteredRows.length;
            const totalPages = Math.ceil(totalItems / ROWS_PER_PAGE) || 1;

            if (currentPage > totalPages) currentPage = 1;

            const startIndex = (currentPage - 1) * ROWS_PER_PAGE;
            const endIndex = startIndex + ROWS_PER_PAGE;
            const visibleRows = filteredRows.slice(startIndex, endIndex);

            // Renderizado DOM
            // 1. Ocultar todo
            mainRows.forEach(row => {
                row.style.display = 'none';
                if (row.nextElementSibling && row.nextElementSibling.classList.contains('role-row-detail')) {
                    row.nextElementSibling.style.display = 'none';
                }
            });

            // 2. Mostrar filtrados de la página actual
            visibleRows.forEach(row => {
                row.style.display = '';
                // Si el detalle estaba abierto (clase bootstrap show), o lógica simple: 
                // En este diseño el detalle siempre está en el DOM, solo controlamos visibilidad de la fila TR
                if (row.nextElementSibling && row.nextElementSibling.classList.contains('role-row-detail')) {
                    row.nextElementSibling.style.display = '';
                }
            });

            // 3. Actualizar Info
            if (paginationInfo) {
                paginationInfo.textContent = totalItems > 0 
                    ? `Mostrando ${startIndex + 1} a ${Math.min(endIndex, totalItems)} de ${totalItems} roles`
                    : 'No se encontraron roles';
            }

            // 4. Actualizar Paginador
            renderPaginationControls(totalPages);
        }

        function renderPaginationControls(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';

            if (totalPages <= 1) return;

            // Botón Anterior
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Anterior">&laquo;</a>`;
            prevLi.onclick = (e) => { e.preventDefault(); if(currentPage > 1) { currentPage--; renderTable(); }};
            paginationControls.appendChild(prevLi);

            // Números
            for (let i = 1; i <= totalPages; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                li.onclick = (e) => { e.preventDefault(); currentPage = i; renderTable(); };
                paginationControls.appendChild(li);
            }

            // Botón Siguiente
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Siguiente">&raquo;</a>`;
            nextLi.onclick = (e) => { e.preventDefault(); if(currentPage < totalPages) { currentPage++; renderTable(); }};
            paginationControls.appendChild(nextLi);
        }

        // Listeners
        searchInput?.addEventListener('input', () => { currentPage = 1; renderTable(); });
        statusFilter?.addEventListener('change', () => { currentPage = 1; renderTable(); });

        // Inicializar
        renderTable();
    }

    // =========================================================
    // 2. CREAR ROL (AJAX)
    // =========================================================
    const formCrear = document.getElementById('formCrearRol');
    if (formCrear) {
        formCrear.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            submitAction(formData, () => {
                // Éxito
                const modalEl = document.getElementById('modalCrearRol');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                modalInstance?.hide();
                this.reset();
                Swal.fire('Creado', 'El rol ha sido creado correctamente.', 'success')
                    .then(() => window.location.reload()); // Recargar para ver cambios
            });
        });
    }

    // =========================================================
    // 3. EDITAR ROL (Modal + AJAX)
    // =========================================================
    // Delegación de eventos para botones de editar
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-editar-rol');
        if (btn) {
            const id = btn.dataset.id;
            const nombre = btn.dataset.nombre;
            const estado = btn.dataset.estado;

            document.getElementById('editRolId').value = id;
            document.getElementById('editRolNombre').value = nombre;
            document.getElementById('editRolEstado').value = estado;

            new bootstrap.Modal(document.getElementById('modalEditarRol')).show();
        }
    });

    const formEditar = document.getElementById('formEditarRol');
    if (formEditar) {
        formEditar.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            submitAction(formData, () => {
                const modalEl = document.getElementById('modalEditarRol');
                bootstrap.Modal.getInstance(modalEl)?.hide();
                Swal.fire('Actualizado', 'Rol actualizado correctamente.', 'success')
                    .then(() => window.location.reload());
            });
        });
    }

    // =========================================================
    // 4. TOGGLE ESTADO (Switch)
    // =========================================================
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('switch-estado-rol')) {
            const checkbox = e.target;
            const id = checkbox.dataset.id;
            const newState = checkbox.checked ? 1 : 0;

            const formData = new FormData();
            formData.append('accion', 'toggle');
            formData.append('id', id);
            formData.append('estado', newState);

            // Enviar sin confirmación (acción rápida) o con toast
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    // Actualizar dataset para el filtro
                    const row = checkbox.closest('tr');
                    if(row) row.dataset.estado = newState;
                    
                    // Toast notificación
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    Toast.fire({
                        icon: 'success',
                        title: newState ? 'Rol activado' : 'Rol desactivado'
                    });
                    
                    // Actualizar badge visualmente (opcional, ya que el switch ya cambió)
                    const badge = row.querySelector('.badge-status');
                    if (badge) {
                        badge.className = `badge-status ${newState ? 'status-active' : 'status-inactive'}`;
                        badge.textContent = newState ? 'Activo' : 'Inactivo';
                    }

                } else {
                    checkbox.checked = !checkbox.checked; // Revertir
                    Swal.fire('Error', data.mensaje, 'error');
                }
            })
            .catch(err => {
                checkbox.checked = !checkbox.checked;
                console.error(err);
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });

    // =========================================================
    // 5. GUARDAR PERMISOS (AJAX)
    // =========================================================
    document.querySelectorAll('.permiso-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            
            // Confirmación suave
            Swal.fire({
                title: '¿Guardar cambios?',
                text: "Se actualizarán los permisos para este rol.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(this);
                    
                    submitAction(formData, () => {
                        Swal.fire('Guardado', 'Permisos asignados correctamente.', 'success');
                        // No es necesario recargar, el estado visual ya está en los switches
                    });
                }
            });
        });
    });

    // =========================================================
    // 6. ELIMINAR ROL
    // =========================================================
    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('delete-form')) {
            e.preventDefault();
            const form = e.target;
            
            Swal.fire({
                title: '¿Eliminar rol?',
                text: "Esta acción no se puede deshacer y podría afectar a usuarios asignados.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(form);
                    submitAction(formData, () => {
                        Swal.fire('Eliminado', 'El rol ha sido eliminado.', 'success')
                            .then(() => window.location.reload());
                    });
                }
            });
        }
    });

    // =========================================================
    // UTILS: Helper Fetch
    // =========================================================
    function submitAction(formData, onSuccess) {
        // Mostrar loading si se desea
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.ok) {
                if (onSuccess) onSuccess(data);
            } else {
                Swal.fire('Error', data.mensaje || 'Ocurrió un error desconocido', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error de sistema', 'No se pudo procesar la solicitud. Revise la consola.', 'error');
        });
    }

    // =========================================================
    // INICIALIZACIÓN
    // =========================================================
    initTable();
    
    // Tooltips bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

});