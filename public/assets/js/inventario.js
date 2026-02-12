(function () {
  'use strict';

  const form = document.getElementById('formMovimientoInventario');
  const modalEl = document.getElementById('modalMovimientoInventario');
  const tipo = document.getElementById('tipoMovimiento');
  const almacen = document.getElementById('almacenMovimiento');
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');
  const itemInput = document.getElementById('itemMovimiento');
  const itemIdInput = document.getElementById('idItemMovimiento');
  const itemList = document.getElementById('listaItemsInventario');
  const sugerenciasItems = document.getElementById('sugerenciasItemsInventario');
  const grupoLote = document.getElementById('grupoLoteMovimiento');
  const inputLote = document.getElementById('loteMovimiento');
  const grupoVencimiento = document.getElementById('grupoVencimientoMovimiento');
  const inputVencimiento = document.getElementById('vencimientoMovimiento');
  const cantidadInput = document.getElementById('cantidadMovimiento');
  const stockHint = document.getElementById('stockDisponibleHint');

  const searchInput = document.getElementById('inventarioSearch');
  const filtroEstado = document.getElementById('inventarioFiltroEstado');
  const filtroCriticidad = document.getElementById('inventarioFiltroCriticidad');
  const filtroAlmacen = document.getElementById('inventarioFiltroAlmacen');
  const filtroVencimiento = document.getElementById('inventarioFiltroVencimiento');
  const tablaStock = document.getElementById('tablaInventarioStock');

  function normalizarTexto(valor) {
    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function filtrarStock() {
    if (!tablaStock) return;

    const termino = normalizarTexto(searchInput ? searchInput.value : '');
    const estado = (filtroEstado ? filtroEstado.value : '').trim();
    const criticidad = (filtroCriticidad ? filtroCriticidad.value : '').trim();
    const almacenSel = (filtroAlmacen ? filtroAlmacen.value : '').trim();
    const vencimiento = (filtroVencimiento ? filtroVencimiento.value : '').trim();

    const filas = Array.from(tablaStock.querySelectorAll('tbody tr'));
    filas.forEach((fila) => {
      if (!fila.dataset.search) return;
      const okTexto = termino === '' || normalizarTexto(fila.dataset.search).includes(termino);
      const okEstado = estado === '' || (fila.dataset.estado || '') === estado;
      const okCrit = criticidad === '' || (fila.dataset.criticidad || '') === criticidad;
      const okAlm = almacenSel === '' || (fila.dataset.almacen || '') === almacenSel;
      const okVenc = vencimiento === '' || (fila.dataset.vencimiento || '') === vencimiento;
      fila.classList.toggle('d-none', !(okTexto && okEstado && okCrit && okAlm && okVenc));
    });
  }

  function obtenerItemSeleccionado() {
    if (!itemInput || !itemList) return null;
    const valor = (itemInput.value || '').trim();
    return Array.from(itemList.options || []).find((option) => option.value.trim() === valor) || null;
  }

  function resolverIdItem() {
    if (!itemInput || !itemIdInput || !itemList) return true;
    const match = obtenerItemSeleccionado();
    if (!match) {
      itemIdInput.value = '';
      return false;
    }
    itemIdInput.value = match.dataset.id || '';
    return itemIdInput.value !== '';
  }

  function toggleCamposLoteVencimiento() {
    if (!grupoLote || !inputLote || !grupoVencimiento || !inputVencimiento || !tipo) return;

    const item = obtenerItemSeleccionado();
    const requiereLote = item ? Number(item.dataset.requiereLote || '0') === 1 : false;
    const requiereVencimiento = item ? Number(item.dataset.requiereVencimiento || '0') === 1 : false;
    const movimientoEntrada = ['INI', 'AJ+'].includes(tipo.value || '');

    grupoLote.classList.toggle('d-none', !requiereLote);
    inputLote.required = requiereLote;
    if (!requiereLote) inputLote.value = '';

    const mostrarVencimiento = requiereVencimiento && movimientoEntrada;
    grupoVencimiento.classList.toggle('d-none', !mostrarVencimiento);
    inputVencimiento.required = mostrarVencimiento;
    if (!mostrarVencimiento) inputVencimiento.value = '';
  }

  function toggleTransferencia() {
    if (!tipo || !grupoDestino || !almacenDestino) return;
    const esTransferencia = tipo.value === 'TRF';
    grupoDestino.classList.toggle('d-none', !esTransferencia);
    almacenDestino.required = esTransferencia;
    if (!esTransferencia) almacenDestino.value = '';
  }

  function esSalida() {
    return ['AJ-', 'CON', 'TRF'].includes((tipo && tipo.value) || '');
  }

  async function actualizarStockHint() {
    if (!stockHint || !itemIdInput || !almacen) return;
    if (!esSalida()) {
      stockHint.textContent = '';
      return;
    }

    const idItem = Number(itemIdInput.value || '0');
    const idAlmacen = Number(almacen.value || '0');
    if (idItem <= 0 || idAlmacen <= 0) {
      stockHint.textContent = 'Selecciona ítem y almacén para ver stock disponible.';
      return;
    }

    try {
      const url = `${window.BASE_URL}?ruta=inventario/stockItem&id_item=${idItem}&id_almacen=${idAlmacen}`;
      const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json();
      const stock = Number((data && data.stock) || 0);
      stockHint.textContent = `Stock disponible en almacén: ${stock.toFixed(4)}`;
      stockHint.classList.toggle('text-danger', stock <= 0);
    } catch (error) {
      stockHint.textContent = 'No se pudo obtener stock en tiempo real.';
    }
  }

  let timerBusqueda = null;
  async function buscarItemsRemoto() {
    if (!itemInput || !sugerenciasItems) return;
    const q = (itemInput.value || '').trim();
    if (q.length < 2) {
      sugerenciasItems.classList.add('d-none');
      sugerenciasItems.innerHTML = '';
      return;
    }

    try {
      const response = await fetch(`${window.BASE_URL}?ruta=inventario/buscarItems&q=${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await response.json();
      const items = Array.isArray(data.items) ? data.items : [];
      sugerenciasItems.innerHTML = '';

      items.slice(0, 8).forEach((item) => {
        const texto = `${item.sku || ''} - ${item.nombre || ''}`.trim();
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action';
        btn.textContent = texto;
        btn.addEventListener('click', function () {
          itemInput.value = texto;
          itemIdInput.value = String(item.id || '');

          let option = Array.from(itemList.options || []).find((o) => o.value === texto);
          if (!option) {
            option = document.createElement('option');
            option.value = texto;
            itemList.appendChild(option);
          }
          option.dataset.id = String(item.id || '');
          option.dataset.requiereLote = String(item.requiere_lote || 0);
          option.dataset.requiereVencimiento = String(item.requiere_vencimiento || 0);

          sugerenciasItems.classList.add('d-none');
          toggleCamposLoteVencimiento();
          actualizarStockHint();
        });
        sugerenciasItems.appendChild(btn);
      });

      sugerenciasItems.classList.toggle('d-none', items.length === 0);
    } catch (error) {
      sugerenciasItems.classList.add('d-none');
    }
  }

  function limpiarFormularioMovimiento() {
    if (!form) return;
    form.reset();
    if (itemIdInput) itemIdInput.value = '';
    if (stockHint) stockHint.textContent = '';
    if (sugerenciasItems) {
      sugerenciasItems.classList.add('d-none');
      sugerenciasItems.innerHTML = '';
    }
    toggleTransferencia();
    toggleCamposLoteVencimiento();
  }

  if (searchInput) searchInput.addEventListener('input', filtrarStock);
  if (filtroEstado) filtroEstado.addEventListener('change', filtrarStock);
  if (filtroCriticidad) filtroCriticidad.addEventListener('change', filtrarStock);
  if (filtroAlmacen) filtroAlmacen.addEventListener('change', filtrarStock);
  if (filtroVencimiento) filtroVencimiento.addEventListener('change', filtrarStock);
  filtrarStock();

  if (!form || !tipo) return;

  if (itemInput) {
    itemInput.addEventListener('input', function () {
      resolverIdItem();
      toggleCamposLoteVencimiento();
      actualizarStockHint();
      clearTimeout(timerBusqueda);
      timerBusqueda = setTimeout(buscarItemsRemoto, 250);
    });
    itemInput.addEventListener('change', function () {
      resolverIdItem();
      toggleCamposLoteVencimiento();
      actualizarStockHint();
    });
  }

  if (almacen) almacen.addEventListener('change', actualizarStockHint);
  if (cantidadInput) cantidadInput.addEventListener('input', actualizarStockHint);

  tipo.addEventListener('change', function () {
    toggleTransferencia();
    toggleCamposLoteVencimiento();
    actualizarStockHint();
  });

  document.querySelectorAll('.btn-movimiento-rapido').forEach((btn) => {
    btn.addEventListener('click', function () {
      if (itemInput) itemInput.value = btn.dataset.itemTexto || '';
      if (itemIdInput) itemIdInput.value = btn.dataset.itemId || '';
      if (almacen) almacen.value = btn.dataset.almacenId || '';
      toggleCamposLoteVencimiento();
      actualizarStockHint();
    });
  });

  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', limpiarFormularioMovimiento);
    modalEl.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      if (!trigger || !trigger.classList.contains('btn-movimiento-rapido')) {
        limpiarFormularioMovimiento();
      }
    });
  }

  toggleTransferencia();
  toggleCamposLoteVencimiento();

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    if (!resolverIdItem()) {
      Swal.fire({ icon: 'warning', title: 'Ítem inválido', text: 'Selecciona un ítem válido desde la lista o sugerencias.' });
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
    if (!confirmacion.isConfirmed) return;

    const data = new FormData(form);

    try {
      const response = await fetch(`${window.BASE_URL}?ruta=inventario/guardarMovimiento`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: data
      });

      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      let result = null;
      if (contentType.includes('application/json')) {
        result = await response.json();
      } else {
        const raw = await response.text();
        throw new Error(`Respuesta no JSON del servidor (${response.status}).` + (raw ? ' Revisa sesión/permisos o errores PHP.' : ''));
      }

      if (!response.ok || !result || !result.ok) {
        throw new Error((result && result.mensaje) || 'No se pudo registrar el movimiento.');
      }

      await Swal.fire({ icon: 'success', title: 'Movimiento guardado', text: result.mensaje || 'Registro completado correctamente.' });
      limpiarFormularioMovimiento();
      window.location.reload();
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Ocurrió un error inesperado.' });
    }
  });
})();
