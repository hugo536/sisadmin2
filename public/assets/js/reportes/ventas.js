/**
 * LÓGICA CENTRALIZADA PARA PERFIL DE ÍTEM
 * Archivo: public/assets/js/items/perfil.js
 */

if (typeof window.inicializarModuloPerfilItem === 'undefined') {

    window.inicializarModuloPerfilItem = function() {
        "use strict";

        // --- 1. VISOR DE DOCUMENTOS ---
        const itemsDocs = document.querySelectorAll('.doc-item');
        const visorContainer = document.getElementById('visorContainer');
        const placeholder = document.getElementById('visorPlaceholder');
        const pdfFrame = document.getElementById('visorPDF');
        const imgVisor = document.getElementById('visorIMG');
        const extVisor = document.getElementById('visorExternal');
        const btnDescarga = document.getElementById('btnDescarga');
        const toolbar = document.getElementById('visorToolbar');
        const toolbarName = document.getElementById('visorFileName');
        const toolbarBtn = document.getElementById('visorBtnOpen');

        itemsDocs.forEach((item) => {
            // Remover event listeners huérfanos si la función se llama varias veces en la SPA
            const nuevoItem = item.cloneNode(true);
            item.parentNode.replaceChild(nuevoItem, item);

            nuevoItem.addEventListener('click', (e) => {
                if (e.target.closest('button') || e.target.closest('form')) return;
                e.preventDefault();

                document.querySelectorAll('.doc-item').forEach((i) => i.classList.remove('active', 'bg-white', 'border-start', 'border-primary', 'border-3'));
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

        // --- 2. CONFIGURACIÓN DE TIPOS DE DOCUMENTO ---
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

        // --- 3. BÚSQUEDA DE DOCUMENTOS ---
        const searchInput = document.getElementById('docSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                document.querySelectorAll('.doc-item').forEach((doc) => {
                    const text = doc.getAttribute('data-search') || '';
                    doc.classList.toggle('d-none', !text.includes(term));
                });
            });
        }

        // --- 4. EDICIÓN DE DOCUMENTOS ---
        const modalEditEl = document.getElementById('modalEditarDoc');
        const editIdInput = document.getElementById('editDocId');
        const btnsEdit = document.querySelectorAll('.btn-edit-doc');

        if (modalEditEl && editIdInput && selectEdit) {
            const bsModal = new bootstrap.Modal(modalEditEl);
            btnsEdit.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation(); 
                    const id = btn.getAttribute('data-id');
                    const tipo = btn.getAttribute('data-tipo');
                    
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

        // --- 5. ELIMINAR DOCUMENTOS (SWEETALERT2) ---
        document.querySelectorAll('.form-eliminar-doc').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
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
                    if (result.isConfirmed) form.submit();
                });
            });
        });

        // --- 6. GRÁFICO SEGURO PARA SPA (HISTORIAL DE COSTOS) ---
        if (typeof Chart !== 'undefined') {
            const crearGraficoSeguroSPA = (canvasElement, config) => {
                const chartInstance = new Chart(canvasElement, config);
                const observer = new MutationObserver(() => {
                    if (!document.body.contains(canvasElement)) {
                        chartInstance.destroy();
                        observer.disconnect();
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                return chartInstance;
            };

            const costosTab = document.getElementById('costos-tab');
            const canvasCosto = document.getElementById('chartPerfilCosto');
            let chartInstanciaCosto = null;

            if (costosTab && canvasCosto) {
                costosTab.addEventListener('shown.bs.tab', function () {
                    if (chartInstanciaCosto) return; 

                    try {
                        const chartData = JSON.parse(canvasCosto.getAttribute('data-historial') || '[]');
                        if (chartData.length === 0) return;

                        chartData.reverse(); 

                        const labels = chartData.map(r => {
                            const partes = r.fecha_movimiento.split(' ')[0].split('-');
                            return `${partes[2]}/${partes[1]}/${partes[0]}`;
                        });
                        const data = chartData.map(r => Number(r.costo_promedio_resultante || 0));

                        chartInstanciaCosto = crearGraficoSeguroSPA(canvasCosto, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Costo Promedio (S/)',
                                    data: data,
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.25,
                                    pointRadius: 3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label(ctx) { return ` S/ ${Number(ctx.parsed.y ?? 0).toFixed(4)}`; }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        ticks: {
                                            callback(value) { return `S/ ${Number(value).toFixed(2)}`; }
                                        }
                                    }
                                }
                            }
                        });
                    } catch (err) {
                        console.error("Error Chart Historial Costos:", err);
                    }
                });
            }
        }
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE (ESTÁNDAR SPA) ---
    document.addEventListener('DOMContentLoaded', window.inicializarModuloPerfilItem);
    document.addEventListener('sisadmin:route-loaded', window.inicializarModuloPerfilItem);
}