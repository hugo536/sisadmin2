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

                // Reinicializar Tooltips y Buscador ERPTable
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

        // Inputs Auto-submit (Selects y Fechas)
        formFiltros.addEventListener('input', (e) => {
            if (e.target.matches('.auto-submit')) {
                clearTimeout(timerFiltro);
                timerFiltro = setTimeout(procesarFiltros, 400);
            }
        });

        // Tabs (Pestañas)
        document.querySelectorAll('.js-tab-cxc').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const vista = e.currentTarget.getAttribute('data-vista');
                if (inputVistaGlobal) inputVistaGlobal.value = vista;
                
                // Efecto visual en tabs
                document.querySelectorAll('.js-tab-cxc').forEach(t => {
                    t.classList.remove('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');
                    t.classList.add('text-secondary', 'bg-light', 'border-0');
                });
                e.currentTarget.classList.remove('text-secondary', 'bg-light', 'border-0');
                e.currentTarget.classList.add('active', 'text-primary', 'border-primary', 'border-bottom-0', 'bg-white');

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
    // 2. LÓGICA DE COBRO MANUAL
    // ========================================================================
    const modalCobroManual = document.getElementById('modalCobroManual');
    const selectCliente = document.getElementById('cobroManualCliente');
    const selectMonedaManual = document.getElementById('cobroManualMoneda');
    const selectCuentaManual = document.getElementById('cobroManualCuentaDestino');
    const hintDeudaManual = document.getElementById('cobroManualDeudaHint');
    const inputMontoManual = document.getElementById('cobroManualMontoInput');

    const filtrarCuentasPorMoneda = (selectMoneda, selectCuenta) => {
        if (!selectMoneda || !selectCuenta) return;
        const moneda = String(selectMoneda.value || '').toUpperCase();
        let primeraValida = null;

        Array.from(selectCuenta.options).forEach(opt => {
            if (!opt.value) return; // Ignorar el placeholder
            const optMoneda = String(opt.dataset.moneda || '').toUpperCase();
            const esValida = !moneda || optMoneda === moneda;
            
            opt.hidden = !esValida;
            opt.disabled = !esValida;
            if (esValida && !primeraValida) primeraValida = opt.value;
        });
        selectCuenta.value = primeraValida || '';
    };

    const actualizarDeudaManual = () => {
        if (!selectCliente || !hintDeudaManual) return;
        const idTercero = selectCliente.value;
        const moneda = selectMonedaManual ? selectMonedaManual.value : 'PEN';
        
        if (!idTercero) {
            hintDeudaManual.innerHTML = '';
            return;
        }

        const opt = selectCliente.querySelector(`option[value="${idTercero}"]`);
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

    if (modalCobroManual) {
        modalCobroManual.addEventListener('shown.bs.modal', () => {
            filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
            if (typeof window.AppSelects !== 'undefined' && !selectCliente.tomselect) {
                window.AppSelects.initLocal('#cobroManualCliente', {
                    dropdownParent: 'body',
                    onChange: actualizarDeudaManual
                });
            }
        });

        modalCobroManual.addEventListener('hidden.bs.modal', () => {
            if (hintDeudaManual) hintDeudaManual.innerHTML = '';
            if (selectCliente && selectCliente.tomselect) selectCliente.tomselect.clear(true);
        });

        if (selectMonedaManual) {
            selectMonedaManual.addEventListener('change', () => {
                filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
                actualizarDeudaManual();
            });
        }
    }

    // ========================================================================
    // 3. LÓGICA DE COBRO REGULAR (MODAL DE DESGLOSE)
    // ========================================================================
    const modalCobro = document.getElementById('modalCobro');
    const formCobro = document.getElementById('formCobro');
    const distribucionRows = document.getElementById('cobroDistribucionRows');
    const btnAddDistribucion = document.getElementById('btnAddCobroDistribucion');
    const hintDistribucion = document.getElementById('cobroDistribucionHint');
    
    const inputTotal = document.getElementById('cobroMonto');
    const naturalezaSelect = document.getElementById('cobroNaturaleza');
    const inputCapital = document.getElementById('cobroMontoCapital');
    const inputInteres = document.getElementById('cobroMontoInteres');

    // Función Global de redondeo seguro
    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    const getDistribucionRows = () => Array.from(distribucionRows?.querySelectorAll('.js-cobro-distribucion-row') || []);

    const actualizarBotonesRemove = () => {
        const rows = getDistribucionRows();
        rows.forEach((row, i) => {
            const btn = row.querySelector('.js-remove-cobro-row');
            if (btn) btn.classList.toggle('d-none', rows.length <= 1 || i === 0);
        });
    };

    const recalcularDistribucion = () => {
        if (!inputTotal || !hintDistribucion) return;
        const suma = getDistribucionRows().reduce((acc, row) => {
            const val = parseFloat(row.querySelector('.js-cobro-monto-distribucion')?.value || 0);
            return acc + val;
        }, 0);

        inputTotal.value = suma > 0 ? suma.toFixed(2) : '';
        const saldo = parseFloat(document.getElementById('cobroSaldo')?.value || 0);
        const diff = saldo - suma;

        if (suma === 0) hintDistribucion.textContent = '';
        else if (Math.abs(diff) < 0.01) hintDistribucion.innerHTML = `<i class="bi bi-check2-all text-success"></i> Deuda cubierta`;
        else if (diff > 0) hintDistribucion.innerHTML = `<span class="text-warning-emphasis">Quedará debiendo: ${diff.toFixed(2)}</span>`;
        else hintDistribucion.innerHTML = `<span class="text-danger">Supera deuda por: ${Math.abs(diff).toFixed(2)}</span>`;
        
        validarNaturaleza();
    };

    if (distribucionRows) {
        distribucionRows.addEventListener('click', (e) => {
            if (e.target.closest('.js-remove-cobro-row')) {
                const row = e.target.closest('.js-cobro-distribucion-row');
                if (getDistribucionRows().length > 1 && row) row.remove();
                actualizarBotonesRemove();
                recalcularDistribucion();
            }
        });

        distribucionRows.addEventListener('input', (e) => {
            if (e.target.matches('.js-cobro-monto-distribucion')) recalcularDistribucion();
        });
    }

    if (btnAddDistribucion) {
        btnAddDistribucion.addEventListener('click', () => {
            const rows = getDistribucionRows();
            if (!rows.length) return;
            
            const nuevaFila = rows[0].cloneNode(true);
            nuevaFila.querySelector('.js-cobro-cuenta').value = '';
            nuevaFila.querySelector('.js-cobro-metodo').value = '';
            
            const inputMonto = nuevaFila.querySelector('.js-cobro-monto-distribucion');
            const total = parseFloat(inputTotal.value || 0);
            const sumaActual = rows.reduce((acc, r) => acc + (parseFloat(r.querySelector('.js-cobro-monto-distribucion')?.value || 0)), 0);
            inputMonto.value = (total - sumaActual > 0) ? (total - sumaActual).toFixed(2) : '';

            distribucionRows.appendChild(nuevaFila);
            actualizarBotonesRemove();
            recalcularDistribucion();
        });
    }

    // Interceptar la apertura del modal para cargar datos
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-open-cobro');
        if (!btn) return;

        document.getElementById('cobroIdOrigen').value = btn.dataset.idOrigen;
        document.getElementById('cobroMoneda').value = btn.dataset.moneda;
        document.getElementById('cobroSaldo').value = parseFloat(btn.dataset.saldo).toFixed(2);
        
        if (inputTotal) {
            inputTotal.value = btn.dataset.saldo;
            inputTotal.setAttribute('max', btn.dataset.saldo);
        }

        const rows = getDistribucionRows();
        if (rows.length === 1) rows[0].querySelector('.js-cobro-monto-distribucion').value = btn.dataset.saldo;

        filtrarCuentasPorMoneda({ value: btn.dataset.moneda }, document.querySelector('.js-cobro-cuenta'));
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
            const capGroup = document.getElementById('grupoCobroCapital');
            const intGroup = document.getElementById('grupoCobroInteres');
            
            capGroup?.classList.toggle('d-none', val !== 'CAPITAL' && val !== 'MIXTO');
            intGroup?.classList.toggle('d-none', val !== 'INTERES' && val !== 'MIXTO');

            if (val !== 'MIXTO' && val !== 'INTERES') {
                inputTotal.setAttribute('max', document.getElementById('cobroSaldo')?.value || 0);
            } else {
                inputTotal.removeAttribute('max');
            }
            validarNaturaleza();
        });
    }

    [inputTotal, inputCapital, inputInteres].forEach(el => el?.addEventListener('input', validarNaturaleza));

    // Validación final antes de enviar
    if (formCobro) {
        formCobro.addEventListener('submit', (e) => {
            const total = parseFloat(inputTotal.value || 0);
            
            if (naturalezaSelect?.value === 'MIXTO') {
                const cap = parseFloat(inputCapital.value || 0);
                const int = parseFloat(inputInteres.value || 0);
                if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    return Swal.fire('Error', 'Capital + Mora debe ser igual al Monto Total.', 'error');
                }
            }

            const sumaDist = getDistribucionRows().reduce((acc, row) => acc + (parseFloat(row.querySelector('.js-cobro-monto-distribucion')?.value || 0)), 0);
            if (Math.abs(roundTo(sumaDist, 2) - roundTo(total, 2)) > 0.009) {
                e.preventDefault(); e.stopImmediatePropagation();
                return Swal.fire('Error', 'La suma de las cuentas debe ser igual al monto total.', 'error');
            }
        });
    }

    // Resetear modal regular al cerrar
    if (modalCobro) {
        modalCobro.addEventListener('hidden.bs.modal', () => {
            formCobro.reset();
            getDistribucionRows().forEach((r, i) => i === 0 ? r.querySelectorAll('input, select').forEach(inpt => inpt.value = '') : r.remove());
            actualizarBotonesRemove();
            if (hintDistribucion) hintDistribucion.textContent = '';
            [inputTotal, inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }

})();