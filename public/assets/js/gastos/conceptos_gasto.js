(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const sw = document.getElementById('esRecurrente');
    const bloque = document.getElementById('bloqueRecurrente');
    const editarSw = document.getElementById('editarEsRecurrente');
    const editarBloque = document.getElementById('editarBloqueRecurrente');

    if (sw && bloque) {
      const sync = () => bloque.classList.toggle('d-none', !sw.checked);
      sw.addEventListener('change', sync);
      sync();
    }

    if (editarSw && editarBloque) {
      const syncEdit = () => editarBloque.classList.toggle('d-none', !editarSw.checked);
      editarSw.addEventListener('change', syncEdit);
      syncEdit();
    }

    const tomSelects = {};
    if (window.TomSelect) {
      ['id_centro_costo', 'editar_id_centro_costo'].forEach(function(id) {
        const elemento = document.getElementById(id);
        if (!elemento) {
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
    const modalEditar = (window.bootstrap && modalEditarEl) ? new bootstrap.Modal(modalEditarEl) : null;

    const campoId = document.getElementById('editarConceptoId');
    const campoCodigo = document.getElementById('editarConceptoCodigo');
    const campoNombre = document.getElementById('editarConceptoNombre');
    const campoCentro = document.getElementById('editar_id_centro_costo');
    const campoDiaVenc = document.getElementById('editarDiaVencimiento');
    const campoDiasAnt = document.getElementById('editarDiasAnticipacion');

    document.querySelectorAll('.js-editar-concepto').forEach(function(btn){
      btn.addEventListener('click', function(){
        if (btn.disabled || !modalEditar) {
          return;
        }

        const esRecurrente = String(btn.dataset.esRecurrente || '0') === '1';
        campoId.value = btn.dataset.id || '';
        campoCodigo.value = btn.dataset.codigo || '';
        campoNombre.value = btn.dataset.nombre || '';
        editarSw.checked = esRecurrente;
        campoDiaVenc.value = btn.dataset.diaVencimiento || '';
        campoDiasAnt.value = btn.dataset.diasAnticipacion || '0';

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
  });
})();
