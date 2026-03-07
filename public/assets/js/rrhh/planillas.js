document.addEventListener('DOMContentLoaded', () => {

    // --- VARIABLES GLOBALES ---
    const searchInput = document.getElementById('searchDetalles');
    const tablaDetalles = document.getElementById('tablaDetallesNomina');

    // ==============================================================
    // 1. GESTIÓN DEL MODAL DE AJUSTES (BONOS/DEDUCCIONES)
    // ==============================================================
    const modalAjustar = document.getElementById('modalAjustarNomina');
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
                formAjuste.querySelector('input[name="monto"]').value = '';
                formAjuste.querySelector('input[name="descripcion"]').value = '';
            }
        });
    }

    // ==============================================================
    // 2. MODAL GENERAR LOTE: FRECUENCIA + RANGO DE FECHAS
    // ==============================================================
    const modalGenerarLote = document.getElementById('modalGenerarLote');
    const selectFrecuenciaLote = document.getElementById('frecuenciaLote');
    const inputFechaInicioLote = document.getElementById('fechaInicioLote');
    const inputFechaFinLote = document.getElementById('fechaFinLote');
    const ayudaFrecuenciaLote = document.getElementById('ayudaFrecuenciaLote');

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

    function addDays(dateValue, days) {
        const date = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        date.setDate(date.getDate() + days);
        return date;
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
    }

    if (modalGenerarLote) {
        modalGenerarLote.addEventListener('shown.bs.modal', ajustarRangoPorFrecuencia);
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
            }
        });
    }

    // ==============================================================
    // 3. BUSCADOR LOCAL EN LA TABLA DE DETALLES (RECIBOS)
    // ==============================================================
    if (searchInput && tablaDetalles) {

        // Prevenir que el "Enter" recargue la página en el buscador
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });

        // Filtrado en vivo (Keyup)
        searchInput.addEventListener('keyup', function () {
            const searchTerm = this.value.toLowerCase().trim();
            const filas = tablaDetalles.querySelectorAll('tbody tr:not(.empty-msg-row)');

            let filasVisibles = 0;

            filas.forEach(fila => {
                // Recuperamos la cadena de búsqueda (data-search)
                const dataSearch = fila.getAttribute('data-search') || '';

                if (dataSearch.includes(searchTerm)) {
                    fila.style.display = ''; // Mostrar
                    filasVisibles++;
                } else {
                    fila.style.display = 'none'; // Ocultar
                }
            });

            // Lógica para mostrar/ocultar el mensaje de "No hay resultados"
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
                    <td colspan="6" class="text-center text-muted py-5">
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
    // 4. SEGURIDAD ADICIONAL (Prevenir doble envíos en pagos)
    // ==============================================================
    const formPagarLote = document.querySelector('#modalPagarLote form');
    if (formPagarLote) {
        formPagarLote.addEventListener('submit', function (e) {
            // Evitar que el usuario presione 2 veces el botón de pagar
            const btnSubmit = this.querySelector('button[type="submit"]');
            if (btnSubmit) {
                // Cambiamos el texto e ícono
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                // Agregamos una clase visual y deshabilitamos el botón físicamente
                btnSubmit.classList.add('disabled');
                btnSubmit.disabled = true;
            }
        });
    }

});
