/**
 * Lógica específica para Tesorería - Cuentas por Pagar (CxP)
 * Archivo: assets/js/tesoreria/cxp.js
 */
(function arrancarCxp() {
    'use strict';

    const cxpApp = document.getElementById('tesoreriaCxpApp');
    if (!cxpApp) return;

    // ========================================================================
    // 1. LÓGICA SPA: FILTROS, PESTAÑAS Y PAGINACIÓN CON AJAX
    // ========================================================================
    const formFiltros = document.getElementById('formFiltrosCxp');
    const contenedorTabla = document.getElementById('contenedorTablaCxp');
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

                const nuevoContenedor = doc.getElementById('contenedorTablaCxp');
                if (nuevoContenedor) {
                    contenedorTabla.innerHTML = nuevoContenedor.innerHTML;
                }

                if (typeof bootstrap !== 'undefined') {
                    [].slice.call(contenedorTabla.querySelectorAll('[data-bs-toggle="tooltip"]'))
                        .forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }
                if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                    window.ERPTable.autoInitFromDataset(cxpApp);
                }
            } catch (error) {
                console.error('Error AJAX CxP:', error);
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

        document.querySelectorAll('.js-tab-cxp').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const vista = e.currentTarget.getAttribute('data-vista');
                if (inputVistaGlobal) inputVistaGlobal.value = vista;
                
                document.querySelectorAll('.js-tab-cxp').forEach(t => {
                    t.classList.remove('active', 'text-warning-emphasis', 'border-warning', 'border-bottom-0', 'bg-white');
                    t.classList.add('text-secondary', 'bg-light', 'border-0');
                });
                e.currentTarget.classList.remove('text-secondary', 'bg-light', 'border-0');
                e.currentTarget.classList.add('active', 'text-warning-emphasis', 'border-warning', 'border-bottom-0', 'bg-white');

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
    // --- FUNCIÓN MAGIA: FILTRADO DE MÉTODOS ESTRICTO (GLOBAL) ---
    // ========================================================================
    window.filtrarMetodosPorCuentaCxp = function(selectCuenta, selectMetodo) {
        if (!selectCuenta || !selectMetodo) return;

        const optSeleccionada = selectCuenta.options[selectCuenta.selectedIndex];
        
        if (!optSeleccionada || !optSeleccionada.value) {
            selectMetodo.value = '';
            selectMetodo.disabled = true;
            return;
        }

        let rawMetodos = optSeleccionada.getAttribute('data-metodos');
        let permitidos = [];

        if (rawMetodos && rawMetodos !== 'null' && rawMetodos !== '') {
            try {
                let parsed = rawMetodos;
                while(typeof parsed === 'string') parsed = JSON.parse(parsed);
                
                if (Array.isArray(parsed)) {
                    permitidos = parsed.map(m => String(m).trim().toLowerCase());
                }
            } catch(e) { console.error("Error parseando JSON de métodos", e); }
        }

        let primerValido = null;
        let seleccionActualValida = false;
        const valorActual = selectMetodo.value;
        let opcionesValidasCount = 0;

        Array.from(selectMetodo.options).forEach(opt => {
            if (!opt.value) return; 
            
            const nombreMetodo = opt.textContent.trim().toLowerCase();
            const esValido = permitidos.some(p => nombreMetodo.includes(p) || p.includes(nombreMetodo));
            
            opt.hidden = !esValido;
            opt.disabled = !esValido;

            if (esValido) {
                opcionesValidasCount++;
                if (!primerValido) primerValido = opt.value;
                if (opt.value === valorActual) seleccionActualValida = true;
            }
        });

        if (opcionesValidasCount === 0) {
            selectMetodo.value = '';
            selectMetodo.disabled = true;
        } else {
            selectMetodo.disabled = false;
            if (!seleccionActualValida) {
                selectMetodo.value = primerValido || '';
            }
        }
    };


    // ========================================================================
    // 2. LÓGICA DE PAGO MANUAL (DOM DINÁMICO & SPA BLINDADO)
    // ========================================================================

    const filtrarCuentasPorMoneda = (selectMoneda, selectCuenta) => {
        if (!selectMoneda || !selectCuenta) return;
        const moneda = String(selectMoneda.value || '').toUpperCase();
        let primeraValida = null;

        Array.from(selectCuenta.options).forEach(opt => {
            if (!opt.value) return; 
            const optMoneda = String(opt.dataset.moneda || '').toUpperCase();
            const esValida = !moneda || optMoneda === moneda;
            
            opt.hidden = !esValida;
            opt.disabled = !esValida;
            if (esValida && !primeraValida) primeraValida = opt.value;
        });
        selectCuenta.value = primeraValida || '';
        selectCuenta.dispatchEvent(new Event('change'));
    };

    const actualizarDeudaManual = () => {
        // Búsqueda dinámica para evitar Stale Closures
        const selectProveedor = document.getElementById('pagoManualProveedor');
        const hintDeudaManual = document.getElementById('pagoManualDeudaHint');
        const selectMonedaManual = document.getElementById('pagoManualMoneda');
        const inputMontoManual = document.getElementById('pagoManualMontoInput');

        if (!selectProveedor || !hintDeudaManual) return;
        const idTercero = selectProveedor.value;
        const moneda = selectMonedaManual ? selectMonedaManual.value : 'PEN';
        
        if (!idTercero) {
            hintDeudaManual.innerHTML = '';
            return;
        }

        const opt = selectProveedor.querySelector(`option[value="${idTercero}"]`);
        const deuda = parseFloat(opt ? opt.getAttribute('data-deuda') : 0) || 0;

        if (deuda > 0) {
            hintDeudaManual.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Debe: ${moneda} ${deuda.toFixed(2)}</span>`;
            if (inputMontoManual && parseFloat(inputMontoManual.value) > deuda) {
                inputMontoManual.value = deuda.toFixed(2);
            }
        } else {
            hintDeudaManual.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Al día (S/ 0.00)</span>`;
        }
    };

    const modalPagoManual = document.getElementById('modalPagoManual');

    // CANDADO SPA: Evitar apilamiento de Event Listeners
    if (modalPagoManual && !modalPagoManual.dataset.eventosCxp) {
        modalPagoManual.dataset.eventosCxp = '1';

        modalPagoManual.addEventListener('shown.bs.modal', () => {
            const selectMonedaManual = document.getElementById('pagoManualMoneda');
            const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
            const selectProveedor = document.getElementById('pagoManualProveedor');

            filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
            
            if (typeof window.AppSelects !== 'undefined' && selectProveedor) {
                if (!selectProveedor.tomselect && !selectProveedor.classList.contains('tomselected')) {
                    window.AppSelects.initLocal('#pagoManualProveedor', {
                        dropdownParent: 'body',
                        onChange: actualizarDeudaManual
                    });
                }
            }
        });

        modalPagoManual.addEventListener('hidden.bs.modal', () => {
            const hintDeudaManual = document.getElementById('pagoManualDeudaHint');
            const hintSaldoManual = document.getElementById('textoSaldoDisponibleManual');
            const selectProveedor = document.getElementById('pagoManualProveedor');

            if (hintDeudaManual) hintDeudaManual.innerHTML = '';
            if (hintSaldoManual) hintSaldoManual.innerHTML = '';
            if (selectProveedor && selectProveedor.tomselect) selectProveedor.tomselect.clear(true);
        });

        // Candado Event Listener de Moneda
        const selectMonedaObserver = document.getElementById('pagoManualMoneda');
        if (selectMonedaObserver && !selectMonedaObserver.dataset.cxpMonedaListener) {
            selectMonedaObserver.addEventListener('change', () => {
                const selectMonedaManual = document.getElementById('pagoManualMoneda');
                const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
                filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
                actualizarDeudaManual();
            });
            selectMonedaObserver.dataset.cxpMonedaListener = '1';
        }
    }


    // ========================================================================
    // 3. LÓGICA DE PAGO REGULAR (MODAL DE DESGLOSE) BLINDADO SPA
    // ========================================================================
    const modalPago = document.getElementById('modalPago');
    const formPago = document.getElementById('formPago');
    const naturalezaSelect = document.getElementById('pagoNaturaleza');
    const inputCapital = document.getElementById('pagoMontoCapital');
    const inputInteres = document.getElementById('pagoMontoInteres');
    const centroCostoGroup = document.getElementById('grupoCentroCostoInteres');
    const inputCentroCosto = document.getElementById('pagoCentroCosto');

    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    window.recalcularModalPago = function() {
        const inputTotal = document.getElementById('pagoMonto');
        const hintDistribucion = document.getElementById('pagoDistribucionHint');
        const filas = document.querySelectorAll('.js-pago-distribucion-row');
        
        if (!inputTotal) return;

        let suma = 0;
        document.querySelectorAll('.js-pago-monto-distribucion').forEach(inp => {
            suma += parseFloat(inp.value) || 0;
        });

        inputTotal.value = suma > 0 ? suma.toFixed(2) : '';

        filas.forEach(fila => {
            const btnQuitar = fila.querySelector('.js-remove-pago-row');
            if (btnQuitar) {
                if (filas.length > 1) btnQuitar.classList.remove('d-none');
                else btnQuitar.classList.add('d-none');
            }
        });

        if (hintDistribucion) {
            const saldoStr = document.getElementById('pagoSaldo')?.value || '0';
            const saldoTotal = parseFloat(saldoStr);
            const diff = saldoTotal - suma;

            if (suma === 0) hintDistribucion.textContent = '';
            else if (Math.abs(diff) < 0.01) hintDistribucion.innerHTML = `<i class="bi bi-check2-all text-success"></i> Deuda cubierta`;
            else if (diff > 0) hintDistribucion.innerHTML = `<span class="text-warning-emphasis">Quedará debiendo: ${diff.toFixed(2)}</span>`;
            else hintDistribucion.innerHTML = `<span class="text-danger">Supera deuda por: ${Math.abs(diff).toFixed(2)}</span>`;
        }
        
        validarNaturaleza();
    };

    window.agregarFilaDistribucionCxp = function() {
        const container = document.getElementById('pagoDistribucionRows');
        const filas = container.querySelectorAll('.js-pago-distribucion-row');
        if (filas.length === 0) return;

        const nuevaFila = filas[0].cloneNode(true);
        
        nuevaFila.querySelector('.js-pago-cuenta').value = '';
        
        const selectMetodo = nuevaFila.querySelector('.js-pago-metodo');
        selectMetodo.value = '';
        selectMetodo.disabled = true;

        nuevaFila.querySelector('.js-pago-monto-distribucion').value = '';

        container.appendChild(nuevaFila);
        window.recalcularModalPago();
    };

    if (!window.cxpEventosGlobalesAtachados) {
        window.cxpEventosGlobalesAtachados = true;

        document.addEventListener('input', (e) => {
            if (e.target.matches('.js-pago-monto-distribucion')) {
                window.recalcularModalPago();
            }
        });

        document.addEventListener('change', (e) => {
            // Filtrado Mágico en Modal de Desglose
            if (e.target.matches('.js-pago-cuenta')) {
                const fila = e.target.closest('.js-pago-distribucion-row');
                if (fila) {
                    const selectMetodo = fila.querySelector('.js-pago-metodo');
                    window.filtrarMetodosPorCuentaCxp(e.target, selectMetodo);
                }
            }
            // Filtrado Mágico en Modal Manual y Lógica de Saldo Disponible
            else if (e.target.id === 'selectCuentaOrigenManual') {
                const selectMetodoManual = document.getElementById('pagoManualMetodoOrigen');
                window.filtrarMetodosPorCuentaCxp(e.target, selectMetodoManual);

                const hintSaldoManual = document.getElementById('textoSaldoDisponibleManual');
                const inputMontoManual = document.getElementById('pagoManualMontoInput');
                
                const opt = e.target.options[e.target.selectedIndex];
                if(!opt || opt.value === "") {
                    if (hintSaldoManual) hintSaldoManual.innerHTML = "";
                    if (inputMontoManual) inputMontoManual.removeAttribute('max');
                    return;
                }
                
                const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
                if (hintSaldoManual) hintSaldoManual.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: S/ ${saldoCuenta.toFixed(2)}`;
                
                const maximo = saldoCuenta > 0 ? saldoCuenta : 0;
                if (inputMontoManual) {
                    inputMontoManual.setAttribute('max', maximo);
                    
                    if(parseFloat(inputMontoManual.value) > maximo) {
                        inputMontoManual.value = maximo.toFixed(2);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'info',
                                title: 'Monto reajustado',
                                text: 'El monto superaba el saldo de la cuenta seleccionada.',
                                timer: 2500,
                                showConfirmButton: false
                            });
                        }
                    }
                }
            }
        });

        // Validación global al enviar el formulario del modal manual
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.closest('#modalPagoManual')) {
                const inputMontoManual = document.getElementById('pagoManualMontoInput');
                const monto = parseFloat(inputMontoManual?.value) || 0;
                if (monto <= 0) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: 'El monto debe ser mayor a 0.' });
                    }
                }
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.closest('#btnAddPagoDistribucion')) {
                window.agregarFilaDistribucionCxp();
            } 
            else if (e.target.closest('.js-remove-pago-row')) {
                const fila = e.target.closest('.js-pago-distribucion-row');
                if (document.querySelectorAll('.js-pago-distribucion-row').length > 1 && fila) {
                    fila.remove();
                    window.recalcularModalPago();
                }
            }
            else if (e.target.closest('.js-open-pago')) {
                const btn = e.target.closest('.js-open-pago');
                document.getElementById('pagoIdOrigen').value = btn.dataset.idOrigen;
                document.getElementById('pagoMoneda').value = btn.dataset.moneda;
                document.getElementById('pagoSaldo').value = parseFloat(btn.dataset.saldo).toFixed(2);
                
                const filas = document.querySelectorAll('.js-pago-distribucion-row');
                filas.forEach((r, i) => {
                    if (i === 0) {
                        r.querySelectorAll('input, select').forEach(inpt => inpt.value = '');
                        r.querySelector('.js-pago-monto-distribucion').value = '';
                    } else {
                        r.remove();
                    }
                });

                window.recalcularModalPago();
                const natSelect = document.getElementById('pagoNaturaleza');
                if (natSelect) natSelect.dispatchEvent(new Event('change'));
            }
        });
    }

    const validarNaturaleza = () => {
        const inputTotal = document.getElementById('pagoMonto');
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
            const capGroup = document.getElementById('grupoPagoCapital');
            const intGroup = document.getElementById('grupoPagoInteres');
            
            capGroup?.classList.toggle('d-none', val !== 'CAPITAL' && val !== 'MIXTO');
            intGroup?.classList.toggle('d-none', val !== 'INTERES' && val !== 'MIXTO');
            
            if (centroCostoGroup) {
                const mostrarInteres = val === 'INTERES' || val === 'MIXTO';
                centroCostoGroup.classList.toggle('d-none', !mostrarInteres);
                if (inputCentroCosto) inputCentroCosto.required = mostrarInteres;
            }

            validarNaturaleza();
        });
    }

    [inputCapital, inputInteres].forEach(el => el?.addEventListener('input', validarNaturaleza));

    if (formPago) {
        formPago.removeEventListener('submit', window.submitPagoHandler);
        window.submitPagoHandler = (e) => {
            const inputTotal = document.getElementById('pagoMonto');
            const total = parseFloat(inputTotal?.value || 0);
            
            if (total <= 0) {
                e.preventDefault(); e.stopImmediatePropagation();
                return Swal.fire('Atención', 'El monto a pagar debe ser mayor a 0.', 'warning');
            }
            
            if (naturalezaSelect?.value === 'MIXTO') {
                const cap = parseFloat(inputCapital?.value || 0);
                const int = parseFloat(inputInteres?.value || 0);
                if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    return Swal.fire('Error', 'Capital + Interés debe ser igual al Monto Total.', 'error');
                }
            }

            let montosPorCuenta = {};
            let saldosPorCuenta = {};
            let nombresCuentas = {};

            const filas = document.querySelectorAll('.js-pago-distribucion-row');
            filas.forEach(fila => {
                const selectCuenta = fila.querySelector('.js-pago-cuenta');
                const montoInput = fila.querySelector('.js-pago-monto-distribucion');
                
                if (selectCuenta && selectCuenta.value && montoInput) {
                    const idC = selectCuenta.value;
                    const opt = selectCuenta.options[selectCuenta.selectedIndex];
                    const saldo = parseFloat(opt.getAttribute('data-saldo')) || 0;
                    const montoFila = parseFloat(montoInput.value) || 0;

                    if (!montosPorCuenta[idC]) {
                        montosPorCuenta[idC] = 0;
                        saldosPorCuenta[idC] = saldo;
                        nombresCuentas[idC] = opt.text.split('(')[0].trim(); 
                    }
                    montosPorCuenta[idC] += montoFila;
                }
            });

            let erroresSaldo = [];
            for (const idC in montosPorCuenta) {
                if (montosPorCuenta[idC] > saldosPorCuenta[idC]) {
                    erroresSaldo.push(`La cuenta <b>${nombresCuentas[idC]}</b> no tiene saldo suficiente.<br>Intenta usar ${montosPorCuenta[idC].toFixed(2)} pero solo dispone de ${saldosPorCuenta[idC].toFixed(2)}.`);
                }
            }

            if (erroresSaldo.length > 0) {
                e.preventDefault(); e.stopImmediatePropagation();
                return Swal.fire({
                    icon: 'error',
                    title: 'Saldo insuficiente',
                    html: erroresSaldo.join('<br><br>')
                });
            }
        };
        formPago.addEventListener('submit', window.submitPagoHandler);
    }

    if (modalPago) {
        modalPago.addEventListener('hidden.bs.modal', () => {
            formPago.reset();
            const filas = document.querySelectorAll('.js-pago-distribucion-row');
            filas.forEach((r, i) => i === 0 ? r.querySelectorAll('input, select').forEach(inpt => inpt.value = '') : r.remove());
            window.recalcularModalPago();
            [inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }
})();