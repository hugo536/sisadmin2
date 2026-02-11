(function () {
  'use strict';

  const form = document.getElementById('formMovimientoInventario');
  const tipo = document.getElementById('tipoMovimiento');
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');
  const itemInput = document.getElementById('itemMovimiento');
  const itemIdInput = document.getElementById('idItemMovimiento');
  const itemList = document.getElementById('listaItemsInventario');
  const grupoLote = document.getElementById('grupoLoteMovimiento');
  const inputLote = document.getElementById('loteMovimiento');
  const grupoVencimiento = document.getElementById('grupoVencimientoMovimiento');
  const inputVencimiento = document.getElementById('vencimientoMovimiento');

  const searchInput = document.getElementById('inventarioSearch');
  const filtroEstado = document.getElementById('inventarioFiltroEstado');
  const tablaStock = document.getElementById('tablaInventarioStock');

  function normalizarTexto(valor) {
    return (valor || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '');
  }

  function filtrarStock() {
    if (!tablaStock) {
      return;
    }

    const termino = normalizarTexto(searchInput ? searchInput.value : '');
    const estado = (filtroEstado ? filtroEstado.value : '').trim();
    const filas = Array.from(tablaStock.querySelectorAll('tbody tr'));

    filas.forEach(function (fila) {
      if (!fila.dataset.search) {
        return;
      }

      const coincideTexto = termino === '' || normalizarTexto(fila.dataset.search).includes(termino);
      const coincideEstado = estado === '' || (fila.dataset.estado || '') === estado;

      fila.classList.toggle('d-none', !(coincideTexto && coincideEstado));
    });
  }

  function obtenerItemSeleccionado() {
    if (!itemInput || !itemList) {
      return null;
    }

    const valor = (itemInput.value || '').trim();
    const opciones = Array.from(itemList.options || []);
    return opciones.find((option) => option.value.trim() === valor) || null;
  }

  function resolverIdItem() {
    if (!itemInput || !itemIdInput || !itemList) {
      return true;
    }

    const match = obtenerItemSeleccionado();
    if (!match) {
      itemIdInput.value = '';
      return false;
    }

    itemIdInput.value = match.dataset.id || '';
    return itemIdInput.value !== '';
  }

  function toggleCamposLoteVencimiento() {
    if (!grupoLote || !inputLote || !grupoVencimiento || !inputVencimiento || !tipo) {
      return;
    }

    const item = obtenerItemSeleccionado();
    const requiereLote = item ? Number(item.dataset.requiereLote || '0') === 1 : false;
    const requiereVencimiento = item ? Number(item.dataset.requiereVencimiento || '0') === 1 : false;
    const movimientoEntrada = ['INI', 'AJ+'].includes(tipo.value || '');

    grupoLote.classList.toggle('d-none', !requiereLote);
    inputLote.required = requiereLote;
    if (!requiereLote) {
      inputLote.value = '';
    }

    const mostrarVencimiento = requiereVencimiento && movimientoEntrada;
    grupoVencimiento.classList.toggle('d-none', !mostrarVencimiento);
    inputVencimiento.required = mostrarVencimiento;
    if (!mostrarVencimiento) {
      inputVencimiento.value = '';
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', filtrarStock);
  }

  if (filtroEstado) {
    filtroEstado.addEventListener('change', filtrarStock);
  }

  filtrarStock();

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

  if (itemInput) {
    itemInput.addEventListener('input', function () {
      resolverIdItem();
      toggleCamposLoteVencimiento();
    });

    itemInput.addEventListener('change', function () {
      resolverIdItem();
      toggleCamposLoteVencimiento();
    });
  }

  tipo.addEventListener('change', function () {
    toggleTransferencia();
    toggleCamposLoteVencimiento();
  });

  toggleTransferencia();
  toggleCamposLoteVencimiento();

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
