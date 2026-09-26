(function () {
    'use strict';

    // Variable global para mantener la instancia del gráfico y evitar duplicados en SPA
    let chartInstance = null;

    // --- 1. VISOR DE DOCUMENTOS ---
    function initVisorDocumentos() {
        const items = document.querySelectorAll('.doc-item');
        const visorContainer = document.getElementById('visorContainer');
        const placeholder = document.getElementById('visorPlaceholder');
        const pdfFrame = document.getElementById('visorPDF');
        const imgVisor = document.getElementById('visorIMG');
        const extVisor = document.getElementById('visorExternal');
        const btnDescarga = document.getElementById('btnDescarga');
        const toolbar = document.getElementById('visorToolbar');
        const toolbarName = document.getElementById('visorFileName');
        const toolbarBtn = document.getElementById('visorBtnOpen');

        if (items.length === 0) return;

        items.forEach((item) => {
            // Removemos listeners previos clonando el nodo (Vital para SPA)
            const nuevoItem = item.cloneNode(true);
            item.parentNode.replaceChild(nuevoItem, item);

            nuevoItem.addEventListener('click', (e) => {
                if (e.target.closest('button') || e.target.closest('form')) return;
                e.preventDefault();

                document.querySelectorAll('.doc-item').forEach((i) => {
                    i.classList.remove('active', 'bg-white', 'border-start', 'border-primary', 'border-3');
                });
                
                nuevoItem.classList.add('active', 'bg-white', 'border-start', 'border-primary', 'border-3');

                const url = nuevoItem.dataset.url;
                const ext = nuevoItem.dataset.type;
                const titleEl = nuevoItem.querySelector('h6');
                const nombreVisual = titleEl ? titleEl.textContent.trim() : 'Documento';

                placeholder?.classList.add('d-none');
                pdfFrame?.classList.add('d-none');
                imgVisor?.classList.add('d-none');
                extVisor?.classList.add('d-none');

                if (toolbar && toolbarName && toolbarBtn) {
                    toolbar.classList.remove('d-none');
                    toolbarName.textContent = nombreVisual;
                    toolbarBtn.href = url;
                }

                if (ext === 'pdf' && pdfFrame) {
                    pdfFrame.src = url;
                    pdfFrame.classList.remove('d-none');
                } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext) && imgVisor) {
                    imgVisor.src = url;
                    imgVisor.classList.remove('d-none');
                } else if (extVisor && btnDescarga) {
                    btnDescarga.href = url;
                    extVisor.classList.remove('d-none');
                }

                if (window.innerWidth < 992 && visorContainer) {
                    visorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    // --- 2. CONFIGURACIÓN DE TIPOS DE DOCUMENTO ---
    function initConfiguracionTipos() {
        const tipos = [
            { val: 'REG_SANITARIO', text: 'Registro Sanitario' },
            { val: 'FICHA_TECNICA', text: 'Ficha Técnica' },
            { val: 'MSDS', text: 'Seguridad MSDS' },
            { val: 'CERT_CALIDAD', text: 'Certificado de Calidad' },
            { val: 'OTRO', text: 'Otros Documentos' }
        ];

        const selectUpload = document.getElementById('docTipoSelect');
        const selectEdit = document.getElementById('editDocTipo');

        const populateSelect = (targetSelect) => {
            if (!targetSelect) return;
            targetSelect.innerHTML = '<option value="">Seleccione tipo...</option>';
            tipos.forEach((tipo) => {
                const opt = document.createElement('option');
                opt.value = tipo.val;
                opt.textContent = tipo.text;
                targetSelect.appendChild(opt);
            });
        };

        populateSelect(selectUpload);
        populateSelect(selectEdit);
    }

    // --- 3. NAVEGACIÓN POR TABS (URL PARAMS) ---
    function initNavegacionTabs() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'documentos') {
            const triggerEl = document.querySelector('#docs-tab');
            if (triggerEl && typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getOrCreateInstance(triggerEl).show();
            }
        }
    }

    // --- 4. BÚSQUEDA DE DOCUMENTOS ---
    function initBusquedaDocumentos() {
        const searchInput = document.getElementById('docSearch');
        if (!searchInput) return;

        const newSearchInput = searchInput.cloneNode(true);
        searchInput.parentNode.replaceChild(newSearchInput, searchInput);

        newSearchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.doc-item').forEach((doc) => {
                const text = doc.getAttribute('data-search') || '';
                doc.classList.toggle('d-none', !text.includes(term));
            });
        });
    }

    // --- 5. EDICIÓN DE DOCUMENTOS ---
    function initEdicionDocumentos() {
        const modalEditEl = document.getElementById('modalEditarDoc');
        const editIdInput = document.getElementById('editDocId');
        const btnsEdit = document.querySelectorAll('.btn-edit-doc');
        const selectEdit = document.getElementById('editDocTipo');

        if (!modalEditEl || !editIdInput || !selectEdit || typeof bootstrap === 'undefined') return;

        const bsModal = new bootstrap.Modal(modalEditEl);
        
        btnsEdit.forEach((btn) => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', (e) => {
                e.stopPropagation(); 
                
                const id = newBtn.getAttribute('data-id');
                const tipo = newBtn.getAttribute('data-tipo');
                
                editIdInput.value = id;
                
                if (tipo && !Array.from(selectEdit.options).some((opt) => opt.value === tipo)) {
                    const opt = document.createElement('option');
                    opt.value = tipo;
                    opt.textContent = tipo;
                    selectEdit.appendChild(opt);
                }
                
                selectEdit.value = tipo;
                bsModal.show();
            });
        });
    }

    // --- 6. ELIMINAR DOCUMENTOS ---
    function initEliminarDocumentos() {
        document.querySelectorAll('.form-eliminar-doc').forEach((form) => {
            const newForm = form.cloneNode(true);
            form.parentNode.replaceChild(newForm, form);

            newForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '¿Eliminar archivo?',
                        text: 'Esta acción no se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            newForm.submit(); 
                        }
                    });
                } else {
                    if (confirm('¿Eliminar archivo? Esta acción no se puede deshacer.')) {
                        newForm.submit();
                    }
                }
            });
        });
    }

    // --- 7. GRÁFICO DE COSTOS Y FLUCTUACIÓN ---
    function initGraficoCostos() {
        const costosTab = document.getElementById('costos-tab');
        const chartCanvas = document.getElementById('chartPerfilCosto');
        
        if (!costosTab || !chartCanvas || typeof Chart === 'undefined') return;

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        const renderChart = () => {
            if (chartInstance) return; 

            const rawData = chartCanvas.getAttribute('data-historial');
            if (!rawData || rawData === '[]') return;

            try {
                const historial = JSON.parse(rawData);
                historial.reverse();

                const labels = historial.map(item => new Date(item.fecha_movimiento).toLocaleDateString());
                const dataCostos = historial.map(item => parseFloat(item.costo_promedio_resultante));

                chartInstance = new Chart(chartCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Costo Promedio (S/)',
                            data: dataCostos,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: '#0d6efd',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#0d6efd'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) { return 'S/ ' + context.parsed.y.toFixed(4); }
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error("Error al procesar los datos:", e);
            }
        };

        costosTab.addEventListener('shown.bs.tab', renderChart);
        
        if (costosTab.classList.contains('active')) {
            renderChart();
        }
    }

    // --- INICIALIZADOR PRINCIPAL ---
    function arrancarModuloPerfil() {
        // Verificar si estamos realmente en la vista del perfil (vital para el orquestador)
        if (!document.getElementById('perfilTabs')) return;

        initVisorDocumentos();
        initConfiguracionTipos();
        initNavegacionTabs();
        initBusquedaDocumentos();
        initEdicionDocumentos();
        initEliminarDocumentos();
        initGraficoCostos();
    }

    // =========================================================
    // MAGIA SPA: Mismos disparadores que tu módulo de Ventas
    // =========================================================
    document.addEventListener('DOMContentLoaded', arrancarModuloPerfil);
    document.addEventListener('sisadmin:route-loaded', arrancarModuloPerfil);

    // Exportar al objeto window (Opcional)
    window.inicializarPerfilItem = arrancarModuloPerfil;

})();