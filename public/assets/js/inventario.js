(function () {
  'use strict';

  // --- REFERENCIAS AL DOM ---
  const form = document.getElementById('formMovimientoInventario');
  const modalEl = document.getElementById('modalMovimientoInventario');
  
  // Selects principales
  const tipo = document.getElementById('tipoMovimiento');
  const almacen = document.getElementById('almacenMovimiento');
  
  // Destino (Solo TRF)
  const grupoDestino = document.getElementById('grupoAlmacenDestino');
  const almacenDestino = document.getElementById('almacenDestinoMovimiento');
  
  // Ítem
  const itemInput = document.getElementById('itemMovimiento');
  const itemIdInput = document.getElementById('idItemMovimiento');
  const itemList = document.getElementById('listaItemsInventario'); // Datalist
  const sugerenciasItems = document.getElementById('sugerenciasItemsInventario'); // Div sugerencias
  
  // Lotes (NUEVA LÓGICA)
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

  // Filtros de la tabla principal
  const searchInput = document.getElementById('inventarioSearch');
  const filtroEstado = document.getElementById('inventarioFiltroEstado');
  const filtroCriticidad = document.getElementById('inventarioFiltroCriticidad');
  const filtroAlmacen = document.getElementById('inventarioFiltroAlmacen');
  const filtroVencimiento = document.getElementById('inventarioFiltroVencimiento');
  const tablaStock = document.getElementById('tablaInventarioStock');

  // --- FUNCIONES DE UTILIDAD PARA LA TABLA PRINCIPAL ---

  async function toggleEstadoItemInventario(switchInput) {
    const id = Number(switchInput.dataset.id || '0');
    if (id <= 0) return;

    const estado = switchInput.checked ? 1 : 0;
    const data = new FormData();
    data.append('accion', 'toggle_estado_item');
    data.append('id', String(id));
    data.append('estado', String(estado));

    try {
      const response = await fetch(`${window.BASE_URL}?ruta=items`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: data
      });
      const result = await response.json();
      if (!response.ok || !result || !result.ok) {
        throw new Error((result && result.mensaje) || 'No se pudo actualizar el estado.');
      }
    } catch (error) {
      switchInput.checked = !switchInput.checked;
      Swal.fire({ icon: 'error', title: 'Error', text: error.message });
    }
  }

  async function eliminarItemInventario(button) {
    const id = Number(button.dataset.id || '0');
    const nombreItem = (button.dataset.item || 'este registro').trim();
    if (id <= 0) {
      await Swal.fire({ icon: 'error', title: 'Error', text: 'No se encontró el ítem a eliminar.' });
      return;
    }

    const confirmacion = await Swal.fire({
      icon: 'warning',
      title: '¿Estás seguro?',
      text: `Se eliminará ${nombreItem}. Esta acción no se puede deshacer.`,
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc3545'
    });

    if (!confirmacion.isConfirmed) return;

    const payload = new URLSearchParams({
      accion: 'eliminar',
      id: String(id)
    });

    try {
      const response = await fetch(`${window.BASE_URL}?ruta=items`, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: payload.toString()
      });

      const data = await response.json();
      if (!response.ok || !data || !data.ok) {
        throw new Error((data && data.mensaje) || 'No se pudo eliminar el ítem.');
      }

      await Swal.fire({
        icon: 'success',
        title: '¡Eliminado!',
        text: data.mensaje || 'Registro eliminado correctamente.'
      });

      const fila = button.closest('tr');
      if (fila) {
        fila.remove();
        filtrarStock();
        return;
      }

      window.location.reload();
    } catch (error) {
      await Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'No se pudo eliminar el ítem.' });
    }
  }

  function normalizarTexto(valor) {
    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function filtrarStock() {
    if (!tablaStock) return;

    const termino = normalizarTexto(searchInput ? searchInput.value : '');
    const estado = (filtroEstado ? filtroEstado.value : '').trim();
    const criticidad = (filtroCriticidad ? filtroCriticidad.value : '').trim();
    const vencimiento = (filtroVencimiento ? filtroVencimiento.value : '').trim();

    const filas = Array.from(tablaStock.querySelectorAll('tbody tr'));
    filas.forEach((fila) => {
      if (!fila.dataset.search) return;
      const okTexto = termino === '' || normalizarTexto(fila.dataset.search).includes(termino);
      const okEstado = estado === '' || (fila.dataset.estado || '') === estado;
      const okCrit = criticidad === '' || (fila.dataset.criticidad || '') === criticidad;
      const okVenc = vencimiento === '' || (fila.dataset.vencimiento || '') === vencimiento;
      fila.classList.toggle('d-none', !(okTexto && okEstado && okCrit && okVenc));
    });
  }


  function aplicarFiltroAlmacenServidor() {
    if (!filtroAlmacen) return;

    const url = new URL(window.location.href);
    const valor = (filtroAlmacen.value || '').trim();

    if (valor === '') {
      url.searchParams.delete('id_almacen');
    } else {
      url.searchParams.set('id_almacen', valor);
    }

    window.location.href = url.toString();
  }

  // --- LÓGICA DEL FORMULARIO DE MOVIMIENTOS ---

  // 1. Obtener datos del ítem seleccionado en el input/datalist
  function obtenerDataDelItem() {
    if (!itemInput || !itemList) return null;
    const valor = (itemInput.value || '').trim();
    // Buscar en las opciones del datalist
    const opcion = Array.from(itemList.options || []).find((option) => option.value === valor);
    
    if (opcion) {
      return {
        id: opcion.dataset.id,
        requiereLote: opcion.dataset.requiereLote == '1',
        requiereVencimiento: opcion.dataset.requiereVencimiento == '1'
      };
    }
    return null;
  }

  // 2. Control Central de la UI del Modal
  function actualizarUIModal() {
    if (!tipo) return;
    
    const tipoVal = tipo.value;
    const itemData = obtenerDataDelItem();
    
    // A. Visibilidad Transferencia
    const esTransferencia = tipoVal === 'TRF';
    if(grupoDestino) grupoDestino.classList.toggle('d-none', !esTransferencia);
    if(almacenDestino) {
        almacenDestino.required = esTransferencia;
        if(!esTransferencia) almacenDestino.value = '';
    }

    // B. Visibilidad Lotes y Vencimiento
    const esEntrada = ['INI', 'AJ+'].includes(tipoVal);
    const esSalida = ['AJ-', 'CON', 'TRF'].includes(tipoVal);
    const requiereLote = itemData ? itemData.requiereLote : false;
    const requiereVenc = itemData ? itemData.requiereVencimiento : false;

    // Resetear visibilidad lotes
    if(grupoLoteInput) grupoLoteInput.classList.add('d-none');
    if(grupoLoteSelect) grupoLoteSelect.classList.add('d-none');
    
    if (requiereLote) {
        if (esEntrada) {
            // ENTRADA: Muestro Input Texto para crear lote nuevo
            if(grupoLoteInput) grupoLoteInput.classList.remove('d-none');
        } else if (esSalida) {
            // SALIDA: Muestro Select para elegir existente
            if(grupoLoteSelect) grupoLoteSelect.classList.remove('d-none');
            // IMPORTANTE: Disparar carga de lotes si ya tenemos ítem y almacén
            cargarLotesDisponibles();
        }
    }

    // C. Visibilidad Fecha Vencimiento (Solo tiene sentido en Entradas)
    if(grupoVencimiento) {
        const mostrarVenc = (esEntrada && (requiereVenc || (requiereLote && esEntrada))); 
        // Nota: A veces se pide vencimiento al crear un lote aunque el item no lo exija estrictamente, 
        // pero respetaremos la config del item.
        const visible = esEntrada && requiereVenc;
        
        grupoVencimiento.classList.toggle('d-none', !visible);
        if(inputVencimiento) inputVencimiento.required = visible;
    }
  }

  // 3. Cargar Lotes vía AJAX (NUEVO)
  let timerLotes = null;
  async function cargarLotesDisponibles() {
    // Solo cargar si es una salida y el select es visible
    if (grupoLoteSelect.classList.contains('d-none')) return;

    const idItem = itemIdInput.value;
    const idAlmacen = almacen.value;

    // Limpiar select
    selectLoteExistente.innerHTML = '<option value="">Seleccione lote...</option>';
    msgSinLotes.classList.add('d-none');

    if (!idItem || !idAlmacen || idItem <= 0 || idAlmacen <= 0) return;

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

  // 4. Actualizar Hint de Stock General
  async function actualizarStockHint() {
    if (!stockHint || !itemIdInput || !almacen) return;
    
    // Solo mostrar hint en salidas
    const esSalida = ['AJ-', 'CON', 'TRF'].includes((tipo && tipo.value) || '');
    if (!esSalida) {
      stockHint.textContent = '';
      return;
    }

    const idItem = Number(itemIdInput.value || '0');
    const idAlmacen = Number(almacen.value || '0');
    if (idItem <= 0 || idAlmacen <= 0) {
      stockHint.textContent = '';
      return;
    }

    try {
      const url = `${window.BASE_URL}?ruta=inventario/stockItem&id_item=${idItem}&id_almacen=${idAlmacen}`;
      const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json();
      const stock = Number((data && data.stock) || 0);
      stockHint.textContent = `Stock total en almacén: ${stock.toFixed(4)}`;
      stockHint.className = stock <= 0 ? 'form-text text-danger fw-bold' : 'form-text text-success';
    } catch (error) {
      stockHint.textContent = '';
    }
  }

  // 5. Buscador de ítems remoto
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
            // Rellenar Input
            itemInput.value = texto;
            itemIdInput.value = String(item.id || '');
            
            // Verificar si existe en datalist, si no, agregarlo para que funcione obtenerDataDelItem()
            let option = Array.from(itemList.options).find(o => o.value === texto);
            if (!option) {
                option = document.createElement('option');
                option.value = texto;
                itemList.appendChild(option);
            }
            // Actualizar dataset vital para la lógica
            option.dataset.id = item.id;
            option.dataset.requiereLote = item.requiere_lote;
            option.dataset.requiereVencimiento = item.requiere_vencimiento;

            sugerenciasItems.classList.add('d-none');
            
            // Disparar actualizaciones
            actualizarUIModal();
            actualizarStockHint();
        });
        sugerenciasItems.appendChild(btn);
      });

      sugerenciasItems.classList.toggle('d-none', items.length === 0);
    } catch (error) {
      sugerenciasItems.classList.add('d-none');
    }
  }

  // --- LISTENERS ---

  // Listeners Tabla Principal
  document.querySelectorAll('.switch-estado-item-inventario').forEach((switchInput) => {
    switchInput.addEventListener('change', () => toggleEstadoItemInventario(switchInput));
  });

  document.querySelectorAll('.btn-eliminar-item-inventario').forEach((button) => {
    button.addEventListener('click', () => eliminarItemInventario(button));
  });

  if (searchInput) searchInput.addEventListener('input', filtrarStock);
  if (filtroEstado) filtroEstado.addEventListener('change', filtrarStock);
  if (filtroCriticidad) filtroCriticidad.addEventListener('change', filtrarStock);
  if (filtroAlmacen) filtroAlmacen.addEventListener('change', aplicarFiltroAlmacenServidor);
  if (filtroVencimiento) filtroVencimiento.addEventListener('change', filtrarStock);
  filtrarStock();

  // Listeners Modal Movimiento
  if (form) {
    // Cambio en tipo de movimiento
    if (tipo) {
        tipo.addEventListener('change', () => {
            actualizarUIModal();
            actualizarStockHint();
        });
    }

    // Cambio en almacén
    if (almacen) {
        almacen.addEventListener('change', () => {
            // Si es salida, al cambiar almacén cambian los lotes disponibles
            if (!grupoLoteSelect.classList.contains('d-none')) {
                cargarLotesDisponibles();
            }
            actualizarStockHint();
        });
    }

    // Input Item
    if (itemInput) {
        itemInput.addEventListener('input', function () {
            // Si el usuario borra, limpiar ID
            const val = this.value;
            const itemData = obtenerDataDelItem();
            if(!itemData && val === '') itemIdInput.value = '';
            
            actualizarUIModal();
            
            clearTimeout(timerBusqueda);
            timerBusqueda = setTimeout(buscarItemsRemoto, 300);
        });
        
        // Al perder foco o seleccionar del datalist nativo
        itemInput.addEventListener('change', function() {
             const itemData = obtenerDataDelItem();
             if(itemData) itemIdInput.value = itemData.id;
             actualizarUIModal();
             actualizarStockHint();
        });
    }

    // Limpieza al cerrar modal
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => {
            form.reset();
            if (itemIdInput) itemIdInput.value = '';
            if (stockHint) stockHint.textContent = '';
            if (sugerenciasItems) {
              sugerenciasItems.innerHTML = '';
              sugerenciasItems.classList.add('d-none');
            }
            // Resetear visibilidades por defecto
            if(grupoLoteInput) grupoLoteInput.classList.add('d-none');
            if(grupoLoteSelect) grupoLoteSelect.classList.add('d-none');
            if(grupoDestino) grupoDestino.classList.add('d-none');
            if(grupoVencimiento) grupoVencimiento.classList.add('d-none');
        });
    }

    // SUBMIT DEL FORMULARIO
    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      if (!itemIdInput.value || itemIdInput.value <= 0) {
        Swal.fire({ icon: 'warning', title: 'Ítem inválido', text: 'Selecciona un ítem de la lista.' });
        return;
      }

      // PREPARAR LOTE FINAL
      // Copiar valor del input o del select al hidden según corresponda
      if (!grupoLoteInput.classList.contains('d-none')) {
          loteFinalEnviar.value = inputLoteNuevo.value; // Entrada: Nuevo lote
      } else if (!grupoLoteSelect.classList.contains('d-none')) {
          loteFinalEnviar.value = selectLoteExistente.value; // Salida: Lote existente
      } else {
          loteFinalEnviar.value = ''; // No requiere lote
      }

      // Validar Lote si es requerido
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
        
        // Cerrar modal y recargar
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if(modalInstance) modalInstance.hide();
        window.location.reload();

      } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
      }
    });
  }

})();
