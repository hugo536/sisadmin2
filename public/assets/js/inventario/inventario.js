(function () {
  'use strict';

  // --- REFERENCIAS AL DOM ---
  const form = document.getElementById('formMovimientoInventario');
  const modalEl = document.getElementById('modalMovimientoInventario');
  
  // Selects principales (Modal)
  const tipo = document.getElementById('tipoMovimiento');
  const almacen = document.getElementById('almacenMovimiento');
  const grupoProveedor = document.getElementById('grupoProveedorMovimiento');
  const proveedor = document.getElementById('proveedorMovimiento'); 
  const grupoMotivo = document.getElementById('grupoMotivoMovimiento');
  const motivo = document.getElementById('motivoMovimiento');
  
  // Centro de Costos
  const grupoCentroCosto = document.getElementById('grupoCentroCostoMovimiento');
  const centroCosto = document.getElementById('centroCostoMovimiento');
  let tomSelectCentroCosto = null; 
  
  // Destino (Solo TRF)
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');
  
  // Ítem 
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
  const grupoCostoUnitario = document.getElementById('grupoCostoUnitarioMovimiento');
  const stockActualItemLabel = document.getElementById('stockActualItemSeleccionado');
  const grupoUnidadMovimiento = document.getElementById('grupoUnidadMovimiento');
  const unidadMovimiento = document.getElementById('unidadMovimiento');
  const unidadMovimientoInfo = document.getElementById('unidadMovimientoInfo');
  const btnAgregarLinea = document.getElementById('btnAgregarLineaMovimiento');
  const movimientosDetalleBody = document.getElementById('movimientosDetalleBody');

  const lineasMovimiento = [];
  const cacheUnidadesItem = new Map();

  function obtenerUnidadBaseItemActual() {
    if (!tomSelectItem) return 'Unidad base';
    const val = tomSelectItem.getValue();
    if (!val) return 'Unidad base';
    const itemData = tomSelectItem.options[val] || {};
    const unidadBase = (itemData.unidad_base || '').toString().trim();
    return unidadBase || 'Unidad base';
  }

  function limpiarUnidadesTransferencia() {
    const etiquetaUnidadBase = obtenerUnidadBaseItemActual();
    if (unidadMovimiento) {
      unidadMovimiento.innerHTML = `<option value="">${etiquetaUnidadBase}</option>`;
      unidadMovimiento.value = '';
      unidadMovimiento.disabled = true;
      unidadMovimiento.classList.add('bg-light'); // Mantiene fondo gris
    }
    if (unidadMovimientoInfo) unidadMovimientoInfo.textContent = '';
  }

  function obtenerFactorUnidadSeleccionada() {
    if (!unidadMovimiento || !unidadMovimiento.value) return 1;
    const opt = unidadMovimiento.options[unidadMovimiento.selectedIndex];
    const factor = Number((opt && opt.dataset && opt.dataset.factor) || 1);
    return Number.isFinite(factor) && factor > 0 ? factor : 1;
  }

  async function obtenerUnidadesItem(idItem) {
    if (idItem <= 0) return [];
    if (cacheUnidadesItem.has(idItem)) return cacheUnidadesItem.get(idItem);

    const response = await fetch(`${window.BASE_URL}?ruta=inventario/unidadesItem&id_item=${idItem}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await response.json();
    if (!response.ok || !data || data.ok !== true) {
      throw new Error((data && data.mensaje) || 'No se pudieron cargar unidades.');
    }

    const unidades = Array.isArray(data.items) ? data.items : [];
    cacheUnidadesItem.set(idItem, unidades);
    return unidades;
  }

  async function cargarUnidadesTransferencia() {
    const tipoVal = (tipo && tipo.value) || '';
    const tipoRegistro = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
    const idItem = Number((itemIdInput && itemIdInput.value) || 0);
    const unidadBase = obtenerUnidadBaseItemActual();

    if (tipoRegistro !== 'item' || idItem <= 0 || !tipoVal) {
      limpiarUnidadesTransferencia();
      return;
    }

    if (!unidadMovimiento) return;
    unidadMovimiento.disabled = true;
    unidadMovimiento.classList.add('bg-light');
    unidadMovimiento.innerHTML = '<option value="">Cargando unidades...</option>';

    try {
      const unidades = await obtenerUnidadesItem(idItem);
      unidadMovimiento.innerHTML = `<option value="">${unidadBase}</option>`;
      unidades.forEach((unidad) => {
        const factor = Number(unidad.factor_conversion || 1);
        const option = document.createElement('option');
        option.value = String(unidad.id || '');
        option.textContent = `${unidad.nombre || 'Unidad'} (x${factor})`;
        option.dataset.factor = String(factor > 0 ? factor : 1);
        unidadMovimiento.appendChild(option);
      });
      
      // Si tiene más unidades, habilitar y quitar fondo gris
      if (unidades.length > 0) {
          unidadMovimiento.disabled = false;
          unidadMovimiento.classList.remove('bg-light');
      } else {
          unidadMovimiento.disabled = true;
          unidadMovimiento.classList.add('bg-light');
      }

      if (unidadMovimientoInfo) {
        unidadMovimientoInfo.textContent = unidades.length > 0
          ? `Si elige una unidad, la cantidad se convertirá automáticamente a ${unidadBase}.`
          : `Este ítem no tiene unidades adicionales; se usará ${unidadBase}.`;
      }
    } catch (error) {
      limpiarUnidadesTransferencia();
    }
  }

  // --- FILTROS DE LA TABLA PRINCIPAL ---
  const filtroCategoria = document.getElementById('inventarioFiltroCategoria');
  const filtroTipoItem = document.getElementById('inventarioFiltroTipoItem');
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
  let tomSelectItem = null;
  const almacenesBase = {
    origen: [],
    destino: []
  };

  function esTipoSalida(tipoVal) {
    return ['AJ-', 'CON', 'TRF', 'SALIDA_MERMA_PLANTA'].includes(tipoVal);
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

  async function esperarTomSelect(maxIntentos = 30, esperaMs = 150) {
    for (let i = 0; i < maxIntentos; i += 1) {
      if (typeof window.TomSelect !== 'undefined') return true;
      await new Promise((resolve) => setTimeout(resolve, esperaMs));
    }
    return false;
  }

  // --- INICIALIZACIÓN PRINCIPAL SPA COMPATIBLE ---
  async function initInventarioApp() {
    const tomSelectListo = await esperarTomSelect();
    if (!tomSelectListo) {
      console.warn('TomSelect no se pudo cargar en Inventario. Se mantendrán selects nativos.');
    }

    const tsConfig = {
        create: false,
        sortField: { field: 'text', direction: 'asc' },
        placeholder: 'Buscar...',
        dropdownParent: 'body'
    };

    if (tipo && tomSelectListo && !tomSelectTipo) tomSelectTipo = new TomSelect('#tipoMovimiento', tsConfig);
    if (almacen && tomSelectListo && !tomSelectAlmacen) tomSelectAlmacen = new TomSelect('#almacenMovimiento', tsConfig);
    if (almacenDestino && tomSelectListo && !tomSelectAlmacenDestino) tomSelectAlmacenDestino = new TomSelect('#almacenDestinoMovimiento', tsConfig);
    if (motivo && tomSelectListo && !tomSelectMotivo) {
      tomSelectMotivo = new TomSelect('#motivoMovimiento', { ...tsConfig, placeholder: 'Seleccione motivo...' });
    }
    if (centroCosto && tomSelectListo && !tomSelectCentroCosto) {
      tomSelectCentroCosto = new TomSelect('#centroCostoMovimiento', { ...tsConfig, placeholder: 'Seleccione centro de costos...' });
    }

    if (almacen) {
      almacenesBase.origen = Array.from(almacen.options).map((opt) => ({ value: opt.value, text: opt.textContent }));
    }
    if (almacenDestino) {
      almacenesBase.destino = Array.from(almacenDestino.options).map((opt) => ({ value: opt.value, text: opt.textContent }));
    }
    if (proveedor && tomSelectListo && !tomSelectProveedor) {
        tomSelectProveedor = new TomSelect('#proveedorMovimiento', { ...tsConfig, placeholder: 'Buscar proveedor...' });
    }

    if (selectItem && tomSelectListo && !tomSelectItem) {
        tomSelectItem = new TomSelect('#itemMovimiento', {
            valueField: 'value',
            labelField: 'nombre',
            searchField: ['sku', 'nombre'],
            placeholder: 'Escriba para buscar...',
            dropdownParent: 'body',
            load: function(query, callback) {
                if (query.length < 1) return callback();
                const idAlmacen = Number((almacen && almacen.value) || 0);
                if (idAlmacen <= 0) return callback();
                
                const tipoVal = (tipo && tipo.value) || '';
                const soloConStock = esTipoSalida(tipoVal) ? '1' : '0';
                const controlaStock = tipoVal === 'TRF' ? '&controla_stock=1' : '';

                const tstamp = new Date().getTime();
                fetch(`${window.BASE_URL}?ruta=inventario/buscarItems&q=${encodeURIComponent(query)}&id_almacen=${idAlmacen}&solo_con_stock=${soloConStock}&tipo_movimiento=${encodeURIComponent(tipoVal)}${controlaStock}&_t=${tstamp}`, {
                   headers: { 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' }
                })
                .then(async (response) => {
                    if (!response.ok) throw new Error(`Error HTTP ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (!data || data.ok !== true) throw new Error('Respuesta inválida.');
                    callback(Array.isArray(data.items) ? data.items : []);
                })
                .catch(() => callback());
            },
            render: {
                option: function(item, escape) {
                    const nota = (item.nota || '').trim();
                    const etiquetaTipo = item.tipo_registro === 'pack' ? '<span class="badge bg-primary-subtle text-primary ms-1">Pack</span>' : '<span class="badge bg-secondary-subtle text-secondary ms-1">Ítem</span>';
                    return `<div class="py-1">
                                <span class="fw-bold d-block">${escape(item.nombre_full || item.nombre || '')}${etiquetaTipo}</span>
                                ${nota ? `<span class="text-primary-emphasis small fst-italic">${escape(nota)}</span>` : ''}
                            </div>`;
                },
                item: function(item, escape) {
                    return `<div>${escape(item.nombre_full || item.nombre || '')}</div>`;
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
                cargarUnidadesTransferencia();
            }
        });

        // REGLA: Bloquear buscador de ítems si no hay almacén inicial seleccionado
        if (!almacen || !almacen.value) {
            tomSelectItem.disable();
        }
    }

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
  }

  // Llamamos a la inicialización Inmediatamente (Compatible con SPA fetch)
  initInventarioApp();

  // --- FUNCIONES DE UTILIDAD PARA LA TABLA PRINCIPAL ---
  function normalizarTexto(valor) {
    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  function initTablaStock() {
    if (!tablaStock || typeof window.ERPTable === 'undefined' || !window.ERPTable.createTableManager) return;
    
    stockTableManager = window.ERPTable.createTableManager({
      tableSelector: tablaStock,
      rowsSelector: 'tbody tr[data-search]',
      searchInput: '#inventarioSearch',
      filters: [
        { el: '#inventarioFiltroCategoria', attr: 'data-categoria', match: 'equals' },
        { el: '#inventarioFiltroTipoItem', attr: 'data-tipo-item', match: 'equals' },
        { el: '#inventarioFiltroEstado', attr: 'data-estado', match: 'equals' }
      ],
      rowsPerPage: 25,
      paginationControls: '#inventarioPaginationControls',
      paginationInfo: '#inventarioPaginationInfo',
      normalizeSearchText: normalizarTexto,
      infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} resultados`,
      emptyText: 'Mostrando 0-0 de 0 resultados'
    }).init();

    // PARCHE: Re-inyectar la clase que ERPTable borra al inicializarse
    const trs = tablaStock.querySelectorAll('tbody tr');
    trs.forEach(tr => {
        tr.classList.add('mobile-expandable-row');
    });

    // El acordeón móvil se maneja de forma global en cards_acordeon.js
    // para mantener el mismo comportamiento que Acuerdos Comerciales.
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
    const reqLote = String(itemData.requiere_lote) === '1' || String(itemData.requiere_lote).toLowerCase() === 'true';
    const reqVenc = String(itemData.requiere_vencimiento) === '1' || String(itemData.requiere_vencimiento).toLowerCase() === 'true';

    return {
        id: Number(itemData.id || 0),
        tipoRegistro: itemData.tipo_registro === 'pack' ? 'pack' : 'item',
        requiereLote: reqLote,
        requiereVencimiento: reqVenc
    };
  }

  const motivosPorTipo = {
    'AJ+': [
      { value: 'Conteo físico', text: 'Conteo físico' },
      { value: 'Error anterior', text: 'Error anterior' },
      { value: 'Devolución interna', text: 'Devolución interna' },
      { value: 'Merma recuperada', text: 'Merma recuperada' },
      { value: 'Muestras', text: 'Muestras' },
      { value: 'Otro', text: 'Otro' }
    ],
    'AJ-': [
      { value: 'Conteo físico', text: 'Conteo físico' },
      { value: 'Error anterior', text: 'Error anterior' },
      { value: 'Robo', text: 'Robo' },
      { value: 'Caducado', text: 'Caducado' },
      { value: 'Desperdicio', text: 'Desperdicio' },
      { value: 'Otro', text: 'Otro' }
    ],
    'CON': [
      { value: 'Consumo administrativo', text: 'Consumo administrativo' },
      { value: 'Pruebas laboratorio', text: 'Pruebas laboratorio' },
      { value: 'Producción', text: 'Producción' },
      { value: 'Mantenimiento', text: 'Mantenimiento' },
      { value: 'Otro', text: 'Otro' }
    ],
    'SALIDA_MERMA_PLANTA': [
      { value: 'Merma de producción', text: 'Merma de producción' },
      { value: 'Desperdicio', text: 'Desperdicio' }
    ]
  };

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
    const esSalida = ['AJ-', 'CON', 'TRF', 'SALIDA_MERMA_PLANTA'].includes(tipoVal);
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

    const habilitarCostoManual = ['INI', 'AJ+'].includes(tipoVal);
    if (grupoCostoUnitario) grupoCostoUnitario.classList.toggle('d-none', !habilitarCostoManual);
    if (costoUnitarioInput) {
      costoUnitarioInput.readOnly = !habilitarCostoManual;
      costoUnitarioInput.classList.toggle('bg-light', !habilitarCostoManual);
      costoUnitarioInput.classList.toggle('text-muted', !habilitarCostoManual);
      if (!habilitarCostoManual) costoUnitarioInput.value = '0.0000';
    }

    if (grupoProveedor) grupoProveedor.classList.add('d-none');
    
    const requiereMotivo = ['AJ+', 'AJ-', 'CON', 'SALIDA_MERMA_PLANTA'].includes(tipoVal);
    if (grupoMotivo) grupoMotivo.classList.toggle('d-none', !requiereMotivo);
    if (motivo) {
        motivo.required = requiereMotivo;
        if (requiereMotivo && tomSelectMotivo) {
            const opciones = motivosPorTipo[tipoVal] || [];
            const valorPrevio = tomSelectMotivo.getValue();
            tomSelectMotivo.clearOptions();
            opciones.forEach(opt => tomSelectMotivo.addOption(opt));
            if (opciones.some(o => o.value === valorPrevio)) {
                tomSelectMotivo.setValue(valorPrevio);
            } else {
                tomSelectMotivo.clear(true);
            }
        } else if (!requiereMotivo && tomSelectMotivo) {
             tomSelectMotivo.clear(true);
        }
    }

    const requiereCentroCosto = ['CON', 'AJ-', 'SALIDA_MERMA_PLANTA'].includes(tipoVal);
    if (grupoCentroCosto) {
        grupoCentroCosto.classList.toggle('d-none', !requiereCentroCosto);
    }
    if (centroCosto) {
        centroCosto.required = requiereCentroCosto;
        if (!requiereCentroCosto && tomSelectCentroCosto) {
            tomSelectCentroCosto.clear(true);
        }
    }

    const tieneItemSeleccionado = itemData && itemData.id > 0;
    if (cantidadInput) {
        cantidadInput.disabled = !tieneItemSeleccionado;
        if (!tieneItemSeleccionado) {
            cantidadInput.classList.add('bg-light');
            cantidadInput.value = '';
        } else {
            cantidadInput.classList.remove('bg-light');
        }
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
    if (!itemIdInput || !stockActualItemLabel || !almacen) return;
    const idItem = Number(itemIdInput.value || '0');
    const idPack = Number((packIdInput && packIdInput.value) || '0');
    const tipoRegistro = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
    const idConsulta = tipoRegistro === 'pack' ? idPack : idItem;
    const idAlmacen = Number(almacen.value || '0');

    if (idConsulta <= 0 || idAlmacen <= 0) {
      stockActualItemLabel.value = '0.0000';
      return;
    }

    stockActualItemLabel.value = 'Consultando...';

    try {
      const stock = await obtenerStockActual(idConsulta, idAlmacen, tipoRegistro);
      stockActualItemLabel.value = Number(stock || 0).toFixed(4);
    } catch (error) {
      stockActualItemLabel.value = '0.0000';
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
            cargarUnidadesTransferencia();
        });
    }

    if (almacen) {
        almacen.addEventListener('change', () => {
            if (tomSelectItem) {
              if (almacen.value) {
                  tomSelectItem.enable();
              } else {
                  tomSelectItem.disable();
              }
            }
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
          window.Swal.fire({ icon: 'warning', title: 'Destino inválido', text: 'El almacén destino no puede ser igual al origen.' });
          if (tomSelectAlmacenDestino) tomSelectAlmacenDestino.clear();
        }
      });
    }

    if (unidadMovimiento) {
      unidadMovimiento.addEventListener('change', () => {
        const factor = obtenerFactorUnidadSeleccionada();
        const unidadBase = obtenerUnidadBaseItemActual();
        if (unidadMovimientoInfo) {
          unidadMovimientoInfo.textContent = factor > 1
            ? `La cantidad se convertirá a ${unidadBase} con factor x${factor}.`
            : `Se registrará en ${unidadBase}.`;
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
      if (cantidadInput) {
          cantidadInput.value = '';
          cantidadInput.disabled = true;
          cantidadInput.classList.add('bg-light');
      }
      if (inputLoteNuevo) inputLoteNuevo.value = '';
      if (selectLoteExistente) selectLoteExistente.value = '';
      if (inputVencimiento) inputVencimiento.value = '';
      if (costoUnitarioInput) costoUnitarioInput.value = '0';
      if (stockHint) stockHint.textContent = '';
      if (stockActualItemLabel) stockActualItemLabel.value = '0.0000';
      limpiarUnidadesTransferencia();
      actualizarUIModal();
    }

    function renderLineasMovimiento() {
      if (!movimientosDetalleBody) return;

      if (lineasMovimiento.length === 0) {
        movimientosDetalleBody.innerHTML = '<tr data-empty="1"><td colspan="7" class="text-center text-muted py-3">Aún no hay ítems agregados.</td></tr>';
        if (window.ERPTable && typeof window.ERPTable.applyResponsiveCards === 'function') {
          window.ERPTable.applyResponsiveCards(document);
        }
        return;
      }

      movimientosDetalleBody.innerHTML = '';
      lineasMovimiento.forEach((linea, idx) => {
        const tr = document.createElement('tr');
        
        const botonEliminarHTML = window.IconosAccion ? 
            window.IconosAccion.crear('eliminar', idx, '', `data-remove-index="${idx}"`) : 
            `<button type="button" class="btn btn-sm btn-outline-danger" data-remove-index="${idx}"><i class="bi bi-trash"></i></button>`;

        tr.innerHTML = `
          <td>${linea.etiqueta}</td>
          <td class="text-end">${Number(linea.cantidad_mostrada || linea.cantidad || 0).toFixed(4)}</td>
          <td>${linea.unidad_nombre || 'Base'}</td>
          <td>${linea.lote || '-'}</td>
          <td>${linea.fecha_vencimiento || '-'}</td>
          <td class="text-end">${Number(linea.costo_unitario || 0).toFixed(4)}</td>
          <td class="text-center">
            ${botonEliminarHTML}
          </td>
        `;
        movimientosDetalleBody.appendChild(tr);
      });

      if (window.ERPTable && typeof window.ERPTable.applyResponsiveCards === 'function') {
        window.ERPTable.applyResponsiveCards(document);
      }
    }

    function construirClaveLinea(tipoRegistroVal, idRegistroVal, loteVal, idUnidadVal = 0) {
      return [tipoRegistroVal, idRegistroVal, (loteVal || '').trim().toLowerCase(), Number(idUnidadVal || 0)].join('|');
    }

    async function agregarLineaMovimiento() {
      const idItemVal = Number((itemIdInput && itemIdInput.value) || '0');
      const idPackVal = Number((packIdInput && packIdInput.value) || '0');
      const tipoRegistroVal = (tipoRegistroInput && tipoRegistroInput.value === 'pack') ? 'pack' : 'item';
      const idRegistroVal = tipoRegistroVal === 'pack' ? idPackVal : idItemVal;
      const cantidadVal = Number((cantidadInput && cantidadInput.value) || 0);
      const tipoVal = (tipo && tipo.value) || '';
      const aplicaUnidadTransferencia = tipoRegistroVal === 'item'; 
      const factorUnidad = aplicaUnidadTransferencia ? obtenerFactorUnidadSeleccionada() : 1;
      const idItemUnidadVal = aplicaUnidadTransferencia && unidadMovimiento && unidadMovimiento.value ? Number(unidadMovimiento.value) : 0;
      const unidadBase = obtenerUnidadBaseItemActual();
      const unidadNombreVal = aplicaUnidadTransferencia && unidadMovimiento && unidadMovimiento.value
        ? (unidadMovimiento.options[unidadMovimiento.selectedIndex]?.textContent || 'Unidad')
        : unidadBase;
      const cantidadBaseVal = cantidadVal * factorUnidad;

      if (!tipoVal) {
        window.Swal.fire({ icon: 'warning', title: 'Tipo requerido', text: 'Seleccione el tipo de movimiento antes de agregar líneas.' });
        return;
      }

      if (idRegistroVal <= 0) {
        window.Swal.fire({ icon: 'warning', title: 'Ítem inválido', text: 'Selecciona un ítem válido.' });
        return;
      }

      const idAlmacenVal = Number((almacen && almacen.value) || '0');
      if (idAlmacenVal <= 0) {
        window.Swal.fire({ icon: 'warning', title: 'Almacén requerido', text: 'Seleccione el almacén origen antes de agregar ítems.' });
        return;
      }

      if (tipoVal === 'INI') {
        if (cantidadVal < 0) {
          window.Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Para INI la cantidad debe ser mayor o igual a 0.' });
          return;
        }
      } else if (cantidadVal <= 0) {
        window.Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'La cantidad debe ser mayor a 0.' });
        return;
      }

      if (lineasMovimiento.length >= 100) {
        window.Swal.fire({ icon: 'warning', title: 'Límite alcanzado', text: 'Solo se permiten hasta 100 líneas por operación.' });
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
        window.Swal.fire({ icon: 'warning', title: 'Falta Lote', text: 'Este producto requiere un número de lote.' });
        return;
      }

      const loteVal = (loteFinalEnviar.value || '').trim();
      const claveLinea = construirClaveLinea(tipoRegistroVal, idRegistroVal, loteVal, idItemUnidadVal);
      const existeDuplicado = lineasMovimiento.some((linea) => {
        const idRegistroLinea = linea.tipo_registro === 'pack' ? Number(linea.id_pack || 0) : Number(linea.id_item || 0);
        return construirClaveLinea(linea.tipo_registro, idRegistroLinea, linea.lote, linea.id_item_unidad) === claveLinea;
      });

      if (existeDuplicado) {
        window.Swal.fire({
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
            return construirClaveLinea(linea.tipo_registro, idRegistroLinea, linea.lote, linea.id_item_unidad) === claveLinea;
          })
          .reduce((total, linea) => total + Number(linea.cantidad || 0), 0);

        const idConsulta = tipoRegistroVal === 'pack' ? idPackVal : idItemVal;
        const stockDisponible = await obtenerStockActual(idConsulta, idAlmacenVal, tipoRegistroVal);
        const cantidadSolicitada = cantidadAcumulada + cantidadBaseVal;

        if (cantidadSolicitada > stockDisponible) {
          window.Swal.fire({
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
        cantidad: cantidadBaseVal,
        cantidad_mostrada: cantidadVal,
        id_item_unidad: idItemUnidadVal > 0 ? idItemUnidadVal : null,
        unidad_nombre: unidadNombreVal,
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
        modalEl.addEventListener('show.bs.modal', () => {
            actualizarUIModal();
            actualizarBloqueoCabecera();
            limpiarUnidadesTransferencia(); 
            if (stockHint) stockHint.textContent = '';
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            form.reset();
            if (tomSelectTipo) tomSelectTipo.clear();
            if (tomSelectAlmacen) tomSelectAlmacen.clear();
            if (tomSelectAlmacenDestino) tomSelectAlmacenDestino.clear();
            if (tomSelectProveedor) tomSelectProveedor.clear();
            if (tomSelectMotivo) tomSelectMotivo.clear();
            if (tomSelectCentroCosto) tomSelectCentroCosto.clear(); 
            if (tomSelectItem) {
                tomSelectItem.clearOptions();
                tomSelectItem.clear();
                tomSelectItem.disable(); 
            }
            
            if (itemIdInput) itemIdInput.value = '0';
            if (packIdInput) packIdInput.value = '0';
            if (tipoRegistroInput) tipoRegistroInput.value = 'item';
            if (stockHint) stockHint.textContent = '';
            if (stockActualItemLabel) stockActualItemLabel.value = '0.0000';
            limpiarUnidadesTransferencia();
            actualizarBloqueoCabecera();
            
            if(grupoLoteInput) grupoLoteInput.classList.add('d-none');
            if(grupoLoteSelect) grupoLoteSelect.classList.add('d-none');
            if(grupoDestino) grupoDestino.classList.add('d-none');
            if(grupoMotivo) grupoMotivo.classList.add('d-none');
            if(grupoCentroCosto) grupoCentroCosto.classList.add('d-none'); 
            if(grupoVencimiento) grupoVencimiento.classList.add('d-none');
            if(costoUnitarioInput) {
              costoUnitarioInput.readOnly = false;
              costoUnitarioInput.classList.remove('bg-light', 'text-muted');
            }
            if(cantidadInput) {
                cantidadInput.disabled = true;
                cantidadInput.classList.add('bg-light');
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
      const idCentroCostoVal = Number((centroCosto && centroCosto.value) || '0');

      if (!tipoVal) {
        window.Swal.fire({ icon: 'warning', title: 'Tipo requerido', text: 'Seleccione el tipo de movimiento.' });
        return;
      }

      if (idAlmacenVal <= 0) {
        window.Swal.fire({ icon: 'warning', title: 'Almacén requerido', text: 'Seleccione el almacén origen/destino principal.' });
        return;
      }

      if (tipoVal === 'TRF' && idAlmacenDestinoVal <= 0) {
        window.Swal.fire({ icon: 'warning', title: 'Destino requerido', text: 'Seleccione el almacén destino para transferencias.' });
        return;
      }

      if (tipoVal === 'TRF' && almacen && almacenDestino && String(almacen.value) === String(almacenDestino.value)) {
        window.Swal.fire({ icon: 'warning', title: 'Almacenes inválidos', text: 'El almacén origen y destino deben ser diferentes.' });
        return;
      }

      if (['AJ+', 'AJ-', 'CON', 'SALIDA_MERMA_PLANTA'].includes(tipoVal) && motivoVal === '') {
        window.Swal.fire({ icon: 'warning', title: 'Motivo requerido', text: 'Seleccione el motivo del movimiento.' });
        return;
      }

      if (['CON', 'AJ-', 'SALIDA_MERMA_PLANTA'].includes(tipoVal) && idCentroCostoVal <= 0) {
          window.Swal.fire({ icon: 'warning', title: 'Centro de Costos', text: 'Debe asignar a qué Centro de Costos irá esta salida o pérdida.' });
          return;
      }

      if (lineasMovimiento.length === 0) {
        window.Swal.fire({ icon: 'warning', title: 'Sin líneas', text: 'Agrega al menos un ítem a la operación antes de guardar.' });
        return;
      }

      const confirmacion = await window.Swal.fire({
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
              motivo: motivoVal,
              id_centro_costo: idCentroCostoVal 
            },
            items: lineasMovimiento.map((linea) => ({
              tipo_registro: linea.tipo_registro,
              id_item: linea.id_item,
              id_pack: linea.id_pack,
              id_item_unidad: linea.id_item_unidad,
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

        await window.Swal.fire({
          icon: 'success',
          title: 'Operación registrada',
          text: `Se registraron ${Array.isArray(result.ids) ? result.ids.length : 0} movimientos.`,
        });

        const modalInstance = window.bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        
        // Dado que usas SPA, mejor recargar suave
        if (typeof window.navigateWithoutReload === 'function') {
          window.navigateWithoutReload(new URL(window.location.href), false);
        } else {
          window.location.reload();
        }

      } catch (error) {
        window.Swal.fire({ icon: 'error', title: 'Error', text: error.message });
      }
    });

    const formRetorno = document.getElementById('formRetornoPlanta');
    if (formRetorno) {
      const inputTeorico = document.getElementById('retornoStockTeorico');
      const inputDevuelto = document.getElementById('retornoPesoDevuelto');
      const badgeMerma = document.getElementById('retornoMermaBadge');
      const modalRetornoEl = document.getElementById('modalRetornoPlanta');

      const pintarMerma = () => {
        const teorico = Number((inputTeorico && inputTeorico.value) || 0);
        const devuelto = Number((inputDevuelto && inputDevuelto.value) || 0);
        const merma = Math.max(0, teorico - devuelto);
        if (!badgeMerma) return;
        badgeMerma.textContent = `Merma calculada automáticamente: ${merma.toFixed(4)}`;
        badgeMerma.className = `alert py-2 mb-0 small ${merma > 0 ? 'alert-danger' : 'alert-success'}`;
      };

      if (inputTeorico) inputTeorico.addEventListener('input', pintarMerma);
      if (inputDevuelto) inputDevuelto.addEventListener('input', pintarMerma);

      formRetorno.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fd = new FormData(formRetorno);

        try {
          const resp = await fetch(`${window.BASE_URL}?ruta=almacenes/devolverSobrantesPlanta`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
          });
          const json = await resp.json();
          if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.mensaje) || 'No se pudo registrar el retorno de planta.');
          }

          await window.Swal.fire({
            icon: 'success',
            title: 'Retorno registrado',
            text: `Devolución: ${Number(json?.data?.cantidad_devolucion || 0).toFixed(4)} | Merma: ${Number(json?.data?.cantidad_merma || 0).toFixed(4)}`
          });

          const inst = modalRetornoEl && window.bootstrap.Modal.getInstance(modalRetornoEl);
          if (inst) inst.hide();
          if (typeof window.navigateWithoutReload === 'function') {
            window.navigateWithoutReload(new URL(window.location.href), false);
          } else {
            window.location.reload();
          }
        } catch (error) {
          window.Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Error de conexión.' });
        }
      });

      if (modalRetornoEl) {
        modalRetornoEl.addEventListener('hidden.bs.modal', () => {
          formRetorno.reset();
          pintarMerma();
        });
      }

      pintarMerma();
    }
  }

})();
