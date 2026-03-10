document.addEventListener('DOMContentLoaded', () => {

    // --- VARIABLES GLOBALES ---
    const searchInput = document.getElementById('searchDetalles');
    const tablaDetalles = document.getElementById('tablaDetallesNomina');

    // ==============================================================
    // 1. GESTIÓN DEL MODAL DE AJUSTES (BONOS/DEDUCCIONES)
    // ==============================================================
    const modalAjustar = document.getElementById('modalAjustarNomina');
    const contenedorMovimientos = document.getElementById('contenedorMovimientosNomina');
    const tplMovimiento = document.getElementById('tplMovimientoNomina');
    const btnAgregarMovimiento = document.getElementById('btnAgregarMovimientoNomina');

    function renombrarCamposMovimientos() {
        if (!contenedorMovimientos) return;
        const items = contenedorMovimientos.querySelectorAll('.movimiento-nomina-item');
        items.forEach((item, idx) => {
            const lbl = item.querySelector('.js-mov-index');
            if (lbl) lbl.textContent = `#${idx + 1}`;
            item.querySelectorAll('[data-name]').forEach((field) => {
                const key = field.getAttribute('data-name');
                field.setAttribute('name', `movimientos[${idx}][${key}]`);
            });
            const btnRemove = item.querySelector('.js-remove-movimiento');
            if (btnRemove) btnRemove.disabled = items.length === 1;
        });
    }

    function agregarMovimientoInicial() {
        if (!contenedorMovimientos || !tplMovimiento) return;
        const nodo = tplMovimiento.content.firstElementChild.cloneNode(true);
        contenedorMovimientos.appendChild(nodo);
        renombrarCamposMovimientos();
    }

    function validarDuplicadosMovimientos() {
        if (!contenedorMovimientos) return true;
        const vistos = new Set();
        const items = contenedorMovimientos.querySelectorAll('.movimiento-nomina-item');
        for (const item of items) {
            const tipo = (item.querySelector('[data-name="tipo_concepto"]')?.value || '').trim().toUpperCase();
            const categoria = (item.querySelector('[data-name="categoria_concepto"]')?.value || '').trim().toLowerCase();
            const descripcion = (item.querySelector('[data-name="descripcion"]')?.value || '').trim().toLowerCase();
            const llave = `${tipo}::${categoria}::${descripcion}`;
            if (vistos.has(llave)) {
                return false;
            }
            vistos.add(llave);
        }
        return true;
    }

    if (modalAjustar) {
        modalAjustar.addEventListener('show.bs.modal', function (event) {
            // Botón (el lápiz amarillo) que disparó el modal
            const button = event.relatedTarget;

            // Extraer info de los atributos data-* que pusimos en el HTML
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');

            // Inyectar en los inputs ocultos y textos visuales del modal
            document.getElementById('ajusteIdDetalle').value = id;
            document.getElementById('ajusteNombreEmpleado').textContent = nombre;

            // Resetear los campos de monto y descripción cada vez que se abre
            const formAjuste = modalAjustar.querySelector('form');
            if (formAjuste) {
                if (contenedorMovimientos) {
                    contenedorMovimientos.innerHTML = '';
                    agregarMovimientoInicial();
                }
                // Restaurar el botón por si se quedó en estado de carga
                restaurarBotonSubmit(formAjuste);
            }
        });
    }

    if (btnAgregarMovimiento) {
        btnAgregarMovimiento.addEventListener('click', () => {
            agregarMovimientoInicial();
        });
    }

    if (contenedorMovimientos) {
        contenedorMovimientos.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-remove-movimiento');
            if (!btn) return;
            const item = btn.closest('.movimiento-nomina-item');
            if (item) {
                item.remove();
                if (!contenedorMovimientos.querySelector('.movimiento-nomina-item')) {
                    agregarMovimientoInicial();
                } else {
                    renombrarCamposMovimientos();
                }
            }
        });
    }

    // ==============================================================
    // 2. MODAL GENERAR LOTE: FRECUENCIA + RANGO DE FECHAS + NOMBRE AUTO
    // ==============================================================
    const modalGenerarLote = document.getElementById('modalGenerarLote');
    const selectFrecuenciaLote = document.getElementById('frecuenciaLote');
    const inputFechaInicioLote = document.getElementById('fechaInicioLote');
    const inputFechaFinLote = document.getElementById('fechaFinLote');
    const ayudaFrecuenciaLote = document.getElementById('ayudaFrecuenciaLote');
    const inputNombreGenerado = document.getElementById('nombreGeneradoLote');

    const PERIODOS_DIAS = {
        TODOS: 30,
        SEMANAL: 7,
        QUINCENAL: 15,
        MENSUAL: 30
    };

    const MENSAJES_PERIODO = {
        TODOS: 'Rango libre recomendado hasta 30 días. Se calcularán todos los empleados activos.',
        SEMANAL: 'Se configuró un rango semanal de 7 días para empleados con frecuencia semanal.',
        QUINCENAL: 'Se configuró un rango quincenal de 15 días para empleados con frecuencia quincenal.',
        MENSUAL: 'Se configuró un rango mensual de 30 días para empleados con frecuencia mensual.'
    };

    function formatDateISO(date) {
        return date.toISOString().slice(0, 10);
    }
    
    // Función para convertir a formato Latino (DD/MM/YYYY) para el nombre visual
    function formatLatino(dateStr) {
        if(!dateStr) return '';
        const [year, month, day] = dateStr.split('-');
        return `${day}/${month}/${year}`;
    }

    function addDays(dateValue, days) {
        const date = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        date.setDate(date.getDate() + days);
        return date;
    }

    // NUEVA FUNCIÓN: Genera el nombre del lote visualmente para el usuario
    function actualizarNombreLote() {
        if(inputNombreGenerado && inputFechaInicioLote.value && inputFechaFinLote.value) {
            const inicio = formatLatino(inputFechaInicioLote.value);
            const fin = formatLatino(inputFechaFinLote.value);
            inputNombreGenerado.value = `NOM - ${inicio} al ${fin}`;
        }
    }

    function ajustarRangoPorFrecuencia() {
        if (!selectFrecuenciaLote || !inputFechaInicioLote || !inputFechaFinLote) {
            return;
        }

        const frecuencia = (selectFrecuenciaLote.value || 'TODOS').toUpperCase();
        const diasPeriodo = PERIODOS_DIAS[frecuencia] ?? 30;

        if (!inputFechaInicioLote.value) {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            inputFechaInicioLote.value = formatDateISO(hoy);
        }

        const fechaFinCalculada = addDays(inputFechaInicioLote.value, diasPeriodo - 1);
        if (fechaFinCalculada) {
            inputFechaFinLote.value = formatDateISO(fechaFinCalculada);
            inputFechaFinLote.min = inputFechaInicioLote.value;
            inputFechaFinLote.max = formatDateISO(addDays(inputFechaInicioLote.value, diasPeriodo - 1));
        }

        if (ayudaFrecuenciaLote) {
            ayudaFrecuenciaLote.innerHTML = `<i class="bi bi-info-circle text-primary me-1"></i> ${MENSAJES_PERIODO[frecuencia] ?? MENSAJES_PERIODO.TODOS}`;
        }
        
        actualizarNombreLote();
    }

    function validarRangoSegunFrecuencia() {
        if (!selectFrecuenciaLote || !inputFechaInicioLote || !inputFechaFinLote || !inputFechaInicioLote.value || !inputFechaFinLote.value) {
            return;
        }

        const frecuencia = (selectFrecuenciaLote.value || 'TODOS').toUpperCase();
        const diasPeriodo = PERIODOS_DIAS[frecuencia] ?? 30;
        const inicio = new Date(inputFechaInicioLote.value + 'T00:00:00');
        const fin = new Date(inputFechaFinLote.value + 'T00:00:00');
        const diferenciaDias = Math.floor((fin.getTime() - inicio.getTime()) / 86400000) + 1;

        if (diferenciaDias <= 0) {
            inputFechaFinLote.setCustomValidity('La fecha fin debe ser mayor o igual a la fecha de inicio.');
            return;
        }

        if (diferenciaDias !== diasPeriodo) {
            inputFechaFinLote.setCustomValidity(`Para frecuencia ${frecuencia.toLowerCase()} el rango debe ser de ${diasPeriodo} días.`);
            return;
        }

        inputFechaFinLote.setCustomValidity('');
        actualizarNombreLote();
    }

    if (modalGenerarLote) {
        modalGenerarLote.addEventListener('shown.bs.modal', function() {
            ajustarRangoPorFrecuencia();
            // Restaurar el botón en caso de que se haya cerrado previamente mientras cargaba
            const formGenerarLote = modalGenerarLote.querySelector('form');
            if(formGenerarLote) restaurarBotonSubmit(formGenerarLote);
        });
    }

    if (selectFrecuenciaLote) {
        selectFrecuenciaLote.addEventListener('change', () => {
            inputFechaFinLote?.setCustomValidity('');
            ajustarRangoPorFrecuencia();
        });
    }

    if (inputFechaInicioLote) {
        inputFechaInicioLote.addEventListener('change', () => {
            inputFechaFinLote?.setCustomValidity('');
            ajustarRangoPorFrecuencia();
        });
    }

    if (inputFechaFinLote) {
        inputFechaFinLote.addEventListener('change', validarRangoSegunFrecuencia);
    }

    const formGenerarLote = modalGenerarLote?.querySelector('form');
    if (formGenerarLote) {
        formGenerarLote.addEventListener('submit', (e) => {
            validarRangoSegunFrecuencia();
            if (!formGenerarLote.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                formGenerarLote.reportValidity();
            } else {
                bloquearBotonSubmit(formGenerarLote, "Calculando Nómina...");
            }
        });
    }

    // ==============================================================
    // 3. BUSCADOR LOCAL EN LA TABLA DE DETALLES (RECIBOS)
    // ==============================================================
    if (searchInput && tablaDetalles) {

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });

        searchInput.addEventListener('keyup', function () {
            const searchTerm = this.value.toLowerCase().trim();
            const filas = tablaDetalles.querySelectorAll('tbody tr:not(.empty-msg-row)');

            let filasVisibles = 0;

            filas.forEach(fila => {
                const dataSearch = fila.getAttribute('data-search') || '';

                if (dataSearch.includes(searchTerm)) {
                    fila.style.display = ''; 
                    filasVisibles++;
                } else {
                    fila.style.display = 'none'; 
                }
            });

            const tbody = tablaDetalles.querySelector('tbody');
            let emptyRow = tbody.querySelector('.empty-msg-row-search');

            if (filasVisibles === 0 && filas.length > 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.className = 'empty-msg-row-search border-bottom-0';
                    tbody.appendChild(emptyRow);
                }
                emptyRow.style.display = '';
                emptyRow.innerHTML = `
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>
                        No se encontraron empleados que coincidan con "<b>${searchTerm}</b>".
                    </td>
                `;
            } else if (emptyRow) {
                emptyRow.style.display = 'none';
            }
        });
    }

    // ==============================================================
    // 4. SEGURIDAD ADICIONAL (UX y Prevención de doble envío)
    // ==============================================================
    
    function bloquearBotonSubmit(form, textoCarga = "Procesando...") {
        const btnSubmit = form.querySelector('button[type="submit"]');
        if (btnSubmit) {
            if (!btnSubmit.dataset.originalHtml) {
                btnSubmit.dataset.originalHtml = btnSubmit.innerHTML;
            }
            btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${textoCarga}`;
            btnSubmit.classList.add('disabled');
            setTimeout(() => { btnSubmit.disabled = true; }, 10);
        }
    }

    function restaurarBotonSubmit(form) {
        const btnSubmit = form.querySelector('button[type="submit"]');
        if (btnSubmit && btnSubmit.dataset.originalHtml) {
            btnSubmit.innerHTML = btnSubmit.dataset.originalHtml;
            btnSubmit.classList.remove('disabled');
            btnSubmit.disabled = false;
        }
    }

    const formPagarLote = document.querySelector('#modalPagarLote form');
    if (formPagarLote) {
        formPagarLote.addEventListener('submit', function (e) {
            if(this.checkValidity()) bloquearBotonSubmit(this, "Registrando Pago...");
        });
    }

    const formAjustar = document.querySelector('#modalAjustarNomina form');
    if (formAjustar) {
        formAjustar.addEventListener('submit', function (e) {
            if (!validarDuplicadosMovimientos()) {
                e.preventDefault();
                e.stopPropagation();
                alert('Hay movimientos repetidos. Ajusta tipo/categoría/descripción para continuar.');
                return;
            }
            if(this.checkValidity()) bloquearBotonSubmit(this, "Guardando Ajuste...");
        });
    }

    const formsEnVista = document.querySelectorAll('form');
    formsEnVista.forEach(form => {
        if (form.getAttribute('action') && form.getAttribute('action').includes('aprobar')) {
            form.addEventListener('submit', function (e) {
                bloquearBotonSubmit(this, "Aprobando y Guardando...");
            });
        }
    });

});
