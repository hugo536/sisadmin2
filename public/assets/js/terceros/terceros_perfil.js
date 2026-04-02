document.addEventListener('DOMContentLoaded', function() {
    
    // =========================================================
    // 1. VARIABLES Y ELEMENTOS DEL DOM
    // =========================================================
    const items = document.querySelectorAll('.doc-item');
    
    // Elementos del Visor
    const visorContainer = document.getElementById('visorContainer');
    const placeholder = document.getElementById('visorPlaceholder');
    const pdfFrame = document.getElementById('visorPDF');
    const imgVisor = document.getElementById('visorIMG');
    const extVisor = document.getElementById('visorExternal');
    const btnDescarga = document.getElementById('btnDescarga');
    
    // Barra de Herramientas (Para Móvil)
    const toolbar = document.getElementById('visorToolbar'); // Asegúrate de que este ID exista en tu HTML si usaste la versión "Compatible con Móvil"
    const toolbarName = document.getElementById('visorFileName');
    const toolbarBtn = document.getElementById('visorBtnOpen');

    // =========================================================
    // 2. LÓGICA DEL VISOR DE DOCUMENTOS
    // =========================================================
    items.forEach(item => {
        item.addEventListener('click', function(e) {
            // Ignorar si el clic fue en un botón de acción (editar/borrar) o dentro de un formulario
            if(e.target.closest('button') || e.target.closest('form')) return;
            
            e.preventDefault();
            
            // A) Resaltar item activo visualmente
            items.forEach(i => i.classList.remove('active', 'bg-white', 'border-start', 'border-primary', 'border-3'));
            this.classList.add('active', 'bg-white', 'border-start', 'border-primary', 'border-3');

            // B) Obtener datos del archivo
            const url = this.dataset.url;
            const ext = this.dataset.type;
            // Intentamos obtener el nombre visual del documento (titulo h6)
            const nombreVisual = this.querySelector('h6') ? this.querySelector('h6').textContent.trim() : 'Documento';

            // C) Resetear vista (ocultar todo)
            placeholder.classList.add('d-none');
            pdfFrame.classList.add('d-none');
            imgVisor.classList.add('d-none');
            extVisor.classList.add('d-none');
            
            // D) Configurar Toolbar (si existe en el HTML)
            if (toolbar && toolbarName && toolbarBtn) {
                toolbar.classList.remove('d-none');
                toolbarName.textContent = nombreVisual;
                toolbarBtn.href = url;
            }

            // E) Mostrar visor según tipo de archivo
            if (ext === 'pdf') {
                pdfFrame.src = url;
                pdfFrame.classList.remove('d-none');
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                imgVisor.src = url;
                imgVisor.classList.remove('d-none');
            } else {
                // Word, Excel, ZIP, etc. -> Mostrar botón descarga
                btnDescarga.href = url;
                extVisor.classList.remove('d-none');
            }

            // F) Scroll automático en móviles (UX)
            // Si la pantalla es pequeña, bajamos automáticamente al visor para que el usuario vea el archivo
            if (window.innerWidth < 992 && visorContainer) {
                visorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // =========================================================
    // 3. SELECTS DINÁMICOS (Tipo de Documento según Rol)
    // =========================================================
    const roles = window.perfilRoles || { empleado: 0, proveedor: 0, cliente: 0 };

    const opcionesDocumentos = {
        'EMPLEADO': [
            {val: 'CV', text: 'Curriculum Vitae'},
            {val: 'CONTRATO_LABORAL', text: 'Contrato de Trabajo'},
            {val: 'DNI', text: 'Copia de DNI'},
            {val: 'ANTECEDENTES', text: 'Antecedentes Policiales'},
            {val: 'BOLETA', text: 'Boleta de Pago'},
            {val: 'TITULO', text: 'Título Profesional'}
        ],
        'PROVEEDOR': [
            {val: 'COTIZACION', text: 'Cotización'},
            {val: 'FICHA_RUC', text: 'Ficha RUC'},
            {val: 'CATALOGO', text: 'Catálogo Productos'},
            {val: 'CONTRATO_COM', text: 'Contrato Comercial'},
            {val: 'CONSTANCIA_BANCARIA', text: 'Constancia Bancaria'}
        ],
        'CLIENTE': [
            {val: 'ORDEN_COMPRA', text: 'Orden de Compra (OC)'},
            {val: 'CONTRATO_SERV', text: 'Contrato Servicio'},
            {val: 'CONFORMIDAD', text: 'Acta Conformidad'},
            {val: 'EVAL_CREDITO', text: 'Evaluación Crediticia'}
        ],
        'GENERAL': [
            {val: 'OTRO', text: 'Otros Documentos'}
        ]
    };

    const selectUpload = document.getElementById('docTipoSelect'); // Select del formulario subir
    const selectEdit = document.getElementById('editDocTipo');     // Select del modal editar
    
    function populateSelect(targetSelect) {
        if (!targetSelect) return;
        targetSelect.innerHTML = '<option value="">Seleccione tipo...</option>';
        
        function addGroup(label, items) {
            const group = document.createElement('optgroup');
            group.label = label;
            items.forEach(i => {
                const opt = document.createElement('option');
                opt.value = i.val;
                opt.textContent = i.text;
                group.appendChild(opt);
            });
            targetSelect.appendChild(group);
        }

        if (roles.empleado) addGroup('Laboral', opcionesDocumentos.EMPLEADO);
        if (roles.proveedor) addGroup('Proveedor', opcionesDocumentos.PROVEEDOR);
        if (roles.cliente) addGroup('Cliente', opcionesDocumentos.CLIENTE);
        addGroup('General', opcionesDocumentos.GENERAL);
    }

    // Llenar ambos selects al cargar
    populateSelect(selectUpload);
    populateSelect(selectEdit);

    // =========================================================
    // 4. FUNCIONALIDAD DE LA URL (Activar pestaña)
    // =========================================================
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'documentos') {
        const triggerEl = document.querySelector('#docs-tab');
        if(triggerEl) bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    }

    // =========================================================
    // 5. BUSCADOR EN TIEMPO REAL
    // =========================================================
    const searchInput = document.getElementById('docSearch');
    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase().trim();
            const docs = document.querySelectorAll('.doc-item');
            
            docs.forEach(doc => {
                // Buscamos en el atributo data-search que contiene todo el texto relevante
                const text = doc.getAttribute('data-search') || '';
                if(text.includes(term)) {
                    doc.classList.remove('d-none');
                } else {
                    doc.classList.add('d-none');
                }
            });
        });
    }

    // =========================================================
    // 6. MODAL DE EDICIÓN
    // =========================================================
    const modalEditEl = document.getElementById('modalEditarDoc');
    const editIdInput = document.getElementById('editDocId');
    const editObsInput = document.getElementById('editDocObs');
    const btnsEdit = document.querySelectorAll('.btn-edit-doc');

    if(modalEditEl && editIdInput && selectEdit) {
        const bsModal = new bootstrap.Modal(modalEditEl);
        
        btnsEdit.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation(); // Evita abrir el visor al hacer clic en editar

                const id = this.getAttribute('data-id');
                const tipo = this.getAttribute('data-tipo');
                const obs = this.getAttribute('data-obs');

                editIdInput.value = id;
                if (tipo && !Array.from(selectEdit.options).some(option => option.value === tipo)) {
                    const fallbackOption = document.createElement('option');
                    fallbackOption.value = tipo;
                    fallbackOption.textContent = tipo;
                    selectEdit.appendChild(fallbackOption);
                }
                selectEdit.value = tipo;
                editObsInput.value = obs;

                bsModal.show();
            });
        });
    }

    // =========================================================
    // 7. SWEETALERT2 PARA ELIMINAR
    // =========================================================
    const formsEliminar = document.querySelectorAll('.form-eliminar-doc');
    formsEliminar.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Detener envío automático
            
            Swal.fire({
                title: '¿Eliminar archivo?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Rojo
                cancelButtonColor: '#6c757d', // Gris
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Enviar formulario si confirma
                }
            });
        });
    });

});
