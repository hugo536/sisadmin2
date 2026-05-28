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

                // Reinicializar Tooltips y Buscador ERPTable
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

        // Inputs Auto-submit (Selects y Fechas)
        formFiltros.addEventListener('input', (e) => {
            if (e.target.matches('.auto-submit')) {
                clearTimeout(timerFiltro);
                timerFiltro = setTimeout(procesarFiltros, 400);
            }
        });

        // Tabs (Pestañas)
        document.querySelectorAll('.js-tab-cxp').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const vista = e.currentTarget.getAttribute('data-vista');
                if (inputVistaGlobal) inputVistaGlobal.value = vista;
                
                // Efecto visual en tabs
                document.querySelectorAll('.js-tab-cxp').forEach(t => {
                    t.classList.remove('active', 'text-warning-emphasis', 'border-warning', 'border-bottom-0', 'bg-white');
                    t.classList.add('text-secondary', 'bg-light', 'border-0');
                });
                e.currentTarget.classList.remove('text-secondary', 'bg-light', 'border-0');
                e.currentTarget.classList.add('active', 'text-warning-emphasis', 'border-warning', 'border-bottom-0', 'bg-white');

                procesarFiltros();
            });
        });

        // Paginación
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
    // 2. LÓGICA DE PAGO MANUAL
    // ========================================================================
    const modalPagoManual = document.getElementById('modalPagoManual');
    const selectProveedor = document.getElementById('pagoManualProveedor');
    const selectMonedaManual = document.getElementById('pagoManualMoneda');
    const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
    const hintDeudaManual = document.getElementById('pagoManualDeudaHint');
    const hintSaldoManual = document.getElementById('textoSaldoDisponibleManual');
    const inputMontoManual = document.getElementById('pagoManualMontoInput');

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
        selectCuenta.dispatchEvent(new Event('change')); // Disparar change para actualizar hint de saldo
    };

    const actualizarDeudaManual = () => {
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

    if (modalPagoManual) {
        modalPagoManual.addEventListener('shown.bs.modal', () => {
            filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
            if (typeof window.AppSelects !== 'undefined' && !selectProveedor.tomselect) {
                window.AppSelects.initLocal('#pagoManualProveedor', {
                    dropdownParent: 'body',
                    onChange: actualizarDeudaManual
                });
            }
        });

        modalPagoManual.addEventListener('hidden.bs.modal', () => {
            if (hintDeudaManual) hintDeudaManual.innerHTML = '';
            if (hintSaldoManual) hintSaldoManual.innerHTML = '';
            if (selectProveedor && selectProveedor.tomselect) selectProveedor.tomselect.clear(true);
        });

        if (selectMonedaManual) {
            selectMonedaManual.addEventListener('change', () => {
                filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
                actualizarDeudaManual();
            });
        }
        
        // Controlar saldo disponible al cambiar cuenta manual
        if (selectCuentaManual && inputMontoManual && hintSaldoManual) {
            selectCuentaManual.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if(!opt || opt.value === "") {
                    hintSaldoManual.innerHTML = "";
                    inputMontoManual.removeAttribute('max');
                    return;
                }
                
                const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
                hintSaldoManual.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: $${saldoCuenta.toFixed(2)}`;
                
                const maximo = saldoCuenta > 0 ? saldoCuenta : 0;
                inputMontoManual.setAttribute('max', maximo);
                
                if(parseFloat(inputMontoManual.value) > maximo) {
                    inputMontoManual.value = maximo.toFixed(2);
                }
            });

            inputMontoManual.addEventListener('input', function() {
                const maxVal = parseFloat(this.getAttribute('max'));
                if(!isNaN(maxVal) && parseFloat(this.value) > maxVal) {
                    this.value = maxVal.toFixed(2);
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
    }

    // ========================================================================
    // 3. LÓGICA DE PAGO REGULAR (MODAL DE DESGLOSE)
    // ========================================================================
    const modalPago = document.getElementById('modalPago');
    const formPago = document.getElementById('formPago');
    const distribucionRows = document.getElementById('pagoDistribucionRows');
    const btnAddDistribucion = document.getElementById('btnAddPagoDistribucion');
    const hintDistribucion = document.getElementById('pagoDistribucionHint');
    
    const inputTotal = document.getElementById('pagoMonto');
    const naturalezaSelect = document.getElementById('pagoNaturaleza');
    const inputCapital = document.getElementById('pagoMontoCapital');
    const inputInteres = document.getElementById('pagoMontoInteres');
    const centroCostoGroup = document.getElementById('grupoCentroCostoInteres');
    const inputCentroCosto = document.getElementById('pagoCentroCosto');

    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);
    const getDistribucionRows = () => Array.from(distribucionRows?.querySelectorAll('.js-pago-distribucion-row') || []);

    const actualizarBotonesRemove = () => {
        const rows = getDistribucionRows();
        rows.forEach((row, i) => {
            const btn = row.querySelector('.js-remove-pago-row');
            if (btn) btn.classList.toggle('d-none', rows.length <= 1 || i === 0);
        });
    };

    const recalcularDistribucion = () => {
        if (!inputTotal || !hintDistribucion) return;
        const suma = getDistribucionRows().reduce((acc, row) => {
            const val = parseFloat(row.querySelector('.js-pago-monto-distribucion')?.value || 0);
            return acc + val;
        }, 0);

        inputTotal.value = suma > 0 ? suma.toFixed(2) : '';
        const saldo = parseFloat(document.getElementById('pagoSaldo')?.value || 0);
        const diff = saldo - suma;

        if (suma === 0) hintDistribucion.textContent = '';
        else if (Math.abs(diff) < 0.01) hintDistribucion.innerHTML = `<i class="bi bi-check2-all text-success"></i> Deuda cubierta`;
        else if (diff > 0) hintDistribucion.innerHTML = `<span class="text-warning-emphasis">Quedará debiendo: ${diff.toFixed(2)}</span>`;
        else hintDistribucion.innerHTML = `<span class="text-danger">Supera deuda por: ${Math.abs(diff).toFixed(2)}</span>`;
        
        validarNaturaleza();
    };

    if (distribucionRows) {
        distribucionRows.addEventListener('click', (e) => {
            if (e.target.closest('.js-remove-pago-row')) {
                const row = e.target.closest('.js-pago-distribucion-row');
                if (getDistribucionRows().length > 1 && row) row.remove();
                actualizarBotonesRemove();
                recalcularDistribucion();
            }
        });

        distribucionRows.addEventListener('input', (e) => {
            if (e.target.matches('.js-pago-monto-distribucion')) recalcularDistribucion();
        });
    }

    if (btnAddDistribucion) {
        btnAddDistribucion.addEventListener('click', () => {
            const rows = getDistribucionRows();
            if (!rows.length) return;
            
            const nuevaFila = rows[0].cloneNode(true);
            nuevaFila.querySelector('.js-pago-cuenta').value = '';
            nuevaFila.querySelector('.js-pago-metodo').value = '';
            
            const inputMonto = nuevaFila.querySelector('.js-pago-monto-distribucion');
            const total = parseFloat(inputTotal.value || 0);
            const sumaActual = rows.reduce((acc, r) => acc + (parseFloat(r.querySelector('.js-pago-monto-distribucion')?.value || 0)), 0);
            inputMonto.value = (total - sumaActual > 0) ? (total - sumaActual).toFixed(2) : '';

            distribucionRows.appendChild(nuevaFila);
            actualizarBotonesRemove();
            recalcularDistribucion();
        });
    }

    // Interceptar apertura del modal para cargar datos
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-open-pago');
        if (!btn) return;

        document.getElementById('pagoIdOrigen').value = btn.dataset.idOrigen;
        document.getElementById('pagoMoneda').value = btn.dataset.moneda;
        document.getElementById('pagoSaldo').value = parseFloat(btn.dataset.saldo).toFixed(2);
        
        if (inputTotal) {
            inputTotal.value = btn.dataset.saldo;
            inputTotal.setAttribute('max', btn.dataset.saldo);
        }

        const rows = getDistribucionRows();
        if (rows.length === 1) rows[0].querySelector('.js-pago-monto-distribucion').value = btn.dataset.saldo;

        filtrarCuentasPorMoneda({ value: btn.dataset.moneda }, document.querySelector('.js-pago-cuenta'));
        recalcularDistribucion();
        if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
    });

    const validarNaturaleza = () => {
        if (!naturalezaSelect || !inputTotal) return;
        const val = naturalezaSelect.value;
        const capital = parseFloat(inputCapital?.value || 0);
        const interes = parseFloat(inputInteres?.value || 0);
        const total = parseFloat(inputTotal.value || 0);

        if (val === 'MIXTO' && (capital + interes).toFixed(2) !== total.toFixed(2)) {
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
            
            // Lógica específica de CxP: Mostrar centro de costo si hay intereses
            if (centroCostoGroup) {
                const mostrarInteres = val === 'INTERES' || val === 'MIXTO';
                centroCostoGroup.classList.toggle('d-none', !mostrarInteres);
                if (inputCentroCosto) inputCentroCosto.required = mostrarInteres;
            }

            if (val !== 'MIXTO' && val !== 'INTERES') {
                inputTotal.setAttribute('max', document.getElementById('pagoSaldo')?.value || 0);
            } else {
                inputTotal.removeAttribute('max');
            }
            validarNaturaleza();
        });
    }

    [inputTotal, inputCapital, inputInteres].forEach(el => el?.addEventListener('input', validarNaturaleza));

    // Validación final antes de enviar
    if (formPago) {
        formPago.addEventListener('submit', (e) => {
            const total = parseFloat(inputTotal.value || 0);
            
            if (naturalezaSelect?.value === 'MIXTO') {
                const cap = parseFloat(inputCapital.value || 0);
                const int = parseFloat(inputInteres.value || 0);
                if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    return Swal.fire('Error', 'Capital + Interés debe ser igual al Monto Total.', 'error');
                }
            }

            const sumaDist = getDistribucionRows().reduce((acc, row) => acc + (parseFloat(row.querySelector('.js-pago-monto-distribucion')?.value || 0)), 0);
            if (Math.abs(roundTo(sumaDist, 2) - roundTo(total, 2)) > 0.009) {
                e.preventDefault(); e.stopImmediatePropagation();
                return Swal.fire('Error', 'La suma de las cuentas debe ser igual al monto total.', 'error');
            }
        });
    }

    // Resetear modal regular al cerrar
    if (modalPago) {
        modalPago.addEventListener('hidden.bs.modal', () => {
            formPago.reset();
            getDistribucionRows().forEach((r, i) => i === 0 ? r.querySelectorAll('input, select').forEach(inpt => inpt.value = '') : r.remove());
            actualizarBotonesRemove();
            if (hintDistribucion) hintDistribucion.textContent = '';
            [inputTotal, inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }

})();