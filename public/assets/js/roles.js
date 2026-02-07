(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    // --- 1. Inicialización de Tooltips ---
    function initTooltips() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    }

    // --- 2. Gestión del Modal CREAR ---
    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearRol');
        if (!modalCreate) return;

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearRol');
            if (form) form.reset();
        });
    }

    // --- 3. Gestión del Modal EDITAR ---
    function initEditModal() {
        // Delegación de eventos para botones dinámicos o estáticos
        document.querySelectorAll('.btn-editar-rol').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('editRolId').value = this.dataset.id;
                document.getElementById('editRolNombre').value = this.dataset.nombre;
                document.getElementById('editRolEstado').value = this.dataset.estado;
                new bootstrap.Modal(document.getElementById('modalEditarRol')).show();
            });
        });
    }

    // --- 4. Gestión de la Tabla (Paginación + Acordeón) ---
    function initTable() {
        const search = document.getElementById('rolesSearch');
        const estado = document.getElementById('filtroEstadoRol');
        
        // Filtramos solo las filas pares (0, 2, 4...) porque las impares son el detalle del acordeón
        const rows = Array.from(document.querySelectorAll('#rolesTable tbody tr')).filter((_, idx) => idx % 2 === 0);
        
        const info = document.getElementById('rolesPaginationInfo');
        const pager = document.getElementById('rolesPaginationControls');

        function render() {
            const txt = (search?.value || '').toLowerCase().trim();
            const st = estado?.value || '';

            const filtered = rows.filter(r => {
                const okTxt = (r.dataset.search || '').includes(txt);
                const okSt = st === '' || (r.dataset.estado || '') === st;
                return okTxt && okSt;
            });

            // Ocultar TODO primero (filas principales y detalles)
            document.querySelectorAll('#rolesTable tbody tr').forEach(r => r.style.display = 'none');

            const total = filtered.length;
            const pages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
            if (currentPage > pages) currentPage = 1;

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;

            // Mostrar solo las paginadas
            filtered.slice(start, end).forEach(r => {
                r.style.display = ''; // Mostrar fila principal
                if (r.nextElementSibling) r.nextElementSibling.style.display = ''; // Mostrar fila del acordeón
            });

            // Actualizar Info y Paginador
            if (info) {
                info.textContent = total === 0 ? 'Sin resultados' : `Mostrando ${start + 1}-${Math.min(end, total)} de ${total} roles`;
            }
            if (pager) {
                pager.innerHTML = '';
                if (pages > 1) {
                    // Botón Anterior
                    const prev = document.createElement('li');
                    prev.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                    prev.innerHTML = `<a class="page-link" href="#">«</a>`;
                    prev.onclick = (e) => { e.preventDefault(); if(currentPage > 1) { currentPage--; render(); }};
                    pager.appendChild(prev);

                    for (let i = 1; i <= pages; i++) {
                        const li = document.createElement('li');
                        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                        li.onclick = (e) => { e.preventDefault(); currentPage = i; render(); };
                        pager.appendChild(li);
                    }
                    
                    // Botón Siguiente
                    const next = document.createElement('li');
                    next.className = `page-item ${currentPage === pages ? 'disabled' : ''}`;
                    next.innerHTML = `<a class="page-link" href="#">»</a>`;
                    next.onclick = (e) => { e.preventDefault(); if(currentPage < pages) { currentPage++; render(); }};
                    pager.appendChild(next);
                }
            }
        }

        search?.addEventListener('input', () => { currentPage = 1; render(); });
        estado?.addEventListener('change', () => { currentPage = 1; render(); });
        
        render();
    }

    // --- 5. Confirmaciones con SweetAlert2 ---
    function bindConfirmations() {
        const confirmAction = (selector, title, text, icon, btnText) => {
            document.querySelectorAll(selector).forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        icon: icon,
                        title: title,
                        text: text,
                        showCancelButton: true,
                        confirmButtonText: btnText,
                        cancelButtonText: 'Cancelar'
                    }).then(r => r.isConfirmed && form.submit());
                });
            });
        };

        confirmAction('.toggle-form', 'Cambiar estado', '¿Deseas cambiar el estado de este rol?', 'question', 'Sí, cambiar');
        confirmAction('.delete-form', 'Eliminar rol', 'Se realizará eliminación lógica del rol.', 'warning', 'Sí, eliminar');
        confirmAction('.permiso-form', 'Guardar permisos', '¿Deseas guardar la configuración de permisos?', 'success', 'Guardar');
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initCreateModal();
        initEditModal();
        initTable();
        bindConfirmations();
    });
})();