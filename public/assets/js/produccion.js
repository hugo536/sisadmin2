(function () {
  'use strict';

  const tablaRecetas = document.getElementById('tablaRecetas');
  const tablaOrdenes = document.getElementById('tablaOrdenes');

  if (window.ERPTable && tablaRecetas) {
    window.ERPTable.createTableManager({ tableSelector: '#tablaRecetas', rowsPerPage: 8 }).init();
  }
  if (window.ERPTable && tablaOrdenes) {
    window.ERPTable.createTableManager({ tableSelector: '#tablaOrdenes', rowsPerPage: 8 }).init();
  }

  const btnAgregarDetalle = document.getElementById('btnAgregarDetalleReceta');
  const detalleWrapper = document.getElementById('detalleRecetaWrapper');
  if (btnAgregarDetalle && detalleWrapper) {
    btnAgregarDetalle.addEventListener('click', () => {
      const first = detalleWrapper.querySelector('.detalle-row');
      if (!first) return;
      const clone = first.cloneNode(true);
      clone.querySelectorAll('input').forEach((input) => {
        input.value = input.name.includes('merma') ? '0' : '';
      });
      clone.querySelectorAll('select').forEach((select) => {
        select.selectedIndex = 0;
      });
      detalleWrapper.appendChild(clone);
    });
  }

  const formAccion = document.getElementById('formAccionOP');
  const accionField = document.getElementById('accionOP');
  const idField = document.getElementById('idOrdenOP');
  const cantField = document.getElementById('cantidadProducidaOP');
  const loteField = document.getElementById('loteIngresoOP');

  document.querySelectorAll('.js-ejecutar-op').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const codigo = btn.dataset.codigo || '';
      const planificada = Number(btn.dataset.planificada || '0');

      const response = await Swal.fire({
        title: `Ejecutar OP ${codigo}`,
        html: `
          <div class="text-start">
            <label class="form-label">Cantidad producida</label>
            <input id="swalCantidad" type="number" min="0.0001" step="0.0001" class="form-control" value="${planificada > 0 ? planificada : ''}">
            <label class="form-label mt-2">Lote de ingreso (opcional)</label>
            <input id="swalLote" type="text" class="form-control" placeholder="LOTE-OP">
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, ejecutar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#2563eb',
        preConfirm: () => {
          const cantidad = Number(document.getElementById('swalCantidad').value || '0');
          const lote = document.getElementById('swalLote').value || '';
          if (cantidad <= 0) {
            Swal.showValidationMessage('Ingresa una cantidad válida.');
            return false;
          }
          return { cantidad, lote };
        }
      });

      if (!response.isConfirmed || !response.value) return;

      accionField.value = 'ejecutar_orden';
      idField.value = String(id);
      cantField.value = String(response.value.cantidad);
      loteField.value = String(response.value.lote || '');
      formAccion.submit();
    });
  });

  document.querySelectorAll('.js-anular-op').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const codigo = btn.dataset.codigo || '';

      const ok = await Swal.fire({
        title: `¿Anular OP ${codigo}?`,
        text: 'La orden quedará en estado anulada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626'
      });

      if (!ok.isConfirmed) return;
      accionField.value = 'anular_orden';
      idField.value = String(id);
      cantField.value = '';
      loteField.value = '';
      formAccion.submit();
    });
  });
})();
