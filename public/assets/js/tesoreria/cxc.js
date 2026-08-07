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
    // --- FUNCIÓN MAGIA: FILTRADO DE MÉTODOS ESTRICTO (GLOBAL) ---
    // ========================================================================
    window.filtrarMetodosPorCuentaCxc = function(selectCuenta, selectMetodo) {
        if (!selectCuenta || !selectMetodo) return;

        // 👇 NUEVO: Si seleccionan Saldo a Favor, forzamos el método a "Cruce"
        if (selectCuenta.value === 'SALDO_FAVOR') {
            selectMetodo.innerHTML = '<option value="CRUCE" class="text-success fw-bold" selected>⭐ Cruce Automático de Documentos</option>';
            selectMetodo.disabled = false;
            return;
        }

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
        const selectCliente = document.getElementById('cobroManualCliente');
        const hintDeudaManual = document.getElementById('cobroManualDeudaHint');
        const selectMonedaManual = document.getElementById('cobroManualMoneda');
        const inputMontoManual = document.getElementById('cobroManualMontoInput');
        const selectCuentaManual = document.getElementById('selectCuentaDestinoManual');

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

                // 👇 NUEVO: Inyectar dinámicamente la opción de Saldo a Favor 👇
                if (selectCuentaManual) {
                    Array.from(selectCuentaManual.options).forEach(o => { if(o.value === 'SALDO_FAVOR') o.remove(); });
                    
                    // Si en CxC la deuda es negativa, significa saldo a favor
                    if (deuda < 0) {
                        const saldoFav = Math.abs(deuda);
                        const opt = document.createElement('option');
                        opt.value = 'SALDO_FAVOR';
                        opt.className = 'text-success fw-bold bg-success-subtle';
                        opt.setAttribute('data-saldo', saldoFav);
                        opt.setAttribute('data-moneda', moneda);
                        opt.innerHTML = `⭐ USAR SALDO A FAVOR (Disp: ${moneda} ${saldoFav.toFixed(2)})`;
                        selectCuentaManual.insertBefore(opt, selectCuentaManual.options[1]);
                    }
                }

            } else {
                hintDeudaManual.innerHTML = `<span class="text-danger fw-bold">Error al calcular</span>`;
            }
        } catch (error) {
            console.error("Error al obtener deuda:", error);
            hintDeudaManual.innerHTML = `<span class="text-danger fw-bold">Error de red</span>`;
        }
    };

    // Función exclusiva para calcular la conversión en el Modal Manual
    window.recalcularConversionManualCxc = function() {
        const selectMonedaDeuda = document.getElementById('cobroManualMoneda');
        const selectCuenta = document.getElementById('selectCuentaDestinoManual');
        const containerConversion = document.getElementById('cobroManualContainerConversion');
        const inputTipoCambio = document.getElementById('cobroManualTipoCambio');
        const inputMontoConvertido = document.getElementById('cobroManualMontoConvertido');
        const labelMontoConvertido = document.getElementById('cobroManualLabelMontoConvertido');
        const inputMontoPagar = document.getElementById('cobroManualMontoInput');

        if(!containerConversion || !selectMonedaDeuda || !selectCuenta) return;

        const monedaDeuda = (selectMonedaDeuda.value || '').trim().toUpperCase();
        const optCuenta = selectCuenta.options[selectCuenta.selectedIndex];
        const monedaCuenta = optCuenta ? (optCuenta.getAttribute('data-moneda') || '').toUpperCase() : '';
        const montoAPagar = parseFloat(inputMontoPagar.value) || 0;

        if (monedaCuenta && monedaDeuda && monedaCuenta !== monedaDeuda) {
            containerConversion.style.display = 'block';
            inputTipoCambio.setAttribute('required', 'required');
            labelMontoConvertido.innerText = `Monto a ingresar (${monedaCuenta})`;

            const tc = parseFloat(inputTipoCambio.value) || 0;
            if (tc > 0) {
                if (monedaDeuda === 'USD' && monedaCuenta === 'PEN') {
                    inputMontoConvertido.value = (montoAPagar * tc).toFixed(2);
                } else if (monedaDeuda === 'PEN' && monedaCuenta === 'USD') {
                    inputMontoConvertido.value = (montoAPagar / tc).toFixed(2);
                } else {
                    inputMontoConvertido.value = (montoAPagar * tc).toFixed(2);
                }
            } else {
                inputMontoConvertido.value = '';
            }
        } else {
            containerConversion.style.display = 'none';
            inputTipoCambio.removeAttribute('required');
            inputTipoCambio.value = '';
            inputMontoConvertido.value = '';
        }
    };

    const modalCobroManual = document.getElementById('modalCobroManual');

    // CANDADO SPA: Evitar apilamiento de Event Listeners
    if (modalCobroManual && !modalCobroManual.dataset.eventosCxc) {
        modalCobroManual.dataset.eventosCxc = '1';

        modalCobroManual.addEventListener('shown.bs.modal', () => {
            const selectCuentaManual = document.getElementById('cobroManualCuentaDestino');
            const selectMonedaManual = document.getElementById('cobroManualMoneda');
            const selectCliente = document.getElementById('cobroManualCliente');

            if (selectCuentaManual) selectCuentaManual.value = '';
            filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
            
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
            const selectMetodoManual = document.getElementById('cobroManualMetodoDestino');
            const selectCliente = document.getElementById('cobroManualCliente');

            if (hintDeudaManual) hintDeudaManual.innerHTML = '';
            if (inputMontoManual) inputMontoManual.value = '';
            if (selectCuentaManual) selectCuentaManual.value = '';
            
            if (selectMetodoManual) {
                selectMetodoManual.innerHTML = '<option value="" selected disabled>Seleccione un método...</option>';
                selectMetodoManual.disabled = true;
            }
            
            if (selectCliente && selectCliente.tomselect) {
                selectCliente.tomselect.clear(true);
            }

            window.recalcularConversionManualCxc();
        });

        const selectMonedaObserver = document.getElementById('cobroManualMoneda');
        if (selectMonedaObserver && !selectMonedaObserver.dataset.cxcMonedaListener) {
            selectMonedaObserver.addEventListener('change', () => {
                const selectCuentaManual = document.getElementById('cobroManualCuentaDestino');
                const selectMonedaManual = document.getElementById('cobroManualMoneda');
                filtrarCuentasPorMoneda(selectMonedaManual, selectCuentaManual);
                actualizarDeudaManual(); 
                window.recalcularConversionManualCxc();
            });
            selectMonedaObserver.dataset.cxcMonedaListener = '1';
        }
    }


    // ========================================================================
    // 3. LÓGICA DE COBRO REGULAR (MODAL DE DESGLOSE) BLINDADO SPA
    // ========================================================================
    const modalCobro = document.getElementById('modalCobro');
    const formCobro = document.getElementById('formCobro');
    const naturalezaSelect = document.getElementById('cobroNaturaleza');
    const inputCapital = document.getElementById('cobroMontoCapital');
    const inputInteres = document.getElementById('cobroMontoInteres');

    const inputMonedaDeuda = document.getElementById('cobroMoneda');
    const containerConversion = document.getElementById('cobroContainerConversion');
    const inputTipoCambio = document.getElementById('cobroTipoCambio');
    const inputMontoConvertido = document.getElementById('cobroMontoConvertido');
    const labelMontoConvertido = document.getElementById('cobroLabelMontoConvertido');

    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    window.recalcularConversionBimonetariaCxc = function() {
        const modalCobroLocal = document.getElementById('modalCobro');
        if (!modalCobroLocal) return;

        const inputMonedaDeuda = document.getElementById('cobroMoneda');
        const containerConversion = document.getElementById('cobroContainerConversion');
        const inputTipoCambio = document.getElementById('cobroTipoCambio');
        const inputMontoConvertido = document.getElementById('cobroMontoConvertido');
        const labelMontoConvertido = document.getElementById('cobroLabelMontoConvertido');

        if (!containerConversion || !inputTipoCambio || !inputMontoConvertido) return;

        const monedaDeuda = (inputMonedaDeuda.value || '').trim().toUpperCase();
        const selectCuenta = modalCobroLocal.querySelector('.js-cobro-cuenta');
        if (!selectCuenta) return;
        
        const optCuenta = selectCuenta.options[selectCuenta.selectedIndex];
        const monedaCuenta = optCuenta ? (optCuenta.getAttribute('data-moneda') || '').toUpperCase() : '';

        const inputTotal = document.getElementById('cobroMonto');
        const montoTotalPagar = parseFloat(inputTotal.value) || 0;

        if (monedaCuenta && monedaDeuda && monedaCuenta !== monedaDeuda) {
            containerConversion.style.display = 'block';
            inputTipoCambio.setAttribute('required', 'required');
            labelMontoConvertido.innerText = `Monto a ingresar al banco (${monedaCuenta})`;
            
            const tc = parseFloat(inputTipoCambio.value) || 0;
            if (tc > 0) {
                if (monedaDeuda === 'USD' && monedaCuenta === 'PEN') {
                    inputMontoConvertido.value = (montoTotalPagar * tc).toFixed(2);
                } else if (monedaDeuda === 'PEN' && monedaCuenta === 'USD') {
                    inputMontoConvertido.value = (montoTotalPagar / tc).toFixed(2);
                } else {
                    inputMontoConvertido.value = (montoTotalPagar * tc).toFixed(2);
                }
            } else {
                inputMontoConvertido.value = '';
            }
        } else {
            containerConversion.style.display = 'none';
            inputTipoCambio.removeAttribute('required');
            inputTipoCambio.value = '';
            inputMontoConvertido.value = '';
        }
    };

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

            const monedaDeudaActiva = document.getElementById('cobroMoneda')?.value || '';
            
            if (suma === 0) hintDistribucion.textContent = '';
            else if (Math.abs(diff) < 0.01) hintDistribucion.innerHTML = `<i class="bi bi-check2-all text-success"></i> Deuda cubierta`;
            else if (diff > 0) hintDistribucion.innerHTML = `<span class="text-warning-emphasis">Quedará debiendo: ${diff.toFixed(2)} ${monedaDeudaActiva}</span>`;
            else hintDistribucion.innerHTML = `<span class="text-danger">Supera deuda por: ${Math.abs(diff).toFixed(2)} ${monedaDeudaActiva}</span>`;
        }
        
        validarNaturaleza();
        window.recalcularConversionBimonetariaCxc();
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

    if (!window.cxcEventosGlobalesAtachados) {
        window.cxcEventosGlobalesAtachados = true;

        document.addEventListener('input', (e) => {
            
            // 1. SEGURO PARA EL MODAL DE PAGO INDIVIDUAL
            if (e.target.matches('.js-cobro-monto-distribucion')) {
                const fila = e.target.closest('.js-cobro-distribucion-row');
                if (fila) {
                    const selectCuenta = fila.querySelector('.js-cobro-cuenta');
                    if (selectCuenta && selectCuenta.value === 'SALDO_FAVOR') {
                        const opt = selectCuenta.options[selectCuenta.selectedIndex];
                        const saldoDisponible = parseFloat(opt.getAttribute('data-saldo')) || 0;
                        const montoDigitado = parseFloat(e.target.value) || 0;

                        if (montoDigitado > saldoDisponible && saldoDisponible > 0) {
                            e.target.value = saldoDisponible.toFixed(2);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'warning', title: 'Límite alcanzado', text: `Solo dispones de ${saldoDisponible.toFixed(2)}`, toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
                            }
                        }
                    }
                }
                window.recalcularModalCobro();
            } 
            else if (e.target.id === 'cobroTipoCambio') {
                window.recalcularConversionBimonetariaCxc();
            }
            // 2. SEGURO PARA EL MODAL DE PAGO MANUAL
            else if (e.target.id === 'cobroManualMontoInput' || e.target.id === 'cobroManualTipoCambio') {
                if (e.target.id === 'cobroManualMontoInput') {
                    const selectCuentaManual = document.getElementById('selectCuentaDestinoManual');
                    if (selectCuentaManual && selectCuentaManual.value === 'SALDO_FAVOR') {
                        const optManual = selectCuentaManual.options[selectCuentaManual.selectedIndex];
                        const saldoDispManual = parseFloat(optManual.getAttribute('data-saldo')) || 0;
                        const montoDigManual = parseFloat(e.target.value) || 0;

                        if (montoDigManual > saldoDispManual && saldoDispManual > 0) {
                            e.target.value = saldoDispManual.toFixed(2);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'warning', title: 'Límite alcanzado', text: `Solo dispones de ${saldoDispManual.toFixed(2)}`, toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
                            }
                        }
                    }
                }
                window.recalcularConversionManualCxc();
            }
        });

        document.addEventListener('change', (e) => {
            if (e.target.id === 'cobroManualMoneda') {
                actualizarDeudaManual();
                window.recalcularConversionManualCxc();
            }
            
            // 1. SELECT DEL MODAL VERDE INDIVIDUAL (Con seguro)
            if (e.target.matches('.js-cobro-cuenta')) {
                const fila = e.target.closest('.js-cobro-distribucion-row');
                
                const selectedValue = e.target.value;
                if (selectedValue) {
                    const allSelects = document.querySelectorAll('#cobroDistribucionRows .js-cobro-cuenta');
                    let coincidencias = 0;
                    allSelects.forEach(sel => {
                        if (sel.value === selectedValue) coincidencias++;
                    });

                    if (coincidencias > 1) {
                        e.target.value = ''; 
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'warning', title: 'Cuenta duplicada', text: 'No puedes usar la misma cuenta más de una vez en este cobro.', confirmButtonColor: '#ffc107' });
                        }
                        if (fila) {
                            const selectMetodo = fila.querySelector('.js-cobro-metodo');
                            const inputMonto = fila.querySelector('.js-cobro-monto-distribucion');
                            if (selectMetodo) {
                                selectMetodo.innerHTML = '<option value="" selected disabled>Método...</option>';
                                selectMetodo.disabled = true;
                            }
                            if (inputMonto) inputMonto.value = '';
                        }
                        window.recalcularModalCobro();
                        return; 
                    }
                }

                if (fila) {
                    const selectMetodo = fila.querySelector('.js-cobro-metodo');
                    window.filtrarMetodosPorCuentaCxc(e.target, selectMetodo);
                }
                window.recalcularConversionBimonetariaCxc();
            }
            // 2. SELECT DEL PAGO MANUAL
            else if (e.target.id === 'selectCuentaDestinoManual') {
                const selectMetodoManual = document.getElementById('cobroManualMetodoDestino');
                window.filtrarMetodosPorCuentaCxc(e.target, selectMetodoManual);

                const inputMontoManual = document.getElementById('cobroManualMontoInput');
                const opt = e.target.options[e.target.selectedIndex];
                
                if(!opt || opt.value === "" || opt.value !== 'SALDO_FAVOR') {
                    if (inputMontoManual) inputMontoManual.removeAttribute('max');
                } else if (opt.value === 'SALDO_FAVOR') {
                    const saldoFav = parseFloat(opt.getAttribute('data-saldo')) || 0;
                    const maximo = saldoFav > 0 ? saldoFav : 0;
                    
                    if (inputMontoManual) {
                        inputMontoManual.setAttribute('max', maximo);
                        if(parseFloat(inputMontoManual.value) > maximo) {
                            inputMontoManual.value = maximo.toFixed(2);
                        }
                    }
                }
                window.recalcularConversionManualCxc();
            }
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.closest('#modalCobroManual')) {
                const inputMontoManual = document.getElementById('cobroManualMontoInput');
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

                const monedaDoc = btn.dataset.moneda.toUpperCase();
                const simbolo = (monedaDoc === 'USD') ? '$' : 'S/';
                document.querySelectorAll('.js-lbl-moneda-doc-addon').forEach(el => el.textContent = simbolo);
                
                // 👇 NUEVO: Calcular saldo a favor visual y agregarlo al Select 👇
                const provName = btn.closest('tr').querySelector('td').innerText.trim().split('\n')[0];
                let saldoFav = 0;
                
                document.querySelectorAll('#cxcTableBody tr').forEach(row => {
                    const rowProv = row.querySelector('td').innerText.trim().split('\n')[0];
                    if(rowProv === provName) {
                        const tdSaldo = row.querySelector('td:nth-child(6)');
                        if(tdSaldo && tdSaldo.textContent.includes('-')) {
                            saldoFav += parseFloat(tdSaldo.textContent.replace(/[^\d.-]/g, ''));
                        }
                    }
                });

                const inyectarSaldoFavor = (selectElement) => {
                    Array.from(selectElement.options).forEach(o => { if(o.value === 'SALDO_FAVOR') o.remove(); });
                    if(saldoFav > 0) {
                        const opt = document.createElement('option');
                        opt.value = 'SALDO_FAVOR';
                        opt.className = 'text-success fw-bold bg-success-subtle';
                        opt.setAttribute('data-saldo', saldoFav);
                        opt.setAttribute('data-moneda', monedaDoc);
                        opt.innerHTML = `⭐ USAR SALDO A FAVOR (Disp: ${monedaDoc} ${saldoFav.toFixed(2)})`;
                        selectElement.insertBefore(opt, selectElement.options[1]); 
                    }
                };
                
                document.querySelectorAll('.js-cobro-cuenta').forEach(inyectarSaldoFavor);
                // 👆 FIN NUEVO 👆

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
            // 👇 NUEVO BLOQUE DE REEMBOLSO AGREGADO AQUÍ 👇
            else if (e.target.closest('.js-open-reembolso')) {
                const btn = e.target.closest('.js-open-reembolso');
                const monedaDoc = btn.dataset.moneda.toUpperCase();
                const simbolo = (monedaDoc === 'USD') ? '$' : 'S/';
                document.querySelectorAll('#modalReembolso .js-lbl-moneda-doc-addon').forEach(el => el.textContent = simbolo);

                document.getElementById('reembolsoIdOrigen').value = btn.dataset.idOrigen;
                document.getElementById('reembolsoMoneda').value = btn.dataset.moneda;
                document.getElementById('reembolsoMonto').value = parseFloat(btn.dataset.saldo).toFixed(2);
                document.getElementById('reembolsoMonto').setAttribute('max', parseFloat(btn.dataset.saldo).toFixed(2));
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
            
            if (total <= 0) {
                e.preventDefault(); e.stopImmediatePropagation();
                return Swal.fire('Atención', 'El monto a cobrar debe ser mayor a 0.', 'warning');
            }
            
            if (naturalezaSelect?.value === 'MIXTO') {
                const cap = parseFloat(inputCapital?.value || 0);
                const int = parseFloat(inputInteres?.value || 0);
                if (roundTo(cap + int, 2) !== roundTo(total, 2)) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    return Swal.fire('Error', 'Capital + Mora debe ser igual al Monto Total.', 'error');
                }
            }
            
            let montosPorCuenta = {};
            let saldosPorCuenta = {};
            let nombresCuentas = {};

            const tc = parseFloat(document.getElementById('cobroTipoCambio')?.value) || 1;
            const monedaDeuda = (document.getElementById('cobroMoneda')?.value || '').trim().toUpperCase();

            const filas = document.querySelectorAll('.js-cobro-distribucion-row');
            filas.forEach(fila => {
                const selectCuenta = fila.querySelector('.js-cobro-cuenta');
                const montoInput = fila.querySelector('.js-cobro-monto-distribucion');
                
                if (selectCuenta && selectCuenta.value && montoInput) {
                    const idC = selectCuenta.value;
                    const opt = selectCuenta.options[selectCuenta.selectedIndex];
                    const saldo = parseFloat(opt.getAttribute('data-saldo')) || 0;
                    const monedaCuenta = (opt.getAttribute('data-moneda') || '').toUpperCase();
                    
                    let montoAExtraer = parseFloat(montoInput.value) || 0;

                    if (monedaCuenta && monedaDeuda && monedaCuenta !== monedaDeuda) {
                        if (monedaDeuda === 'USD' && monedaCuenta === 'PEN') {
                            montoAExtraer = montoAExtraer * tc;
                        } else if (monedaDeuda === 'PEN' && monedaCuenta === 'USD') {
                            montoAExtraer = montoAExtraer / tc;
                        }
                    }

                    if (!montosPorCuenta[idC]) {
                        montosPorCuenta[idC] = 0;
                        saldosPorCuenta[idC] = saldo;
                        nombresCuentas[idC] = opt.text.split('(')[0].trim(); 
                    }
                    montosPorCuenta[idC] += montoAExtraer;
                }
            });

            let erroresSaldo = [];
            for (const idC in montosPorCuenta) {
                if (idC === 'SALDO_FAVOR' && montosPorCuenta[idC] > saldosPorCuenta[idC]) {
                    erroresSaldo.push(`El saldo a favor no tiene fondos suficientes.<br>Se intentan extraer ${montosPorCuenta[idC].toFixed(2)} pero dispone de ${saldosPorCuenta[idC].toFixed(2)}.`);
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
        formCobro.addEventListener('submit', window.submitCobroHandler);
    }

    if (modalCobro) {
        modalCobro.addEventListener('hidden.bs.modal', () => {
            formCobro.reset();
            const filas = document.querySelectorAll('.js-cobro-distribucion-row');
            filas.forEach((r, i) => i === 0 ? r.querySelectorAll('input, select').forEach(inpt => inpt.value = '') : r.remove());
            
            if (containerConversion) containerConversion.style.display = 'none';
            if (inputTipoCambio) inputTipoCambio.removeAttribute('required');

            window.recalcularModalCobro();
            [inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }

    // ========================================================================
    // LIMPIEZA DE MODAL REEMBOLSO AL CERRAR
    // ========================================================================
    const modalReembolso = document.getElementById('modalReembolso');
    if (modalReembolso) {
        modalReembolso.addEventListener('hidden.bs.modal', () => {
            const formR = document.getElementById('formReembolso');
            if (formR) formR.reset();
            const selectMetodo = document.getElementById('reembolsoMetodoIngreso');
            if (selectMetodo) {
                selectMetodo.innerHTML = '<option value="" selected disabled>Seleccione un método...</option>';
                selectMetodo.disabled = true;
            }
        });
    }
})();