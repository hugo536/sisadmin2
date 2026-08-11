/**
 * LÓGICA PARA EL MÓDULO DE PLANILLAS Y PAGOS
 * Archivo: public/assets/js/rrhh/planillas.js
 * Compatible con arquitectura SPA
 */

(function() {
    'use strict';

    function iniciarModuloPlanillas() {
        const appContenedor = document.getElementById('planillasApp');
        
        if (!appContenedor || appContenedor.dataset.iniciado === '1') return;
        appContenedor.dataset.iniciado = '1';

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

        function crearItemMovimiento(data = {}) {
            if (!contenedorMovimientos || !tplMovimiento) return null;
            const nodo = tplMovimiento.content.firstElementChild.cloneNode(true);
            
            const tipo = (data.tipo || '').toString().trim().toUpperCase();
            const categoria = (data.categoria || '').toString().trim();
            const descripcion = (data.descripcion || '').toString().trim();
            const monto = Number.parseFloat(data.monto ?? 0);
            
            const idConcepto = data.id_concepto || '';
            const idAdelantoRef = data.id_adelanto_ref || '';

            const inputTipo = nodo.querySelector('[data-name="tipo_concepto"]');
            const inputCategoria = nodo.querySelector('[data-name="categoria_concepto"]');
            const inputDescripcion = nodo.querySelector('[data-name="descripcion"]');
            const inputMonto = nodo.querySelector('[data-name="monto"]');
            
            const inputIdConcepto = nodo.querySelector('[data-name="id_concepto"]');
            const inputIdAdelantoRef = nodo.querySelector('[data-name="id_adelanto_ref"]');
            const msgAdelanto = nodo.querySelector('.js-msg-adelanto');

            if (inputTipo && (tipo === 'PERCEPCION' || tipo === 'DEDUCCION')) inputTipo.value = tipo;
            if (inputCategoria && categoria !== '') inputCategoria.value = categoria;
            if (inputDescripcion) inputDescripcion.value = descripcion;
            if (inputMonto && Number.isFinite(monto) && monto > 0) inputMonto.value = monto.toFixed(2);
            
            if (inputIdConcepto) inputIdConcepto.value = idConcepto;
            if (inputIdAdelantoRef) inputIdAdelantoRef.value = idAdelantoRef;

            if (idAdelantoRef !== '' || categoria === 'Adelanto') {
                if (msgAdelanto) msgAdelanto.classList.remove('d-none');
                
                if (inputTipo) { inputTipo.setAttribute('readonly', true); inputTipo.style.pointerEvents = 'none'; }
                if (inputCategoria) { inputCategoria.setAttribute('readonly', true); inputCategoria.style.pointerEvents = 'none'; }
                if (inputDescripcion) { inputDescripcion.setAttribute('readonly', true); }
                
                const btnRemove = nodo.querySelector('.js-remove-movimiento');
                if (btnRemove) {
                    btnRemove.style.display = 'none';
                }
            }

            contenedorMovimientos.appendChild(nodo);
            return nodo;
        }

        function agregarMovimientoInicial() {
            if (!contenedorMovimientos || !tplMovimiento) return;
            crearItemMovimiento();
            renombrarCamposMovimientos();
        }

        async function cargarMovimientosGuardados(idDetalle) {
            if (!contenedorMovimientos || !idDetalle) return;
            const url = `${window.BASE_URL}?ruta=planillas&accion=movimientos_detalle&id_detalle=${encodeURIComponent(idDetalle)}`;

            try {
                const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                const movimientos = Array.isArray(data?.movimientos) ? data.movimientos : [];

                contenedorMovimientos.innerHTML = '';
                if (movimientos.length === 0) {
                    agregarMovimientoInicial();
                    return;
                }

                movimientos.forEach((mov) => {
                    crearItemMovimiento({
                        id_concepto: mov.id || '',
                        id_adelanto_ref: mov.id_adelanto_ref || '',
                        tipo: mov.tipo,
                        categoria: mov.categoria,
                        descripcion: mov.descripcion,
                        monto: mov.monto,
                    });
                });
                renombrarCamposMovimientos();
            } catch (error) {
                console.error('No se pudieron cargar movimientos guardados', error);
                contenedorMovimientos.innerHTML = '';
                agregarMovimientoInicial();
            }
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
                if (vistos.has(llave)) return false;
                vistos.add(llave);
            }
            return true;
        }

        if (modalAjustar) {
            modalAjustar.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const nombre = button.getAttribute('data-nombre');

                const inputAjusteIdDetalle = document.getElementById('ajusteIdDetalle');
                const spanAjusteNombre = document.getElementById('ajusteNombreEmpleado');

                if (inputAjusteIdDetalle) inputAjusteIdDetalle.value = id;
                if (spanAjusteNombre) spanAjusteNombre.textContent = nombre;

                const formAjuste = modalAjustar.querySelector('form');
                if (formAjuste) {
                    if (contenedorMovimientos) {
                        contenedorMovimientos.innerHTML = '<div class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando ajustes...</div>';
                        cargarMovimientosGuardados(id);
                    }
                    restaurarBotonSubmit(formAjuste);
                }
            });
        }

        if (btnAgregarMovimiento) {
            btnAgregarMovimiento.addEventListener('click', (e) => {
                e.preventDefault();
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

        const formAjustar = document.querySelector('#modalAjustarNomina form');
        if (formAjustar) {
            formAjustar.addEventListener('submit', function (e) {
                if (!validarDuplicadosMovimientos()) {
                    e.preventDefault(); e.stopPropagation();
                    if(typeof Swal !== 'undefined') {
                        Swal.fire('Atención', 'Hay movimientos repetidos. Ajusta tipo/categoría/descripción para continuar.', 'warning');
                    } else {
                        alert('Hay movimientos repetidos. Ajusta tipo/categoría/descripción para continuar.');
                    }
                    return;
                }
                if (this.checkValidity()) bloquearBotonSubmit(this, "Guardando...");
            });
        }

        const modalGenerarLote = document.getElementById('modalGenerarLote');
        const selectFrecuenciaLote = document.getElementById('frecuenciaLote');
        const inputFechaInicioLote = document.getElementById('fechaInicioLote');
        const inputFechaFinLote = document.getElementById('fechaFinLote');
        const ayudaFrecuenciaLote = document.getElementById('ayudaFrecuenciaLote');
        const inputNombreGenerado = document.getElementById('nombreGeneradoLote');

        const PERIODOS_DIAS = { TODOS: 30, SEMANAL: 7, QUINCENAL: 15, MENSUAL: 30 };
        const MENSAJES_PERIODO = {
            TODOS: 'Rango libre recomendado hasta 30 días. Se calcularán todos los empleados activos.',
            SEMANAL: 'Se configuró un rango de 7 días para empleados con frecuencia semanal.',
            QUINCENAL: 'Se configuró un rango de 15 días para empleados con frecuencia quincenal.',
            MENSUAL: 'Se configuró un rango de 30 días para empleados con frecuencia mensual.'
        };

        function formatDateISO(date) { return date.toISOString().slice(0, 10); }
        function formatLatino(dateStr) { if (!dateStr) return ''; const [year, month, day] = dateStr.split('-'); return `${day}/${month}/${year}`; }
        function addDays(dateValue, days) { const date = new Date(dateValue + 'T00:00:00'); if (isNaN(date.getTime())) return null; date.setDate(date.getDate() + days); return date; }

        function actualizarNombreLote() {
            if (inputNombreGenerado && inputFechaInicioLote.value && inputFechaFinLote.value) {
                inputNombreGenerado.value = `NOM - ${formatLatino(inputFechaInicioLote.value)} al ${formatLatino(inputFechaFinLote.value)}`;
            }
        }

        function ajustarRangoPorFrecuencia() {
            if (!selectFrecuenciaLote || !inputFechaInicioLote || !inputFechaFinLote) return;
            const frecuencia = (selectFrecuenciaLote.value || 'TODOS').toUpperCase();
            const diasPeriodo = PERIODOS_DIAS[frecuencia] ?? 30;

            if (!inputFechaInicioLote.value) {
                const hoy = new Date(); hoy.setHours(0,0,0,0);
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
            if (!selectFrecuenciaLote || !inputFechaInicioLote || !inputFechaFinLote || !inputFechaInicioLote.value || !inputFechaFinLote.value) return;

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
            modalGenerarLote.addEventListener('shown.bs.modal', () => {
                ajustarRangoPorFrecuencia();
                const formGenerarLote = modalGenerarLote.querySelector('form');
                if (formGenerarLote) restaurarBotonSubmit(formGenerarLote);
            });
        }
        if (selectFrecuenciaLote) selectFrecuenciaLote.addEventListener('change', () => { inputFechaFinLote?.setCustomValidity(''); ajustarRangoPorFrecuencia(); });
        if (inputFechaInicioLote) inputFechaInicioLote.addEventListener('change', () => { inputFechaFinLote?.setCustomValidity(''); ajustarRangoPorFrecuencia(); });
        if (inputFechaFinLote) inputFechaFinLote.addEventListener('change', validarRangoSegunFrecuencia);

        const formGenerarLote = modalGenerarLote?.querySelector('form');
        if (formGenerarLote) {
            formGenerarLote.addEventListener('submit', (e) => {
                validarRangoSegunFrecuencia();
                if (!formGenerarLote.checkValidity()) {
                    e.preventDefault(); e.stopPropagation();
                    formGenerarLote.reportValidity();
                } else {
                    bloquearBotonSubmit(formGenerarLote, "Calculando Nómina...");
                }
            });
        }

        const searchInput = document.getElementById('searchDetalles');
        const tablaDetalles = document.getElementById('tablaDetallesNomina');

        if (searchInput && tablaDetalles) {
            searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
            searchInput.addEventListener('keyup', function () {
                const searchTerm = this.value.toLowerCase().trim();
                const filas = tablaDetalles.querySelectorAll('tbody tr:not(.empty-msg-row)');
                let filasVisibles = 0;

                filas.forEach(fila => {
                    const dataSearch = fila.getAttribute('data-search') || '';
                    if (dataSearch.includes(searchTerm)) { fila.style.display = ''; filasVisibles++; }
                    else fila.style.display = 'none';
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

        function bloquearBotonSubmit(form, textoCarga = "Procesando...") {
            const btnSubmit = form.querySelector('button[type="submit"]');
            if (btnSubmit) {
                if (!btnSubmit.dataset.originalHtml) btnSubmit.dataset.originalHtml = btnSubmit.innerHTML;
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

        const formCerrar = document.getElementById('formCerrarLote');
        if (formCerrar) {
            formCerrar.addEventListener('submit', function (e) {
                e.preventDefault();

                const hayConflictos = tablaDetalles && tablaDetalles.querySelector('.bi-exclamation-triangle-fill') !== null;

                if (hayConflictos) {
                    if (!window.Swal) {
                        alert('No se puede cerrar la planilla. Existen empleados con asistencia incompleta.');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se puede cerrar la planilla',
                            html: 'Existen empleados con asistencia incompleta.<br>Corrige los registros marcados en rojo antes de continuar.',
                            confirmButtonText: 'Entendido'
                        });
                    }
                    return;
                }

                if (!window.Swal) {
                    if (confirm("¿Estás seguro? Cerrarás la planilla y ya no podrás agregar bonos ni descuentos.")) {
                        bloquearBotonSubmit(formCerrar, "Cerrando...");
                        HTMLFormElement.prototype.submit.call(formCerrar);
                    }
                } else {
                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: "Cerrarás la planilla y ya no podrás agregar bonos ni descuentos manuales.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-lock-fill me-1"></i> Sí, cerrar planilla',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            bloquearBotonSubmit(formCerrar, "Cerrando...");
                            // CAMBIO CLAVE AQUI: Forzamos el submit real saltando el EventListener
                            HTMLFormElement.prototype.submit.call(formCerrar);
                        }
                    });
                }
            });
        }

        const formPagarLote = document.getElementById('formPagarLote');
        if (formPagarLote) {
            formPagarLote.addEventListener('submit', function (e) {
                if (this.checkValidity()) {
                    bloquearBotonSubmit(this, "Emitiendo Pagos...");
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarModuloPlanillas);
    } else {
        iniciarModuloPlanillas();
    }

})();

window.imprimirSiEsValido = function(idLote, esBorrador, tienePagos) {
    if (esBorrador) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Planilla en Edición',
                text: 'Debes "Cerrar Planilla" (botón verde) para guardar los cálculos antes de poder generar e imprimir las boletas.',
                confirmButtonColor: '#198754'
            });
        } else {
            alert('Debes "Cerrar Planilla" para guardar los cálculos antes de imprimir.');
        }
        return;
    }
    
    if (!tienePagos) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Lote sin pagos',
                text: 'No se encontraron empleados con montos a pagar mayores a S/ 0.00 en este lote.',
                confirmButtonColor: '#0d6efd'
            });
        } else {
            alert('No se encontraron pagos mayores a S/ 0.00.');
        }
        return; 
    }

    const url = window.BASE_URL + '?ruta=planillas/imprimir_masivo&id_lote=' + idLote;
    window.open(url, '_blank');
};