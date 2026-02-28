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
  const btnAgregarLinea = document.getElementById('btnAgregarLineaMovimiento');
  const movimientosDetalleBody = document.getElementById('movimientosDetalleBody');

  const lineasMovimiento = [];

  // --- FILTROS DE LA TABLA PRINCIPAL ---
  const filtroTipoRegistro = document.getElementById('inventarioFiltroTipoRegistro');
  const filtroEstado = document.getElementById('inventarioFiltroEstado');
  const filtroAlmacen = document.getElementById('inventarioFiltroAlmacen');
  const tablaStock = document.getElementById('tablaInventarioStock');

  let stockTableManager = null;

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

  function esTipoSalida(tipoVal) {
    return ['AJ-', 'CON', 'TRF'].includes(tipoVal);
  }

  function actualizarBloqueoCabecera() {
    const bloquear = lineasMovimiento.length > 0;
    if (tipo) {
      tipo.disabled = bloquear;
      if (tomSelectTipo) bloquear ? tomSelectTipo.disable() : tomSelectTipo.enable();
    }
    if (almacen) {
      almacen.disabled = bloquear;
      if (tomSelectAlmacen) bloquear ? tomSelectAlmacen.disable() : tomSelectAlmacen.enable();
    }
    if (almacenDestino) {
      almacenDestino.disabled = bloquear;
      if (tomSelectAlmacenDestino) bloquear ? tomSelectAlmacenDestino.disable() : tomSelectAlmacenDestino.enable();
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
                const idAlmacen = Number((almacen && almacen.value) || 0);
                if (idAlmacen <= 0) {
                    callback();
                    return;
                }
                const tipoVal = (tipo && tipo.value) || '';
                const soloConStock = esTipoSalida(tipoVal) ? '1' : '0';

                const tstamp = new Date().getTime();
                fetch(`${window.BASE_URL}?ruta=inventario/buscarItems&q=${encodeURIComponent(query)}&id_almacen=${idAlmacen}&solo_con_stock=${soloConStock}&tipo_movimiento=${encodeURIComponent(tipoVal)}&_t=${tstamp}`, {
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
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
            }
        });
    }

    // ACCIÓN 2: Tooltips inicializados desde la vista, pero nos aseguramos aquí
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
  });

  // --- FUNCIONES DE UTILIDAD PARA LA TABLA PRINCIPAL ---
  function normalizarTexto(valor) {
    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  function initTablaStock() {
    if (!tablaStock || typeof ERPTable === 'undefined' || !ERPTable.createTableManager) return;

    stockTableManager = ERPTable.createTableManager({
      tableSelector: tablaStock,
      rowsSelector: 'tbody tr[data-search]',
      searchInput: '#inventarioSearch',
      filters: [
        { el: '#inventarioFiltroTipoRegistro', attr: 'data-tipo-registro', match: 'equals' },
        { el: '#inventarioFiltroEstado', attr: 'data-estado', match: 'equals' }
      ],
      rowsPerPage: 25,
      paginationControls: '#inventarioPaginationControls',
      paginationInfo: '#inventarioPaginationInfo',
      normalizeSearchText: normalizarTexto,
      infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} resultados`,
      emptyText: 'Mostrando 0-0 de 0 resultados'
    }).init();
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
    
    // Parseo robusto (Asegura que "0", 0 o false no activen el lote)
    const reqLote = String(itemData.requiere_lote) === '1' || String(itemData.requiere_lote).toLowerCase() === 'true';
    const reqVenc = String(itemData.requiere_vencimiento) === '1' || String(itemData.requiere_vencimiento).toLowerCase() === 'true';

    return {
        id: Number(itemData.id || 0),
        tipoRegistro: itemData.tipo_registro === 'pack' ? 'pack' : 'item',
        requiereLote: reqLote,
        requiereVencimiento: reqVenc
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
        const visible = (esEntrada && requiereVenc) || (!esEntrada && requiereLote && requiereVenc);
        grupoVencimiento.classList.toggle('d-none', !visible);
        if(inputVencimiento) {
          inputVencimiento.required = visible;
          inputVencimiento.readOnly = !esEntrada;
        }
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
    if (!tipo || !tomSelectAlmacen) return;
    const tipoVal = tipo.value;
    const origenActual = almacen ? almacen.value : '';

    if (tipoVal === '') {
      setSelectOptions(tomSelectAlmacen, almacenesBase.origen, origenActual);
      actualizarOpcionesDestino();
      return;
    }

    setSelectOptions(tomSelectAlmacen, almacenesBase.origen, origenActual);
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
                option.dataset.vencimiento = l.fecha_vencimiento || '';
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
    
    const esSalida = esTipoSalida((tipo && tipo.value) || '');
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
      const idConsulta = tipoRegistro === 'pack' ? idPack : idItem;
      const url = `${window.BASE_URL}?ruta=inventario/stockItem&id_item=${idConsulta}&id_almacen=${idAlmacen}&tipo_registro=${encodeURIComponent(tipoRegistro)}`;
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
  if (filtroAlmacen) filtroAlmacen.addEventListener('change', aplicarFiltroAlmacenServidor);

  initTablaStock();

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
            if (tomSelectItem) {
              tomSelectItem.clearOptions();
              tomSelectItem.clear(true);
            }
            if (itemIdInput) itemIdInput.value = '0';
            if (packIdInput) packIdInput.value = '0';
            if (!grupoLoteSelect.classList.contains('d-none')) cargarLotesDisponibles();
            actualizarOpcionesDestino();
            actualizarStockHint();
            cargarResumenItem();
        });
    }

    if (selectLoteExistente) {
      selectLoteExistente.addEventListener('change', () => {
        if (!inputVencimiento) return;
        const opcion = selectLoteExistente.options[selectLoteExistente.selectedIndex];
        inputVencimiento.value = (opcion && opcion.dataset && opcion.dataset.vencimiento) ? opcion.dataset.vencimiento : '';
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

    function limpiarEditorLinea() {
      if (tomSelectItem) {
        tomSelectItem.clear(true);
      }
      if (itemIdInput) itemIdInput.value = '0';
      if (packIdInput) packIdInput.value = '0';
      if (tipoRegistroInput) tipoRegistroInput.value = 'item';
      if (cantidadInput) cantidadInput.value = '';
      if (inputLoteNuevo) inputLoteNuevo.value = '';
      if (selectLoteExistente) selectLoteExistente.value = '';
      if (inputVencimiento) inputVencimiento.value = '';
      if (costoUnitarioInput) costoUnitarioInput.value = '0';
      if (stockHint) stockHint.textContent = '';
      if (costoPromedioActualLabel) costoPromedioActualLabel.textContent = 'S/ 0.00';
      if (stockActualItemLabel) stockActualItemLabel.textContent = '0.0000';
      actualizarUIModal();
    }

    function renderLineasMovimiento() {
      if (!movimientosDetalleBody) return;

      if (lineasMovimiento.length === 0) {
        movimientosDetalleBody.innerHTML = '<tr data-empty="1"><td colspan="6" class="text-center text-muted py-3">Aún no hay ítems agregados.</td></tr>';
        return;
      }

      movimientosDetalleBody.innerHTML = '';
      lineasMovimiento.forEach((linea, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${linea.etiqueta}</td>
          <td class="text-end">${Number(linea.cantidad || 0).toFixed(4)}</td>
          <td>${linea.lote || '-'}</td>
          <td>${linea.fecha_vencimiento || '-'}</td>
          <td class="text-end">${Number(linea.costo_unitario || 0).toFixed(4)}</td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-index="${idx}"><i class="bi bi-trash"></i></button>
          </td>
        `;
        movimientosDetalleBody.appendChild(tr);
      });
    }

    function construirClaveLinea(tipoRegistroVal, idRegistroVal, loteVal) {
      return [tipoRegistroVal, idRegistroVal, (loteVal || '').trim().toLowerCase()].join('|');
    }

    async function agregarLineaMovimiento() {
      const idItemVal = Number((itemIdInput && itemIdInput.value) || '0');
      const idPackVal = Number((packIdInput && packIdInput.value) || '0');
      const tipoRegistroVal = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
      const idRegistroVal = tipoRegistroVal === 'pack' ? idPackVal : idItemVal;
      const cantidadVal = Number((cantidadInput && cantidadInput.value) || 0);
      const tipoVal = (tipo && tipo.value) || '';

      if (!tipoVal) {
        Swal.fire({ icon: 'warning', title: 'Tipo requerido', text: 'Seleccione el tipo de movimiento antes de agregar líneas.' });
        return;
      }

      if (idRegistroVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Ítem inválido', text: 'Selecciona un ítem válido.' });
        return;
      }

      const idAlmacenVal = Number((almacen && almacen.value) || '0');
      if (idAlmacenVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Almacén requerido', text: 'Seleccione el almacén origen antes de agregar ítems.' });
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

      if (lineasMovimiento.length >= 100) {
        Swal.fire({ icon: 'warning', title: 'Límite alcanzado', text: 'Solo se permiten hasta 100 líneas por operación.' });
        return;
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

      const loteVal = (loteFinalEnviar.value || '').trim();
      const claveLinea = construirClaveLinea(tipoRegistroVal, idRegistroVal, loteVal);
      const existeDuplicado = lineasMovimiento.some((linea) => {
        const idRegistroLinea = linea.tipo_registro === 'pack' ? Number(linea.id_pack || 0) : Number(linea.id_item || 0);
        return construirClaveLinea(linea.tipo_registro, idRegistroLinea, linea.lote) === claveLinea;
      });

      if (existeDuplicado) {
        Swal.fire({
          icon: 'warning',
          title: 'Ítem duplicado',
          text: 'Este producto ya fue agregado a la operación. Edite la cantidad o elimine la línea existente.'
        });
        return;
      }

      if (esTipoSalida(tipoVal)) {
        const cantidadAcumulada = lineasMovimiento
          .filter((linea) => {
            const idRegistroLinea = linea.tipo_registro === 'pack' ? Number(linea.id_pack || 0) : Number(linea.id_item || 0);
            return construirClaveLinea(linea.tipo_registro, idRegistroLinea, linea.lote) === claveLinea;
          })
          .reduce((total, linea) => total + Number(linea.cantidad || 0), 0);

        const idConsulta = tipoRegistroVal === 'pack' ? idPackVal : idItemVal;
        const stockDisponible = await obtenerStockActual(idConsulta, idAlmacenVal, tipoRegistroVal);
        const cantidadSolicitada = cantidadAcumulada + cantidadVal;

        if (cantidadSolicitada > stockDisponible) {
          Swal.fire({
            icon: 'error',
            title: 'Stock insuficiente',
            text: `No puedes mover ${cantidadSolicitada.toFixed(4)}. Stock disponible: ${stockDisponible.toFixed(4)}.`
          });
          return;
        }
      }

      const selectedValue = tomSelectItem ? tomSelectItem.getValue() : '';
      const selectedData = (tomSelectItem && selectedValue) ? tomSelectItem.options[selectedValue] : {};
      const etiqueta = `${selectedData.sku || ''} - ${selectedData.nombre_full || selectedData.nombre || 'Ítem'}`;

      lineasMovimiento.push({
        tipo_registro: tipoRegistroVal,
        id_item: tipoRegistroVal === 'item' ? idItemVal : 0,
        id_pack: tipoRegistroVal === 'pack' ? idPackVal : 0,
        cantidad: cantidadVal,
        lote: loteVal,
        fecha_vencimiento: (inputVencimiento && inputVencimiento.value) ? inputVencimiento.value : '',
        costo_unitario: Number((costoUnitarioInput && costoUnitarioInput.value) || 0),
        etiqueta,
      });

      renderLineasMovimiento();
      actualizarBloqueoCabecera();
      limpiarEditorLinea();
    }

    if (btnAgregarLinea) {
      btnAgregarLinea.addEventListener('click', agregarLineaMovimiento);
    }

    if (movimientosDetalleBody) {
      renderLineasMovimiento();
      movimientosDetalleBody.addEventListener('click', (event) => {
        const target = event.target.closest('[data-remove-index]');
        if (!target) return;
        const idx = Number(target.getAttribute('data-remove-index'));
        if (Number.isNaN(idx) || idx < 0 || idx >= lineasMovimiento.length) return;
        lineasMovimiento.splice(idx, 1);
        renderLineasMovimiento();
        actualizarBloqueoCabecera();
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
            actualizarBloqueoCabecera();
            
            if(grupoLoteInput) grupoLoteInput.classList.add('d-none');
            if(grupoLoteSelect) grupoLoteSelect.classList.add('d-none');
            if(grupoDestino) grupoDestino.classList.add('d-none');
            if(grupoMotivo) grupoMotivo.classList.add('d-none');
            if(grupoVencimiento) grupoVencimiento.classList.add('d-none');
            if(costoUnitarioInput) {
              costoUnitarioInput.readOnly = false;
              costoUnitarioInput.classList.remove('bg-light', 'text-muted');
            }
            lineasMovimiento.length = 0;
            renderLineasMovimiento();
            actualizarBloqueoCabecera();
        });
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      const tipoVal = (tipo && tipo.value) || '';
      const idAlmacenVal = Number((almacen && almacen.value) || '0');
      const referenciaVal = ((document.getElementById('referenciaMovimiento') || {}).value || '').trim();
      const motivoVal = ((motivo || {}).value || '').trim();
      const idAlmacenDestinoVal = Number((almacenDestino && almacenDestino.value) || '0');

      if (!tipoVal) {
        Swal.fire({ icon: 'warning', title: 'Tipo requerido', text: 'Seleccione el tipo de movimiento.' });
        return;
      }

      if (idAlmacenVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Almacén requerido', text: 'Seleccione el almacén origen/destino principal.' });
        return;
      }

      if (tipoVal === 'TRF' && idAlmacenDestinoVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Destino requerido', text: 'Seleccione el almacén destino para transferencias.' });
        return;
      }

      if (tipoVal === 'TRF' && almacen && almacenDestino && String(almacen.value) === String(almacenDestino.value)) {
        Swal.fire({ icon: 'warning', title: 'Almacenes inválidos', text: 'El almacén origen y destino deben ser diferentes.' });
        return;
      }

      if (['AJ+', 'AJ-', 'CON'].includes(tipoVal) && motivoVal === '') {
        Swal.fire({ icon: 'warning', title: 'Motivo requerido', text: 'Seleccione el motivo del movimiento.' });
        return;
      }

      if (['AJ+', 'AJ-'].includes(tipoVal) && referenciaVal === '') {
        Swal.fire({ icon: 'warning', title: 'Referencia requerida', text: 'Debe ingresar una referencia para este tipo de movimiento.' });
        return;
      }

      if (lineasMovimiento.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Sin líneas', text: 'Agrega al menos un ítem a la operación antes de guardar.' });
        return;
      }

      const confirmacion = await Swal.fire({
        icon: 'question',
        title: '¿Confirmar operación masiva?',
        text: `Se registrarán ${lineasMovimiento.length} línea(s) de inventario en modo atómico.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
      });

      if (!confirmacion.isConfirmed) return;

      try {
        const response = await fetch(`${window.BASE_URL}?ruta=inventario/guardarMovimientoLote`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            modo: 'atomico',
            cabecera: {
              tipo_movimiento: tipoVal,
              id_almacen: idAlmacenVal,
              id_almacen_destino: idAlmacenDestinoVal,
              referencia: referenciaVal,
              motivo: motivoVal
            },
            items: lineasMovimiento.map((linea) => ({
              tipo_registro: linea.tipo_registro,
              id_item: linea.id_item,
              id_pack: linea.id_pack,
              cantidad: linea.cantidad,
              lote: linea.lote,
              fecha_vencimiento: linea.fecha_vencimiento,
              costo_unitario: linea.costo_unitario
            }))
          })
        });

        const result = await response.json();

        if (!response.ok || !result || !result.ok) {
          throw new Error((result && result.mensaje) || 'Error al guardar.');
        }

        await Swal.fire({
          icon: 'success',
          title: 'Operación registrada',
          text: `Se registraron ${Array.isArray(result.ids) ? result.ids.length : 0} movimientos.`,
        });

        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        window.location.reload();

      } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
      }
    });
  }
})();
