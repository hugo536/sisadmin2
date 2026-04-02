(function () {
    "use strict";

    function initRubrosModal() {
        const form = document.getElementById('formGestionRubro');
        if (!form) return;

        const accion = document.getElementById('rubroAccion');
        const idInput = document.getElementById('rubroId');
        const nombre = document.getElementById('rubroNombre');
        const descripcion = document.getElementById('rubroDescripcion');
        const estado = document.getElementById('rubroEstado');
        const btnGuardar = document.getElementById('btnGuardarRubro');
        const btnReset = document.getElementById('btnResetRubro');

        const resetForm = () => {
            if (accion) accion.value = 'crear_rubro';
            if (idInput) idInput.value = '';
            if (nombre) nombre.value = '';
            if (descripcion) descripcion.value = '';
            if (estado) estado.value = '1';
            // Usamos innerHTML por si decides agregarle iconos al botón de enviar en el futuro
            if (btnGuardar) btnGuardar.innerHTML = 'Guardar Rubro';
        };

        document.querySelectorAll('.btn-editar-rubro').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (accion) accion.value = 'editar_rubro';
                if (idInput) idInput.value = btn.getAttribute('data-id') || '';
                if (nombre) nombre.value = btn.getAttribute('data-nombre') || '';
                if (descripcion) descripcion.value = btn.getAttribute('data-descripcion') || '';
                if (estado) estado.value = btn.getAttribute('data-estado') || '1';
                if (btnGuardar) btnGuardar.innerHTML = 'Actualizar Rubro';
                nombre?.focus();
            });
        });

        btnReset?.addEventListener('click', resetForm);
        document.getElementById('modalGestionRubros')?.addEventListener('show.bs.modal', resetForm);
    }

    function initCategoriasModal() {
        const form = document.getElementById('formGestionCategoria');
        if (!form) return;

        const accion = document.getElementById('categoriaAccion');
        const idInput = document.getElementById('categoriaId');
        const nombre = document.getElementById('categoriaNombre');
        const descripcion = document.getElementById('categoriaDescripcion');
        const estado = document.getElementById('categoriaEstado');
        const btnGuardar = document.getElementById('btnGuardarCategoria');
        const btnReset = document.getElementById('btnResetCategoria');

        const resetForm = () => {
            if (accion) accion.value = 'crear_categoria';
            if (idInput) idInput.value = '';
            if (nombre) nombre.value = '';
            if (descripcion) descripcion.value = '';
            if (estado) estado.value = '1';
            // Usamos innerHTML aquí también
            if (btnGuardar) btnGuardar.innerHTML = 'Guardar Categoría';
        };

        document.querySelectorAll('.btn-editar-categoria').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (accion) accion.value = 'editar_categoria';
                if (idInput) idInput.value = btn.getAttribute('data-id') || '';
                if (nombre) nombre.value = btn.getAttribute('data-nombre') || '';
                if (descripcion) descripcion.value = btn.getAttribute('data-descripcion') || '';
                if (estado) estado.value = btn.getAttribute('data-estado') || '1';
                if (btnGuardar) btnGuardar.innerHTML = 'Actualizar Categoría';
                nombre?.focus();
            });
        });

        btnReset?.addEventListener('click', resetForm);
        document.getElementById('modalGestionCategorias')?.addEventListener('show.bs.modal', resetForm);
    }

    window.ItemsCategoriasRubros = window.ItemsCategoriasRubros || {
        init: function () {
            initRubrosModal();
            initCategoriasModal();
        }
    };
})();