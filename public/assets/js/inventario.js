(function () {
  'use strict';

  const form = document.getElementById('formMovimientoInventario');
  const tipo = document.getElementById('tipoMovimiento');
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');

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

  tipo.addEventListener('change', toggleTransferencia);
  toggleTransferencia();

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

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
