document.addEventListener('DOMContentLoaded', function() {
    
    // --- LLEGADA TEMPRANO ---
    const checkTemprano = document.getElementById('checkTemprano');
    const txtEjemploTemprano = document.getElementById('txtEjemploTemprano');
    
    if (checkTemprano && txtEjemploTemprano) {
        checkTemprano.addEventListener('change', function() {
            if (this.checked) {
                txtEjemploTemprano.textContent = '07:45 AM';
                txtEjemploTemprano.classList.replace('bg-secondary', 'bg-primary');
            } else {
                txtEjemploTemprano.textContent = '08:00 AM';
                txtEjemploTemprano.classList.replace('bg-primary', 'bg-secondary');
            }
        });
    }

    // --- HORAS EXTRAS (GENERAL) ---
    const checkTarde = document.getElementById('checkTarde');
    const cajaConfigExtras = document.getElementById('cajaConfigExtras');
    const selectTipoCalculo = document.getElementById('selectTipoCalculo');
    
    // Nuevos inputs de Bloques Escalonados
    const umbralMedia = document.getElementById('umbralMedia');
    const umbralHora = document.getElementById('umbralHora');
    
    if (checkTarde && cajaConfigExtras) {
        checkTarde.addEventListener('change', function() {
            if (this.checked) {
                // Encender la sección
                cajaConfigExtras.classList.remove('opacity-50');
                if(selectTipoCalculo) selectTipoCalculo.removeAttribute('disabled');
                if(umbralMedia) umbralMedia.removeAttribute('readonly');
                if(umbralHora) umbralHora.removeAttribute('readonly');
            } else {
                // Apagar la sección
                cajaConfigExtras.classList.add('opacity-50');
                if(selectTipoCalculo) selectTipoCalculo.setAttribute('disabled', 'disabled');
                if(umbralMedia) umbralMedia.setAttribute('readonly', 'readonly');
                if(umbralHora) umbralHora.setAttribute('readonly', 'readonly');
            }
        });
    }

    // --- LÓGICA DE BLOQUES ESCALONADOS (MUESTRA Y OCULTA) ---
    const cajaBloques = document.getElementById('cajaBloques');
    const textoEjemploBloques = document.getElementById('textoEjemploBloques');

    // Función para que el texto cambie mientras escribes los números
    function actualizarEjemploBloques() {
        if (!umbralMedia || !umbralHora || !textoEjemploBloques) return;
        
        let uMedia = parseInt(umbralMedia.value) || 0;
        let uHora = parseInt(umbralHora.value) || 0;
        
        // Prevenir números negativos en el texto
        let noPaga = uMedia - 1 < 0 ? 0 : uMedia - 1;

        textoEjemploBloques.innerHTML = `
            <i class="bi bi-calculator text-primary me-2 fs-5"></i><strong>¿Cómo se calculará el tiempo extra?</strong><br>
            <ul class="mb-0 mt-2 ps-4 text-muted">
                <li>Si se queda entre <span class="text-danger fw-bold">0 y ${noPaga}</span> min &rarr; Cobra <span class="text-danger fw-bold">0 min</span>.</li>
                <li>Si se queda entre <span class="text-warning-emphasis fw-bold">${uMedia} y ${uHora - 1}</span> min &rarr; Cobra <span class="text-success fw-bold">30 min (Media hora)</span>.</li>
                <li>Si se queda <span class="text-success fw-bold">${uHora}</span> min o más &rarr; Cobra <span class="text-success fw-bold">60 min (1 Hora)</span>.</li>
            </ul>
            <div class="mt-2 text-primary" style="font-size: 0.8rem;"><em>Nota: Después de 60 minutos, la regla se repite cíclicamente (Ej. 1h y ${uMedia}m = 1.5 horas extra).</em></div>
        `;
    }

    // Mostrar/Ocultar los bloques si elige "BLOQUES" o "EXACTO"
    if (selectTipoCalculo && cajaBloques) {
        selectTipoCalculo.addEventListener('change', function() {
            if (this.value === 'BLOQUES') {
                cajaBloques.classList.remove('d-none');
                actualizarEjemploBloques();
            } else {
                cajaBloques.classList.add('d-none');
            }
        });
    }

    // Escuchar cuando el usuario teclea en los inputs numéricos para actualizar el texto en vivo
    if (umbralMedia && umbralHora) {
        umbralMedia.addEventListener('input', actualizarEjemploBloques);
        umbralHora.addEventListener('input', actualizarEjemploBloques);
    }

    // Inicializar el texto al cargar la página
    actualizarEjemploBloques();

    // --- VALIDACIÓN ANTES DE GUARDAR ---
    const formConfig = document.getElementById('formConfigRRHH');
    if (formConfig) {
        formConfig.addEventListener('submit', function(e) {
            // Solo validamos si las horas extras están activas y el modo es BLOQUES
            if (checkTarde && checkTarde.checked && selectTipoCalculo && selectTipoCalculo.value === 'BLOQUES') {
                const uMedia = parseInt(umbralMedia.value) || 0;
                const uHora = parseInt(umbralHora.value) || 0;
                
                if (uMedia <= 0) {
                    e.preventDefault(); // Detiene el guardado
                    alert('El umbral para Media Hora debe ser mayor a 0.');
                    umbralMedia.focus();
                } else if (uHora <= uMedia) {
                    e.preventDefault(); // Detiene el guardado
                    alert('El umbral para la Hora Completa debe ser lógicamente mayor que el de Media Hora.');
                    umbralHora.focus();
                }
            }
        });
    }
});