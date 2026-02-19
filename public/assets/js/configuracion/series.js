(() => {
  const modalEl = document.getElementById('modalSerie');
  if (!modalEl) return;

  const form = document.getElementById('formSerie');
  const setCreate = () => {
    form.reset();
    document.getElementById('ser_id').value = '0';
    document.getElementById('ser_modulo').value = 'VENTAS';
    document.getElementById('ser_estado').value = '1';
    document.getElementById('ser_longitud_correlativo').value = '6';
  };

  modalEl.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;
    if (!trigger || !trigger.classList.contains('js-edit-serie')) {
      setCreate();
      return;
    }

    document.getElementById('ser_id').value = trigger.dataset.id || '0';
    document.getElementById('ser_modulo').value = trigger.dataset.modulo || 'VENTAS';
    document.getElementById('ser_tipo_documento').value = trigger.dataset.tipoDocumento || '';
    document.getElementById('ser_codigo_serie').value = trigger.dataset.codigoSerie || '';
    document.getElementById('ser_prefijo').value = trigger.dataset.prefijo || '';
    document.getElementById('ser_correlativo_actual').value = trigger.dataset.correlativoActual || '0';
    document.getElementById('ser_longitud_correlativo').value = trigger.dataset.longitudCorrelativo || '6';
    document.getElementById('ser_predeterminada').checked = (trigger.dataset.predeterminada || '0') === '1';
    document.getElementById('ser_estado').value = trigger.dataset.estado || '1';
    document.getElementById('ser_observaciones').value = trigger.dataset.observaciones || '';
  });
})();
