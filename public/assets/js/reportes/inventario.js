// Inicialización cuando el documento está listo
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. LÓGICA DE LAS PESTAÑAS (TABS) ---
    const botonesTabs = document.querySelectorAll('.btn-tab-seccion');
    const form = document.getElementById('formFiltrosInventario');
    const inputSeccion = document.getElementById('input_seccion_activa');

    botonesTabs.forEach(boton => {
        boton.addEventListener('click', function() {
            const seccionSeleccionada = this.getAttribute('data-seccion');
            
            // Solo enviamos si el usuario hizo clic en una pestaña diferente a la actual
            if (inputSeccion.value !== seccionSeleccionada) {
                inputSeccion.value = seccionSeleccionada;
                
                // Deshabilitar la validación HTML temporalmente para cambiar de pestaña sin trabas
                const fechas = form.querySelectorAll('input[type="date"]');
                fechas.forEach(f => f.required = false);
                
                form.submit();
            }
        });
    });

    // --- 2. LÓGICA DE LOS FILTROS Y AUTO-SUBMIT ---
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

    // --- NUEVO: INICIALIZACIÓN DEL MULTI-SELECTOR DE ETIQUETAS (TOMSELECT) ---
    const filtroTipoItemEl = document.getElementById('filtroTipoItem');
    if (filtroTipoItemEl && typeof TomSelect !== 'undefined') {
        new TomSelect(filtroTipoItemEl, {
            plugins: ['remove_button', 'clear_button'],
            maxOptions: null,
            closeAfterSelect: false,
            placeholder: 'Todos los tipos',
            onChange: function(value) {
                // Al elegir o quitar una etiqueta, enviamos el formulario
                if(form.checkValidity()) {
                    form.submit();
                }
            }
        });

        // IMPORTANTE: Quitamos la clase 'auto-submit' original de este select 
        // para que no haya conflictos con nuestro evento onChange de TomSelect
        filtroTipoItemEl.classList.remove('auto-submit');
    }
    // --------------------------------------------------------------------------

    // --- 3. INICIALIZACIÓN DE GRÁFICOS (Datos de Prueba) ---
    
   // Gráficos de la Pestaña "Stock"
    if (document.getElementById('chartStockDona') && window.datosInventario) {
        
        // Leemos los datos reales que nos mandó PHP
        const donaData = window.datosInventario.graficoDona;
        const barrasData = window.datosInventario.graficoBarras;

        // Solo dibujamos la dona si hay datos
        if (donaData.labels && donaData.labels.length > 0) {
            new Chart(document.getElementById('chartStockDona'), {
                type: 'doughnut',
                data: {
                    labels: donaData.labels,
                    datasets: [{ 
                        data: donaData.data, 
                        // Colores aleatorios profesionales
                        backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6610f2'] 
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // Solo dibujamos las barras si hay datos
        if (barrasData.labels && barrasData.labels.length > 0) {
            new Chart(document.getElementById('chartStockBarras'), {
                type: 'bar',
                data: {
                    labels: barrasData.labels,
                    datasets: [{ 
                        label: 'Valor Total', 
                        data: barrasData.data, 
                        backgroundColor: '#0dcaf0' 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', 
                    plugins: { legend: { display: false } }
                }
            });
        }
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