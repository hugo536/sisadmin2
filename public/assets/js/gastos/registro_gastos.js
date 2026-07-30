(function(){
  'use strict';

  const APP_SELECTOR = '#gastosRegistroApp';
  const TOM_SELECT_IDS = ['idConceptoGasto', 'id_proveedor', 'idCentroCostoGasto'];

  function onReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }
    callback();
  }

  function initGastosRegistro(){
    const app = document.querySelector(APP_SELECTOR);
    if (!app || app.dataset.gastosRegistroInit === '1') {
      return;
    }

    app.dataset.gastosRegistroInit = '1';
    
    // ==========================================
    // 1. Inicialización de selectores avanzados (TomSelect)
    // ==========================================
    function inicializarTomSelects() {
      if (typeof window.TomSelect === 'undefined') return false;

      TOM_SELECT_IDS.forEach(function(id) {
        const elemento = document.getElementById(id);
        if (elemento && !elemento.tomselect) {
          new window.TomSelect(elemento, {
            create: false, 
            sortField: { field: 'text', direction: 'asc' },
            placeholder: elemento.dataset.tomPlaceholder || elemento.getAttribute('placeholder') || 'Seleccione una opción...'
          });
        }
      });
      return true;
    }

    function inicializarTomSelectsConReintentos(intentosRestantes) {
      if (inicializarTomSelects()) return;
      if (intentosRestantes <= 0) {
        console.warn('TomSelect no se pudo cargar para Registro de Gastos. Se mantendrán selectores simples.');
        return;
      }
      window.setTimeout(function () {
        inicializarTomSelectsConReintentos(intentosRestantes - 1);
      }, 150);
    }

    inicializarTomSelectsConReintentos(20);

    const modalTom = document.getElementById('modalNuevoGasto');
    if (modalTom) {
      modalTom.addEventListener('shown.bs.modal', function () {
        inicializarTomSelectsConReintentos(20);
      });
    }

    const selectConcepto = document.getElementById('idConceptoGasto');
    const selectCentroCosto = document.getElementById('idCentroCostoGasto');

    // ==========================================
    // 3. Modal de Detalles de Gasto
    // ==========================================
    const modalEl = document.getElementById('modalDetalleGasto');
    const modalDetalle = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;

    function setText(id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = value || '-';
    }

    app.addEventListener('click', function(ev) {
      const btn = ev.target.closest('.js-ver-gasto');
      if (!btn || !modalDetalle) return;

      const monedaDoc = btn.dataset.moneda || 'PEN';
      const simbolo = (monedaDoc === 'USD') ? '$ ' : 'S/ ';

      setText('detGastoId', btn.dataset.id || '-');
      setText('detGastoFecha', btn.dataset.fecha || '-');
      setText('detGastoProveedor', btn.dataset.proveedor || '-');
      setText('detGastoConcepto', btn.dataset.concepto || '-');
      setText('detGastoImpuesto', btn.dataset.impuesto || '-');
      setText('detGastoMonto', btn.dataset.monto ? simbolo + btn.dataset.monto : '-');
      setText('detGastoTotal', btn.dataset.total ? simbolo + btn.dataset.total : '-');
      setText('detGastoEstado', btn.dataset.estado || '-');
      setText('detGastoCxp', btn.dataset.cxp && btn.dataset.cxp !== '0' ? btn.dataset.cxp : 'No generado');
      setText('detGastoAsiento', btn.dataset.asiento && btn.dataset.asiento !== '0' ? btn.dataset.asiento : 'No generado');
      setText('detGastoObservacion', btn.dataset.observacion || '-');

      modalDetalle.show();
    });

    // ==============================================================
    // --- MAGIA: FILTRADO DINÁMICO DE MÉTODOS POR CUENTA ---
    // ==============================================================
    function filtrarMetodosPorCuentaGastos(selectCuenta, selectMetodo) {
        if (!selectCuenta || !selectMetodo) return;

        const idCuentaSeleccionada = parseInt(selectCuenta.value);
        const valorPrevio = selectMetodo.value; 
        
        selectMetodo.innerHTML = '<option value="" selected disabled>Método...</option>';

        const arrayCuentas = Array.isArray(window.TESORERIA_CUENTAS) ? window.TESORERIA_CUENTAS : Object.values(window.TESORERIA_CUENTAS || {});
        const arrayMetodos = Array.isArray(window.TESORERIA_METODOS) ? window.TESORERIA_METODOS : Object.values(window.TESORERIA_METODOS || {});

        if (!idCuentaSeleccionada) {
            arrayMetodos.forEach(m => selectMetodo.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
            return;
        }

        const cuentaObj = arrayCuentas.find(c => parseInt(c.id) === idCuentaSeleccionada);
        if (!cuentaObj) return;

        let metodosPermitidos = [];
        let tieneFiltro = false; 

        let rawMetodos = cuentaObj.metodos_pago;

        if (rawMetodos === null || rawMetodos === "" || rawMetodos === "null" || rawMetodos === "[]") {
            tieneFiltro = true;
            metodosPermitidos = [];
        } 
        else if (rawMetodos !== undefined) {
            try {
                let parsed = rawMetodos;
                while(typeof parsed === 'string') { parsed = JSON.parse(parsed); }
                if (Array.isArray(parsed)) {
                    metodosPermitidos = parsed;
                    tieneFiltro = true;
                }
            } catch (e) {
                console.error("Error al parsear el JSON de métodos:", rawMetodos);
            }
        }

        const permitidosNormalizados = metodosPermitidos.map(m => String(m).trim().toLowerCase());
        let primerValido = null;
        let encontroPrevio = false;

        arrayMetodos.forEach(m => {
            const nombreDB = String(m.nombre).trim().toLowerCase();
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

        let conteoValidos = selectMetodo.options.length - 1;
        
        if (conteoValidos <= 0) {
            selectMetodo.innerHTML = '<option value="" selected disabled>Sin métodos configurados</option>';
        } else {
            if (encontroPrevio) {
                selectMetodo.value = valorPrevio;
            } else if (conteoValidos === 1 && primerValido) {
                selectMetodo.value = primerValido;
            } else {
                selectMetodo.value = '';
            }
        }
    }


    // ==========================================
    // 4. Lógica de Cobro Inmediato (Multimoneda)
    // ==========================================
    const switchPago = document.getElementById('switchPagoInmediato');
    const seccionPago = document.getElementById('seccionPagoInmediato');
    const contenedorPagos = document.getElementById('contenedorMetodosPagoGasto');
    const btnAgregarPago = document.getElementById('btnAgregarPagoInmediatoGasto');
    const totalPagadoText = document.getElementById('totalPagadoInmediatoGasto');
    const inputMontoTotalGasto = document.getElementById('gastoMontoTotal');
    const selectMonedaGasto = document.getElementById('gastoMoneda');

    // NUEVO: Funciones Bimonetarias
    function actualizarSimboloMonedaUI() {
        const moneda = selectMonedaGasto ? selectMonedaGasto.value : 'PEN';
        const simbolo = moneda === 'USD' ? '$' : 'S/';
        
        document.querySelectorAll('.js-lbl-moneda-gasto').forEach(el => el.textContent = simbolo);
        calcularTotalPagado();
    }

    window.recalcularConversionGasto = function() {
        const monedaGasto = (selectMonedaGasto?.value || 'PEN').trim().toUpperCase();
        const containerConversion = document.getElementById('gastoContainerConversion');
        const inputTipoCambio = document.getElementById('gastoTipoCambio');
        const inputMontoConvertido = document.getElementById('gastoMontoConvertido');
        const labelMontoConvertido = document.getElementById('gastoLabelMontoConvertido');
        
        if (!containerConversion || !inputTipoCambio || !inputMontoConvertido) return;

        let cruzaMoneda = false;
        let cuentaMonedaDiferente = '';
        let montoTotalAExtraerConvertido = 0;
        const tc = parseFloat(inputTipoCambio.value) || 0;

        document.querySelectorAll('.fila-pago-gasto').forEach(fila => {
            const selCuenta = fila.querySelector('.select-cuenta-pago');
            const inputMonto = fila.querySelector('.input-monto-pago');
            const opt = selCuenta.options[selCuenta.selectedIndex];
            const monto = parseFloat(inputMonto.value) || 0;
            
            if (opt && opt.value) {
                const monedaCuenta = (opt.getAttribute('data-moneda') || '').toUpperCase();
                if (monedaCuenta && monedaCuenta !== monedaGasto) {
                    cruzaMoneda = true;
                    cuentaMonedaDiferente = monedaCuenta;
                    
                    if (tc > 0) {
                        if (monedaGasto === 'USD' && monedaCuenta === 'PEN') {
                            montoTotalAExtraerConvertido += (monto * tc);
                        } else if (monedaGasto === 'PEN' && monedaCuenta === 'USD') {
                            montoTotalAExtraerConvertido += (monto / tc);
                        }
                    }
                } else {
                    montoTotalAExtraerConvertido += monto; 
                }
            }
        });

        if (cruzaMoneda) {
            containerConversion.style.display = 'block';
            inputTipoCambio.setAttribute('required', 'required');
            labelMontoConvertido.innerText = `Monto a descontar (${cuentaMonedaDiferente})`;
            
            if (tc > 0) {
                inputMontoConvertido.value = montoTotalAExtraerConvertido.toFixed(2);
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

    function calcularTotalPagado() {
      let total = 0;
      const inputsMonto = contenedorPagos.querySelectorAll('.input-monto-pago');
      
      inputsMonto.forEach(input => {
        total += parseFloat(input.value) || 0;
      });
      
      if (totalPagadoText) {
        const moneda = selectMonedaGasto?.value === 'USD' ? '$' : 'S/';
        totalPagadoText.textContent = moneda + ' ' + total.toFixed(2);
        
        const totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
        
        if (total > totalGasto) {
            totalPagadoText.className = 'fw-bold fs-5 text-danger'; 
        } else if (total === totalGasto && total > 0) {
            totalPagadoText.className = 'fw-bold fs-5 text-success'; 
        } else {
            totalPagadoText.className = 'fw-bold fs-5 text-dark'; 
        }
      }
    }

    function agregarFilaPago() {
      const cuentas = window.TESORERIA_CUENTAS || [];
      const moneda = selectMonedaGasto?.value === 'USD' ? '$' : 'S/';
      
      let opcionesCuentas = '<option value="" selected disabled>Seleccionar Cuenta...</option>';
      cuentas.forEach(c => { 
          const saldo = parseFloat(c.saldo_actual || c.saldo || 0);
          opcionesCuentas += `<option value="${c.id}" data-saldo="${saldo}" data-moneda="${c.moneda}">${c.nombre} (Disp: ${c.moneda} ${saldo.toFixed(2)})</option>`; 
      });
      
      let totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
      let totalActual = 0;
      contenedorPagos.querySelectorAll('.input-monto-pago').forEach(inp => totalActual += (parseFloat(inp.value) || 0));
      let montoSugerido = Math.max(0, totalGasto - totalActual).toFixed(2);

      const numFilas = contenedorPagos.querySelectorAll('.fila-pago-gasto').length;

      const div = document.createElement('div');
      div.className = 'd-flex flex-column flex-sm-row gap-2 align-items-start align-items-sm-center bg-white p-2 rounded border border-success-subtle mb-2 fila-pago-gasto animate__animated animate__fadeIn';
      
      div.innerHTML = `
        <div class="w-100">
            <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-cuenta-pago" name="pago_cuenta[]" required>
                ${opcionesCuentas}
            </select>
        </div>
        <div class="w-100">
            <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary select-metodo-pago" name="pago_metodo[]" required disabled>
                <option value="" selected disabled>Método...</option>
            </select>
        </div>
        <div class="w-100 d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm w-100">
                <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle js-lbl-moneda-gasto">${moneda}</span>
                <input type="number" step="0.01" min="0.01" class="form-control text-end text-success fw-bold shadow-none border-secondary-subtle input-monto-pago" name="pago_monto[]" value="${montoSugerido}" placeholder="0.00" required readonly>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-quitar-pago ${numFilas === 0 ? 'd-none' : ''} px-2" title="Quitar pago">
                <i class="bi bi-trash"></i>
            </button>
        </div>
      `;
      
      contenedorPagos.appendChild(div);

      const selCuenta = div.querySelector('.select-cuenta-pago');
      const selMetodo = div.querySelector('.select-metodo-pago');
      const inputMonto = div.querySelector('.input-monto-pago');
      
      selCuenta.addEventListener('change', () => {
          filtrarMetodosPorCuentaGastos(selCuenta, selMetodo);
          selMetodo.disabled = !selCuenta.value;
          inputMonto.readOnly = !selMetodo.value;
          
          window.recalcularConversionGasto();
          
          // Validación de saldo visual suave si cruza moneda, estricta si es misma moneda
          const opt = selCuenta.options[selCuenta.selectedIndex];
          if(opt && opt.value) {
              const saldoDisp = parseFloat(opt.getAttribute('data-saldo')) || 0;
              const monedaCuenta = (opt.getAttribute('data-moneda') || '').toUpperCase();
              const monedaGasto = (selectMonedaGasto?.value || 'PEN').toUpperCase();
              
              if(monedaCuenta === monedaGasto) {
                  inputMonto.setAttribute('max', saldoDisp > 0 ? saldoDisp : 0);
                  if(parseFloat(inputMonto.value) > saldoDisp) {
                      inputMonto.value = saldoDisp > 0 ? saldoDisp.toFixed(2) : '';
                      if (typeof Swal !== 'undefined') {
                          Swal.fire({ icon: 'info', title: 'Monto reajustado', text: 'El monto supera el saldo disponible de esta cuenta.', timer: 2500, showConfirmButton: false });
                      }
                  }
              } else {
                  inputMonto.removeAttribute('max'); // Se validará en el submit final con TC
              }
          } else {
              inputMonto.removeAttribute('max');
          }

          calcularTotalPagado();
      });

      selMetodo.addEventListener('change', () => {
          inputMonto.readOnly = !selMetodo.value;
          if (selMetodo.value && !inputMonto.value) inputMonto.focus();
          calcularTotalPagado();
      });
      
      div.querySelector('.btn-quitar-pago').addEventListener('click', function() {
        div.remove();
        const filasRestantes = contenedorPagos.querySelectorAll('.fila-pago-gasto');
        if (filasRestantes.length === 1) {
            filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
        }
        calcularTotalPagado();
        window.recalcularConversionGasto();
      });
      
      inputMonto.addEventListener('input', () => {
          calcularTotalPagado();
          window.recalcularConversionGasto();
      });
      
      calcularTotalPagado();
    }

    if (switchPago && seccionPago) {
      switchPago.addEventListener('change', function() {
        const totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
        
        if (this.checked && totalGasto <= 0) {
            this.checked = false;
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: 'Ingrese el Monto', text: 'Debe ingresar el Monto Total del gasto antes de habilitar el pago al contado.', timer: 3000, showConfirmButton: false });
            }
            if (inputMontoTotalGasto) inputMontoTotalGasto.focus();
            return;
        }

        if (this.checked) {
          seccionPago.classList.remove('d-none');
          if (contenedorPagos.children.length === 0) {
            agregarFilaPago();
          } else {
            calcularTotalPagado();
            window.recalcularConversionGasto();
          }
        } else {
          seccionPago.classList.add('d-none');
          contenedorPagos.innerHTML = '';
          calcularTotalPagado();
          window.recalcularConversionGasto();
        }
      });
    }

    if (btnAgregarPago) {
      btnAgregarPago.addEventListener('click', () => {
          agregarFilaPago();
          contenedorPagos.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
      });
    }

    if (selectMonedaGasto) {
        selectMonedaGasto.addEventListener('change', () => {
            actualizarSimboloMonedaUI();
            window.recalcularConversionGasto();
            
            // Si el pago inmediato está encendido, forzamos re-validación de las filas
            if (switchPago && switchPago.checked) {
                contenedorPagos.querySelectorAll('.select-cuenta-pago').forEach(select => {
                    select.dispatchEvent(new Event('change'));
                });
            }
        });
    }

    const inputTipoCambio = document.getElementById('gastoTipoCambio');
    if (inputTipoCambio) {
        inputTipoCambio.addEventListener('input', () => {
            window.recalcularConversionGasto();
        });
    }
    
    if (inputMontoTotalGasto) {
      inputMontoTotalGasto.addEventListener('input', () => {
          calcularTotalPagado();
          if (switchPago && switchPago.checked) {
              const filasPago = contenedorPagos.querySelectorAll('.fila-pago-gasto');
              if (filasPago.length === 1) { 
                  filasPago[0].querySelector('.input-monto-pago').value = inputMontoTotalGasto.value;
              }
              calcularTotalPagado();
              window.recalcularConversionGasto();
          }
      });
    }

    // ==========================================
    // 5. Limpieza automática del Modal al Cerrar
    // ==========================================
    const modalNuevoGastoEl = document.getElementById('modalNuevoGasto');
    
    if (modalNuevoGastoEl) {
      modalNuevoGastoEl.addEventListener('hidden.bs.modal', function () {
        const formulario = document.getElementById('formNuevoGasto');
        if (formulario) formulario.reset();

        TOM_SELECT_IDS.forEach(function(id) {
          const elemento = document.getElementById(id);
          if (elemento && elemento.tomselect) elemento.tomselect.clear(true);
        });

        if (switchPago) switchPago.checked = false;
        if (seccionPago) seccionPago.classList.add('d-none');
        if (contenedorPagos) contenedorPagos.innerHTML = '';
        
        const containerConversion = document.getElementById('gastoContainerConversion');
        if (containerConversion) containerConversion.style.display = 'none';

        if (totalPagadoText) {
          totalPagadoText.textContent = 'S/ 0.00';
          totalPagadoText.className = 'fw-bold text-dark fs-5';
        }
        
        actualizarSimboloMonedaUI();
      });
    }

    // ==========================================
    // 6. Validación al Guardar el Gasto
    // ==========================================
    const formNuevoGasto = document.getElementById('formNuevoGasto');
    
    if (formNuevoGasto) {
      formNuevoGasto.addEventListener('submit', function(e) {
        const switchPagoCheck = document.getElementById('switchPagoInmediato');
        
        if (switchPagoCheck && switchPagoCheck.checked) {
          const contenedorMetodos = document.getElementById('contenedorMetodosPagoGasto');
          const inputMontoGasto = document.getElementById('gastoMontoTotal');
          const tcGlobal = parseFloat(document.getElementById('gastoTipoCambio')?.value) || 1;
          const monedaGasto = (document.getElementById('gastoMoneda')?.value || 'PEN').toUpperCase();
            
          let totalPagado = 0;
          let faltanDatos = false;

          let montosPorCuenta = {};
          let saldosPorCuenta = {};
          let nombresCuentas = {};

          if (contenedorMetodos) {
              contenedorMetodos.querySelectorAll('.fila-pago-gasto').forEach(fila => {
                const selCuenta = fila.querySelector('.select-cuenta-pago');
                const cuenta = selCuenta.value;
                const metodo = fila.querySelector('.select-metodo-pago').value;
                const monto = parseFloat(fila.querySelector('.input-monto-pago').value) || 0;
                
                if (!cuenta || !metodo || monto <= 0) {
                    faltanDatos = true;
                }

                if (cuenta && monto > 0) {
                    const opt = selCuenta.options[selCuenta.selectedIndex];
                    const saldo = parseFloat(opt.getAttribute('data-saldo')) || 0;
                    const monedaCuenta = (opt.getAttribute('data-moneda') || '').toUpperCase();
                    const nombreStr = opt.text.split('(')[0].trim();
                    
                    let montoAExtraerConvertido = monto;

                    if (monedaCuenta && monedaGasto && monedaCuenta !== monedaGasto) {
                        if (monedaGasto === 'USD' && monedaCuenta === 'PEN') {
                            montoAExtraerConvertido = monto * tcGlobal;
                        } else if (monedaGasto === 'PEN' && monedaCuenta === 'USD') {
                            montoAExtraerConvertido = monto / tcGlobal;
                        }
                    }

                    if (!montosPorCuenta[cuenta]) {
                        montosPorCuenta[cuenta] = 0;
                        saldosPorCuenta[cuenta] = saldo;
                        nombresCuentas[cuenta] = nombreStr;
                    }
                    montosPorCuenta[cuenta] += montoAExtraerConvertido;
                }

                totalPagado += monto;
              });
          }
          
          if (faltanDatos) {
            e.preventDefault();
            Swal.fire('Faltan Datos', 'Debe seleccionar la Cuenta y el Método en todas las filas de pago.', 'warning');
            return;
          }

          let erroresSaldo = [];
          for (const idC in montosPorCuenta) {
              if (montosPorCuenta[idC] > saldosPorCuenta[idC]) {
                  erroresSaldo.push(`La cuenta <b>${nombresCuentas[idC]}</b> no tiene saldo suficiente. Intentas extraer el equivalente a ${montosPorCuenta[idC].toFixed(2)} pero solo dispone de ${saldosPorCuenta[idC].toFixed(2)}.`);
              }
          }

          if (erroresSaldo.length > 0) {
              e.preventDefault();
              Swal.fire({
                  icon: 'error',
                  title: 'Fondos insuficientes',
                  html: erroresSaldo.join('<br><br>')
              });
              return;
          }

          const totalGasto = parseFloat(inputMontoGasto ? inputMontoGasto.value : 0) || 0;
          
          if (totalPagado === 0) {
            e.preventDefault(); 
            Swal.fire('Atención', 'Has activado el pago inmediato pero no has ingresado un monto válido.', 'warning');
            return;
          }

          if (totalPagado > totalGasto + 0.01) {
            e.preventDefault();
            const smbl = monedaGasto === 'USD' ? '$' : 'S/';
            Swal.fire('Error', `El monto ingresado (${smbl} ${totalPagado.toFixed(2)}) supera el total del gasto (${smbl} ${totalGasto.toFixed(2)}).`, 'error');
            return;
          }

          if (totalPagado < totalGasto - 0.01) {
            e.preventDefault();
            const smbl = monedaGasto === 'USD' ? '$' : 'S/';
            Swal.fire({
              icon: 'warning',
              title: 'Pago Incompleto',
              text: `El gasto es de ${smbl} ${totalGasto.toFixed(2)}, pero solo se registrarán ${smbl} ${totalPagado.toFixed(2)}. La diferencia quedará como deuda. ¿Deseas guardar así?`,
              showCancelButton: true,
              confirmButtonText: 'Sí, guardar con deuda',
              cancelButtonText: 'No, corregir monto',
              confirmButtonColor: '#ffc107',
              cancelButtonColor: '#6c757d'
            }).then((result) => {
              if (result.isConfirmed) {
                formNuevoGasto.submit(); 
              }
            });
            return;
          }
        }
      });
    }
    
  }

  onReady(initGastosRegistro);
  document.addEventListener('sisadmin:route-loaded', initGastosRegistro);
})();