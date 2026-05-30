document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. LÓGICA DE LAS PESTAÑAS (TABS) ---
    const form = document.getElementById('formFiltrosInventario');
    const inputSeccion = document.getElementById('input_seccion_activa');

    if (form && inputSeccion) {
        document.querySelectorAll('.btn-tab-seccion').forEach(boton => {
            boton.addEventListener('click', function() {
                const seccionSeleccionada = this.getAttribute('data-seccion');
                if (inputSeccion.value !== seccionSeleccionada) {
                    inputSeccion.value = seccionSeleccionada;
                    // Evitar que la validación HTML5 bloquee el cambio de pestaña
                    form.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(f => f.required = false);
                    form.submit();
                }
            });
        });
    }

    // --- 2. LÓGICA DE LOS FILTROS Y AUTO-SUBMIT ---
    const fechaDesde = document.getElementById('fecha_desde');
    const fechaHasta = document.getElementById('fecha_hasta');

    if(fechaDesde && fechaHasta) {
        fechaDesde.addEventListener('change', function() {
            if(this.value) fechaHasta.min = this.value;
        });
    }

    if (form) {
        form.querySelectorAll('.auto-submit').forEach(input => {
            input.addEventListener('change', function() {
                if(form.checkValidity()) {
                    form.submit();
                } else {
                    form.reportValidity();
                    // Revertir el checkbox si el formulario no es válido para evitar confusión visual
                    if(this.type === 'checkbox') this.checked = !this.checked; 
                }
            });
        });
    }

    // --- 3. LÓGICA DE SELECCIONAR TODOS (MENÚS MÚLTIPLES) ---
    document.querySelectorAll('.dropdown-multi').forEach(dropdown => {
        const chkTodos = dropdown.querySelector('.chk-todos');
        const chkItems = dropdown.querySelectorAll('.chk-item');
        
        if(chkTodos && chkItems.length > 0) {
            const updateTodos = () => {
                chkTodos.checked = Array.from(chkItems).every(c => c.checked);
            };
            
            // Inicializar estado
            updateTodos();
            
            // Evento: Clic en "Seleccionar Todos"
            chkTodos.addEventListener('change', function() {
                chkItems.forEach(c => c.checked = this.checked);
            });
            
            // Evento: Clic en cualquier ítem individual
            chkItems.forEach(c => c.addEventListener('change', updateTodos));
        }
    });

    // --- 4. GRÁFICOS (CHART.JS) ---
    // Usamos encadenamiento opcional (?.) para evitar errores si el objeto no existe
    const datos = window.datosInventario || {};

    // 4.1 Gráfico de Dona (Distribución del Valor)
    if (document.getElementById('chartStockDona') && datos.graficoDona?.labels?.length > 0) {
        new Chart(document.getElementById('chartStockDona'), {
            type: 'doughnut',
            data: {
                labels: datos.graficoDona.labels,
                datasets: [{ 
                    data: datos.graficoDona.data, 
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6610f2'] 
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // 4.2 Gráfico de Barras (Top 5 Valor)
    if (document.getElementById('chartStockBarras') && datos.graficoBarras?.labels?.length > 0) {
        new Chart(document.getElementById('chartStockBarras'), {
            type: 'bar',
            data: {
                labels: datos.graficoBarras.labels,
                datasets: [{ 
                    label: 'Valor Total', 
                    data: datos.graficoBarras.data, 
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

    // 4.3 Gráfico de Líneas (Kardex: Entradas vs Salidas)
    if (document.getElementById('chartKardexLineas') && datos.graficoKardex?.labels?.length > 0) {
        new Chart(document.getElementById('chartKardexLineas'), {
            type: 'line',
            data: {
                labels: datos.graficoKardex.labels, // Ej: ['Lun', 'Mar', 'Mié', ...]
                datasets: [
                    { 
                        label: 'Ingresos', 
                        data: datos.graficoKardex.ingresos, // ¡Datos reales desde PHP!
                        borderColor: '#198754', 
                        tension: 0.3, 
                        fill: false 
                    },
                    { 
                        label: 'Salidas', 
                        data: datos.graficoKardex.salidas, // ¡Datos reales desde PHP!
                        borderColor: '#dc3545', 
                        tension: 0.3, 
                        fill: false 
                    }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // 4.4 Gráfico de Pastel (Estado de Lotes)
    if (document.getElementById('chartLotesPie') && datos.graficoLotes?.data?.length > 0) {
        new Chart(document.getElementById('chartLotesPie'), {
            type: 'pie',
            data: {
                labels: ['Sanos (Verde)', 'Próx. a vencer (Amarillo)', 'Vencidos (Rojo)'],
                datasets: [{ 
                    data: datos.graficoLotes.data, // Ej: [85, 10, 5] desde PHP
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'] 
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
});