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
    const naturalezaSelect = document.getElementById('cobroNaturaleza');
    const inputCapital = document.getElementById('cobroMontoCapital');
    const inputInteres = document.getElementById('cobroMontoInteres');

    const roundTo = (val, dec) => Math.round((Number(val) + Number.EPSILON) * Math.pow(10, dec)) / Math.pow(10, dec);

    // --- FUNCIÓN TOTALIZADORA AUTOMÁTICA ---
    window.recalcularModalCobro = function() {
        const inputTotal = document.getElementById('cobroMonto');
        const hintDistribucion = document.getElementById('cobroDistribucionHint');
        const filas = document.querySelectorAll('.js-cobro-distribucion-row');
        
        if (!inputTotal) return;

        // Sumar todos los inputs de distribución
        let suma = 0;
        document.querySelectorAll('.js-cobro-monto-distribucion').forEach(inp => {
            suma += parseFloat(inp.value) || 0;
        });

        // Actualizar el campo Total (readonly)
        inputTotal.value = suma > 0 ? suma.toFixed(2) : '';

        // Ocultar/Mostrar botones de basurero (solo mostrar si hay más de 1 fila)
        filas.forEach(fila => {
            const btnQuitar = fila.querySelector('.js-remove-cobro-row');
            if (btnQuitar) {
                if (filas.length > 1) btnQuitar.classList.remove('d-none');
                else btnQuitar.classList.add('d-none');
            }
        });

        // Actualizar el texto de ayuda
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

    // --- FUNCIÓN PARA AGREGAR FILA ---
    window.agregarFilaDistribucion = function() {
        const container = document.getElementById('cobroDistribucionRows');
        const filas = container.querySelectorAll('.js-cobro-distribucion-row');
        if (filas.length === 0) return;

        const nuevaFila = filas[0].cloneNode(true);
        
        nuevaFila.querySelector('.js-cobro-cuenta').value = '';
        
        // Limpiamos y bloqueamos el método
        const selectMetodo = nuevaFila.querySelector('.js-cobro-metodo');
        selectMetodo.value = '';
        selectMetodo.disabled = true; 
        
        nuevaFila.querySelector('.js-cobro-monto-distribucion').value = '';

        container.appendChild(nuevaFila);
        window.recalcularModalCobro();
    };

    // --- FUNCIÓN MAGIA: FILTRADO DE MÉTODOS (CORREGIDA) ---
    window.filtrarMetodosPorCuenta = function(selectCuenta, selectMetodo) {
        if (!selectCuenta || !selectMetodo) return;

        const optSeleccionada = selectCuenta.options[selectCuenta.selectedIndex];
        
        // Si no hay cuenta seleccionada, bloqueamos el método
        if (!optSeleccionada || !optSeleccionada.value) {
            selectMetodo.value = '';
            selectMetodo.disabled = true;
            return;
        }

        let rawMetodos = optSeleccionada.getAttribute('data-metodos');
        let permitidos = [];

        // Extraer los métodos permitidos (si los hay)
        if (rawMetodos && rawMetodos !== 'null' && rawMetodos !== '') {
            try {
                let parsed = rawMetodos;
                // Si viene como string escapado, lo parseamos hasta que sea un Array
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

        // Ocultar/Mostrar opciones según la configuración estricta
        Array.from(selectMetodo.options).forEach(opt => {
            if (!opt.value) return; // Ignorar el placeholder "Método..."
            
            const nombreMetodo = opt.textContent.trim().toLowerCase();
            
            // LA CLAVE: Si "permitidos" está vacío, NINGÚN método es válido.
            const esValido = permitidos.some(p => nombreMetodo.includes(p) || p.includes(nombreMetodo));
            
            opt.hidden = !esValido;
            opt.disabled = !esValido;

            if (esValido) {
                opcionesValidasCount++;
                if (!primerValido) primerValido = opt.value;
                if (opt.value === valorActual) seleccionActualValida = true;
            }
        });

        // Qué hacer con el select según la cantidad de métodos válidos
        if (opcionesValidasCount === 0) {
            // Si la cuenta tiene 0 métodos vinculados, se bloquea la cajita
            selectMetodo.value = '';
            selectMetodo.disabled = true;
            
            // Opcional: mostrar una alerta si quieres que el cajero sepa el por qué
            // console.warn("La cuenta seleccionada no tiene métodos de pago configurados.");
        } else {
            // Si tiene métodos, la habilitamos y seleccionamos el primero válido si es necesario
            selectMetodo.disabled = false;
            if (!seleccionActualValida) {
                selectMetodo.value = primerValido || '';
            }
        }
    };

    if (!window.cxcEventosGlobalesAtachados) {
        window.cxcEventosGlobalesAtachados = true; // Candado activado

        document.addEventListener('input', (e) => {
            if (e.target.matches('.js-cobro-monto-distribucion')) {
                window.recalcularModalCobro();
            }
        });

        // Escuchar cuando el usuario cambia de Cuenta
        document.addEventListener('change', (e) => {
            // Modal múltiple (filas clonables)
            if (e.target.matches('.js-cobro-cuenta')) {
                const fila = e.target.closest('.js-cobro-distribucion-row');
                if (fila) {
                    const selectMetodo = fila.querySelector('.js-cobro-metodo');
                    window.filtrarMetodosPorCuenta(e.target, selectMetodo);
                }
            }
            // Modal Manual
            else if (e.target.id === 'cobroManualCuentaDestino') {
                // Asegúrate de que el select de métodos en el modal manual tenga este id o ajusta el selector
                const selectMetodo = document.getElementById('cobroManualMetodoDestino') 
                                  || document.querySelector('#modalCobroManual select[name="id_metodo_pago"]');
                window.filtrarMetodosPorCuenta(e.target, selectMetodo);
            }
        });

        document.addEventListener('click', (e) => {
            // 1. Clic en Dividir Pago
            if (e.target.closest('#btnAddCobroDistribucion')) {
                window.agregarFilaDistribucion();
            } 
            // 2. Clic en Quitar Fila
            else if (e.target.closest('.js-remove-cobro-row')) {
                const fila = e.target.closest('.js-cobro-distribucion-row');
                if (document.querySelectorAll('.js-cobro-distribucion-row').length > 1 && fila) {
                    fila.remove();
                    window.recalcularModalCobro();
                }
            }
            // 3. Clic al abrir el modal (Botón $)
            else if (e.target.closest('.js-open-cobro')) {
                const btn = e.target.closest('.js-open-cobro');
                document.getElementById('cobroIdOrigen').value = btn.dataset.idOrigen;
                document.getElementById('cobroMoneda').value = btn.dataset.moneda;
                document.getElementById('cobroSaldo').value = parseFloat(btn.dataset.saldo).toFixed(2);
                
                // Limpiar exceso de filas antiguas y reiniciar la primera
                const filas = document.querySelectorAll('.js-cobro-distribucion-row');
                filas.forEach((r, i) => {
                    if (i === 0) {
                        r.querySelectorAll('input, select').forEach(inpt => inpt.value = '');
                        // BUG FIX: Nace vacío para que el usuario escriba desde cero
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

    // --- VALIDACIONES DE FORMULARIO ---
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
        // Prevenir múltiples validaciones apiladas
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