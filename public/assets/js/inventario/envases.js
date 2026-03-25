(function () {
    'use strict';

    function initModuloEnvases() {
        // 1. REVIVIR LA TABLA Y LA PAGINACIÓN
        // Usamos la función global de tu renderizadores.js para inicializar la tabla nueva
        if (window.ERPTable) {
            const contenedor = document.getElementById('spa-main-content') || document;
            window.ERPTable.autoInitFromDataset(contenedor);
        }

        // 2. REVIVIR LOS SELECTS CON BÚSQUEDA (Tom Select)
        if (typeof TomSelect !== 'undefined') {
            const configTS = { 
                create: false, 
                sortField: { field: "text", direction: "asc" },
                // VITAL: dropdownParent 'body' evita que la lista de resultados se esconda detrás del modal
                dropdownParent: 'body' 
            };

            const selectCliente = document.getElementById('id_tercero');
            if (selectCliente && !selectCliente.tomselect) {
                new TomSelect(selectCliente, { ...configTS, placeholder: "Buscar cliente..." });
            }

            const selectItem = document.getElementById('id_item_envase');
            if (selectItem && !selectItem.tomselect) {
                new TomSelect(selectItem, { ...configTS, placeholder: "Buscar envase..." });
            }

            const selectOp = document.getElementById('tipo_operacion');
            if (selectOp && !selectOp.tomselect) {
                new TomSelect(selectOp, { create: false, controlInput: null });
            }
        }

        // 3. ENVIAR FORMULARIO POR AJAX (Para mantener la experiencia SPA)
        const form = document.getElementById('formMovimientoEnvase');
        if (form && !form.dataset.eventBound) {
            form.dataset.eventBound = 'true'; // Evitar que el evento se duplique
            const selectTipo = document.getElementById('tipo_operacion');
            const selectAlmacen = document.getElementById('id_almacen');

            const actualizarReglasAlmacen = () => {
                if (!selectTipo || !selectAlmacen) return;
                const requiereKardex = ['RECEPCION_VACIO', 'ENTREGA_LLENO'].includes(selectTipo.value);
                selectAlmacen.required = requiereKardex;
            };

            if (selectTipo) {
                selectTipo.addEventListener('change', actualizarReglasAlmacen);
                actualizarReglasAlmacen();
            }
            
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (selectTipo && selectAlmacen) {
                    const requiereKardex = ['RECEPCION_VACIO', 'ENTREGA_LLENO'].includes(selectTipo.value);
                    if (requiereKardex && !selectAlmacen.value) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Falta almacén', 'Debes seleccionar un almacén para impactar Kardex.', 'warning');
                        }
                        selectAlmacen.focus();
                        return;
                    }
                }
                
                const btn = (e.submitter instanceof HTMLElement)
                    ? e.submitter
                    : document.querySelector(`button[type="submit"][form="${form.id}"]`) || form.querySelector('button[type="submit"], input[type="submit"]');
                const btnOriginal = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (response.ok) {
                        // Ocultamos el modal
                        const modalInst = bootstrap.Modal.getInstance(document.getElementById('modalMovimientoEnvase'));
                        if (modalInst) modalInst.hide();

                        // Alerta de éxito y refresco SPA
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success', 
                                title: 'Guardado', 
                                text: 'El movimiento se registró correctamente.', 
                                timer: 1500, 
                                showConfirmButton: false
                            }).then(() => {
                                // Refrescar la vista usando tu función nativa de main.js
                                if (typeof window.navigateWithoutReload === 'function') {
                                    window.navigateWithoutReload(new URL(window.location.href), false);
                                } else {
                                    window.location.reload();
                                }
                            });
                        }
                    } else {
                        throw new Error('Error en la respuesta del servidor');
                    }
                } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Ocurrió un problema al guardar.', 'error');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = btnOriginal;
                    }
                }
            });
        }

        // ==========================================
        // 4. LÓGICA BLINDADA PARA VER HISTORIAL
        // ==========================================
        
        // Usamos una variable global (window) para asegurar que el evento
        // se registre UNA SOLA VEZ en tu SPA, pase lo que pase.
        if (!window.historialEventBound) {
            window.historialEventBound = true; 

            document.addEventListener('click', async function(e) {
                // Buscamos si el clic fue en el botón o en el ícono del reloj
                const btnHistorial = e.target.closest('.btn-ver-historial');
                
                if (btnHistorial) {
                    e.preventDefault(); // Evitamos comportamientos raros

                    // Extraemos los datos
                    const idTercero = btnHistorial.dataset.tercero;
                    const idItem = btnHistorial.dataset.item;
                    const nombreCliente = btnHistorial.dataset.clienteNombre;

                    // Verificamos que el HTML del modal exista
                    const modalEl = document.getElementById('modalHistorial');
                    if (!modalEl) {
                        if (typeof Swal !== 'undefined') Swal.fire('Error', 'Falta el código HTML del modal', 'error');
                        return;
                    }

                    // Preparamos los textos
                    document.getElementById('historialClienteNombre').textContent = 'Cliente: ' + nombreCliente;
                    const tbody = document.getElementById('tablaHistorialBody');
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted mb-0">Cargando historial...</p></td></tr>';
                    
                    try {
                        // Usamos getOrCreateInstance que es más seguro en Bootstrap 5
                        const modalObj = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modalObj.show();

                        // Petición AJAX
                        const url = `?ruta=inventario/envases/historial&tercero=${idTercero}&item=${idItem}`;

                        const response = await fetch(url, { 
                            headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                        });
                        
                        if (!response.ok) throw new Error(`Error de red: ${response.status} ${response.statusText}`);
                        
                        const datos = await response.json();

                        tbody.innerHTML = ''; 
                        
                        if (datos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay movimientos detallados para mostrar.</td></tr>';
                            return;
                        }

                        datos.forEach(mov => {
                            let badgeClass = 'bg-secondary';
                            let icon = '';
                            let nombreOperacion = mov.tipo_operacion;

                            if (mov.tipo_operacion === 'RECEPCION_VACIO') { badgeClass = 'bg-success'; icon = '📥'; nombreOperacion = 'Recepción Vacíos'; } 
                            else if (mov.tipo_operacion === 'ENTREGA_LLENO') { badgeClass = 'bg-primary'; icon = '📤'; nombreOperacion = 'Préstamo / Entrega'; } 
                            else if (mov.tipo_operacion === 'AJUSTE_CLIENTE' || mov.tipo_operacion === 'MERMA_PLANTA') { badgeClass = 'bg-warning text-dark'; icon = '⚠️'; nombreOperacion = 'Ajuste / Merma'; }

                            tbody.innerHTML += `
                                <tr>
                                    <td class="text-muted small">#${mov.id}</td>
                                    <td><span class="badge ${badgeClass} px-2 py-1">${icon} ${nombreOperacion}</span></td>
                                    <td class="text-center fw-bold text-dark">${mov.cantidad}</td>
                                    <td class="small text-muted text-break">${mov.observaciones || '<span class="text-light-subtle">Sin observaciones</span>'}</td>
                                </tr>
                            `;
                        });
                        
                    } catch (error) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-4 d-block mb-2"></i> Ocurrió un error al cargar la información.</td></tr>';
                    }
                }
            });
        }
    }

    // Asegurar que se ejecute tanto en recarga completa como en navegación SPA
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModuloEnvases);
    } else {
        // Pequeño retraso para asegurar que el HTML del SPA ya está en pantalla
        setTimeout(initModuloEnvases, 50);
    }

})();
