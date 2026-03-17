(function(){
  document.addEventListener('DOMContentLoaded', function(){
    
    // ==========================================
    // 1. Inicialización de selectores avanzados (TomSelect)
    // ==========================================
    if (window.TomSelect) {
      // Agregamos los IDs de todos los selects que queremos mejorar
      const selectoresAvanzados = ['idConceptoGasto', 'id_proveedor', 'idCentroCostoGasto'];

      selectoresAvanzados.forEach(function(id) {
        const elemento = document.getElementById(id);
        
        // Si el elemento existe en esta vista, lo inicializamos
        if (elemento) {
          new TomSelect(elemento, {
            create: false, 
            sortField: { field: 'text', direction: 'asc' },
            placeholder: elemento.getAttribute('placeholder') || 'Seleccione una opción...'
          });
        }
      });
    }


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
    
  });
})();
