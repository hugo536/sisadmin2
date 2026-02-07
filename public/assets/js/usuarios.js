(function () {
    // --- 1. CONFIGURACIÓN GENERAL ---
    const ROWS_PER_PAGE = 5; 
    let currentPage = 1;

    // Configuración compartida para SweetAlert
    const swalBootstrap = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-primary px-4 fw-bold',
            cancelButton: 'btn btn-outline-secondary px-4 me-2',
            popup: 'rounded-3 shadow-sm'
        },
        buttonsStyling: false
    });

    // Configuración para Toasts
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        customClass: { popup: 'rounded-3 shadow-sm' }
    });

    // --- 2. FUNCIONES DE UTILIDAD UI ---
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // --- 3. GESTOR DE TABLA (FILTROS + PAGINACIÓN) ---
    function initTableManager() {
        const searchInput = document.getElementById('usuarioSearch');
        const filtroRol = document.getElementById('filtroRol');
        const filtroEstado = document.getElementById('filtroEstado');
        const paginationControls = document.getElementById('paginationControls');
        const paginationInfo = document.getElementById('paginationInfo');
        
        const allRows = Array.from(document.querySelectorAll('#usuariosTable tbody tr')); 

        if (allRows.length === 0) return;

        window.updateTable = function() {
            // A. FILTRADO
            const texto = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const rolSeleccionado = (filtroRol ? filtroRol.value : '');
            const estadoSeleccionado = (filtroEstado ? filtroEstado.value : '');

            const visibleRows = allRows.filter(row => {
                const dataSearch = row.getAttribute('data-search') || '';
                const dataRol = row.getAttribute('data-rol') || '';
                const dataEstado = row.getAttribute('data-estado') || '';

                const coincideTexto = dataSearch.includes(texto);
                const coincideRol = rolSeleccionado === '' || dataRol === rolSeleccionado;
                const coincideEstado = estadoSeleccionado === '' || dataEstado === estadoSeleccionado;

                return coincideTexto && coincideRol && coincideEstado;
            });

            // B. CÁLCULOS DE PAGINACIÓN
            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE);

            if (currentPage > totalPages) currentPage = 1;
            if (currentPage < 1) currentPage = 1;
            if (totalPages === 0) currentPage = 1;

            // C. RENDERIZADO
            allRows.forEach(row => row.style.display = 'none');

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;
            
            const rowsToShow = visibleRows.slice(start, end);
            rowsToShow.forEach(row => row.style.display = '');

            // D. ACTUALIZAR UI
            updatePaginationUI(start, end, totalRows, totalPages);
        };

        function updatePaginationUI(start, end, totalRows, totalPages) {
            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const realEnd = Math.min(end, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${realEnd} de ${totalRows} usuarios`;
                }
            }
            if (paginationControls) {
                renderPaginationControls(totalPages);
            }
        }

        function renderPaginationControls(totalPages) {
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return; 

            const createItem = (text, page, isActive = false, isDisabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="return false;">${text}</a>`;
                li.onclick = () => { 
                    if (!isActive && !isDisabled) {
                        currentPage = page; 
                        window.updateTable(); 
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

        const onFilterChange = () => { 
            currentPage = 1; 
            window.updateTable(); 
        };

        if (searchInput) searchInput.addEventListener('input', onFilterChange);
        if (filtroRol) filtroRol.addEventListener('change', onFilterChange);
        if (filtroEstado) filtroEstado.addEventListener('change', onFilterChange);

        window.updateTable();
    }

    // --- 4. LÓGICA DEL SWITCH DE ESTADO ---
    function initStatusSwitch() {
        document.querySelectorAll('.switch-estado').forEach(switchInput => {
            switchInput.addEventListener('change', function() {
                const idUsuario = this.getAttribute('data-id');
                const nuevoEstado = this.checked ? 1 : 0;
                
                // Formulario oculto para enviar POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                // Inputs
                const inputAccion = document.createElement('input');
                inputAccion.name = 'accion';
                inputAccion.value = 'estado';
                
                const inputId = document.createElement('input');
                inputId.name = 'id';
                inputId.value = idUsuario;

                const inputEstado = document.createElement('input');
                inputEstado.name = 'estado';
                inputEstado.value = nuevoEstado;

                form.appendChild(inputAccion);
                form.appendChild(inputId);
                form.appendChild(inputEstado);
                document.body.appendChild(form);

                // Enviamos (la página recargará y mostrará el flash del backend)
                form.submit();
            });
        });
    }

    // --- 5. ELIMINAR Y FLASH MESSAGES ---
    function bindDeleteForms() {
        const forms = document.querySelectorAll('.delete-form');
        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                swalBootstrap.fire({
                    title: '¿Eliminar usuario?',
                    text: 'Esta acción eliminará al usuario del sistema.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    confirmButtonColor: '#ef4444',
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    }

    function handleCreateFlash() {
        // En este diseño, el backend hace el render, así que 
        // el modal se cerrará automáticamente al recargar la página.
        // Si quisieras limpiarlo manualmente:
        // const form = document.getElementById('formCrearUsuario');
        // if(form) form.reset();
    }

    // --- 6. GLOBALES (Aquí agregamos las funciones onclick) ---
    
    // NUEVA FUNCIÓN: Abrir Modal Crear
    window.abrirModalCrear = function() {
        const form = document.getElementById('formCrearUsuario');
        if(form) form.reset(); // Limpia los campos anteriores
        const modal = new bootstrap.Modal(document.getElementById('modalCrearUsuario'));
        modal.show();
    };

    window.editarUsuario = function (id, nombreCompleto, usuario, email, idRol) {
        document.getElementById('editId').value = id;
        document.getElementById('editNombreCompleto').value = nombreCompleto;
        document.getElementById('editUsuario').value = usuario;
        document.getElementById('editEmail').value = email;
        document.getElementById('editRol').value = idRol;
        const passField = document.querySelector('#formEditarUsuario input[name="clave"]');
        if(passField) passField.value = '';
        new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
    };

    // --- INICIALIZACIÓN ---
    document.addEventListener('DOMContentLoaded', function() {
        initTooltips();
        initTableManager(); 
        initStatusSwitch();
        bindDeleteForms();
        handleCreateFlash();
    });

})();