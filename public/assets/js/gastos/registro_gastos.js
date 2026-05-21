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
      const metodos = window.TESORERIA_METODOS || [];
      
      let opcionesCuentas = '<option value="" selected disabled>Seleccionar Cuenta...</option>';
      cuentas.forEach(c => { opcionesCuentas += `<option value="${c.id}">${c.nombre}</option>`; });
      
      let opcionesMetodos = '<option value="" selected disabled>Método...</option>';
      metodos.forEach(m => { opcionesMetodos += `<option value="${m.id}">${m.nombre}</option>`; });
      
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
            <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary" name="pago_cuenta[]" required>
                ${opcionesCuentas}
            </select>
        </div>
        <div class="w-100">
            <select class="form-select form-select-sm border-secondary-subtle fw-semibold text-secondary" name="pago_metodo[]" required>
                ${opcionesMetodos}
            </select>
        </div>
        <div class="w-100 d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm w-100">
                <span class="input-group-text bg-light text-muted fw-semibold border-secondary-subtle">S/</span>
                <input type="number" step="0.01" min="0.01" class="form-control text-end text-success fw-bold shadow-none border-secondary-subtle input-monto-pago" name="pago_monto[]" value="${montoSugerido}" placeholder="0.00" required>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-quitar-pago ${numFilas === 0 ? 'd-none' : ''} px-2" title="Quitar pago">
                <i class="bi bi-trash"></i>
            </button>
        </div>
      `;
      
      contenedorPagos.appendChild(div);
      
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
      
      div.querySelector('.input-monto-pago').addEventListener('input', calcularTotalPagado);
      
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
      btnAgregarPago.addEventListener('click', () => {
          agregarFilaPago();
          // Asegurarnos de que todas las filas muestren el basurero cuando hay más de una
          contenedorPagos.querySelectorAll('.btn-quitar-pago').forEach(btn => btn.classList.remove('d-none'));
      });
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
          if (elemento && elemento.tomselect) {
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
          if (contenedorMetodos) {
              contenedorMetodos.querySelectorAll('.input-monto-pago').forEach(inp => {
                totalPagado += parseFloat(inp.value) || 0;
              });
          }
          
          const totalGasto = parseFloat(inputMontoGasto ? inputMontoGasto.value : 0) || 0;
          
          // Validación 1: Evitar que pague 0
          if (totalPagado === 0) {
            e.preventDefault(); 
            Swal.fire('Atención', 'Has activado el pago inmediato pero no has ingresado un monto válido.', 'warning');
            return;
          }

          // Validación 2: Evitar que pague más de lo que cuesta
          if (totalPagado > totalGasto) {
            e.preventDefault();
            Swal.fire('Error', `El monto ingresado (S/ ${totalPagado.toFixed(2)}) supera el total del gasto (S/ ${totalGasto.toFixed(2)}).`, 'error');
            return;
          }

          // Validación 3: Pago parcial (Avisamos al usuario y si confirma enviamos)
          if (totalPagado < totalGasto) {
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
                // Si confirma, enviamos manualmente el formulario
                formNuevoGasto.submit(); 
              }
            });
            return;
          }
        }
      });
    }
    
})();
