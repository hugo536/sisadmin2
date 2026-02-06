document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('empresaForm');
    const inputLogo = document.getElementById('inputLogo');
    const previewLogo = document.getElementById('previewLogo');
    const noLogo = document.getElementById('noLogo');

    // 1. Previsualización de Imagen
    if (inputLogo) {
        inputLogo.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // Validar tamaño (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo muy pesado',
                        text: 'El logo no puede superar los 2MB.'
                    });
                    this.value = ''; // Limpiar input
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    previewLogo.src = e.target.result;
                    previewLogo.classList.remove('d-none');
                    if (noLogo) noLogo.classList.add('d-none');
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // 2. Confirmación de Envío
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'question',
                title: '¿Guardar cambios?',
                text: 'Se actualizará la información corporativa.',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd'
            }).then(result => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    }
});