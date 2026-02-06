(function () {
    // --- 1. CONFIGURACIÓN GENERAL ---
    const ROWS_PER_PAGE = 5; // <--- CÁMBIALO AQUÍ SI QUIERES MÁS FILAS
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
    // Esta función encapsula toda la lógica de visualización de datos
    function initTableManager() {
        const searchInput = document.getElementById('usuarioSearch');
        const filtroRol = document.getElementById('filtroRol');
        const filtroEstado = document.getElementById('filtroEstado');
        const paginationControls = document.getElementById('paginationControls');
        const paginationInfo = document.getElementById('paginationInfo');
        
        // Guardamos TODAS las filas originales al cargar la página
        const allRows = Array.from(document.querySelectorAll('#usuariosTable tbody tr')); 

        // Si no hay tabla, no hacemos nada
        if (allRows.length === 0) return;

        // --- FUNCIÓN PRINCIPAL: FILTRA Y PAGINA ---
        window.updateTable = function() { // La hacemos accesible globalmente por si el switch la necesita
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

            // Ajuste de seguridad para la página actual
            if (currentPage > totalPages) currentPage = 1;
            if (currentPage < 1) currentPage = 1;
            if (totalPages === 0) currentPage = 1;

            // C. RENDERIZADO (OCULTAR/MOSTRAR)
            // 1. Ocultar todo primero
            allRows.forEach(row => row.style.display = 'none');

            // 2. Calcular rango a mostrar
            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;
            
            // 3. Mostrar solo el slice correspondiente
            const rowsToShow = visibleRows.slice(start, end);
            rowsToShow.forEach(row => row.style.display = '');

            // D. ACTUALIZAR UI DE CONTROLES
            updatePaginationUI(start, end, totalRows, totalPages);
        };

        // --- FUNCIONES AUXILIARES DE UI ---
        function updatePaginationUI(start, end, totalRows, totalPages) {
            // Texto informativo
            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const realEnd = Math.min(end, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${realEnd} de ${totalRows} usuarios`;
                }
            }

            // Botones de paginación
            if (paginationControls) {
                renderPaginationControls(totalPages);
            }
        }

        function renderPaginationControls(totalPages) {
            paginationControls.innerHTML = '';
            
            if (totalPages <= 1) return; // No mostrar si solo hay 1 página

            // Función helper para crear items
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

            // Botón Anterior
            paginationControls.appendChild(createItem('Anterior', currentPage - 1, false, currentPage === 1));

            // Botones Numéricos
            for (let i = 1; i <= totalPages; i++) {
                paginationControls.appendChild(createItem(i, i, i === currentPage));
            }

            // Botón Siguiente
            paginationControls.appendChild(createItem('Siguiente', currentPage + 1, false, currentPage === totalPages));
        }

        // --- LISTENERS DE FILTROS ---
        const onFilterChange = () => { 
            currentPage = 1; // Resetear a página 1 al filtrar
            window.updateTable(); 
        };

        if (searchInput) searchInput.addEventListener('input', onFilterChange);
        if (filtroRol) filtroRol.addEventListener('change', onFilterChange);
        if (filtroEstado) filtroEstado.addEventListener('change', onFilterChange);

        // Inicializar tabla
        window.updateTable();
    }

    // --- 4. LÓGICA DEL SWITCH DE ESTADO ---
    function initStatusSwitch() {
        document.querySelectorAll('.switch-estado').forEach(switchInput => {
            switchInput.addEventListener('change', function() {
                const idUsuario = this.getAttribute('data-id');
                const nuevoEstado = this.checked ? 1 : 0;
                
                // Referencias DOM
                const fila = this.closest('tr');
                const label = this.nextElementSibling;
                const badge = document.getElementById(`badge_status_${idUsuario}`);

                // A) Actualizar atributos y texto visual
                if (fila) fila.setAttribute('data-estado', nuevoEstado);
                if (label) label.textContent = nuevoEstado === 1 ? 'Activo' : 'Inactivo';

                // B) Actualizar Badge
                if (badge) {
                    if (nuevoEstado === 1) {
                        badge.textContent = 'Activo';
                        badge.className = 'badge-status status-active';
                    } else {
                        badge.textContent = 'Inactivo';
                        badge.className = 'badge-status status-inactive';
                    }
                }

                // C) Feedback
                Toast.fire({ icon: 'success', title: `Usuario ${nuevoEstado === 1 ? 'activado' : 'desactivado'}` });

                // D) RE-RENDERIZAR TABLA
                // Esto es vital: si tienes un filtro "Activos" y desactivas uno,
                // debe desaparecer de la lista inmediatamente.
                if (window.updateTable) window.updateTable();

                // E) AQUÍ FETCH AL BACKEND...
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
                    text: 'Esta acción desactivará el usuario permanentemente.',
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
        if (!window.USUARIOS_FLASH || window.USUARIOS_FLASH.tipo !== 'success' || window.USUARIOS_FLASH.accion !== 'crear') return;

        const collapseElement = document.getElementById('crearUsuarioCollapse');
        const createForm = document.getElementById('formCrearUsuario');

        if (createForm) createForm.reset();
        if (collapseElement) {
            const collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });
            collapseInstance.hide();
        }
    }

    // --- 6. GLOBALES (Para onclick HTML) ---
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
        initTableManager(); // Aquí arranca la tabla, los filtros y la paginación
        initStatusSwitch();
        bindDeleteForms();
        handleCreateFlash();
    });

})();