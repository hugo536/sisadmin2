(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    function initTooltips() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    }

    function initTable() {
        const search = document.getElementById('rolesSearch');
        const estado = document.getElementById('filtroEstadoRol');
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

            rows.forEach(r => {
                r.style.display = 'none';
                if (r.nextElementSibling) r.nextElementSibling.style.display = 'none';
            });

            const total = filtered.length;
            const pages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
            if (currentPage > pages) currentPage = 1;
            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;
            filtered.slice(start, end).forEach(r => {
                r.style.display = '';
                if (r.nextElementSibling) r.nextElementSibling.style.display = '';
            });

            if (info) {
                info.textContent = total === 0 ? 'Sin resultados' : `Mostrando ${start + 1}-${Math.min(end, total)} de ${total} roles`;
            }
            if (pager) {
                pager.innerHTML = '';
                if (pages > 1) {
                    for (let i = 1; i <= pages; i++) {
                        const li = document.createElement('li');
                        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                        li.onclick = (e) => { e.preventDefault(); currentPage = i; render(); };
                        pager.appendChild(li);
                    }
                }
            }
        }

        search?.addEventListener('input', () => { currentPage = 1; render(); });
        estado?.addEventListener('change', () => { currentPage = 1; render(); });
        render();
    }

    function bindConfirmations() {
        document.querySelectorAll('.toggle-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({ icon: 'question', title: 'Cambiar estado', text: '¿Deseas cambiar el estado de este rol?', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' })
                    .then(r => r.isConfirmed && form.submit());
            });
        });
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Eliminar rol', text: 'Se realizará eliminación lógica del rol.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' })
                    .then(r => r.isConfirmed && form.submit());
            });
        });
        document.querySelectorAll('.permiso-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({ icon: 'success', title: 'Guardar permisos', text: '¿Deseas guardar la configuración de permisos?', showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar' })
                    .then(r => r.isConfirmed && form.submit());
            });
        });
    }

    function bindEditModal() {
        document.querySelectorAll('.btn-editar-rol').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('editRolId').value = this.dataset.id;
                document.getElementById('editRolNombre').value = this.dataset.nombre;
                document.getElementById('editRolEstado').value = this.dataset.estado;
                new bootstrap.Modal(document.getElementById('modalEditarRol')).show();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initTable();
        bindConfirmations();
        bindEditModal();
    });
})();
