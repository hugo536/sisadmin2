(function(){
  document.addEventListener('DOMContentLoaded', function(){
    
    // ==========================================
    // 1. Inicialización de selectores avanzados (TomSelect)
    // ==========================================
    if (window.TomSelect) {
      const selectoresAvanzados = ['idConceptoGasto', 'id_proveedor', 'idCentroCostoGasto'];

      selectoresAvanzados.forEach(function(id) {
        const elemento = document.getElementById(id);
        if (elemento) {
          new TomSelect(elemento, {
            create: false, 
            sortField: { field: 'text', direction: 'asc' },
            placeholder: elemento.getAttribute('placeholder') || 'Seleccione una opción...'
          });
        }
      });
    }

    // ==========================================
    // 2. Autocompletado de Centro de Costo
    // ==========================================
    const selectConcepto = document.getElementById('idConceptoGasto');
    const selectCentroCosto = document.getElementById('idCentroCostoGasto');

    if (selectConcepto && selectCentroCosto) {
      const autocompletarCentro = function() {
        const opt = selectConcepto.options[selectConcepto.selectedIndex];
        if (!opt) return;
        const idCentro = Number(opt.dataset.centroCosto || 0);
        if (idCentro > 0) {
          selectCentroCosto.value = String(idCentro);
          if (selectCentroCosto.tomselect) {
            selectCentroCosto.tomselect.setValue(String(idCentro), true);
          }
        }
      };
      selectConcepto.addEventListener('change', autocompletarCentro);
      autocompletarCentro();
    }

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

    document.addEventListener('click', function(ev) {
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

      modalDetalle.show();
    });

    // ==========================================
    // 4. Lógica de Cobro Inmediato (Nuevo)
    // ==========================================
    const switchPago = document.getElementById('switchPagoInmediato');
    const seccionPago = document.getElementById('seccionPagoInmediato');
    const contenedorPagos = document.getElementById('contenedorMetodosPagoGasto');
    const btnAgregarPago = document.getElementById('btnAgregarPagoInmediatoGasto');
    const totalPagadoText = document.getElementById('totalPagadoInmediatoGasto');
    const inputMontoTotalGasto = document.getElementById('gastoMontoTotal');

    // Función para calcular el total ingresado en las filas de pago
    function calcularTotalPagado() {
      let total = 0;
      const inputsMonto = contenedorPagos.querySelectorAll('.input-monto-pago');
      
      inputsMonto.forEach(input => {
        total += parseFloat(input.value) || 0;
      });
      
      if (totalPagadoText) {
        totalPagadoText.textContent = 'S/ ' + total.toFixed(2);
        
        // Cambio de color visual si el monto coincide con el total del gasto
        const totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
        if (total === totalGasto && total > 0) {
          totalPagadoText.classList.replace('text-dark', 'text-success');
        } else {
          totalPagadoText.classList.replace('text-success', 'text-dark');
        }
      }
    }

    // Función para crear una nueva fila dinámica de pago
    function agregarFilaPago() {
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-center mb-2 animate__animated animate__fadeIn'; 
      
      const cuentas = window.TESORERIA_CUENTAS || [];
      const metodos = window.TESORERIA_METODOS || [];
      
      // Construimos el HTML de las opciones con un placeholder por defecto (Igual que en Ventas)
      let opcionesCuentas = '<option value="" selected disabled>Seleccionar Cuenta...</option>';
      cuentas.forEach(c => { opcionesCuentas += `<option value="${c.id}">${c.nombre}</option>`; });
      
      let opcionesMetodos = '<option value="" selected disabled>Método...</option>';
      metodos.forEach(m => { opcionesMetodos += `<option value="${m.id}">${m.nombre}</option>`; });
      
      // Autocompletar el monto restante por defecto
      let totalGasto = parseFloat(inputMontoTotalGasto.value) || 0;
      let totalActual = 0;
      contenedorPagos.querySelectorAll('.input-monto-pago').forEach(inp => totalActual += (parseFloat(inp.value) || 0));
      let montoSugerido = Math.max(0, totalGasto - totalActual).toFixed(2);

      row.innerHTML = `
        <div class="col-md-5">
          <select class="form-select form-select-sm shadow-none border-secondary-subtle" name="pago_cuenta[]" required>
            ${opcionesCuentas}
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select form-select-sm shadow-none border-secondary-subtle" name="pago_metodo[]" required>
            ${opcionesMetodos}
          </select>
        </div>
        <div class="col-md-3">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-secondary-subtle fw-semibold">S/</span>
            <input type="number" step="0.01" min="0.01" class="form-control text-end text-success fw-bold shadow-none border-secondary-subtle input-monto-pago" name="pago_monto[]" value="${montoSugerido}" required>
          </div>
        </div>
        <div class="col-md-1 text-center">
          <button type="button" class="btn btn-sm text-danger border-0 btn-quitar-pago rounded-circle p-1" title="Quitar">
            <i class="bi bi-trash-fill fs-6"></i>
          </button>
        </div>
      `;
      
      contenedorPagos.appendChild(row);
      
      row.querySelector('.btn-quitar-pago').addEventListener('click', function() {
        row.remove();
        calcularTotalPagado();
      });
      
      row.querySelector('.input-monto-pago').addEventListener('input', calcularTotalPagado);
      
      calcularTotalPagado();
    }

    // Eventos para interactuar con la interfaz de pagos
    if (switchPago && seccionPago) {
      switchPago.addEventListener('change', function() {
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
        }
      });
    }

    if (btnAgregarPago) {
      btnAgregarPago.addEventListener('click', agregarFilaPago);
    }
    
    // Si el usuario cambia el total del gasto principal, actualizamos el texto del total pagado visualmente
    if (inputMontoTotalGasto) {
      inputMontoTotalGasto.addEventListener('input', calcularTotalPagado);
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
        const selectoresAvanzados = ['idConceptoGasto', 'id_proveedor', 'idCentroCostoGasto'];
        selectoresAvanzados.forEach(function(id) {
          const elemento = document.getElementById(id);
          if (element && elemento.tomselect) {
            elemento.tomselect.clear(true); // Remueve la selección visual de TomSelect sin disparar eventos extra
          }
        });

        // 3. Apagar el switch de pago al contado
        const switchPago = document.getElementById('switchPagoInmediato');
        if (switchPago) {
          switchPago.checked = false;
        }

        // 4. Ocultar la sección verde de Pago Rápido
        const seccionPago = document.getElementById('seccionPagoInmediato');
        if (seccionPago) {
          seccionPago.classList.add('d-none');
        }

        // 5. Vaciar todas las filas dinámicas de cuentas/métodos acumuladas
        const contenedorPagos = document.getElementById('contenedorMetodosPagoGasto');
        if (contenedorPagos) {
          contenedorPagos.innerHTML = '';
        }

        // 6. Resetear el marcador del total pagado a cero
        const totalPagadoText = document.getElementById('totalPagadoInmediatoGasto');
        if (totalPagadoText) {
          totalPagadoText.textContent = 'S/ 0.00';
          totalPagadoText.className = 'fw-bold text-dark fs-5'; // Regresa al color oscuro por defecto
        }
      });
    }
  });
})();