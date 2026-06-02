// Envolvemos todo en una función principal para poder re-ejecutarla cuando carguemos con AJAX
function inicializarEventosInventario() {
    
    const form = document.getElementById('formFiltrosInventario');
    const inputSeccion = document.getElementById('input_seccion_activa');

    // --- 1. LÓGICA DE LAS PESTAÑAS (TABS) CON AJAX ---
    if (form && inputSeccion) {
        document.querySelectorAll('.btn-tab-seccion').forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault(); // Detenemos el comportamiento normal del botón
                
                const seccionSeleccionada = this.getAttribute('data-seccion');
                if (inputSeccion.value !== seccionSeleccionada) {
                    inputSeccion.value = seccionSeleccionada;
                    // Quitamos el required para que HTML5 no bloquee el cambio de tab
                    form.querySelectorAll('input, select').forEach(f => f.required = false);
                    
                    cargarVistaSinParpadeo(form); // Llamamos a nuestra nueva función mágica
                }
            });
        });
    }

    // --- 2. LÓGICA DE LOS FILTROS Y AUTO-SUBMIT CON AJAX ---
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
                    cargarVistaSinParpadeo(form); // En vez de form.submit()
                } else {
                    form.reportValidity();
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
            
            updateTodos();
            
            chkTodos.addEventListener('change', function() {
                chkItems.forEach(c => c.checked = this.checked);
            });
            
            chkItems.forEach(c => c.addEventListener('change', updateTodos));
        }
    });

    // --- 4. GRÁFICOS (CHART.JS) ---
    const datos = window.datosInventario || {};

    // 4.1 Gráfico de Líneas (Kardex: Entradas vs Salidas)
    if (document.getElementById('chartKardexLineas') && datos.graficoKardex?.labels?.length > 0) {
        new Chart(document.getElementById('chartKardexLineas'), {
            type: 'line',
            data: {
                labels: datos.graficoKardex.labels,
                datasets: [
                    { 
                        label: 'Ingresos', 
                        data: datos.graficoKardex.ingresos, 
                        borderColor: '#198754', 
                        tension: 0.3, 
                        fill: false 
                    },
                    { 
                        label: 'Salidas', 
                        data: datos.graficoKardex.salidas, 
                        borderColor: '#dc3545', 
                        tension: 0.3, 
                        fill: false 
                    }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // 4.2 Gráfico de Pastel (Estado de Lotes)
    if (document.getElementById('chartLotesPie') && datos.graficoLotes?.data?.length > 0) {
        new Chart(document.getElementById('chartLotesPie'), {
            type: 'pie',
            data: {
                labels: ['Sanos (Verde)', 'Próx. a vencer (Amarillo)', 'Vencidos (Rojo)'],
                datasets: [{ 
                    data: datos.graficoLotes.data,
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'] 
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // --- 5. LÓGICA DEL MODAL DE EXPORTACIÓN A PDF ---
    const btnGenerarPdf = document.getElementById('btnGenerarPdfInventario');
    
    if (btnGenerarPdf) {
        // Prevenir múltiples listeners si la vista se recarga por partes
        const nuevoBtnGenerarPdf = btnGenerarPdf.cloneNode(true);
        btnGenerarPdf.parentNode.replaceChild(nuevoBtnGenerarPdf, btnGenerarPdf);

        nuevoBtnGenerarPdf.addEventListener('click', function() {
            const formObj = document.getElementById('formFiltrosInventario');
            if (!formObj) return;

            // 1. Leemos qué opción eligió el usuario (Completo o Ciego)
            const selectTipo = document.getElementById('tipoReporteInventario');
            const ocultarValores = selectTipo ? selectTipo.value : '0';
            
            // 2. Colocamos ese valor en el input oculto
            const hiddenOcultar = document.getElementById('hidden_ocultar_valores');
            if (hiddenOcultar) hiddenOcultar.value = ocultarValores;
            
            // 3. Capturamos el término de búsqueda según la pestaña activa
            const seccion = inputSeccion ? inputSeccion.value : 'stock';
            let searchInputId = 'filtroRepStock';
            if (seccion === 'historico') searchInputId = 'filtroRepHistorico';
            if (seccion === 'kardex') searchInputId = 'filtroRepKardex';
            if (seccion === 'vencimientos') searchInputId = 'filtroRepVencimientos';
            
            const searchInput = document.getElementById(searchInputId);
            const hiddenSearch = document.getElementById('hidden_busqueda');
            if (searchInput && hiddenSearch) hiddenSearch.value = searchInput.value;
            
            // 4. Creamos un input temporal simulando el clic del botón exportar original
            const inputExportar = document.createElement('input');
            inputExportar.type = 'hidden';
            inputExportar.name = 'exportar_pdf';
            inputExportar.value = '1';
            formObj.appendChild(inputExportar);

            // 5. Enviamos el formulario abriendo una pestaña nueva
            formObj.target = '_blank';
            formObj.submit();
            
            // 6. Restauramos todo a la normalidad y cerramos el modal
            formObj.target = ''; 
            inputExportar.remove();
            
            const modalEl = document.getElementById('modalExportarInventario');
            if(typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }
        });
    }
}

// --- 6. FUNCIÓN MÁGICA: RECARGA EN SEGUNDO PLANO (AJAX) ---
async function cargarVistaSinParpadeo(form) {
    const contenedor = document.getElementById('reportesInventarioApp');
    if (!contenedor) return form.submit(); // Salvavidas: Si falla, hace recarga normal

    // 1. Efecto visual de "Cargando"
    contenedor.style.opacity = '0.5';
    contenedor.style.pointerEvents = 'none';

    // 2. Preparamos la URL con los filtros
    const formData = new FormData(form);
    // Eliminamos la orden de exportar por si quedó pegada
    formData.delete('exportar_pdf'); 
    
    const params = new URLSearchParams(formData);
    const url = form.action + '&' + params.toString();

    try {
        // 3. Buscamos la página en el servidor de forma invisible
        const response = await fetch(url);
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const nuevoContenedor = doc.getElementById('reportesInventarioApp');

        if (nuevoContenedor) {
            // 4. Reemplazamos el HTML sin parpadear
            contenedor.innerHTML = nuevoContenedor.innerHTML;
            
            // 5. Cambiamos la URL arriba en el navegador para que funcione el botón "Atrás"
            window.history.pushState({}, '', url);

            // 6. TRUCO VITAL: Ejecutar los <script> que vinieron en el nuevo HTML
            contenedor.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                document.body.appendChild(newScript).parentNode.removeChild(newScript);
            });

            // 7. Reinicializamos los eventos y gráficos en los elementos nuevos
            inicializarEventosInventario();
            
            // 8. Reinicializar herramienta de tablas (limitado al contenedor actual)
            if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                window.ERPTable.autoInitFromDataset(contenedor);
            }

        } else {
            form.submit(); // Fallback
        }
    } catch (error) {
        console.error("Error cargando la vista:", error);
        form.submit(); // Si se cae el internet, intenta recarga normal
    } finally {
        // Quitamos el efecto de "Cargando"
        contenedor.style.opacity = '1';
        contenedor.style.pointerEvents = 'auto';
    }
}

// Inicializar por primera vez al abrir la página
document.addEventListener('DOMContentLoaded', inicializarEventosInventario);