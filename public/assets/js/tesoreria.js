(function () {
  const openModal = (id) => {
    const el = document.getElementById(id);
    if (!el) return null;
    return bootstrap.Modal.getOrCreateInstance(el);
  };

  document.querySelectorAll('.js-open-cobro').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('cobroIdOrigen').value = btn.dataset.idOrigen || '';
      document.getElementById('cobroMoneda').value = btn.dataset.moneda || 'PEN';
      document.getElementById('cobroSaldo').value = btn.dataset.saldo || '0';
      document.getElementById('cobroMonto').setAttribute('max', btn.dataset.saldo || '0');
      openModal('modalCobro')?.show();
    });
  });

  document.querySelectorAll('.js-open-pago').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('pagoIdOrigen').value = btn.dataset.idOrigen || '';
      document.getElementById('pagoMoneda').value = btn.dataset.moneda || 'PEN';
      document.getElementById('pagoSaldo').value = btn.dataset.saldo || '0';
      document.getElementById('pagoMonto').setAttribute('max', btn.dataset.saldo || '0');
      openModal('modalPago')?.show();
    });
  });

  const validateMontoVsSaldo = (form) => {
    if (!form.classList.contains('js-form-monto')) return true;
    const montoEl = form.querySelector('input[name="monto"]');
    const saldoEl = form.querySelector('[data-saldo-target]');
    if (!montoEl || !saldoEl) return true;
    const monto = Number(montoEl.value || 0);
    const saldo = Number(saldoEl.value || 0);
    if (monto <= 0 || monto > saldo) {
      const msg = 'El monto debe ser mayor a 0 y menor o igual al saldo.';
      if (window.Swal) {
        window.Swal.fire({ icon: 'error', title: 'Validación', text: msg });
      } else {
        alert(msg);
      }
      return false;
    }
    return true;
  };

  document.querySelectorAll('.js-form-confirm').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!validateMontoVsSaldo(form)) return;
      const go = () => form.submit();

      if (window.Swal) {
        window.Swal.fire({
          title: '¿Confirmar operación?',
          text: 'Esta acción quedará registrada en auditoría.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, confirmar',
          cancelButtonText: 'Cancelar'
        }).then((r) => {
          if (r.isConfirmed) go();
        });
      } else if (window.confirm('¿Confirmar operación de tesorería?')) {
        go();
      }
    });
  });
})();
