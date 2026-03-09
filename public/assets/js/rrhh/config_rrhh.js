document.addEventListener('DOMContentLoaded', function() {
    
    const checkTemprano = document.getElementById('checkTemprano');
    const txtEjemploTemprano = document.getElementById('txtEjemploTemprano');
    
    const checkTarde = document.getElementById('checkTarde');
    const cajaConfigExtras = document.getElementById('cajaConfigExtras');
    const inputsExtras = cajaConfigExtras.querySelectorAll('input');

    // Efecto visual para Llegada Temprano
    checkTemprano.addEventListener('change', function() {
        if (this.checked) {
            txtEjemploTemprano.textContent = '07:45 AM';
            txtEjemploTemprano.classList.replace('bg-secondary', 'bg-primary');
        } else {
            txtEjemploTemprano.textContent = '08:00 AM';
            txtEjemploTemprano.classList.replace('bg-primary', 'bg-secondary');
        }
    });

    // Efecto visual para Salida Tarde (Horas Extras)
    checkTarde.addEventListener('change', function() {
        if (this.checked) {
            cajaConfigExtras.classList.remove('opacity-50');
            inputsExtras.forEach(input => input.removeAttribute('readonly'));
        } else {
            cajaConfigExtras.classList.add('opacity-50');
            inputsExtras.forEach(input => input.setAttribute('readonly', 'readonly'));
        }
    });

    // Validar antes de enviar
    document.getElementById('formConfigRRHH').addEventListener('submit', function(e) {
        const minExtras = parseInt(document.getElementById('minExtras').value) || 0;
        
        if (checkTarde.checked && minExtras <= 0) {
            e.preventDefault();
            alert('Si habilita el pago de Horas Extras, el tiempo mínimo debe ser mayor a 0.');
        }
    });
});