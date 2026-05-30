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
        // Validamos que exista y que NO tenga ya un tomselect creado
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

    // Intento 1: Al cargar/inyectar la página, con reintentos por si la CDN de TomSelect termina después.
    inicializarTomSelectsConReintentos(20);

    // Intento 2: Cuando el modal termina de aparecer en pantalla
    const modalTom = document.getElementById('modalNuevoGasto');
    if (modalTom) {
      modalTom.addEventListener('shown.bs.modal', function () {
        inicializarTomSelectsConReintentos(20);
      });
    }

    // ==========================================
    // 2. Autocompletado de Centro de Costo
    // ==========================================
    // (Desactivado a petición: El usuario seleccionará el Centro de Costo de forma 100% manual)
    const selectConcepto = document.getElementById('idConceptoGasto');
    const selectCentroCosto = document.getElementById('idCentroCostoGasto');


    // ==========================================
    // 3. Modal de Detalles de Gasto
    // ==========================================
    const modalEl = document.getElementById('modalDetalleGasto');
    const modalDetalle = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;

    function setText(id, value) {
      const el = document.getElementById(id);
      if (el) {
        el.textContent = value || '-';
      }
    }

    app.addEventListener('click', function(ev) {
      const btn = ev.target.closest('.js-ver-gasto');
      if (!btn || !modalDetalle) {
        return;
      }

      setText('detGastoId', btn.dataset.id || '-');
      setText('detGastoFecha', btn.dataset.fecha || '-');
      setText('detGastoProveedor', btn.dataset.proveedor || '-');
      setText('detGastoConcepto', btn.dataset.concepto || '-');
      setText('detGastoImpuesto', btn.dataset.impuesto || '-');
      setText('detGastoMonto', btn.dataset.monto ? 'S/ ' + btn.dataset.monto : '-');
      setText('detGastoTotal', btn.dataset.total ? 'S/ ' + btn.dataset.total : '-');
      setText('detGastoEstado', btn.dataset.estado || '-');
      setText('detGastoCxp', btn.dataset.cxp && btn.dataset.cxp !== '0' ? btn.dataset.cxp : 'No generado');
      setText('detGastoAsiento', btn.dataset.asiento && btn.dataset.asiento !== '0' ? btn.dataset.asiento : 'No generado');
      
      // 👇 NUEVA LÍNEA PARA MOSTRAR LA OBSERVACIÓN 👇
      setText('detGastoObservacion', btn.dataset.observacion || '-');

      modalDetalle.show();
    });

    // ==============================================================
    // --- MAGIA OPCIÓN B: FILTRADO DINÁMICO DE MÉTODOS POR CUENTA ---
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

        let conteoValidos = selectMetodo.options.length - 1; // Restamos la opción por defecto
        
        if (conteoValidos <= 0) {
            selectMetodo.innerHTML = '<option value="" selected disabled>Sin métodos configurados</option>';
        } else {
            if (encontroPrevio) {
                selectMetodo.value = valorPrevio;
            } else if (conteoValidos === 1 && primerValido) {
                // Auto-seleccionar si solo existe 1 opción válida
                selectMetodo.value = primerValido;
            } else {
                // Si hay más de 1, lo dejamos vacío para que el usuario elija
                selectMetodo.value = '';
            }
        }
    }


    // ==========================================
    // 4. Lógica de Cobro Inmediato (Nuevo - Estilo Ventas)
    // ==========================================
    const switchPago = document.getElementById('switchPagoInmediato');
    const seccionPago = document.getElementById('seccionPagoInmediato');
    const contenedorPagos = document.getElementById('contenedorMetodosPagoGasto');
    const btnAgregarPago = document.getElementById('btnAgregarPagoInmediatoGasto');
    const totalPagadoText = document.getElementById('totalPagadoInmediatoGasto');
    const inputMontoTotalGasto = document.getElementById('gastoMontoTotal');

    // Función para calcular el total ingresado en las filas de pago (Con colores UX)
    function calcularTotalPagado() {
      let total = 0;
      const inputsMonto = contenedorPagos.querySelectorAll('.input-monto-pago');
      
      inputsMonto.forEach(input => {
        total += parseFloat(input.value) || 0;
      });
      
      if (totalPagadoText) {
        totalPagadoText.textContent = 'S/ ' + total.toFixed(2);
        
        // Cambio de color visual calcando el estilo de Ventas
        const totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
        
        if (total > totalGasto) {
            totalPagadoText.className = 'fw-bold fs-5 text-danger'; // Rojo si se pasa
        } else if (total === totalGasto && total > 0) {
            totalPagadoText.className = 'fw-bold fs-5 text-success'; // Verde si está exacto
        } else {
            totalPagadoText.className = 'fw-bold fs-5 text-dark'; // Oscuro si falta
        }
      }
    }

    // Función para crear una nueva fila dinámica de pago (Estilo Flex Compacto)
    function agregarFilaPago() {
      const cuentas = window.TESORERIA_CUENTAS || [];
      
      let opcionesCuentas = '<option value="" selected disabled>Seleccionar Cuenta...</option>';
      cuentas.forEach(c => { opcionesCuentas += `<option value="${c.id}">${c.nombre} (${c.moneda})</option>`; });
      
      // Autocompletar el monto restante por defecto
      let totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
      let totalActual = 0;
      contenedorPagos.querySelectorAll('.input-monto-pago').forEach(inp => totalActual += (parseFloat(inp.value) || 0));
      let montoSugerido = Math.max(0, totalGasto - totalActual).toFixed(2);

      const numFilas = contenedorPagos.querySelectorAll('.fila-pago-gasto').length;

      const div = document.createElement('div');
      // Aplicamos exactamente las mismas clases de Ventas (bg-white, bordes verdes, flex)
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
                <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle">S/</span>
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
      
      // Evento: Al cambiar la cuenta, filtra los métodos
      selCuenta.addEventListener('change', () => {
          filtrarMetodosPorCuentaGastos(selCuenta, selMetodo);
          selMetodo.disabled = !selCuenta.value;
          
          // Si el método se autoseleccionó (porque solo había uno), liberamos el monto
          if (selMetodo.value) {
              inputMonto.readOnly = false;
          } else {
              inputMonto.readOnly = true;
          }
          
          calcularTotalPagado();
      });

      // Evento: Al elegir el método, libera el campo de monto
      selMetodo.addEventListener('change', () => {
          inputMonto.readOnly = !selMetodo.value;
          if (selMetodo.value && !inputMonto.value) {
              inputMonto.focus();
          }
          calcularTotalPagado();
      });
      
      // Lógica al eliminar una fila
      div.querySelector('.btn-quitar-pago').addEventListener('click', function() {
        div.remove();
        // Si solo queda 1 fila, ocultamos su basurero
        const filasRestantes = contenedorPagos.querySelectorAll('.fila-pago-gasto');
        if (filasRestantes.length === 1) {
            filasRestantes[0].querySelector('.btn-quitar-pago').classList.add('d-none');
        }
        calcularTotalPagado();
      });
      
      inputMonto.addEventListener('input', calcularTotalPagado);
      
      calcularTotalPagado();
    }

    // Eventos para interactuar con la interfaz de pagos
    if (switchPago && seccionPago) {
      switchPago.addEventListener('change', function() {
        // Bloqueo de seguridad: Si el total es 0, no permite activar el pago inmediato
        const totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
        
        if (this.checked && totalGasto <= 0) {
            this.checked = false;
            
            // 👇 NUEVO: Le explicamos al usuario por qué no se enciende
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'Ingrese el Monto',
                    text: 'Debe ingresar el Monto Total del gasto antes de habilitar el pago al contado.',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
            // Hacemos que el cursor salte directo al campo para que escriba
            if (inputMontoTotalGasto) inputMontoTotalGasto.focus();
            
            return;
        }

        if (this.checked) {
          seccionPago.classList.remove('d-none');
          // Agregar la primera fila si el contenedor está vacío
          if (contenedorPagos.children.length === 0) {
            agregarFilaPago();
          } else {
            calcularTotalPagado();
          }
        } else {
          seccionPago.classList.add('d-none');
          contenedorPagos.innerHTML = '';
          calcularTotalPagado();
        }
      });
    }

    if (btnAgregarPago) {
      btnAgregarPago.addEventListener('click', () => {
          agregarFilaPago();
          // Asegurarnos de que todas las filas muestren el basurero cuando hay más de una
          contenedorPagos.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
      });
    }
    
    // Si el usuario cambia el total del gasto principal, actualizamos el texto del total pagado visualmente
    if (inputMontoTotalGasto) {
      inputMontoTotalGasto.addEventListener('input', () => {
          calcularTotalPagado();
          // Si el switch está activo y solo hay una fila, podemos auto-actualizar el monto
          if (switchPago && switchPago.checked) {
              const filasPago = contenedorPagos.querySelectorAll('.fila-pago-gasto');
              if (filasPago.length === 1) { 
                  filasPago[0].querySelector('.input-monto-pago').value = inputMontoTotalGasto.value;
              }
              calcularTotalPagado();
          }
      });
    }

    // ==========================================
    // 5. Limpieza automática del Modal al Cerrar
    // ==========================================
    const modalNuevoGastoEl = document.getElementById('modalNuevoGasto');
    
    if (modalNuevoGastoEl) {
      modalNuevoGastoEl.addEventListener('hidden.bs.modal', function () {
        // 1. Resetear inputs nativos (Fecha, Monto, etc.)
        const formulario = document.getElementById('formNuevoGasto');
        if (formulario) {
          formulario.reset();
        }

        // 2. Limpiar los selectores avanzados (TomSelect)
        TOM_SELECT_IDS.forEach(function(id) {
          const elemento = document.getElementById(id);
          if (elemento && elemento.tomselect) {
            elemento.tomselect.clear(true);
          }
        });

        // 3. Apagar el switch de pago al contado
        if (switchPago) {
          switchPago.checked = false;
        }

        // 4. Ocultar la sección verde de Pago Rápido
        if (seccionPago) {
          seccionPago.classList.add('d-none');
        }

        // 5. Vaciar todas las filas dinámicas de cuentas/métodos acumuladas
        if (contenedorPagos) {
          contenedorPagos.innerHTML = '';
        }

        // 6. Resetear el marcador del total pagado a cero
        if (totalPagadoText) {
          totalPagadoText.textContent = 'S/ 0.00';
          totalPagadoText.className = 'fw-bold text-dark fs-5';
        }
      });
    }

    // ==========================================
    // 6. Validación al Guardar el Gasto
    // ==========================================
    const formNuevoGasto = document.getElementById('formNuevoGasto');
    
    if (formNuevoGasto) {
      formNuevoGasto.addEventListener('submit', function(e) {
        const switchPagoCheck = document.getElementById('switchPagoInmediato');
        
        // Solo validamos montos si el switch de pago inmediato está activado
        if (switchPagoCheck && switchPagoCheck.checked) {
          const contenedorMetodos = document.getElementById('contenedorMetodosPagoGasto');
          const inputMontoGasto = document.getElementById('gastoMontoTotal');
            
          let totalPagado = 0;
          let faltanDatos = false;

          if (contenedorMetodos) {
              contenedorMetodos.querySelectorAll('.fila-pago-gasto').forEach(fila => {
                const cuenta = fila.querySelector('.select-cuenta-pago').value;
                const metodo = fila.querySelector('.select-metodo-pago').value;
                const monto = parseFloat(fila.querySelector('.input-monto-pago').value) || 0;
                
                if (!cuenta || !metodo || monto <= 0) {
                    faltanDatos = true;
                }
                totalPagado += monto;
              });
          }
          
          if (faltanDatos) {
            e.preventDefault();
            Swal.fire('Faltan Datos', 'Debe seleccionar la Cuenta y el Método en todas las filas de pago.', 'warning');
            return;
          }

          const totalGasto = parseFloat(inputMontoGasto ? inputMontoGasto.value : 0) || 0;
          
          // Validación 1: Evitar que pague 0
          if (totalPagado === 0) {
            e.preventDefault(); 
            Swal.fire('Atención', 'Has activado el pago inmediato pero no has ingresado un monto válido.', 'warning');
            return;
          }

          // Validación 2: Evitar que pague más de lo que cuesta
          if (totalPagado > totalGasto + 0.01) {
            e.preventDefault();
            Swal.fire('Error', `El monto ingresado (S/ ${totalPagado.toFixed(2)}) supera el total del gasto (S/ ${totalGasto.toFixed(2)}).`, 'error');
            return;
          }

          // Validación 3: Pago parcial (Avisamos al usuario y si confirma enviamos)
          if (totalPagado < totalGasto - 0.01) {
            e.preventDefault();
            Swal.fire({
              icon: 'warning',
              title: 'Pago Incompleto',
              text: `El gasto es de S/ ${totalGasto.toFixed(2)}, pero solo se registrarán S/ ${totalPagado.toFixed(2)}. La diferencia quedará como deuda. ¿Deseas guardar así?`,
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
