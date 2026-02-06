(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('empresaForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'question',
                title: 'Guardar configuración',
                text: '¿Deseas guardar los cambios de empresa?',
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        });
    });
})();
