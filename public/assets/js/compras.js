document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicialización y Referencias
    const app = document.getElementById('comprasApp');
    if (!app) return;

    const urls = {
        index: app.dataset.urlIndex,
        guardar: app.dataset.urlGuardar,
        aprobar: app.dataset.urlAprobar,
        anular: app.dataset.urlAnular,
        recepcionar: app.dataset.urlRecepcionar,
    };

    // --- AQUI ESTÁ LA MAGIA (PASO 3: Tom Select) ---
    // Esto convierte el select normal en uno con buscador
    let tomSelectProveedor = null;

    if (document.getElementById('idProveedor')) {
        tomSelectProveedor = new TomSelect("#idProveedor", {
            create: false, // No permite crear nombres nuevos, solo seleccionar existentes
            sortField: { field: "text", direction: "asc" },
            placeholder: "Escribe para buscar proveedor...",
            dropdownParent: 'body' // Evita que el menú quede oculto dentro del modal
        });
    }
    // -----------------------------------------------

    // Modales
    const modalOrdenEl = document.getElementById('modalOrdenCompra');
    const modalOrden = new bootstrap.Modal(modalOrdenEl);
    const modalRecepcionEl = document.getElementById('modalRecepcionCompra');
    const modalRecepcion = new bootstrap.Modal(modalRecepcionEl);

    // Elementos del DOM (Filtros y Tabla)
    const tablaCompras = document.getElementById('tablaCompras');
    const tbodyTabla = tablaCompras.querySelector('tbody');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    // Elementos del Formulario Orden
    const formOrden = document.getElementById('formOrdenCompra');
    const ordenId = document.getElementById('ordenId');
    const idProveedor = document.getElementById('idProveedor'); // El select original (ahora oculto por Tom Select)
    const fechaEntrega = document.getElementById('fechaEntrega');
    const observaciones = document.getElementById('observaciones');
    const tbodyDetalle = document.querySelector('#tablaDetalleCompra tbody');
    const ordenTotal = document.getElementById('ordenTotal');
    const templateFila = document.getElementById('templateFilaDetalle');
    const btnGuardarOrden = document.getElementById('btnGuardarOrden');

    // Elementos del Formulario Recepción
    const recepcionOrdenId = document.getElementById('recepcionOrdenId');
    const recepcionAlmacen = document.getElementById('recepcionAlmacen');
    const btnConfirmarRecepcion = document.getElementById('btnConfirmarRecepcion');

    // 2. Funciones de Cálculo y Detalle
    function filaToPayload(fila) {
        return {
            id_item: Number(fila.querySelector('.detalle-item').value || 0),
            cantidad: parseFloat(fila.querySelector('.detalle-cantidad').value || 0),
            costo_unitario: parseFloat(fila.querySelector('.detalle-costo').value || 0),
        };
    }

    function recalcularFila(fila) {
        const { cantidad, costo_unitario } = filaToPayload(fila);
        const subtotal = cantidad * costo_unitario;
        fila.querySelector('.detalle-subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
        recalcularTotalGeneral();
    }

    function recalcularTotalGeneral() {
        let total = 0;
        tbodyDetalle.querySelectorAll('tr').forEach((fila) => {
            const item = filaToPayload(fila);
            total += item.cantidad * item.costo_unitario;
        });
        ordenTotal.textContent = `S/ ${total.toFixed(2)}`;
    }

    function agregarFila(item = null) {
        const clone = templateFila.content.cloneNode(true);
        const fila = clone.querySelector('tr');

        // Referencias a inputs dentro de la fila
        const inputItem = fila.querySelector('.detalle-item');
        const inputCantidad = fila.querySelector('.detalle-cantidad');
        const inputCosto = fila.querySelector('.detalle-costo');
        const btnQuitar = fila.querySelector('.btn-quitar-fila');

        // Si estamos editando, llenar valores
        if (item) {
            inputItem.value = item.id_item;
            inputCantidad.value = item.cantidad; // Viene del backend
            inputCosto.value = item.costo_unitario; // Viene del backend
        }

        // Event Listeners para recálculos en vivo
        [inputCantidad, inputCosto].forEach(input => {
            input.addEventListener('input', () => recalcularFila(fila));
        });
        
        // Cambio de ítem (opcional: podrías cargar el costo predeterminado aquí si tuvieras esa data)
        inputItem.addEventListener('change', () => {
             // Lógica futura: obtener precio del item seleccionado
        });

        btnQuitar.addEventListener('click', () => {
            fila.remove();
            recalcularTotalGeneral();
        });

        tbodyDetalle.appendChild(fila);
        recalcularFila(fila); // Calcular subtotal inicial
    }

    function limpiarModalOrden() {
        formOrden.reset();
        ordenId.value = 0;
        
        // --- LIMPIEZA DEL BUSCADOR ---
        if (tomSelectProveedor) {
            tomSelectProveedor.clear(); // Borra la selección visualmente
        } else {
            idProveedor.value = '';
        }
        // -----------------------------

        tbodyDetalle.innerHTML = '';
        ordenTotal.textContent = 'S/ 0.00';
        agregarFila(); // Agregar una fila vacía por defecto
    }

    // 3. Helpers de Red (AJAX)
    async function postJson(url, data, btnElement = null) {
        let originalText = '';
        if (btnElement) {
            originalText = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data),
            });
            
            const payload = await response.json();
            
            if (!response.ok || !payload.ok) {
                throw new Error(payload.mensaje || 'Error desconocido en el servidor.');
            }
            return payload;
        } finally {
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalText;
            }
        }
    }

    function recargarPagina() {
        window.location.href = `${urls.index}&${new URLSearchParams({
            q: filtroBusqueda.value,
            estado: filtroEstado.value,
            fecha_desde: filtroFechaDesde.value,
            fecha_hasta: filtroFechaHasta.value
        }).toString()}`;
    }

    // 4. Lógica de Botones Principales (Guardar / Recepcionar)

    // Guardar (Crear/Editar)
    btnGuardarOrden.addEventListener('click', async () => {
        // Validaciones Frontend
        if (!idProveedor.value) return Swal.fire('Error', 'Debe seleccionar un proveedor.', 'warning');
        
        const detalle = [];
        let errorDetalle = false;

        tbodyDetalle.querySelectorAll('tr').forEach(fila => {
            const datos = filaToPayload(fila);
            if (datos.id_item <= 0 || datos.cantidad <= 0) errorDetalle = true;
            detalle.push(datos);
        });

        if (detalle.length === 0) return Swal.fire('Error', 'Agregue al menos un ítem.', 'warning');
        if (errorDetalle) return Swal.fire('Error', 'Verifique que todos los ítems tengan producto y cantidad mayor a 0.', 'warning');

        try {
            const payload = {
                id: Number(ordenId.value),
                id_proveedor: Number(idProveedor.value),
                fecha_entrega: fechaEntrega.value,
                observaciones: observaciones.value,
                detalle: detalle
            };

            const res = await postJson(urls.guardar, payload, btnGuardarOrden);
            await Swal.fire('Guardado', res.mensaje, 'success');
            modalOrden.hide();
            recargarPagina();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    });

    // Confirmar Recepción
    btnConfirmarRecepcion.addEventListener('click', async () => {
        const almacenId = Number(recepcionAlmacen.value);
        if (almacenId <= 0) return Swal.fire('Atención', 'Seleccione un almacén de destino.', 'warning');

        const confirm = await Swal.fire({
            title: '¿Confirmar ingreso?',
            text: "Se actualizará el stock físico del almacén seleccionado.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, recepcionar'
        });

        if (confirm.isConfirmed) {
            try {
                const res = await postJson(urls.recepcionar, {
                    id_orden: Number(recepcionOrdenId.value),
                    id_almacen: almacenId
                }, btnConfirmarRecepcion);
                
                await Swal.fire('Éxito', res.mensaje, 'success');
                modalRecepcion.hide();
                recargarPagina();
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        }
    });

    // 5. Manejo de Acciones en Tabla (Delegación)
    tbodyTabla.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return; // Si no clickeó un botón, salir

        const fila = target.closest('tr');
        const id = Number(fila.dataset.id);

        // A. Editar / Ver
        if (target.classList.contains('btn-editar')) {
            try {
                // Petición AJAX para obtener datos frescos
                const res = await fetch(`${urls.index}&accion=ver&id=${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                
                if (json.ok && json.data) {
                    const d = json.data;
                    limpiarModalOrden();
                    
                    // Llenar cabecera
                    ordenId.value = d.id;
                    
                    // --- CARGAR DATO EN EL BUSCADOR ---
                    if (tomSelectProveedor) {
                        tomSelectProveedor.setValue(d.id_proveedor);
                    } else {
                        idProveedor.value = d.id_proveedor;
                    }
                    // ----------------------------------

                    fechaEntrega.value = d.fecha_entrega || '';
                    observaciones.value = d.observaciones || '';
                    
                    // Llenar detalle
                    tbodyDetalle.innerHTML = ''; // Limpiar fila vacía por defecto
                    if (d.detalle && d.detalle.length > 0) {
                        d.detalle.forEach(item => agregarFila(item));
                    } else {
                        agregarFila();
                    }
                    
                    // Si el estado NO es borrador (0), deshabilitar inputs para modo "Solo Lectura"
                    const esEditable = Number(d.estado) === 0;
                    btnGuardarOrden.style.display = esEditable ? 'block' : 'none';
                    
                    modalOrden.show();
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'No se pudo cargar la orden.', 'error');
            }
        }

        // B. Aprobar
        if (target.classList.contains('btn-aprobar')) {
            const confirm = await Swal.fire({
                title: '¿Aprobar Orden?',
                text: "La orden pasará a estado Pendiente/Aprobada y ya no podrá editarse.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Sí, aprobar'
            });

            if (confirm.isConfirmed) {
                try {
                    const res = await postJson(urls.aprobar, { id: id });
                    await Swal.fire('Aprobada', res.mensaje, 'success');
                    recargarPagina();
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        }

        // C. Anular
        if (target.classList.contains('btn-anular')) {
            const confirm = await Swal.fire({
                title: '¿Anular Orden?',
                text: "Esta acción no se puede deshacer.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, anular'
            });

            if (confirm.isConfirmed) {
                try {
                    const res = await postJson(urls.anular, { id: id });
                    await Swal.fire('Anulada', res.mensaje, 'success');
                    recargarPagina();
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        }

        // D. Recepcionar (Abrir modal)
        if (target.classList.contains('btn-recepcionar')) {
            recepcionOrdenId.value = id;
            recepcionAlmacen.value = ""; // Resetear select
            modalRecepcion.show();
        }
    });

    // 6. Listeners Generales
    document.getElementById('btnNuevaOrden').addEventListener('click', () => {
        limpiarModalOrden();
        // Asegurar que el botón guardar sea visible (por si quedó oculto de un "ver")
        btnGuardarOrden.style.display = 'block';
        modalOrden.show();
    });

    document.getElementById('btnAgregarFila').addEventListener('click', () => agregarFila());

    // Filtros (Recarga simple)
    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach(el => {
        el.addEventListener('change', recargarPagina);
    });
    
    // Búsqueda con Enter
    filtroBusqueda.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            recargarPagina();
        }
    });

});