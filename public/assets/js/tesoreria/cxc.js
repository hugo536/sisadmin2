/**
 * Lógica específica para Tesorería - Cuentas por Cobrar (CxC)
 * Archivo: assets/js/tesoreria/cxc.js
 */
(function arrancarCxc() {
    'use strict';

    const cxcApp = document.getElementById('tesoreriaCxcApp');
    if (!cxcApp) return;

    // ========================================================================
    // 1. LÓGICA SPA: FILTROS, PESTAÑAS Y PAGINACIÓN CON AJAX
    // ========================================================================
    const formFiltros = document.getElementById('formFiltrosCxc');
    const contenedorTabla = document.getElementById('contenedorTablaCxc');
    const inputVistaGlobal = document.getElementById('inputVistaGlobal');
    let timerFiltro = null;

    if (formFiltros && contenedorTabla) {
        const cargarDatosAjax = async (urlStr) => {
            contenedorTabla.style.opacity = '0.4';
            contenedorTabla.style.pointerEvents = 'none';

            try {
                window.history.replaceState({}, '', urlStr);
                const response = await fetch(urlStr, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Error al obtener datos');

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const nuevoContenedor = doc.getElementById('contenedorTablaCxc');
                if (nuevoContenedor) {
                    contenedorTabla.innerHTML = nuevoContenedor.innerHTML;
                }

                if (typeof bootstrap !== 'undefined') {
                    [].slice.call(contenedorTabla.querySelectorAll('[data-bs-toggle="tooltip"]'))
                        .forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }
                if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                    window.ERPTable.autoInitFromDataset(cxcApp);
                }
            } catch (error) {
                console.error('Error AJAX CxC:', error);
            } finally {
                contenedorTabla.style.opacity = '1';
                contenedorTabla.style.pointerEvents = 'auto';
            }
        };

        const procesarFiltros = () => {
            const formData = new FormData(formFiltros);
            const url = new URL(window.location.origin + window.location.pathname);
            formData.forEach((value, key) => {
                if (value) url.searchParams.set(key, value);
            });
            cargarDatosAjax(url.toString());
        };

        formFiltros.addEventListener('input', (e) => {
            if (e.target.matches('.auto-submit')) {
                clearTimeout(timerFiltro);
                timerFiltro = setTimeout(procesarFiltros, 400);
            }
        });

        document.querySelectorAll('.js-tab-cxc').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const vista = e.currentTarget.getAttribute('data-vista');
                if (inputVistaGlobal) inputVistaGlobal.value = vista;
                
                document.querySelectorAll('.js-tab-cxc').forEach(t => {
                    t.classList.remove('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');
                    t.classList.add('text-secondary', 'bg-light', 'border-0');
                });
                e.currentTarget.classList.remove('text-secondary', 'bg-light', 'border-0');
                e.currentTarget.classList.add('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');

                procesarFiltros();
            });
        });

        contenedorTabla.addEventListener('click', (e) => {
            const linkPaginacion = e.target.closest('.pagination a.page-link');
            if (linkPaginacion) {
                e.preventDefault();
                cargarDatosAjax(linkPaginacion.href);
            }
        });

        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            procesarFiltros();
        });
    }

    // ========================================================================
    // 2. LÓGICA DE COBRO MANUAL (MEJORADA CON AJAX Y DOM DINÁMICO)
    // ========================================================================
    
    const filtrarCuentasPorMoneda = (selectMoneda, selectCuenta, opciones = {}) => {
        if (!selectMoneda || !selectCuenta) return;
        const moneda = String(selectMoneda.value || '').toUpperCase();
        const valorActual = selectCuenta.value;
        const debeSeleccionarPrimera = opciones.seleccionarPrimera === true;
        let primeraValida = null;
        let valorActualSigueValido = false;

        Array.from(selectCuenta.options).forEach(opt => {
            if (!opt.value) return;
            const optMoneda = String(opt.dataset.moneda || '').toUpperCase();
            const esValida = !moneda || optMoneda === moneda;

            opt.hidden = !esValida;
            opt.disabled = !esValida;
            if (esValida && !primeraValida) primeraValida = opt.value;
            if (esValida && opt.value === valorActual) valorActualSigueValido = true;
        });

        if (valorActualSigueValido) {
            selectCuenta.value = valorActual;
        } else {
            selectCuenta.value = debeSeleccionarPrimera ? (primeraValida || '') : '';
        }

        selectCuenta.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const actualizarDeudaManual = async () => {
        // Búsqueda dinámica en el DOM
        const selectCliente = document.getElementById('cobroManualCliente');
        const hintDeudaManual = document.getElementById('cobroManualDeudaHint');
        const selectMonedaManual = document.getElementById('cobroManualMoneda');
        const inputMontoManual = document.getElementById('cobroManualMontoInput');

        if (!selectCliente || !hintDeudaManual) return;
        
        const idTercero = selectCliente.value;
        const moneda = selectMonedaManual ? selectMonedaManual.value : 'PEN';
        
        if (!idTercero) {
            hintDeudaManual.innerHTML = '';
            return;
        }

        hintDeudaManual.innerHTML = `<span class="text-muted fw-bold"><i class="spinner-border spinner-border-sm me-1"></i>Calculando...</span>`;

        try {
            const url = `index.php?ruta=tesoreria/ajax_obtener_deuda_tercero&id_tercero=${idTercero}&moneda=${moneda}&tipo=CXC`;
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await response.json();

            if (json.ok) {
                const deuda = parseFloat(json.deuda) || 0;

                if (deuda > 0) {
                    hintDeudaManual.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Debe: ${moneda} ${deuda.toFixed(2)}</span>`;
                    
                    if (inputMontoManual && parseFloat(inputMontoManual.value) > deuda) {
                        inputMontoManual.value = deuda.toFixed(2);
                    }
                } else {
                    hintDeudaManual.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Al día (${moneda} 0.00)</span>`;
                }
            } else {
                hintDeudaManual.innerHTML = `<span class="text-danger fw-bold">Error al calcular</span>`;
            }
        } catch (error) {
            console.error("Error al obtener deuda:", error);
            hintDeudaManual.innerHTML = `<span class="text-danger fw-bold">Error de red</span>`;
        }
    };

    const sincronizarMetodoManual = () => {
        // Búsqueda dinámica en el DOM
        const cuentaSelect = document.getElementById('cobroManualCuentaDestino');
        const metodoSelect = document.getElementById('cobroManualMetodoDestino') || document.querySelector('#modalCobroManual select[name="id_metodo_pago"]');
        
        if (typeof window.filtrarMetodosPorCuenta === 'function' && cuentaSelect && metodoSelect) {
            window.filtrarMetodosPorCuenta(cuentaSelect, metodoSelect);
        }
    };

    const modalCobroManual = document.getElementById('modalCobroManual');

    if (modalCobroManual && !modalCobroManual.dataset.eventosCxc) {
        modalCobroManual.dataset.eventosCxc = '1';

        modalCobroManual.addEventListener('shown.bs.modal', () => {
            const selectCuentaManual = document.getElementById('cobroManualCuentaDestino');
            const selectMonedaManual = document.getElementById('cobroManualMoneda');
            const selectCliente = document.getElementById('cobroManualCliente');

            if (selectCuentaManual) selectCuentaManual.value = '';
            filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
            sincronizarMetodoManual();
            
            if (typeof window.AppSelects !== 'undefined' && selectCliente) {
                if (!selectCliente.tomselect && !selectCliente.classList.contains('tomselected')) {
                    window.AppSelects.initLocal('#cobroManualCliente', {
                        dropdownParent: 'body',
                        onChange: actualizarDeudaManual
                    });
                }
            }
            
            if (selectCliente && !selectCliente.dataset.cxcDeudaListener) {
                selectCliente.addEventListener('change', actualizarDeudaManual);
                selectCliente.dataset.cxcDeudaListener = '1';
            }
        });

        modalCobroManual.addEventListener('hidden.bs.modal', () => {
            const hintDeudaManual = document.getElementById('cobroManualDeudaHint');
            const inputMontoManual = document.getElementById('cobroManualMontoInput');
            const selectCuentaManual = document.getElementById('cobroManualCuentaDestino');
            const selectCliente = document.getElementById('cobroManualCliente');

            if (hintDeudaManual) hintDeudaManual.innerHTML = '';
            if (inputMontoManual) inputMontoManual.value = '';
            if (selectCuentaManual) selectCuentaManual.value = '';
            sincronizarMetodoManual();
            
            if (selectCliente && selectCliente.tomselect) {
                selectCliente.tomselect.clear(true);
            }
        });

        const selectMonedaObserver = document.getElementById('cobroManualMoneda');
        if (selectMonedaObserver && !selectMonedaObserver.dataset.cxcMonedaListener) {
            selectMonedaObserver.addEventListener('change', () => {
                const selectCuentaManual = document.getElementById('cobroManualCuentaDestino');
                const selectMonedaManual = document.getElementById('cobroManualMoneda');
                filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
                actualizarDeudaManual(); 
            });
            selectMonedaObserver.dataset.cxcMonedaListener = '1';
        }
    }

    // ========================================================================
    // 3. LÓGICA DE COBRO REGULAR (MODAL DE DESGLOSE) Y EVENTOS GLOBALES
    // ========================================================================
    const modalCobro = document.getElementById('modalCobro');
    const formCobro = document.getElementById('formCobro');
    const naturalezaSelect = document.getElementById('cobroNaturaleza');
    const inputCapital = document.getElementById('cobroMontoCapital');
    const inputInteres = document.getElementById('cobroMontoInteres');

    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    window.recalcularModalCobro = function() {
        const inputTotal = document.getElementById('cobroMonto');
        const hintDistribucion = document.getElementById('cobroDistribucionHint');
        const filas = document.querySelectorAll('.js-cobro-distribucion-row');
        
        if (!inputTotal) return;

        let suma = 0;
        document.querySelectorAll('.js-cobro-monto-distribucion').forEach(inp => {
            suma += parseFloat(inp.value) || 0;
        });

        inputTotal.value = suma > 0 ? suma.toFixed(2) : '';

        filas.forEach(fila => {
            const btnQuitar = fila.querySelector('.js-remove-cobro-row');
            if (btnQuitar) {
                if (filas.length > 1) btnQuitar.classList.remove('d-none');
                else btnQuitar.classList.add('d-none');
            }
        });

        if (hintDistribucion) {
            const saldoStr = document.getElementById('cobroSaldo')?.value || '0';
            const saldoTotal = parseFloat(saldoStr);
            const diff = saldoTotal - suma;

            if (suma === 0) hintDistribucion.textContent = '';
            else if (Math.abs(diff) < 0.01) hintDistribucion.innerHTML = `<i class="bi bi-check2-all text-success"></i> Deuda cubierta`;
            else if (diff > 0) hintDistribucion.innerHTML = `<span class="text-warning-emphasis">Quedará debiendo: ${diff.toFixed(2)}</span>`;
            else hintDistribucion.innerHTML = `<span class="text-danger">Supera deuda por: ${Math.abs(diff).toFixed(2)}</span>`;
        }
        
        validarNaturaleza();
    };

    window.agregarFilaDistribucion = function() {
        const container = document.getElementById('cobroDistribucionRows');
        const filas = container.querySelectorAll('.js-cobro-distribucion-row');
        if (filas.length === 0) return;

        const nuevaFila = filas[0].cloneNode(true);
        
        nuevaFila.querySelector('.js-cobro-cuenta').value = '';
        
        const selectMetodo = nuevaFila.querySelector('.js-cobro-metodo');
        selectMetodo.value = '';
        selectMetodo.disabled = true; 
        
        nuevaFila.querySelector('.js-cobro-monto-distribucion').value = '';

        container.appendChild(nuevaFila);
        window.recalcularModalCobro();
    };

    const parsearMetodosPermitidosCuenta = (rawMetodos) => {
        if (rawMetodos === undefined || rawMetodos === null || rawMetodos === '') {
            return { tieneFiltro: false, metodos: [] };
        }

        const rawTexto = String(rawMetodos).trim();
        if (rawTexto === '' || rawTexto.toLowerCase() === 'null') {
            return { tieneFiltro: false, metodos: [] };
        }

        try {
            let parsed = rawMetodos;
            while (typeof parsed === 'string') {
                parsed = JSON.parse(parsed);
            }

            if (Array.isArray(parsed)) {
                return { tieneFiltro: true, metodos: parsed };
            }
        } catch (e) {
            console.error('No se pudo parsear el JSON de métodos:', rawMetodos);
        }

        return { tieneFiltro: false, metodos: [] };
    };

    window.filtrarMetodosPorCuenta = function(selectCuenta, selectMetodo) {
        if (!selectCuenta || !selectMetodo) return;

        const optSeleccionada = selectCuenta.options[selectCuenta.selectedIndex];
        const idCuentaSeleccionada = parseInt(selectCuenta.value);
        const valorPrevio = selectMetodo.value;

        selectMetodo.innerHTML = '<option value="" selected disabled>Seleccione un método...</option>';

        if (!optSeleccionada || !optSeleccionada.value || !idCuentaSeleccionada || isNaN(idCuentaSeleccionada)) {
            selectMetodo.disabled = true;
            return;
        }

        const arrayCuentas = Array.isArray(window.TESORERIA_CUENTAS)
                             ? window.TESORERIA_CUENTAS
                             : Object.values(window.TESORERIA_CUENTAS || {});

        const arrayMetodos = Array.isArray(window.TESORERIA_METODOS)
                             ? window.TESORERIA_METODOS
                             : Object.values(window.TESORERIA_METODOS || {});

        const cuentaObj = arrayCuentas.find(c => parseInt(c.id) === idCuentaSeleccionada) || {};

        const rawMetodos = optSeleccionada.getAttribute('data-metodos') ?? cuentaObj.metodos_pago;
        const { tieneFiltro, metodos } = parsearMetodosPermitidosCuenta(rawMetodos);
        const permitidosNormalizados = metodos.map(m => String(m).trim().toLowerCase());

        let primerValido = null;
        let encontroPrevio = false;

        arrayMetodos.forEach(m => {
            const nombreDB = String(m.nombre || '').trim().toLowerCase();
            const esValido = !tieneFiltro || permitidosNormalizados.some(p => nombreDB.includes(p) || p.includes(nombreDB));

            if (esValido) {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.nombre;
                selectMetodo.appendChild(opt);

                if (!primerValido) primerValido = m.id;
                if (String(m.id) === String(valorPrevio)) encontroPrevio = true;
            }
        });

        if (selectMetodo.options.length <= 1) {
            selectMetodo.innerHTML = '<option value="" selected disabled>Sin métodos configurados</option>';
            selectMetodo.disabled = true;
        } else {
            selectMetodo.disabled = false;
            if (encontroPrevio) selectMetodo.value = valorPrevio;
            else if (primerValido) selectMetodo.value = primerValido;
        }
    };


    if (!window.cxcEventosGlobalesAtachados) {
        window.cxcEventosGlobalesAtachados = true;

        document.addEventListener('input', (e) => {
            if (e.target.matches('.js-cobro-monto-distribucion')) {
                window.recalcularModalCobro();
            }
        });

        document.addEventListener('change', (e) => {
            if (e.target.matches('.js-cobro-cuenta')) {
                const fila = e.target.closest('.js-cobro-distribucion-row');
                if (fila) {
                    const selectMetodo = fila.querySelector('.js-cobro-metodo');
                    window.filtrarMetodosPorCuenta(e.target, selectMetodo);
                }
            }
            else if (e.target.id === 'cobroManualCuentaDestino') {
                const metodoVivo = document.getElementById('cobroManualMetodoDestino') || document.querySelector('#modalCobroManual select[name="id_metodo_pago"]');
                window.filtrarMetodosPorCuenta(e.target, metodoVivo);
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.closest('#btnAddCobroDistribucion')) {
                window.agregarFilaDistribucion();
            } 
            else if (e.target.closest('.js-remove-cobro-row')) {
                const fila = e.target.closest('.js-cobro-distribucion-row');
                if (document.querySelectorAll('.js-cobro-distribucion-row').length > 1 && fila) {
                    fila.remove();
                    window.recalcularModalCobro();
                }
            }
            else if (e.target.closest('.js-open-cobro')) {
                const btn = e.target.closest('.js-open-cobro');
                document.getElementById('cobroIdOrigen').value = btn.dataset.idOrigen;
                document.getElementById('cobroMoneda').value = btn.dataset.moneda;
                document.getElementById('cobroSaldo').value = parseFloat(btn.dataset.saldo).toFixed(2);
                
                const filas = document.querySelectorAll('.js-cobro-distribucion-row');
                filas.forEach((r, i) => {
                    if (i === 0) {
                        r.querySelectorAll('input, select').forEach(inpt => inpt.value = '');
                        r.querySelector('.js-cobro-monto-distribucion').value = ''; 
                    } else {
                        r.remove();
                    }
                });

                window.recalcularModalCobro();
                const natSelect = document.getElementById('cobroNaturaleza');
                if (natSelect) natSelect.dispatchEvent(new Event('change'));
            }
        });
    }

    const validarNaturaleza = () => {
        const inputTotal = document.getElementById('cobroMonto');
        if (!naturalezaSelect || !inputTotal) return;
        
        const val = naturalezaSelect.value;
        const capital = parseFloat(inputCapital?.value || 0);
        const interes = parseFloat(inputInteres?.value || 0);
        const total = parseFloat(inputTotal.value || 0);

        if (val === 'MIXTO' && roundTo(capital + interes, 2) !== roundTo(total, 2)) {
            inputCapital?.classList.add('is-invalid');
            inputInteres?.classList.add('is-invalid');
        } else {
            inputCapital?.classList.remove('is-invalid');
            inputInteres?.classList.remove('is-invalid');
        }
    };

    if (naturalezaSelect) {
        naturalezaSelect.addEventListener('change', () => {
            const val = naturalezaSelect.value;
            const capGroup = document.getElementById('grupoCobroCapital');
            const intGroup = document.getElementById('grupoCobroInteres');
            
            capGroup?.classList.toggle('d-none', val !== 'CAPITAL' && val !== 'MIXTO');
            intGroup?.classList.toggle('d-none', val !== 'INTERES' && val !== 'MIXTO');
            
            validarNaturaleza();
        });
    }

    [inputCapital, inputInteres].forEach(el => el?.addEventListener('input', validarNaturaleza));

    if (formCobro) {
        formCobro.removeEventListener('submit', window.submitCobroHandler);
        window.submitCobroHandler = (e) => {
            const inputTotal = document.getElementById('cobroMonto');
            const total = parseFloat(inputTotal?.value || 0);
            
            if (naturalezaSelect?.value === 'MIXTO') {
                const cap = parseFloat(inputCapital?.value || 0);
                const int = parseFloat(inputInteres?.value || 0);
                if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    return Swal.fire('Error', 'Capital + Mora debe ser igual al Monto Total.', 'error');
                }
            }
        };
        formCobro.addEventListener('submit', window.submitCobroHandler);
    }

    if (modalCobro) {
        modalCobro.addEventListener('hidden.bs.modal', () => {
            formCobro.reset();
            const filas = document.querySelectorAll('.js-cobro-distribucion-row');
            filas.forEach((r, i) => i === 0 ? r.querySelectorAll('input, select').forEach(inpt => inpt.value = '') : r.remove());
            window.recalcularModalCobro();
            [inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }
})();