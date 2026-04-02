document.addEventListener('DOMContentLoaded', () => {
    "use strict";

    // --- 1. VISOR DE DOCUMENTOS ---
    const items = document.querySelectorAll('.doc-item');
    const visorContainer = document.getElementById('visorContainer');
    const placeholder = document.getElementById('visorPlaceholder');
    const pdfFrame = document.getElementById('visorPDF');
    const imgVisor = document.getElementById('visorIMG');
    const extVisor = document.getElementById('visorExternal');
    const btnDescarga = document.getElementById('btnDescarga');
    const toolbar = document.getElementById('visorToolbar');
    const toolbarName = document.getElementById('visorFileName');
    const toolbarBtn = document.getElementById('visorBtnOpen');

    items.forEach((item) => {
        item.addEventListener('click', (e) => {
            // Ignorar clics si provienen de botones de acción (editar/eliminar)
            if (e.target.closest('button') || e.target.closest('form')) return;
            e.preventDefault();

            // Limpiar estado activo de todos los items
            items.forEach((i) => i.classList.remove('active', 'bg-white', 'border-start', 'border-primary', 'border-3'));
            
            // Activar el item actual
            item.classList.add('active', 'bg-white', 'border-start', 'border-primary', 'border-3');

            const url = item.dataset.url;
            const ext = item.dataset.type;
            const titleEl = item.querySelector('h6');
            const nombreVisual = titleEl ? titleEl.textContent.trim() : 'Documento';

            // Ocultar todos los visores de forma segura
            placeholder?.classList.add('d-none');
            pdfFrame?.classList.add('d-none');
            imgVisor?.classList.add('d-none');
            extVisor?.classList.add('d-none');

            // Actualizar Toolbar
            if (toolbar && toolbarName && toolbarBtn) {
                toolbar.classList.remove('d-none');
                toolbarName.textContent = nombreVisual;
                toolbarBtn.href = url;
            }

            // Mostrar el visor correspondiente según la extensión
            if (ext === 'pdf' && pdfFrame) {
                pdfFrame.src = url;
                pdfFrame.classList.remove('d-none');
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext) && imgVisor) {
                imgVisor.src = url;
                imgVisor.classList.remove('d-none');
            } else if (extVisor && btnDescarga) {
                btnDescarga.href = url;
                extVisor.classList.remove('d-none');
            }

            // Scroll automático en dispositivos móviles para ver el documento
            if (window.innerWidth < 992 && visorContainer) {
                visorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // --- 2. CONFIGURACIÓN DE TIPOS DE DOCUMENTO ---
    const tipos = [
        { val: 'REG_SANITARIO', text: 'Registro Sanitario' },
        { val: 'FICHA_TECNICA', text: 'Ficha Técnica' },
        { val: 'MSDS', text: 'Seguridad MSDS' },
        { val: 'CERT_CALIDAD', text: 'Certificado de Calidad' },
        { val: 'OTRO', text: 'Otros Documentos' }
    ];

    const selectUpload = document.getElementById('docTipoSelect');
    const selectEdit = document.getElementById('editDocTipo');

    const populateSelect = (targetSelect) => {
        if (!targetSelect) return;
        targetSelect.innerHTML = '<option value="">Seleccione tipo...</option>';
        tipos.forEach((tipo) => {
            const opt = document.createElement('option');
            opt.value = tipo.val;
            opt.textContent = tipo.text;
            targetSelect.appendChild(opt);
        });
    };

    populateSelect(selectUpload);
    populateSelect(selectEdit);

    // --- 3. NAVEGACIÓN POR TABS (URL PARAMS) ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'documentos') {
        const triggerEl = document.querySelector('#docs-tab');
        if (triggerEl) bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    }

    // --- 4. BÚSQUEDA DE DOCUMENTOS ---
    const searchInput = document.getElementById('docSearch');
    if (searchInput) {
        // Se usa 'input' en lugar de 'keyup' para detectar también cuando se pega texto con el ratón
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.doc-item').forEach((doc) => {
                const text = doc.getAttribute('data-search') || '';
                doc.classList.toggle('d-none', !text.includes(term));
            });
        });
    }

    // --- 5. EDICIÓN DE DOCUMENTOS ---
    const modalEditEl = document.getElementById('modalEditarDoc');
    const editIdInput = document.getElementById('editDocId');
    const btnsEdit = document.querySelectorAll('.btn-edit-doc');

    if (modalEditEl && editIdInput && selectEdit) {
        const bsModal = new bootstrap.Modal(modalEditEl);
        
        btnsEdit.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); // Evitar que el clic dispare el visor del documento
                
                const id = btn.getAttribute('data-id');
                const tipo = btn.getAttribute('data-tipo');
                
                editIdInput.value = id;
                
                // Si el tipo actual no existe en el select, lo agregamos temporalmente
                if (tipo && !Array.from(selectEdit.options).some((opt) => opt.value === tipo)) {
                    const opt = document.createElement('option');
                    opt.value = tipo;
                    opt.textContent = tipo;
                    selectEdit.appendChild(opt);
                }
                
                selectEdit.value = tipo;
                bsModal.show();
            });
        });
    }

    // --- 6. ELIMINAR DOCUMENTOS (SWEETALERT2) ---
    document.querySelectorAll('.form-eliminar-doc').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            Swal.fire({
                title: '¿Eliminar archivo?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});