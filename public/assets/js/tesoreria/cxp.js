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
    // 2. LÓGICA DE PAGO MANUAL (DOM DINÁMICO & SPA BLINDADO)
    // ========================================================================

    const actualizarDeudaManual = async () => {
        const selectProveedor = document.getElementById('pagoManualProveedor');
        const hintDeudaManual = document.getElementById('pagoManualDeudaHint');
        const selectMonedaManual = document.getElementById('pagoManualMoneda');
        const inputMontoManual = document.getElementById('pagoManualMontoInput');
        const selectCuentaManual = document.getElementById('selectCuentaOrigenManual'); // <--- NUEVO

        if (!selectProveedor || !hintDeudaManual) return;
        const idTercero = selectProveedor.value;
        const moneda = selectMonedaManual ? selectMonedaManual.value : 'PEN';
        
        if (!idTercero) {
            hintDeudaManual.innerHTML = '';
            return;
        }

        hintDeudaManual.innerHTML = `<span class="text-secondary"><i class="spinner-border spinner-border-sm me-1" role="status"></i>Calculando deuda...</span>`;

        try {
            const baseUrl = window.location.origin + window.location.pathname; 
            const url = `${baseUrl}?ruta=tesoreria/ajax_obtener_deuda_tercero&tipo=CXP&id_tercero=${idTercero}&moneda=${moneda}`;
            
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Error al conectar con el servidor');
            
            const data = await response.json();

            if (data.ok) {
                const deuda = parseFloat(data.deuda) || 0;

                if (deuda > 0) {
                    hintDeudaManual.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Debe: ${moneda} ${deuda.toFixed(2)}</span>`;
                    if (inputMontoManual && parseFloat(inputMontoManual.value) > deuda) {
                        inputMontoManual.value = deuda.toFixed(2);
                    }
                } else {
                    // Si la deuda es 0 o negativa (saldo a favor), solo mostramos "Al día"
                    hintDeudaManual.innerHTML = `<span class="text-info fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Al día (${moneda} 0.00)</span>`;
                }

                // 👇 NUEVO: Inyectar dinámicamente la opción de Saldo a Favor en el select de Cuentas del Pago Manual 👇
                if (selectCuentaManual) {
                    // Limpiar opción anterior si existe
                    Array.from(selectCuentaManual.options).forEach(o => { if(o.value === 'SALDO_FAVOR') o.remove(); });
                    
                    // Si la deuda es negativa, significa que hay saldo a favor (ej: deuda = -100, entonces saldo a favor = 100)
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
                // 👆 FIN NUEVO 👆

            } else {
                throw new Error(data.mensaje || 'Error desconocido');
            }
        } catch (error) {
            console.error('SisAdmin2 Error:', error);
            hintDeudaManual.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Error al consultar deuda</span>`;
        }
    };

    // Función exclusiva para calcular la conversión en el Modal Manual
    window.recalcularConversionManual = function() {
        const selectMonedaDeuda = document.getElementById('pagoManualMoneda');
        const selectCuenta = document.getElementById('selectCuentaOrigenManual');
        const containerConversion = document.getElementById('pagoManualContainerConversion');
        const inputTipoCambio = document.getElementById('pagoManualTipoCambio');
        const inputMontoConvertido = document.getElementById('pagoManualMontoConvertido');
        const labelMontoConvertido = document.getElementById('pagoManualLabelMontoConvertido');
        const inputMontoPagar = document.getElementById('pagoManualMontoInput');

        if(!containerConversion || !selectMonedaDeuda || !selectCuenta) return;

        const monedaDeuda = (selectMonedaDeuda.value || '').trim().toUpperCase();
        const optCuenta = selectCuenta.options[selectCuenta.selectedIndex];
        const monedaCuenta = optCuenta ? (optCuenta.getAttribute('data-moneda') || '').toUpperCase() : '';
        const montoAPagar = parseFloat(inputMontoPagar.value) || 0;

        if (monedaCuenta && monedaDeuda && monedaCuenta !== monedaDeuda) {
            containerConversion.style.display = 'block';
            inputTipoCambio.setAttribute('required', 'required');
            labelMontoConvertido.innerText = `Monto a descontar (${monedaCuenta})`;

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

    const modalPagoManual = document.getElementById('modalPagoManual');

    // CANDADO SPA: Evitar apilamiento de Event Listeners
    if (modalPagoManual && !modalPagoManual.dataset.eventosCxp) {
        modalPagoManual.dataset.eventosCxp = '1';

        modalPagoManual.addEventListener('shown.bs.modal', () => {
            const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
            const selectProveedor = document.getElementById('pagoManualProveedor');

            if (selectCuentaManual) selectCuentaManual.value = ''; 
            
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
            const inputMontoManual = document.getElementById('pagoManualMontoInput');
            const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
            const selectMetodoManual = document.getElementById('pagoManualMetodoOrigen');

            if (hintDeudaManual) hintDeudaManual.innerHTML = '';
            if (hintSaldoManual) hintSaldoManual.innerHTML = '';
            if (inputMontoManual) inputMontoManual.value = '';
            if (selectCuentaManual) selectCuentaManual.value = '';
            
            if (selectMetodoManual) {
                selectMetodoManual.innerHTML = '<option value="" selected disabled>Seleccione un método...</option>';
                selectMetodoManual.disabled = true;
            }

            if (selectProveedor && selectProveedor.tomselect) selectProveedor.tomselect.clear(true);
            window.recalcularConversionManual();
        });
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

    const inputMonedaDeuda = document.getElementById('pagoMoneda');
    const containerConversion = document.getElementById('pagoContainerConversion');
    const inputTipoCambio = document.getElementById('pagoTipoCambio');
    const inputMontoConvertido = document.getElementById('pagoMontoConvertido');
    const labelMontoConvertido = document.getElementById('pagoLabelMontoConvertido');

    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    window.recalcularConversionBimonetaria = function() {
        const modalPago = document.getElementById('modalPago');
        if (!modalPago) return;

        const inputMonedaDeuda = document.getElementById('pagoMoneda');
        const containerConversion = document.getElementById('pagoContainerConversion');
        const inputTipoCambio = document.getElementById('pagoTipoCambio');
        const inputMontoConvertido = document.getElementById('pagoMontoConvertido');
        const labelMontoConvertido = document.getElementById('pagoLabelMontoConvertido');

        if (!containerConversion || !inputTipoCambio || !inputMontoConvertido) return;

        const monedaDeuda = (inputMonedaDeuda.value || '').trim().toUpperCase();
        const selectCuenta = modalPago.querySelector('.js-pago-cuenta');
        if (!selectCuenta) return;
        
        const optCuenta = selectCuenta.options[selectCuenta.selectedIndex];
        const monedaCuenta = optCuenta ? (optCuenta.getAttribute('data-moneda') || '').toUpperCase() : '';

        const inputTotal = document.getElementById('pagoMonto');
        const montoTotalPagar = parseFloat(inputTotal.value) || 0;

        if (monedaCuenta && monedaDeuda && monedaCuenta !== monedaDeuda) {
            containerConversion.style.display = 'block';
            inputTipoCambio.setAttribute('required', 'required');
            labelMontoConvertido.innerText = `Monto a descontar (${monedaCuenta})`;
            
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

            const monedaDeudaActiva = document.getElementById('pagoMoneda')?.value || '';
            
            if (suma === 0) hintDistribucion.textContent = '';
            else if (Math.abs(diff) < 0.01) hintDistribucion.innerHTML = `<i class="bi bi-check2-all text-success"></i> Deuda cubierta`;
            else if (diff > 0) hintDistribucion.innerHTML = `<span class="text-warning-emphasis">Quedará debiendo: ${diff.toFixed(2)} ${monedaDeudaActiva}</span>`;
            else hintDistribucion.innerHTML = `<span class="text-danger">Supera deuda por: ${Math.abs(diff).toFixed(2)} ${monedaDeudaActiva}</span>`;
        }
        
        validarNaturaleza();
        window.recalcularConversionBimonetaria();
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
            
            // 1. SEGURO PARA EL MODAL DE PAGO INDIVIDUAL
            if (e.target.matches('.js-pago-monto-distribucion')) {
                const fila = e.target.closest('.js-pago-distribucion-row');
                if (fila) {
                    const selectCuenta = fila.querySelector('.js-pago-cuenta');
                    if (selectCuenta && selectCuenta.value) {
                        const opt = selectCuenta.options[selectCuenta.selectedIndex];
                        const saldoDisponible = parseFloat(opt.getAttribute('data-saldo')) || 0;
                        const montoDigitado = parseFloat(e.target.value) || 0;

                        // Si intenta usar más del saldo a favor o de banco
                        if (montoDigitado > saldoDisponible && saldoDisponible > 0) {
                            e.target.value = saldoDisponible.toFixed(2); // Auto-corrige el valor
                            
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ 
                                    icon: 'warning', 
                                    title: 'Límite alcanzado', 
                                    text: `Solo dispones de ${saldoDisponible.toFixed(2)}`,
                                    toast: true, 
                                    position: 'top-end', 
                                    timer: 2500, 
                                    showConfirmButton: false 
                                });
                            }
                        }
                    }
                }
                window.recalcularModalPago();
            } 
            else if (e.target.id === 'pagoTipoCambio') {
                window.recalcularConversionBimonetaria();
            } 
            // 2. SEGURO PARA EL MODAL DE PAGO MANUAL
            else if (e.target.id === 'pagoManualMontoInput' || e.target.id === 'pagoManualTipoCambio') {
                
                if (e.target.id === 'pagoManualMontoInput') {
                    const selectCuentaManual = document.getElementById('selectCuentaOrigenManual');
                    if (selectCuentaManual && selectCuentaManual.value) {
                        const optManual = selectCuentaManual.options[selectCuentaManual.selectedIndex];
                        const saldoDispManual = parseFloat(optManual.getAttribute('data-saldo')) || 0;
                        const montoDigManual = parseFloat(e.target.value) || 0;

                        if (montoDigManual > saldoDispManual && saldoDispManual > 0) {
                            e.target.value = saldoDispManual.toFixed(2); // Auto-corrige el valor
                            
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ 
                                    icon: 'warning', 
                                    title: 'Límite alcanzado', 
                                    text: `Solo dispones de ${saldoDispManual.toFixed(2)}`,
                                    toast: true, 
                                    position: 'top-end', 
                                    timer: 2500, 
                                    showConfirmButton: false 
                                });
                            }
                        }
                    }
                }
                window.recalcularConversionManual();
            }
        });

        document.addEventListener('change', (e) => {
            if (e.target.id === 'pagoManualMoneda') {
                actualizarDeudaManual();
                window.recalcularConversionManual();
            }
            
            // 1. SELECT DEL MODAL AMARILLO (Con seguro contra duplicados)
            if (e.target.matches('.js-pago-cuenta')) {
                const fila = e.target.closest('.js-pago-distribucion-row');
                
                const selectedValue = e.target.value;
                if (selectedValue) {
                    const allSelects = document.querySelectorAll('#pagoDistribucionRows .js-pago-cuenta');
                    let coincidencias = 0;
                    allSelects.forEach(sel => {
                        if (sel.value === selectedValue) coincidencias++;
                    });

                    if (coincidencias > 1) {
                        e.target.value = ''; 
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ 
                                icon: 'warning', 
                                title: 'Cuenta duplicada', 
                                text: 'No puedes usar la misma cuenta más de una vez en este pago. Por favor, suma el monto en la fila anterior.',
                                confirmButtonColor: '#ffc107'
                            });
                        }
                        if (fila) {
                            const selectMetodo = fila.querySelector('.js-pago-metodo');
                            const inputMonto = fila.querySelector('.js-pago-monto-distribucion');
                            if (selectMetodo) {
                                selectMetodo.innerHTML = '<option value="" selected disabled>Método...</option>';
                                selectMetodo.disabled = true;
                            }
                            if (inputMonto) inputMonto.value = '';
                        }
                        window.recalcularModalPago();
                        return; 
                    }
                }

                if (fila) {
                    const selectMetodo = fila.querySelector('.js-pago-metodo');
                    window.filtrarMetodosPorCuentaCxp(e.target, selectMetodo);
                }
                window.recalcularConversionBimonetaria();
            }
            // 2. SELECT DEL PAGO MANUAL
            else if (e.target.id === 'selectCuentaOrigenManual') {
                const selectMetodoManual = document.getElementById('pagoManualMetodoOrigen');
                window.filtrarMetodosPorCuentaCxp(e.target, selectMetodoManual);

                const hintSaldoManual = document.getElementById('textoSaldoDisponibleManual');
                const inputMontoManual = document.getElementById('pagoManualMontoInput');
                
                const opt = e.target.options[e.target.selectedIndex];
                if(!opt || opt.value === "") {
                    if (hintSaldoManual) hintSaldoManual.innerHTML = "";
                    if (inputMontoManual) inputMontoManual.removeAttribute('max');
                    window.recalcularConversionManual();
                    return;
                }
                
                const saldoCuenta = parseFloat(opt.getAttribute('data-saldo')) || 0;
                if (hintSaldoManual) hintSaldoManual.innerHTML = `<i class="bi bi-wallet2"></i> Saldo en banco: S/ ${saldoCuenta.toFixed(2)}`;
                
                const maximo = saldoCuenta > 0 ? saldoCuenta : 0;
                if (inputMontoManual) {
                    const monedaDeuda = document.getElementById('pagoManualMoneda').value;
                    const monedaCuenta = opt.getAttribute('data-moneda');
                    
                    if(monedaDeuda === monedaCuenta) {
                        inputMontoManual.setAttribute('max', maximo);
                        if(parseFloat(inputMontoManual.value) > maximo) {
                            inputMontoManual.value = maximo.toFixed(2);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'info', title: 'Monto reajustado', text: 'El monto superaba el saldo de la cuenta.', timer: 2500, showConfirmButton: false });
                            }
                        }
                    } else {
                        inputMontoManual.removeAttribute('max');
                    }
                }
                window.recalcularConversionManual();
            }
            // 3. SELECT DEL MODAL REEMBOLSO (Verde)
            else if (e.target.id === 'reembolsoCuentaDestino') {
                const selectMetodoReembolso = document.getElementById('reembolsoMetodoIngreso');
                window.filtrarMetodosPorCuentaCxp(e.target, selectMetodoReembolso);
            }
        });

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
            const idTercero = btn.closest('tr').dataset.search.split(' ')[0]; 
            
            document.getElementById('pagoIdOrigen').value = btn.dataset.idOrigen;
            document.getElementById('pagoMoneda').value = btn.dataset.moneda;
            document.getElementById('pagoSaldo').value = parseFloat(btn.dataset.saldo).toFixed(2);

            const monedaDoc = btn.dataset.moneda.toUpperCase();
            const simbolo = (monedaDoc === 'USD') ? '$' : 'S/';
            document.querySelectorAll('.js-lbl-moneda-doc-addon').forEach(el => el.textContent = simbolo);
            
            // 👇 NUEVO: Calcular saldo a favor visual y agregarlo al Select 👇
            const provName = btn.closest('tr').querySelector('td').innerText.trim().split('\n')[0];
            let saldoFav = 0;
            
            // Buscamos todas las Notas de Crédito de este proveedor en la tabla
            document.querySelectorAll('#cxpTableBody tr').forEach(row => {
                const rowProv = row.querySelector('td').innerText.trim().split('\n')[0];
                if(rowProv === provName) {
                    const tdSaldo = row.querySelector('td:nth-child(6)');
                    if(tdSaldo && tdSaldo.textContent.includes('+')) {
                        saldoFav += parseFloat(tdSaldo.textContent.replace(/[^\d.-]/g, ''));
                    }
                }
            });

            const inyectarSaldoFavor = (selectElement) => {
                // Limpiar opción anterior si existe
                Array.from(selectElement.options).forEach(o => { if(o.value === 'SALDO_FAVOR') o.remove(); });
                // Crear la cuenta virtual si hay saldo
                if(saldoFav > 0) {
                    const opt = document.createElement('option');
                    opt.value = 'SALDO_FAVOR';
                    opt.className = 'text-success fw-bold bg-success-subtle';
                    opt.setAttribute('data-saldo', saldoFav);
                    opt.setAttribute('data-moneda', monedaDoc);
                    opt.innerHTML = `⭐ USAR SALDO A FAVOR (Disp: ${monedaDoc} ${saldoFav.toFixed(2)})`;
                    selectElement.insertBefore(opt, selectElement.options[1]); // Lo pone de segundo, debajo de "Cuenta origen..."
                }
            };
            
            document.querySelectorAll('.js-pago-cuenta').forEach(inyectarSaldoFavor);
            // 👆 FIN NUEVO 👆

            // 👇 NUEVO: Consulta rápida para ver si hay saldos a favor globales
            const alertContainer = document.getElementById('alertaSaldoAFavorIndividual');
            if (alertContainer) alertContainer.innerHTML = '';
            
            // Extraer el ID del proveedor desde el botón de historial que está al lado
            const btnHistorial = btn.parentElement.querySelector('[href*="id_tercero="]');
            if (btnHistorial) {
                const idProvUrl = new URL(btnHistorial.href).searchParams.get('id_tercero');
                fetch(`${window.location.origin}${window.location.pathname}?ruta=tesoreria/ajax_obtener_deuda_tercero&tipo=CXP&id_tercero=${idProvUrl}&moneda=${monedaDoc}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok && parseFloat(data.deuda) < parseFloat(btn.dataset.saldo)) {
                        }
                    });
            }
            // 👆 FIN NUEVO

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
        // 👇 NUEVO BLOQUE DE REEMBOLSO AGREGADO AQUÍ 👇
        else if (e.target.closest('.js-open-reembolso')) {
            const btn = e.target.closest('.js-open-reembolso');
            document.getElementById('reembolsoIdOrigen').value = btn.dataset.idOrigen;
            document.getElementById('reembolsoMoneda').value = btn.dataset.moneda;
            document.getElementById('reembolsoMonto').value = parseFloat(btn.dataset.saldo).toFixed(2);
            document.getElementById('reembolsoMonto').setAttribute('max', parseFloat(btn.dataset.saldo).toFixed(2));
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

            const tc = parseFloat(document.getElementById('pagoTipoCambio')?.value) || 1;
            const monedaDeuda = (document.getElementById('pagoMoneda')?.value || '').trim().toUpperCase();

            const filas = document.querySelectorAll('.js-pago-distribucion-row');
            filas.forEach(fila => {
                const selectCuenta = fila.querySelector('.js-pago-cuenta');
                const montoInput = fila.querySelector('.js-pago-monto-distribucion');
                
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
                if (montosPorCuenta[idC] > saldosPorCuenta[idC]) {
                    erroresSaldo.push(`La cuenta <b>${nombresCuentas[idC]}</b> no tiene saldo suficiente.<br>Se intentan extraer ${montosPorCuenta[idC].toFixed(2)} pero dispone de ${saldosPorCuenta[idC].toFixed(2)}.`);
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
            
            if (containerConversion) containerConversion.style.display = 'none';
            if (inputTipoCambio) inputTipoCambio.removeAttribute('required');

            window.recalcularModalPago();
            [inputCapital, inputInteres].forEach(el => el?.classList.remove('is-invalid'));
            if (naturalezaSelect) naturalezaSelect.dispatchEvent(new Event('change'));
        });
    }

    if (modalPago) {
        // (Tu código actual del modalPago se mantiene intacto aquí arriba)
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