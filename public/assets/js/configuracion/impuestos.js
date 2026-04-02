(() => {
  const modalEl = document.getElementById('modalImpuesto');
  if (!modalEl) return;

  const form = document.getElementById('formImpuesto');
  const setCreate = () => {
    form.reset();
    document.getElementById('imp_id').value = '0';
    document.getElementById('imp_estado').value = '1';
  };

  modalEl.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;
    if (!trigger || !trigger.classList.contains('js-edit-impuesto')) {
      setCreate();
      return;
    }

    document.getElementById('imp_id').value = trigger.dataset.id || '0';
    document.getElementById('imp_codigo').value = trigger.dataset.codigo || '';
    document.getElementById('imp_nombre').value = trigger.dataset.nombre || '';
    document.getElementById('imp_porcentaje').value = trigger.dataset.porcentaje || '0';
    document.getElementById('imp_tipo').value = trigger.dataset.tipo || 'VENTA';
    document.getElementById('imp_estado').value = trigger.dataset.estado || '1';
    document.getElementById('imp_default').checked = (trigger.dataset.esDefault || '0') === '1';
    document.getElementById('imp_observaciones').value = trigger.dataset.observaciones || '';
  });
})();
