// Función para cambiar de pestaña y hacer auto-submit
function cambiarSeccion(seccion) {
    const form = document.getElementById('formFiltrosInventario');
    const inputSeccion = document.getElementById('input_seccion_activa');
    
    // Solo enviamos si el usuario hizo clic en una pestaña diferente a la actual
    if (inputSeccion.value !== seccion) {
        inputSeccion.value = seccion;
        
        // Deshabilitar la validación HTML temporalmente para cambiar de pestaña sin trabas
        const fechas = form.querySelectorAll('input[type="date"]');
        fechas.forEach(f => f.required = false);
        
        form.submit();
    }
}

// Inicialización cuando el documento está listo
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formFiltrosInventario');
    const inputsAutoSubmit = form.querySelectorAll('.auto-submit');
    const fechaDesde = document.getElementById('fecha_desde');
    const fechaHasta = document.getElementById('fecha_hasta');

    // Validación: Evitar que la fecha final sea anterior a la inicial
    if(fechaDesde && fechaHasta) {
        fechaDesde.addEventListener('change', function() {
            if(this.value) fechaHasta.min = this.value;
        });
    }

    // Configurar el envío automático de los filtros
    inputsAutoSubmit.forEach(input => {
        input.addEventListener('change', function() {
            if(form.checkValidity()) {
                form.submit();
            } else {
                form.reportValidity();
                // Si es un checkbox/switch, lo devolvemos a su estado anterior visualmente
                if(this.type === 'checkbox') this.checked = !this.checked; 
            }
        });
    });

    // ==========================================
    // INICIALIZACIÓN DE GRÁFICOS (Datos de Prueba)
    // ==========================================

    // Gráficos de la Pestaña "Stock"
    if (document.getElementById('chartStockDona')) {
        new Chart(document.getElementById('chartStockDona'), {
            type: 'doughnut',
            data: {
                labels: ['Mat. Prima', 'Prod. Terminado', 'Insumos'],
                datasets: [{ data: [45, 35, 20], backgroundColor: ['#0d6efd', '#198754', '#ffc107'] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        new Chart(document.getElementById('chartStockBarras'), {
            type: 'bar',
            data: {
                labels: ['Cajas Cartón', 'Etiquetas', 'Botellas 1L', 'Tapas', 'Pegamento'],
                datasets: [{ label: 'Valor en S/', data: [1500, 1200, 900, 600, 300], backgroundColor: '#0dcaf0' }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                indexAxis: 'y', // Hace que las barras sean horizontales
                plugins: { legend: { display: false } }
            }
        });
    }

    // Gráfico de la Pestaña "Kardex"
    if (document.getElementById('chartKardexLineas')) {
        new Chart(document.getElementById('chartKardexLineas'), {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [
                    { label: 'Ingresos', data: [10, 25, 5, 40, 15, 0, 0], borderColor: '#198754', tension: 0.3, fill: false },
                    { label: 'Salidas', data: [5, 10, 20, 15, 30, 5, 0], borderColor: '#dc3545', tension: 0.3, fill: false }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Gráfico de la Pestaña "Vencimientos"
    if (document.getElementById('chartLotesPie')) {
        new Chart(document.getElementById('chartLotesPie'), {
            type: 'pie',
            data: {
                labels: ['Sanos (Verde)', 'Próx. a vencer (Amarillo)', 'Vencidos (Rojo)'],
                datasets: [{ data: [85, 10, 5], backgroundColor: ['#198754', '#ffc107', '#dc3545'] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
});