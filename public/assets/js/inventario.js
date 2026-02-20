(function () {
  'use strict';

  // --- REFERENCIAS AL DOM ---
  const form = document.getElementById('formMovimientoInventario');
  const modalEl = document.getElementById('modalMovimientoInventario');
  
  // Selects principales (Modal)
  const tipo = document.getElementById('tipoMovimiento');
  const almacen = document.getElementById('almacenMovimiento');
  const grupoProveedor = document.getElementById('grupoProveedorMovimiento');
  const proveedor = document.getElementById('proveedorMovimiento'); // NUEVO: Select de Proveedor
  const grupoMotivo = document.getElementById('grupoMotivoMovimiento');
  const motivo = document.getElementById('motivoMovimiento');
  
  // Destino (Solo TRF)
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');
  
  // Ítem (AHORA ES UN SELECT)
  const selectItem = document.getElementById('itemMovimiento');
  const itemIdInput = document.getElementById('idItemMovimiento');
  const packIdInput = document.getElementById('idPackMovimiento');
  const tipoRegistroInput = document.getElementById('tipoRegistroMovimiento');
  
  // Lotes
  const grupoLoteInput = document.getElementById('grupoLoteInput');
  const inputLoteNuevo = document.getElementById('loteMovimientoInput');
  const grupoLoteSelect = document.getElementById('grupoLoteSelect');
  const selectLoteExistente = document.getElementById('loteMovimientoSelect');
  const msgSinLotes = document.getElementById('msgSinLotes');
  const loteFinalEnviar = document.getElementById('loteFinalEnviar'); // Hidden
  
  // Vencimiento y Costo
  const grupoVencimiento = document.getElementById('grupoVencimientoMovimiento');
  const inputVencimiento = document.getElementById('vencimientoMovimiento');
  const cantidadInput = document.getElementById('cantidadMovimiento');
  const stockHint = document.getElementById('stockDisponibleHint');
  const costoUnitarioInput = document.getElementById('costoUnitarioMovimiento');
  const costoPromedioActualLabel = document.getElementById('costoPromedioActual');
  const stockActualItemLabel = document.getElementById('stockActualItemSeleccionado');

  // --- FILTROS DE LA TABLA PRINCIPAL ---
  const searchInput = document.getElementById('inventarioSearch');
  const filtroTipoRegistro = document.getElementById('inventarioFiltroTipoRegistro');
  const filtroEstado = document.getElementById('inventarioFiltroEstado');
  const filtroAlmacen = document.getElementById('inventarioFiltroAlmacen');
  const filtroVencimiento = document.getElementById('inventarioFiltroVencimiento');
  const tablaStock = document.getElementById('tablaInventarioStock');
  const paginationInfo = document.getElementById('inventarioPaginationInfo');
  const paginationControls = document.getElementById('inventarioPaginationControls');

  const ITEMS_PER_PAGE = 20;
  let currentStockPage = 1;

  // Instancias TomSelect
  let tomSelectTipo = null;
  let tomSelectAlmacen = null;
  let tomSelectProveedor = null;
  let tomSelectMotivo = null;
  let tomSelectAlmacenDestino = null;
  let tomSelectItem = null;      // Instancia Ítem
  const almacenesBase = {
    origen: [],
    destino: []
  };

  function toggleAlmacenOrigenSegunItem() {
    if (!almacen || !tomSelectAlmacen || !itemIdInput) return;
    const hayItemSeleccionado = Number(itemIdInput.value || '0') > 0 || Number((packIdInput && packIdInput.value) || '0') > 0;

    almacen.disabled = !hayItemSeleccionado;
    if (hayItemSeleccionado) {
      tomSelectAlmacen.enable();
    } else {
      tomSelectAlmacen.clear(true);
      tomSelectAlmacen.disable();
      if (stockHint) stockHint.textContent = '';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Configuración base para TomSelects estáticos
    const tsConfig = {
        create: false,
        sortField: { field: 'text', direction: 'asc' },
        placeholder: 'Buscar...',
        dropdownParent: 'body'
    };

    if (tipo) tomSelectTipo = new TomSelect('#tipoMovimiento', tsConfig);
    if (almacen) tomSelectAlmacen = new TomSelect('#almacenMovimiento', tsConfig);
    if (almacenDestino) tomSelectAlmacenDestino = new TomSelect('#almacenDestinoMovimiento', tsConfig);
    if (motivo) {
      tomSelectMotivo = new TomSelect('#motivoMovimiento', {
        ...tsConfig,
        placeholder: 'Seleccione motivo...'
      });
    }

    if (almacen) {
      almacenesBase.origen = Array.from(almacen.options).map((opt) => ({ value: opt.value, text: opt.textContent }));
    }
    if (almacenDestino) {
      almacenesBase.destino = Array.from(almacenDestino.options).map((opt) => ({ value: opt.value, text: opt.textContent }));
    }
    
    // Inicializar Tom Select Proveedor
    if (proveedor) {
        tomSelectProveedor = new TomSelect('#proveedorMovimiento', {
            ...tsConfig,
            placeholder: 'Buscar proveedor...'
        });
    }

    // Inicializar Tom Select para Buscar Ítem (Carga Remota)
    if (selectItem) {
        tomSelectItem = new TomSelect('#itemMovimiento', {
            valueField: 'value',
            labelField: 'nombre',
            searchField: ['sku', 'nombre'],
            placeholder: 'Escriba para buscar...',
            dropdownParent: 'body',
            load: function(query, callback) {
                if (!query.length) return callback();
                
                fetch(`${window.BASE_URL}?ruta=inventario/buscarItems&q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(async (response) => {
                    if (!response.ok) {
                        const body = await response.text();
                        throw new Error(`Error HTTP ${response.status} al buscar ítems: ${body.slice(0, 180)}`);
                    }

                    const contentType = (response.headers.get('content-type') || '').toLowerCase();
                    if (!contentType.includes('application/json')) {
                        const body = await response.text();
                        throw new Error(`Respuesta no JSON al buscar ítems: ${body.slice(0, 180)}`);
                    }

                    return response.json();
                })
                .then(data => {
                    if (!data || data.ok !== true) {
                        throw new Error('Respuesta inválida del endpoint inventario/buscarItems.');
                    }

                    const items = Array.isArray(data.items) ? data.items : [];
                    callback(items);
                })
                .catch((error) => {
                    // Evita silencios que terminan en "No results found" sin contexto.
                    console.error('[Inventario] Error al cargar ítems para el selector:', error);
                    callback();
                });
            },
            render: {
                option: function(item, escape) {
                    const nota = (item.nota || '').trim();
                    const etiquetaTipo = item.tipo_registro === 'pack'
                        ? '<span class="badge bg-primary-subtle text-primary ms-1">Pack</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary ms-1">Ítem</span>';
                    return `<div class="py-1">
                                <span class="fw-bold d-block">${escape(item.sku || '')}${etiquetaTipo}</span>
                                <span class="text-muted small d-block">${escape(item.nombre_full || item.nombre || '')}</span>
                                ${nota ? `<span class="text-primary-emphasis small fst-italic">${escape(nota)}</span>` : ''}
                            </div>`;
                },
                item: function(item, escape) {
                    return `<div>${escape(item.sku || '')} - ${escape(item.nombre_full || item.nombre || '')}</div>`;
                }
            },
            onChange: function(value) {
                if (value) {
                    const itemData = tomSelectItem.options[value] || {};
                    const tipoRegistro = itemData.tipo_registro === 'pack' ? 'pack' : 'item';
                    if (tipoRegistroInput) tipoRegistroInput.value = tipoRegistro;
                    if (tipoRegistro === 'pack') {
                        if (itemIdInput) itemIdInput.value = '0';
                        if (packIdInput) packIdInput.value = String(itemData.id || 0);
                    } else {
                        if (itemIdInput) itemIdInput.value = String(itemData.id || 0);
                        if (packIdInput) packIdInput.value = '0';
                    }
                } else {
                    if (itemIdInput) itemIdInput.value = '0';
                    if (packIdInput) packIdInput.value = '0';
                    if (tipoRegistroInput) tipoRegistroInput.value = 'item';
                }
                actualizarUIModal();
                filtrarAlmacenesPorTipo();
                actualizarStockHint();
                cargarResumenItem();
                toggleAlmacenOrigenSegunItem();
            }
        });
    }

    toggleAlmacenOrigenSegunItem();
    // ACCIÓN 2: Tooltips inicializados desde la vista, pero nos aseguramos aquí
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
  });

  // --- FUNCIONES DE UTILIDAD PARA LA TABLA PRINCIPAL ---
  function normalizarTexto(valor) {
    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function construirControlesPaginacion(totalPages) {
    if (!paginationControls) return;
    paginationControls.innerHTML = '';

    const crearItem = (label, page, active = false, disabled = false) => {
      const li = document.createElement('li');
      li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = label;
      a.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (disabled || active || page == null) return;
        currentStockPage = page;
        filtrarStock();
      });
      li.appendChild(a);
      paginationControls.appendChild(li);
    };

    const crearPuntos = () => {
      const li = document.createElement('li');
      li.className = 'page-item disabled';
      const span = document.createElement('span');
      span.className = 'page-link';
      span.textContent = '...';
      li.appendChild(span);
      paginationControls.appendChild(li);
    };

    const construirTokensPaginas = () => {
      const pages = new Set([1, totalPages]);
      for (let i = currentStockPage - 1; i <= currentStockPage + 1; i += 1) {
        if (i > 1 && i < totalPages) pages.add(i);
      }
      const ordenadas = Array.from(pages).sort((a, b) => a - b);
      const tokens = [];
      ordenadas.forEach((page, idx) => {
        if (idx > 0 && page - ordenadas[idx - 1] > 1) tokens.push('dots');
        tokens.push(page);
      });
      return tokens;
    };

    crearItem('Anterior', currentStockPage - 1, false, currentStockPage === 1);

    construirTokensPaginas().forEach((token) => {
      if (token === 'dots') crearPuntos();
      else crearItem(String(token), token, token === currentStockPage, false);
    });

    crearItem('Siguiente', currentStockPage + 1, false, currentStockPage === totalPages || totalPages === 0);
  }

  function filtrarStock() {
    if (!tablaStock) return;
    const termino = normalizarTexto(searchInput ? searchInput.value : '');
    const tipoFiltro = (filtroTipoRegistro ? filtroTipoRegistro.value : '').trim();
    const estado = (filtroEstado ? filtroEstado.value : '').trim();
    const vencimiento = (filtroVencimiento ? filtroVencimiento.value : '').trim();

    const filas = Array.from(tablaStock.querySelectorAll('tbody tr')).filter((fila) => !!fila.dataset.search);
    const visibles = filas.filter((fila) => {
      const okTexto = termino === '' || normalizarTexto(fila.dataset.search).includes(termino);
      const okTipo = tipoFiltro === '' || (fila.dataset.tipoRegistro || '') === tipoFiltro;
      const okEstado = estado === '' || (fila.dataset.estado || '') === estado;
      const okVenc = vencimiento === '' || (fila.dataset.vencimiento || '') === vencimiento;
      return okTexto && okTipo && okEstado && okVenc;
    });

    const totalItems = visibles.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / ITEMS_PER_PAGE));
    if (currentStockPage > totalPages) currentStockPage = totalPages;

    const start = (currentStockPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;

    filas.forEach((fila) => fila.classList.add('d-none'));
    visibles.slice(start, end).forEach((fila) => fila.classList.remove('d-none'));

    if (paginationInfo) {
      if (totalItems === 0) {
        paginationInfo.textContent = 'Mostrando 0-0 de 0 resultados';
      } else {
        paginationInfo.textContent = `Mostrando ${start + 1}-${Math.min(end, totalItems)} de ${totalItems} resultados`;
      }
    }

    construirControlesPaginacion(totalPages);
  }

  function aplicarFiltroAlmacenServidor() {
    if (!filtroAlmacen) return;
    const url = new URL(window.location.href);
    const valor = (filtroAlmacen.value || '').trim();
    if (valor === '') url.searchParams.delete('id_almacen');
    else url.searchParams.set('id_almacen', valor);
    window.location.href = url.toString();
  }

  // --- LÓGICA DEL FORMULARIO DE MOVIMIENTOS ---
  function obtenerDataDelItem() {
    if (!tomSelectItem) return null;
    const val = tomSelectItem.getValue();
    if (!val) return null;
    
    const itemData = tomSelectItem.options[val];
    return {
        id: Number(itemData.id || 0),
        tipoRegistro: itemData.tipo_registro === 'pack' ? 'pack' : 'item',
        requiereLote: itemData.requiere_lote == '1',
        requiereVencimiento: itemData.requiere_vencimiento == '1'
    };
  }

  function actualizarUIModal() {
    if (!tipo) return;
    const tipoVal = tipo.value;
    const itemData = obtenerDataDelItem();
    
    const esTransferencia = tipoVal === 'TRF';
    if(grupoDestino) grupoDestino.classList.toggle('d-none', !esTransferencia);
    if(almacenDestino) {
        almacenDestino.required = esTransferencia;
        if (!esTransferencia) {
            if (tomSelectAlmacenDestino) tomSelectAlmacenDestino.clear();
            else almacenDestino.value = '';
        }
    }

    const esEntrada = ['INI', 'AJ+'].includes(tipoVal);
    const esSalida = ['AJ-', 'CON', 'TRF'].includes(tipoVal);
    const requiereLote = itemData ? itemData.requiereLote : false;
    const requiereVenc = itemData ? itemData.requiereVencimiento : false;

    if(grupoLoteInput) grupoLoteInput.classList.add('d-none');
    if(grupoLoteSelect) grupoLoteSelect.classList.add('d-none');
    
    if (requiereLote) {
        if (esEntrada) {
            if(grupoLoteInput) grupoLoteInput.classList.remove('d-none');
        } else if (esSalida) {
            if(grupoLoteSelect) grupoLoteSelect.classList.remove('d-none');
            cargarLotesDisponibles();
        }
    }

    if(grupoVencimiento) {
        const visible = esEntrada && requiereVenc;
        grupoVencimiento.classList.toggle('d-none', !visible);
        if(inputVencimiento) inputVencimiento.required = visible;
    }

    if (costoUnitarioInput) {
      const habilitarCostoManual = tipoVal === 'INI';
      costoUnitarioInput.readOnly = !habilitarCostoManual;
      costoUnitarioInput.classList.toggle('bg-light', !habilitarCostoManual);
      costoUnitarioInput.classList.toggle('text-muted', !habilitarCostoManual);
      if (tipoVal === 'INI') costoUnitarioInput.value = '0.0000';
    }

    if (grupoProveedor) grupoProveedor.classList.add('d-none');
    if (grupoMotivo) grupoMotivo.classList.toggle('d-none', !['AJ+', 'AJ-', 'CON'].includes(tipoVal));
    if (motivo) motivo.required = ['AJ+', 'AJ-', 'CON'].includes(tipoVal);

    if (cantidadInput) {
      cantidadInput.min = tipoVal === 'INI' ? '0' : '0.0001';
    }

    if (inputVencimiento && tipoVal === 'INI') {
      inputVencimiento.required = !!(itemData && itemData.requiereVencimiento);
    }

    filtrarAlmacenesPorTipo();
  }

  async function obtenerStockActual(idItem, idAlmacen, tipoRegistro = 'item') {
    if (idItem <= 0 || idAlmacen <= 0) return 0;
    try {
      const url = `${window.BASE_URL}?ruta=inventario/stockItem&id_item=${idItem}&id_almacen=${idAlmacen}&tipo_registro=${encodeURIComponent(tipoRegistro)}`;
      const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json();
      return Number((data && data.stock) || 0);
    } catch (error) {
      return 0;
    }
  }

  function setSelectOptions(selectTom, opciones, valorActual = '') {
    if (!selectTom) return;
    selectTom.clearOptions();
    opciones.forEach((opcion) => selectTom.addOption(opcion));
    selectTom.refreshOptions(false);
    if (valorActual && opciones.some((o) => String(o.value) === String(valorActual))) {
      selectTom.setValue(String(valorActual), true);
      return;
    }
    selectTom.clear(true);
  }

  async function filtrarAlmacenesPorTipo() {
    if (!tipo || !itemIdInput || !tomSelectAlmacen) return;
    const tipoVal = tipo.value;
    const idItem = Number(itemIdInput.value || '0');
    const idPack = Number((packIdInput && packIdInput.value) || '0');
    const idRegistro = idPack > 0 ? idPack : idItem;
    const tipoRegistro = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
    const origenActual = almacen ? almacen.value : '';

    if (tipoVal === '' || idRegistro <= 0) {
      setSelectOptions(tomSelectAlmacen, almacenesBase.origen, origenActual);
      toggleAlmacenOrigenSegunItem();
      actualizarOpcionesDestino();
      return;
    }

    const requiereStockOrigen = ['TRF', 'AJ-', 'CON'].includes(tipoVal);
    let origenFiltrado = almacenesBase.origen.filter((opt) => opt.value === '' || Number(opt.value) > 0);

    if (requiereStockOrigen) {
      const validaciones = await Promise.all(origenFiltrado.map(async (opt) => {
        if (opt.value === '') return opt;
        const stock = await obtenerStockActual(idRegistro, Number(opt.value), tipoRegistro);
        return stock > 0 ? opt : null;
      }));
      origenFiltrado = validaciones.filter(Boolean);
    }

    setSelectOptions(tomSelectAlmacen, origenFiltrado, origenActual);
    toggleAlmacenOrigenSegunItem();

    actualizarOpcionesDestino();
  }

  function actualizarOpcionesDestino() {
    if (!tomSelectAlmacenDestino) return;
    const origenSel = String((almacen && almacen.value) || '');
    const destinoFiltrado = almacenesBase.destino.filter((opt) => opt.value === '' || String(opt.value) !== origenSel);
    setSelectOptions(tomSelectAlmacenDestino, destinoFiltrado, almacenDestino ? almacenDestino.value : '');
  }

  async function obtenerResumenItemReal(idItem, tipoRegistro = 'item') {
    if (idItem <= 0) {
      return { costo_promedio_actual: 0, stock_actual: 0 };
    }

    const url = `${window.BASE_URL}?ruta=inventario/resumenItem&id_item=${idItem}&tipo_registro=${encodeURIComponent(tipoRegistro)}`;
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await response.json();
    if (!data || data.ok !== true) {
      throw new Error('Respuesta inválida en inventario/resumenItem.');
    }

    return data.resumen || { costo_promedio_actual: 0, stock_actual: 0 };
  }

  async function cargarResumenItem() {
    if (!itemIdInput) return;
    const idItem = Number(itemIdInput.value || '0');
    const idPack = Number((packIdInput && packIdInput.value) || '0');
    const idRegistro = idPack > 0 ? idPack : idItem;
    const tipoRegistro = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
    if (!costoPromedioActualLabel || !stockActualItemLabel) return;

    if (idRegistro <= 0) {
      costoPromedioActualLabel.textContent = '$0.0000';
      stockActualItemLabel.textContent = '0.0000';
      return;
    }

    costoPromedioActualLabel.textContent = 'Consultando...';
    stockActualItemLabel.textContent = 'Consultando...';

    try {
      const resumen = await obtenerResumenItemReal(idRegistro, tipoRegistro);
      const costoPromedio = Number(resumen.costo_promedio_actual || 0);
      const stock = Number(resumen.stock_actual || 0);

      costoPromedioActualLabel.textContent = `$${costoPromedio.toFixed(4)}`;
      stockActualItemLabel.textContent = stock.toFixed(4);

      if (costoUnitarioInput) {
        if (tipo && tipo.value === 'INI') costoUnitarioInput.value = '0.0000';
        else costoUnitarioInput.value = costoPromedio.toFixed(4);
      }
    } catch (error) {
      costoPromedioActualLabel.textContent = '$0.0000';
      stockActualItemLabel.textContent = '0.0000';
    }
  }

  async function cargarLotesDisponibles() {
    if (grupoLoteSelect.classList.contains('d-none')) return;
    const idItem = itemIdInput.value;
    const idAlmacen = almacen.value;
    const tipoRegistro = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';

    selectLoteExistente.innerHTML = '<option value="">Seleccione lote...</option>';
    msgSinLotes.classList.add('d-none');

    if (tipoRegistro !== 'item' || !idItem || !idAlmacen || idItem <= 0 || idAlmacen <= 0) return;

    try {
        const response = await fetch(`${window.BASE_URL}?ruta=inventario/buscarLotes&id_item=${idItem}&id_almacen=${idAlmacen}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.ok && data.lotes && data.lotes.length > 0) {
            data.lotes.forEach(l => {
                const stock = parseFloat(l.stock_lote);
                const venc = l.fecha_vencimiento ? l.fecha_vencimiento : 'N/A';
                const texto = `${l.lote} | Vence: ${venc} | Disp: ${stock}`;
                const option = document.createElement('option');
                option.value = l.lote;
                option.textContent = texto;
                selectLoteExistente.appendChild(option);
            });
        } else {
            msgSinLotes.classList.remove('d-none');
        }
    } catch (error) {
        console.error("Error cargando lotes", error);
    }
  }

  async function actualizarStockHint() {
    if (!stockHint || !itemIdInput || !almacen) return;
    
    const esSalida = ['AJ-', 'CON', 'TRF'].includes((tipo && tipo.value) || '');
    if (!esSalida) {
      stockHint.textContent = '';
      return;
    }

    const idItem = Number(itemIdInput.value || '0');
    const idPack = Number((packIdInput && packIdInput.value) || '0');
    const idRegistro = idPack > 0 ? idPack : idItem;
    const tipoRegistro = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
    const idAlmacen = Number(almacen.value || '0');
    if (idRegistro <= 0 || idAlmacen <= 0) {
      stockHint.textContent = '';
      return;
    }

    try {
      const url = `${window.BASE_URL}?ruta=inventario/stockItem&id_item=${idItem}&id_almacen=${idAlmacen}&tipo_registro=${encodeURIComponent(tipoRegistro)}`;
      const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json();
      const stock = Number((data && data.stock) || 0);
      stockHint.textContent = `Stock total en almacén: ${stock.toFixed(4)}`;
      stockHint.className = stock <= 0 ? 'form-text text-danger fw-bold' : 'form-text text-success';
    } catch (error) {
      stockHint.textContent = '';
    }
  }

  // --- LISTENERS ---
  if (searchInput) searchInput.addEventListener('input', () => { currentStockPage = 1; filtrarStock(); });
  if (filtroTipoRegistro) filtroTipoRegistro.addEventListener('change', () => { currentStockPage = 1; filtrarStock(); });
  if (filtroEstado) filtroEstado.addEventListener('change', () => { currentStockPage = 1; filtrarStock(); });
  if (filtroVencimiento) filtroVencimiento.addEventListener('change', () => { currentStockPage = 1; filtrarStock(); });
  if (filtroAlmacen) filtroAlmacen.addEventListener('change', aplicarFiltroAlmacenServidor);
  
  filtrarStock();

  if (form) {
    if (tipo) {
        tipo.addEventListener('change', () => {
            actualizarUIModal();
            actualizarStockHint();
            if (Number((itemIdInput && itemIdInput.value) || '0') > 0 || Number((packIdInput && packIdInput.value) || '0') > 0) cargarResumenItem();
        });
    }

    if (almacen) {
        almacen.addEventListener('change', () => {
            if (!grupoLoteSelect.classList.contains('d-none')) cargarLotesDisponibles();
            actualizarOpcionesDestino();
            actualizarStockHint();
            cargarResumenItem();
        });
    }

    if (almacenDestino) {
      almacenDestino.addEventListener('change', () => {
        if ((tipo && tipo.value) === 'TRF' && almacen && String(almacen.value) === String(almacenDestino.value)) {
          Swal.fire({ icon: 'warning', title: 'Destino inválido', text: 'El almacén destino no puede ser igual al origen.' });
          if (tomSelectAlmacenDestino) tomSelectAlmacenDestino.clear();
        }
      });
    }

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => {
            form.reset();
            if (tomSelectTipo) tomSelectTipo.clear();
            if (tomSelectAlmacen) tomSelectAlmacen.clear();
            if (tomSelectAlmacenDestino) tomSelectAlmacenDestino.clear();
            if (tomSelectProveedor) tomSelectProveedor.clear();
            if (tomSelectMotivo) tomSelectMotivo.clear();
            if (tomSelectItem) {
                tomSelectItem.clearOptions();
                tomSelectItem.clear();
            }
            
            if (itemIdInput) itemIdInput.value = '0';
            if (packIdInput) packIdInput.value = '0';
            if (tipoRegistroInput) tipoRegistroInput.value = 'item';
            if (stockHint) stockHint.textContent = '';
            if (costoPromedioActualLabel) costoPromedioActualLabel.textContent = '$0.0000';
            if (stockActualItemLabel) stockActualItemLabel.textContent = '0.0000';
            toggleAlmacenOrigenSegunItem();
            
            if(grupoLoteInput) grupoLoteInput.classList.add('d-none');
            if(grupoLoteSelect) grupoLoteSelect.classList.add('d-none');
            if(grupoDestino) grupoDestino.classList.add('d-none');
            if(grupoMotivo) grupoMotivo.classList.add('d-none');
            if(grupoVencimiento) grupoVencimiento.classList.add('d-none');
            if(costoUnitarioInput) {
              costoUnitarioInput.readOnly = false;
              costoUnitarioInput.classList.remove('bg-light', 'text-muted');
            }
        });
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      const idItemVal = Number((itemIdInput && itemIdInput.value) || '0');
      const idPackVal = Number((packIdInput && packIdInput.value) || '0');
      const tipoRegistroVal = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
      const idRegistroVal = tipoRegistroVal === 'pack' ? idPackVal : idItemVal;

      if (idRegistroVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Ítem inválido', text: 'Selecciona un ítem de la lista.' });
        return;
      }

      const tipoVal = (tipo && tipo.value) || '';
      const idAlmacenVal = Number((almacen && almacen.value) || '0');
      const cantidadVal = Number((cantidadInput && cantidadInput.value) || 0);
      const referenciaVal = ((document.getElementById('referenciaMovimiento') || {}).value || '').trim();
      const motivoVal = ((motivo || {}).value || '').trim();

      if (!tipoVal) {
        Swal.fire({ icon: 'warning', title: 'Tipo requerido', text: 'Seleccione el tipo de movimiento.' });
        return;
      }

      if (idAlmacenVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Almacén requerido', text: 'Primero seleccione un ítem y luego el almacén origen.' });
        return;
      }

      if (['AJ+', 'AJ-', 'CON'].includes(tipoVal) && motivoVal === '') {
        Swal.fire({ icon: 'warning', title: 'Motivo requerido', text: 'Seleccione el motivo del movimiento.' });
        return;
      }

      if (['AJ+', 'AJ-', 'INI'].includes(tipoVal) && referenciaVal === '') {
        Swal.fire({ icon: 'warning', title: 'Referencia requerida', text: 'Debe ingresar una referencia para este tipo de movimiento.' });
        return;
      }

      if (tipoVal === 'INI') {
        if (cantidadVal < 0) {
          Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Para INI la cantidad debe ser mayor o igual a 0.' });
          return;
        }
      } else if (cantidadVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'La cantidad debe ser mayor a 0.' });
        return;
      }

      if (tipoVal === 'TRF' && almacen && almacenDestino && String(almacen.value) === String(almacenDestino.value)) {
        Swal.fire({ icon: 'warning', title: 'Almacenes inválidos', text: 'El almacén origen y destino deben ser diferentes.' });
        return;
      }

      if (['AJ-', 'CON', 'TRF'].includes(tipoVal)) {
        const stockDisponible = await obtenerStockActual(idRegistroVal, Number((almacen && almacen.value) || '0'), tipoRegistroVal);
        if (stockDisponible <= 0) {
          Swal.fire({ icon: 'warning', title: 'Sin stock', text: 'El almacén origen no tiene stock para el ítem seleccionado.' });
          return;
        }
        if (cantidadVal > stockDisponible) {
          Swal.fire({ icon: 'warning', title: 'Cantidad excedida', text: `La cantidad no puede superar el stock disponible (${stockDisponible.toFixed(4)}).` });
          return;
        }
      }

      if (!grupoLoteInput.classList.contains('d-none')) {
          loteFinalEnviar.value = inputLoteNuevo.value; 
      } else if (!grupoLoteSelect.classList.contains('d-none')) {
          loteFinalEnviar.value = selectLoteExistente.value; 
      } else {
          loteFinalEnviar.value = ''; 
      }

      const itemData = obtenerDataDelItem();
      if (itemData && itemData.requiereLote && loteFinalEnviar.value.trim() === '') {
          Swal.fire({ icon: 'warning', title: 'Falta Lote', text: 'Este producto requiere un número de lote.' });
          return;
      }

      const confirmacion = await Swal.fire({
        icon: 'question',
        title: '¿Confirmar movimiento?',
        text: 'Se actualizará el inventario.',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
      });

      if (!confirmacion.isConfirmed) return;

      const data = new FormData(form);
      if (motivoVal) {
        data.set('motivo', motivoVal);
      }

      try {
        const response = await fetch(`${window.BASE_URL}?ruta=inventario/guardarMovimiento`, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: data
        });

        const result = await response.json();

        if (!response.ok || !result || !result.ok) {
          throw new Error((result && result.mensaje) || 'Error al guardar.');
        }

        await Swal.fire({ icon: 'success', title: 'Guardado', text: result.mensaje });
        
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if(modalInstance) modalInstance.hide();
        window.location.reload();

      } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
      }
    });
  }
})();
