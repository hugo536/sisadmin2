(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // --- 0. Gestión Modal CREAR (Limpiar form) ---
    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearTercero');
        if (!modalCreate) return;

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearTercero');
            if (form) form.reset();
        });
    }

    // --- 1. Gestión Modal EDITAR (Cargar datos) ---
    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarTercero');
        if (!modalEdit) return;

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const fields = {
                'editTerceroId': 'data-id',
                'editTipoPersona': 'data-tipo-persona',
                'editTipoDoc': 'data-tipo-doc',
                'editNumeroDoc': 'data-numero-doc',
                'editNombre': 'data-nombre',
                'editDireccion': 'data-direccion',
                'editTelefono': 'data-telefono',
                'editEmail': 'data-email',
                'editCondicionPago': 'data-condicion-pago',
                'editDiasCredito': 'data-dias-credito',
                'editLimiteCredito': 'data-limite-credito',
                'editCargo': 'data-cargo',
                'editArea': 'data-area',
                'editFechaIngreso': 'data-fecha-ingreso',
                'editEstadoLaboral': 'data-estado-laboral',
                'editEstado': 'data-estado'
            };

            for (let id in fields) {
                const el = document.getElementById(id);
                if (el) el.value = button.getAttribute(fields[id]) || '';
            }

            // Defaults si vienen vacíos
            if (!document.getElementById('editTipoPersona').value) document.getElementById('editTipoPersona').value = 'NATURAL';
            if (!document.getElementById('editTipoDoc').value) document.getElementById('editTipoDoc').value = 'DNI';
            if (!document.getElementById('editEstado').value) document.getElementById('editEstado').value = '1';

            // Checkboxes
            const checks = {
                'editEsCliente': 'data-es-cliente',
                'editEsProveedor': 'data-es-proveedor',
                'editEsEmpleado': 'data-es-empleado'
            };

            for (let id in checks) {
                const el = document.getElementById(id);
                if (el) el.checked = button.getAttribute(checks[id]) === '1';
            }
        });
    }

    // --- 2. Tabla ---
    function initTableManager() {
        const searchInput = document.getElementById('terceroSearch');
        const filtroRol = document.getElementById('terceroFiltroRol');
        const filtroEstado = document.getElementById('terceroFiltroEstado');
        const paginationControls = document.getElementById('tercerosPaginationControls');
        const paginationInfo = document.getElementById('tercerosPaginationInfo');
        
        const table = document.getElementById('tercerosTable');
        if (!table) return;

        const allRows = Array.from(table.querySelectorAll('tbody tr'));

        const updateTable = function () {
            const texto = (searchInput?.value || '').toLowerCase().trim();
            const rolSeleccionado = (filtroRol ? filtroRol.value : '');
            const estadoSeleccionado = (filtroEstado ? filtroEstado.value : '');

            const visibleRows = allRows.filter(row => {
                const dataSearch = row.getAttribute('data-search') || '';
                const dataRoles = row.getAttribute('data-roles') || '';
                const dataEstado = row.getAttribute('data-estado') || '';

                const coincideTexto = dataSearch.includes(texto);
                const coincideRol = rolSeleccionado === '' || dataRoles.includes(rolSeleccionado);
                const coincideEstado = estadoSeleccionado === '' || dataEstado === estadoSeleccionado;

                return coincideTexto && coincideRol && coincideEstado;
            });

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE) || 1;

            if (currentPage > totalPages) currentPage = 1;
            if (currentPage < 1) currentPage = 1;

            allRows.forEach(row => row.style.display = 'none');

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;
            visibleRows.slice(start, end).forEach(row => row.style.display = '');

            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const realEnd = Math.min(end, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${realEnd} de ${totalRows} terceros`;
                }
            }
            renderPagination(totalPages);
        };

        function renderPagination(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return;

            const createItem = (text, page, isActive = false, isDisabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="return false;">${text}</a>`;
                li.onclick = (e) => {
                    e.preventDefault();
                    if (!isActive && !isDisabled) {
                        currentPage = page;
                        updateTable();
                    }
                };
                return li;
            };

            paginationControls.appendChild(createItem('Anterior', currentPage - 1, false, currentPage === 1));
            for (let i = 1; i <= totalPages; i++) {
                paginationControls.appendChild(createItem(i, i, i === currentPage));
            }
            paginationControls.appendChild(createItem('Siguiente', currentPage + 1, false, currentPage === totalPages));
        }

        if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; updateTable(); });
        if (filtroRol) filtroRol.addEventListener('change', () => { currentPage = 1; updateTable(); });
        if (filtroEstado) filtroEstado.addEventListener('change', () => { currentPage = 1; updateTable(); });

        window.updateTercerosTable = updateTable;
        updateTable();
    }

    // --- 3. Switch Estado (AJAX) ---
    function initStatusSwitch() {
        document.querySelectorAll('.switch-estado-tercero').forEach(switchInput => {
            switchInput.addEventListener('change', function () {
                const terceroId = this.getAttribute('data-id');
                const nuevoEstado = this.checked ? 1 : 0;
                const fila = this.closest('tr');
                const badge = document.getElementById(`badge_status_tercero_${terceroId}`);

                const formData = new FormData();
                formData.append('accion', 'toggle_estado');
                formData.append('id', terceroId);
                formData.append('estado', nuevoEstado);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.ok) throw new Error(data.mensaje);
                        if (fila) fila.setAttribute('data-estado', nuevoEstado);
                        if (badge) {
                            badge.textContent = nuevoEstado === 1 ? 'Activo' : 'Inactivo';
                            badge.className = nuevoEstado === 1 ? 'badge-status status-active' : 'badge-status status-inactive';
                        }
                        if (window.updateTercerosTable) window.updateTercerosTable();
                    })
                    .catch(err => {
                        console.error(err);
                        this.checked = !this.checked; // Revertir
                        // Aquí podrías usar Swal.fire para mostrar error
                    });
            });
        });
    }

    // --- 4. Validación Documento ---
    function initDocumentoValidation() {
        const campos = [
            { tipo: 'crearTipoDoc', numero: 'crearNumeroDoc', excludeId: null },
            { tipo: 'editTipoDoc', numero: 'editNumeroDoc', excludeId: () => document.getElementById('editTerceroId')?.value || null }
        ];

        const validar = (tipoEl, numeroEl, excludeIdVal) => {
            if (!tipoEl || !numeroEl) return;
            const tipo = tipoEl.value;
            const numero = numeroEl.value.trim();
            if (tipo === '' || numero === '') return;

            const formData = new FormData();
            formData.append('accion', 'validar_documento');
            formData.append('tipo_documento', tipo);
            formData.append('numero_documento', numero);
            if (excludeIdVal) formData.append('exclude_id', excludeIdVal);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.existe) {
                        numeroEl.setCustomValidity('El documento ya se encuentra registrado.');
                        numeroEl.classList.add('is-invalid'); // Visual feedback
                    } else {
                        numeroEl.setCustomValidity('');
                        numeroEl.classList.remove('is-invalid');
                    }
                    numeroEl.reportValidity();
                })
                .catch(console.error);
        };

        campos.forEach(c => {
            const tEl = document.getElementById(c.tipo);
            const nEl = document.getElementById(c.numero);
            if(tEl && nEl) {
                const handler = () => validar(tEl, nEl, typeof c.excludeId === 'function' ? c.excludeId() : c.excludeId);
                tEl.addEventListener('change', handler);
                nEl.addEventListener('blur', handler);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initCreateModal();
        initEditModal();
        initTableManager();
        initStatusSwitch();
        initDocumentoValidation();
    });
})();