(function() {
    // Solo ejecutar si el usuario ya está logueado (verificamos si existe el menú lateral, por ejemplo)
    if (!document.querySelector('.app-container')) return;

    // Configuración (en milisegundos)
    const SESSION_TIMEOUT = 180 * 60 * 1000; // 3 horas en total (10,800,000 ms)
    const WARNING_TIME = 5 * 60 * 1000;      // Avisar 5 minutos antes (300,000 ms)
    const MAX_IDLE_TIME = SESSION_TIMEOUT - WARNING_TIME; // 2 horas y 55 minutos

    let lastActivity = Date.now();
    let warningModalOpen = false;
    let checkInterval;

    // Función para actualizar el tiempo de actividad de forma local
    const updateActivity = () => {
        lastActivity = Date.now();
    };

    // Escuchamos interacciones del usuario (teclado, mouse, scroll)
    window.addEventListener('mousemove', updateActivity, {passive: true});
    window.addEventListener('keydown', updateActivity, {passive: true});
    window.addEventListener('click', updateActivity, {passive: true});
    window.addEventListener('scroll', updateActivity, {passive: true});

    // Revisar el estado de inactividad cada 1 minuto
    checkInterval = setInterval(() => {
        const idleTime = Date.now() - lastActivity;

        // 1. Si el tiempo excedió las 3 horas completas (el usuario dejó la PC y no respondió)
        if (idleTime >= SESSION_TIMEOUT) {
            clearInterval(checkInterval);
            window.location.href = window.BASE_URL + '?ruta=login/index&error=expired';
            return;
        }

        // 2. Si entró en los últimos 5 minutos, mostramos la advertencia
        if (idleTime >= MAX_IDLE_TIME && !warningModalOpen) {
            warningModalOpen = true;
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Sigues ahí?',
                    text: 'Tu sesión está a punto de expirar por inactividad para proteger tus datos.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0B5ED7',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, continuar trabajando',
                    cancelButtonText: 'Cerrar sesión',
                    allowOutsideClick: false,
                    timer: WARNING_TIME, // Se cierra solo cuando se acaba el tiempo
                    timerProgressBar: true
                }).then((result) => {
                    warningModalOpen = false;
                    
                    if (result.isConfirmed) {
                        // El usuario dio clic en continuar: Hacemos un "ping" a PHP para reiniciar la sesión real
                        fetch(window.BASE_URL + '?ruta=login/renovar_sesion')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    updateActivity(); // Reiniciamos el cronómetro de JS
                                } else {
                                    window.location.href = window.BASE_URL + '?ruta=login/index&error=expired';
                                }
                            })
                            .catch(() => updateActivity()); // Si hay un micro-corte de internet, confiamos en lo local
                    } else if (result.dismiss === Swal.DismissReason.cancel || result.dismiss === Swal.DismissReason.timer) {
                        // Si dio cancelar o se acabó el cronómetro de la alerta
                        window.location.href = window.BASE_URL + '?ruta=login/index&error=expired';
                    }
                });
            }
        }
    }, 60000); // La revisión se hace cada 60 segundos
})();
