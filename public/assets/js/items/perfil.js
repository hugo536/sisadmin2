document.addEventListener('DOMContentLoaded', function () {
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
        item.addEventListener('click', function (e) {
            if (e.target.closest('button') || e.target.closest('form')) return;
            e.preventDefault();

            items.forEach((i) => i.classList.remove('active', 'bg-white', 'border-start', 'border-primary', 'border-3'));
            this.classList.add('active', 'bg-white', 'border-start', 'border-primary', 'border-3');

            const url = this.dataset.url;
            const ext = this.dataset.type;
            const nombreVisual = this.querySelector('h6') ? this.querySelector('h6').textContent.trim() : 'Documento';

            placeholder.classList.add('d-none');
            pdfFrame.classList.add('d-none');
            imgVisor.classList.add('d-none');
            extVisor.classList.add('d-none');

            if (toolbar && toolbarName && toolbarBtn) {
                toolbar.classList.remove('d-none');
                toolbarName.textContent = nombreVisual;
                toolbarBtn.href = url;
            }

            if (ext === 'pdf') {
                pdfFrame.src = url;
                pdfFrame.classList.remove('d-none');
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                imgVisor.src = url;
                imgVisor.classList.remove('d-none');
            } else {
                btnDescarga.href = url;
                extVisor.classList.remove('d-none');
            }

            if (window.innerWidth < 992 && visorContainer) {
                visorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    const tipos = [
        { val: 'REG_SANITARIO', text: 'Registro Sanitario' },
        { val: 'FICHA_TECNICA', text: 'Ficha Técnica' },
        { val: 'MSDS', text: 'Seguridad MSDS' },
        { val: 'CERT_CALIDAD', text: 'Certificado de Calidad' },
        { val: 'OTRO', text: 'Otros Documentos' },
    ];

    const selectUpload = document.getElementById('docTipoSelect');
    const selectEdit = document.getElementById('editDocTipo');

    function populateSelect(targetSelect) {
        if (!targetSelect) return;
        targetSelect.innerHTML = '<option value="">Seleccione tipo...</option>';
        tipos.forEach((tipo) => {
            const opt = document.createElement('option');
            opt.value = tipo.val;
            opt.textContent = tipo.text;
            targetSelect.appendChild(opt);
        });
    }

    populateSelect(selectUpload);
    populateSelect(selectEdit);

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'documentos') {
        const triggerEl = document.querySelector('#docs-tab');
        if (triggerEl) bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    }

    const searchInput = document.getElementById('docSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.doc-item').forEach((doc) => {
                const text = doc.getAttribute('data-search') || '';
                doc.classList.toggle('d-none', !text.includes(term));
            });
        });
    }

    const modalEditEl = document.getElementById('modalEditarDoc');
    const editIdInput = document.getElementById('editDocId');
    const btnsEdit = document.querySelectorAll('.btn-edit-doc');

    if (modalEditEl && editIdInput && selectEdit) {
        const bsModal = new bootstrap.Modal(modalEditEl);
        btnsEdit.forEach((btn) => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const id = this.getAttribute('data-id');
                const tipo = this.getAttribute('data-tipo');
                editIdInput.value = id;
                if (tipo && !Array.from(selectEdit.options).some((option) => option.value === tipo)) {
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

    document.querySelectorAll('.form-eliminar-doc').forEach((form) => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar archivo?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
