document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. LÓGICA DE LAS PESTAÑAS (TABS) ---
    const form = document.getElementById('formFiltrosInventario');
    const inputSeccion = document.getElementById('input_seccion_activa');

    document.querySelectorAll('.btn-tab-seccion').forEach(boton => {
        boton.addEventListener('click', function() {
            const seccionSeleccionada = this.getAttribute('data-seccion');
            if (inputSeccion.value !== seccionSeleccionada) {
                inputSeccion.value = seccionSeleccionada;
                form.querySelectorAll('input[type="date"]').forEach(f => f.required = false);
                form.submit();
            }
        });
    });

    // --- 2. LÓGICA DE LOS FILTROS Y AUTO-SUBMIT ---
    const fechaDesde = document.getElementById('fecha_desde');
    const fechaHasta = document.getElementById('fecha_hasta');

    if(fechaDesde && fechaHasta) {
        fechaDesde.addEventListener('change', function() {
            if(this.value) fechaHasta.min = this.value;
        });
    }

    form.querySelectorAll('.auto-submit').forEach(input => {
        input.addEventListener('change', function() {
            if(form.checkValidity()) {
                form.submit();
            } else {
                form.reportValidity();
                if(this.type === 'checkbox') this.checked = !this.checked; 
            }
        });
    });

    // --- 3. LÓGICA DE SELECCIONAR TODOS (MENÚS MÚLTIPLES) ---
    document.querySelectorAll('.dropdown-multi').forEach(dropdown => {
        const chkTodos = dropdown.querySelector('.chk-todos');
        const chkItems = dropdown.querySelectorAll('.chk-item');
        
        if(chkTodos && chkItems.length > 0) {
            const updateTodos = () => {
                chkTodos.checked = Array.from(chkItems).every(c => c.checked);
            };
            updateTodos();
            
            chkTodos.addEventListener('change', function() {
                chkItems.forEach(c => c.checked = this.checked);
            });
            
            chkItems.forEach(c => c.addEventListener('change', updateTodos));
        }
    });

    // --- 4. GRÁFICOS ---
    if (document.getElementById('chartStockDona') && window.datosInventario) {
        const donaData = window.datosInventario.graficoDona;
        const barrasData = window.datosInventario.graficoBarras;

        if (donaData.labels && donaData.labels.length > 0) {
            new Chart(document.getElementById('chartStockDona'), {
                type: 'doughnut',
                data: {
                    labels: donaData.labels,
                    datasets: [{ data: donaData.data, backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6610f2'] }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        if (barrasData.labels && barrasData.labels.length > 0) {
            new Chart(document.getElementById('chartStockBarras'), {
                type: 'bar',
                data: {
                    labels: barrasData.labels,
                    datasets: [{ label: 'Valor Total', data: barrasData.data, backgroundColor: '#0dcaf0' }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
            });
        }
    }

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