(function () {
    function initGestionItemsModal() {
        const { postAction, showError, confirmAction, refreshAtributosSelectores } = window.ItemsShared || {};
        if (!postAction || !showError || !confirmAction || !refreshAtributosSelectores) return;

        const modalEl = document.getElementById('modalGestionItems');
        if (!modalEl) return;

        const editModalEl = document.getElementById('modalEditarAtributo');
        const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
        const gestionModal = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.js-open-gestion-items').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                const trigger = document.getElementById(`${tab}-tab`);
                if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
                gestionModal.show();
            });
        });

        const bindCreateForm = (formId) => {
            const form = document.getElementById(formId);
            if (!form) return;
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = Object.fromEntries(new FormData(form).entries());
                try {
                    await postAction(payload);
                    form.reset();
                    window.location.reload();
                } catch (error) {
                    showError(error.message);
                }
            });
        };

        bindCreateForm('formAgregarMarca');
        bindCreateForm('formAgregarSabor');
        bindCreateForm('formAgregarPresentacion');

        document.querySelectorAll('.js-editar-atributo').forEach((btn) => {
            btn.addEventListener('click', () => {
                const configByTarget = {
                    marca: { accion: 'editar_marca', titulo: 'Editar marca' },
                    sabor: { accion: 'editar_sabor', titulo: 'Editar sabor' },
                    presentacion: { accion: 'editar_presentacion', titulo: 'Editar presentación' }
                };
                const targetConfig = configByTarget[btn.dataset.target || ''] || configByTarget.presentacion;

                document.getElementById('editarAtributoAccion').value = targetConfig.accion;
                document.getElementById('editarAtributoId').value = btn.dataset.id || '';
                document.getElementById('editarAtributoNombre').value = btn.dataset.nombre || '';
                document.getElementById('editarAtributoEstado').checked = (btn.dataset.estado || '1') === '1';
                document.getElementById('tituloEditarAtributo').textContent = targetConfig.titulo;
                editModal?.show();
            });
        });

        document.getElementById('formEditarAtributo')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            const payload = Object.fromEntries(new FormData(form).entries());
            if (!payload.estado) payload.estado = '0';

            try {
                await postAction(payload);
                editModal?.hide();
                await refreshAtributosSelectores();
                window.location.reload();
            } catch (error) {
                showError(error.message);
            }
        });

        document.querySelectorAll('.js-eliminar-atributo').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const confirmed = await confirmAction({ title: '¿Estás seguro?', text: 'Esta acción no se puede deshacer.' });
                if (!confirmed) return;
                try {
                    const result = await postAction({ accion: btn.dataset.accion, id: btn.dataset.id });
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Eliminado!',
                        text: result?.mensaje || 'Registro eliminado correctamente.'
                    });
                    await refreshAtributosSelectores();
                    window.location.reload();
                } catch (error) {
                    showError(error.message);
                }
            });
        });

        document.querySelectorAll('.js-toggle-atributo').forEach((input) => {
            input.addEventListener('change', async () => {
                try {
                    await postAction({
                        accion: input.dataset.accion,
                        id: input.dataset.id,
                        nombre: input.dataset.nombre,
                        estado: input.checked ? '1' : '0'
                    });
                    await refreshAtributosSelectores();
                } catch (error) {
                    input.checked = !input.checked;
                    showError(error.message);
                }
            });
        });

        document.querySelectorAll('.js-swal-confirm').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '0';
                    return;
                }
                event.preventDefault();
                const confirmed = await confirmAction({
                    title: form.dataset.confirmTitle || '¿Confirmar acción?',
                    text: form.dataset.confirmText || 'Esta acción no se puede deshacer.'
                });
                if (!confirmed) return;
                form.dataset.confirmed = '1';
                form.requestSubmit();
            });
        });

        const bindSearch = (inputId, tableId) => {
            const input = document.getElementById(inputId);
            const rows = Array.from(document.querySelectorAll(`#${tableId} tbody tr`));
            if (!input || rows.length === 0) return;
            input.addEventListener('input', () => {
                const term = input.value.toLowerCase().trim();
                rows.forEach((row) => {
                    row.classList.toggle('d-none', !(row.getAttribute('data-search') || '').includes(term));
                });
            });
        };

        bindSearch('buscarMarcas', 'tablaMarcasGestion');
        bindSearch('buscarSabores', 'tablaSaboresGestion');
        bindSearch('buscarPresentaciones', 'tablaPresentacionesGestion');

        modalEl.addEventListener('hidden.bs.modal', async () => {
            try {
                await refreshAtributosSelectores();
            } catch (_) { }
        });
    }

    window.ItemsAtributos = window.ItemsAtributos || {
        init: function () {
            initGestionItemsModal();
        }
    };
})();
