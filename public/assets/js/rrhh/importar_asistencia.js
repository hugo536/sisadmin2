(function () {
    'use strict';

    // Validación para evitar que el script se inicialice múltiples veces 
    // en la navegación del SPA si no se limpia el caché del JS
    if (window._importarAsistenciaJS_Loaded) return;
    window._importarAsistenciaJS_Loaded = true;

    // EVENTOS SUBMIT (Delegación al document)
    document.addEventListener('submit', function (e) {
        
        // 1. Confirmación de Sincronización
        if (e.target && e.target.id === 'formSincronizar') {
            e.preventDefault(); // Detenemos el envío
            const form = e.target;
            const pendientes = form.getAttribute('data-pendientes');

            Swal.fire({
                title: '¿Sincronizar marcas?',
                text: `Se procesarán y enviarán ${pendientes} registros pendientes al panel semanal.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Sí, sincronizar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Sincronizando registros, por favor espera.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit(); // Disparamos el formulario si acepta
                }
            });
        }

        // 2. Confirmación para Descartar Huérfanos
        if (e.target && e.target.id === 'formDescartar') {
            e.preventDefault(); 
            const form = e.target;

            Swal.fire({
                title: '¿Limpiar marcas huérfanas?',
                text: 'Se marcarán como procesadas todas las marcas que NO tengan un empleado asignado (ej. ex-trabajadores). Dejarán de aparecer como pendientes.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Color rojo danger
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Sí, limpiar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Limpiando...',
                        text: 'Descartando registros sin empleado, por favor espera.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit(); 
                }
            });
        }

        // 3. Al subir el archivo TXT (Loader de espera)
        if (e.target && e.target.querySelector('input[type="file"]#archivoTxtBiometrico')) {
            const inputTxt = document.getElementById('archivoTxtBiometrico');
            if (inputTxt && inputTxt.files.length > 0) {
                Swal.fire({
                    title: 'Subiendo archivo...',
                    text: 'Extrayendo marcas del biométrico.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        }
    });

})();
