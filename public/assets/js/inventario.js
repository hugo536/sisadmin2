(function () {
  'use strict';

  const form = document.getElementById('formMovimientoInventario');
  const tipo = document.getElementById('tipoMovimiento');
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');
  const itemInput = document.getElementById('itemMovimiento');
  const itemIdInput = document.getElementById('idItemMovimiento');
  const itemList = document.getElementById('listaItemsInventario');

  if (!form || !tipo) {
    return;
  }

  function toggleTransferencia() {
    const esTransferencia = tipo.value === 'TRF';
    grupoDestino.classList.toggle('d-none', !esTransferencia);
    almacenDestino.required = esTransferencia;

    if (!esTransferencia) {
      almacenDestino.value = '';
    }
  }

  function resolverIdItem() {
    if (!itemInput || !itemIdInput || !itemList) {
      return true;
    }

    const valor = (itemInput.value || '').trim();
    const opciones = Array.from(itemList.options || []);
    const match = opciones.find((option) => option.value.trim() === valor);

    if (!match) {
      itemIdInput.value = '';
      return false;
    }

    itemIdInput.value = match.dataset.id || '';
    return itemIdInput.value !== '';
  }

  if (itemInput) {
    itemInput.addEventListener('input', resolverIdItem);
    itemInput.addEventListener('change', resolverIdItem);
  }

  tipo.addEventListener('change', toggleTransferencia);
  toggleTransferencia();

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    if (!resolverIdItem()) {
      Swal.fire({
        icon: 'warning',
        title: 'Ítem inválido',
        text: 'Selecciona un ítem válido desde la lista (SKU / Nombre).',
        confirmButtonText: 'Entendido'
      });
      return;
    }

    const confirmacion = await Swal.fire({
      icon: 'question',
      title: '¿Registrar movimiento?',
      text: 'Se actualizará el stock del inventario.',
      showCancelButton: true,
      confirmButtonText: 'Sí, guardar',
      cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) {
      return;
    }

    const data = new FormData(form);

    try {
      const response = await fetch(window.BASE_URL + '?ruta=inventario/guardarMovimiento', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: data
      });

      const result = await response.json();

      if (!response.ok || !result.ok) {
        throw new Error(result.mensaje || 'No se pudo registrar el movimiento.');
      }

      await Swal.fire({
        icon: 'success',
        title: 'Movimiento guardado',
        text: result.mensaje || 'Registro completado correctamente.',
        confirmButtonText: 'Aceptar'
      });

      window.location.reload();
    } catch (error) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: error.message || 'Ocurrió un error inesperado.',
        confirmButtonText: 'Entendido'
      });
    }
  });
})();
