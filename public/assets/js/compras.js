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

    // --- MAGIA 1: Tom Select para Proveedor ---
    let tomSelectProveedor = null;
    if (document.getElementById('idProveedor')) {
        tomSelectProveedor = new TomSelect("#idProveedor", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "Escribe para buscar proveedor...",
            dropdownParent: 'body' 
        });
    }
    // ------------------------------------------

    // Modales
    const modalOrdenEl = document.getElementById('modalOrdenCompra');
    const modalOrden = new bootstrap.Modal(modalOrdenEl);
    const modalRecepcionEl = document.getElementById('modalRecepcionCompra');
    const modalRecepcion = new bootstrap.Modal(modalRecepcionEl);

    // Elementos del DOM
    const tablaCompras = document.getElementById('tablaCompras');
    const tbodyTabla = tablaCompras.querySelector('tbody');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    // Elementos del Formulario Orden
    const formOrden = document.getElementById('formOrdenCompra');
    const ordenId = document.getElementById('ordenId');
    const idProveedor = document.getElementById('idProveedor'); 
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

        const inputItem = fila.querySelector('.detalle-item');
        const inputCantidad = fila.querySelector('.detalle-cantidad');
        const inputCosto = fila.querySelector('.detalle-costo');
        const btnQuitar = fila.querySelector('.btn-quitar-fila');

        tbodyDetalle.appendChild(fila);

        const tomSelectItem = new TomSelect(inputItem, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "Buscar ítem...",
            dropdownParent: 'body'
        });

        if (item) {
            tomSelectItem.setValue(item.id_item); 
            inputCantidad.value = item.cantidad; 
            inputCosto.value = item.costo_unitario; 
        }

        [inputCantidad, inputCosto].forEach(input => {
            input.addEventListener('input', () => recalcularFila(fila));
        });
        
        // CORRECCIÓN: Evitar duplicados
        tomSelectItem.on('change', function(value) {
            if (!value) return;
            
            let contadorDuplicados = 0;
            tbodyDetalle.querySelectorAll('.detalle-item').forEach(select => {
                if (select.value === value) {
                    contadorDuplicados++;
                }
            });

            if (contadorDuplicados > 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ítem duplicado',
                    text: 'Este producto ya está en la lista.',
                    confirmButtonColor: '#3085d6'
                });
                tomSelectItem.clear(); // Limpia la selección para que elija otro
            }
        });

        btnQuitar.addEventListener('click', () => {
            tomSelectItem.destroy(); 
            fila.remove();
            recalcularTotalGeneral();
        });

        recalcularFila(fila); 
    }

    function limpiarModalOrden() {
        formOrden.reset();
        ordenId.value = 0;
        
        if (tomSelectProveedor) {
            tomSelectProveedor.clear(); 
        } else {
            idProveedor.value = '';
        }

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
        const params = new URLSearchParams({
            q: filtroBusqueda.value,
            estado: filtroEstado.value,
            fecha_desde: filtroFechaDesde.value,
            fecha_hasta: filtroFechaHasta.value
        }).toString();
        
        // CORRECCIÓN 404: Verificamos si la url base ya tiene un "?" para usar "&" en su lugar
        const separador = urls.index.includes('?') ? '&' : '?';
        window.location.href = `${urls.index}${separador}${params}`;
    }

    // 4. Lógica de Botones Principales (Guardar / Recepcionar)
    btnGuardarOrden.addEventListener('click', async () => {
        // Validaciones Frontend con SweetAlert2
        if (!idProveedor.value) {
            return Swal.fire('Falta Proveedor', 'Debe seleccionar un proveedor.', 'warning');
        }
        
        // CORRECCIÓN: Validación de Fecha Obligatoria
        if (!fechaEntrega.value) {
            return Swal.fire('Falta Fecha', 'La fecha de entrega estimada es obligatoria.', 'warning');
        }
        
        const detalle = [];
        let errorDetalle = false;

        tbodyDetalle.querySelectorAll('tr').forEach(fila => {
            const datos = filaToPayload(fila);
            if (datos.id_item > 0) {
                if (datos.cantidad <= 0) errorDetalle = true;
                detalle.push(datos);
            }
        });

        // CORRECCIÓN: Debe haber mínimo 1 ítem
        if (detalle.length === 0) {
            return Swal.fire({
                icon: 'error',
                title: 'Orden vacía',
                text: 'Debe agregar al menos un producto a la orden de compra.'
            });
        }
        
        if (errorDetalle) {
            return Swal.fire('Verifique Cantidades', 'Todos los ítems deben tener una cantidad mayor a 0.', 'warning');
        }

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
        if (!target) return; 

        const fila = target.closest('tr');
        const id = Number(fila.dataset.id);

        if (target.classList.contains('btn-editar')) {
            try {
                // Asegúrate de que urls.index tenga el formato correcto para concatenar
                const separador = urls.index.includes('?') ? '&' : '?';
                const res = await fetch(`${urls.index}${separador}accion=ver&id=${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                
                if (json.ok && json.data) {
                    const d = json.data;
                    limpiarModalOrden();
                    
                    ordenId.value = d.id;
                    
                    if (tomSelectProveedor) {
                        tomSelectProveedor.setValue(d.id_proveedor);
                    } else {
                        idProveedor.value = d.id_proveedor;
                    }

                    fechaEntrega.value = d.fecha_entrega || '';
                    observaciones.value = d.observaciones || '';
                    
                    // Llenar detalle
                    // CORRECCIÓN: Primero destruimos las instancias de Tom Select antes de borrar el HTML
                    tbodyDetalle.querySelectorAll('.detalle-item').forEach(select => {
                        if (select.tomselect) {
                            select.tomselect.destroy();
                        }
                    });
                    tbodyDetalle.innerHTML = '';
                    if (d.detalle && d.detalle.length > 0) {
                        d.detalle.forEach(item => agregarFila(item));
                    } else {
                        agregarFila();
                    }
                    
                    const esEditable = Number(d.estado) === 0;
                    btnGuardarOrden.style.display = esEditable ? 'block' : 'none';
                    
                    modalOrden.show();
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'No se pudo cargar la orden.', 'error');
            }
        }

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

        if (target.classList.contains('btn-recepcionar')) {
            recepcionOrdenId.value = id;
            recepcionAlmacen.value = ""; 
            modalRecepcion.show();
        }
    });

    // 6. Listeners Generales
    document.getElementById('btnNuevaOrden').addEventListener('click', () => {
        limpiarModalOrden();
        btnGuardarOrden.style.display = 'block';
        modalOrden.show();
    });

    document.getElementById('btnAgregarFila').addEventListener('click', () => agregarFila());

    [filtroBusqueda, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach(el => {
        el.addEventListener('change', recargarPagina);
    });
    
    filtroBusqueda.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            recargarPagina();
        }
    });

});