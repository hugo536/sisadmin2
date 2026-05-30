(function(){
  'use strict';

  const bindOnce = function(element, eventName, handler) {
    if (!element) return;
    const key = `conceptosBound${eventName}`;
    if (element.dataset[key] === '1') return;
    element.addEventListener(eventName, handler);
    element.dataset[key] = '1';
  };

  const initConceptosGasto = function(){
    const app = document.getElementById('gastosConceptosApp');
    if (!app || app.dataset.conceptosInitialized === '1') {
      return;
    }
    app.dataset.conceptosInitialized = '1';

    const sw = document.getElementById('esRecurrente');
    const bloque = document.getElementById('bloqueRecurrente');
    const editarSw = document.getElementById('editarEsRecurrente');
    const editarBloque = document.getElementById('editarBloqueRecurrente');

    if (sw && bloque) {
      const sync = () => bloque.classList.toggle('d-none', !sw.checked);
      bindOnce(sw, 'change', sync);
      sync();
    }

    if (editarSw && editarBloque) {
      const syncEdit = () => editarBloque.classList.toggle('d-none', !editarSw.checked);
      bindOnce(editarSw, 'change', syncEdit);
      syncEdit();
    }

    const tomSelects = {};
    if (window.TomSelect) {
      ['id_centro_costo', 'editar_id_centro_costo'].forEach(function(id) {
        const elemento = document.getElementById(id);
        if (!elemento) {
          return;
        }

        if (elemento.tomselect) {
          tomSelects[id] = elemento.tomselect;
          return;
        }

        tomSelects[id] = new TomSelect(elemento, {
          create: false,
          sortField: { field: 'text', direction: 'asc' },
          placeholder: elemento.getAttribute('placeholder') || 'Seleccionar...'
        });
      });
    }

    const modalEditarEl = document.getElementById('modalEditarConcepto');
    const modalEditar = (window.bootstrap && modalEditarEl) ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    const campoId = document.getElementById('editarConceptoId');
    const campoCodigo = document.getElementById('editarConceptoCodigo');
    const campoNombre = document.getElementById('editarConceptoNombre');
    const campoCentro = document.getElementById('editar_id_centro_costo');
    const campoDiaVenc = document.getElementById('editarDiaVencimiento');
    const campoDiasAnt = document.getElementById('editarDiasAnticipacion');

    document.querySelectorAll('.js-editar-concepto').forEach(function(btn){
      bindOnce(btn, 'click', function(){
        if (btn.disabled || !modalEditar) {
          return;
        }

        const esRecurrente = String(btn.dataset.esRecurrente || '0') === '1';
        if (campoId) campoId.value = btn.dataset.id || '';
        if (campoCodigo) campoCodigo.value = btn.dataset.codigo || '';
        if (campoNombre) campoNombre.value = btn.dataset.nombre || '';
        if (editarSw) editarSw.checked = esRecurrente;
        if (campoDiaVenc) campoDiaVenc.value = btn.dataset.diaVencimiento || '';
        if (campoDiasAnt) campoDiasAnt.value = btn.dataset.diasAnticipacion || '0';

        if (tomSelects.editar_id_centro_costo) {
          tomSelects.editar_id_centro_costo.setValue(btn.dataset.idCentro || '', true);
        } else if (campoCentro) {
          campoCentro.value = btn.dataset.idCentro || '';
        }

        if (editarBloque) {
          editarBloque.classList.toggle('d-none', !esRecurrente);
        }

        modalEditar.show();
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConceptosGasto);
  } else {
    initConceptosGasto();
  }

  document.addEventListener('sisadmin:route-loaded', initConceptosGasto);
})();
