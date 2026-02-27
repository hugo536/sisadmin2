/**
 * GESTIÓN COMERCIAL
 * - Acuerdos comerciales (matriz de tarifas)
 * - Tarifa General por Volumen
 */

document.addEventListener('DOMContentLoaded', () => {
    const appAcuerdos = document.getElementById('acuerdosComercialesApp');
    if (!appAcuerdos) return;

    const urls = {
        clientesDisponibles: appAcuerdos.dataset.urlClientesDisponibles,
        crearAcuerdo: appAcuerdos.dataset.urlCrearAcuerdo,
        obtenerMatriz: appAcuerdos.dataset.urlObtenerMatriz,
        presentacionesDisponibles: appAcuerdos.dataset.urlPresentacionesDisponibles,
        agregarProducto: appAcuerdos.dataset.urlAgregarProducto,
        actualizarPrecio: appAcuerdos.dataset.urlActualizarPrecio,
        togglePrecio: appAcuerdos.dataset.urlTogglePrecio,
        eliminarProducto: appAcuerdos.dataset.urlEliminarProducto,
        suspenderAcuerdo: appAcuerdos.dataset.urlSuspenderAcuerdo,
        activarAcuerdo: appAcuerdos.dataset.urlActivarAcuerdo,
        eliminarAcuerdo: appAcuerdos.dataset.urlEliminarAcuerdo,
        itemsVolumen: appAcuerdos.dataset.urlItemsVolumen,
        agregarVolumen: appAcuerdos.dataset.urlAgregarVolumen,
        actualizarVolumen: appAcuerdos.dataset.urlActualizarVolumen,
        eliminarVolumen: appAcuerdos.dataset.urlEliminarVolumen,
    };

    const sidebarList = document.getElementById('acuerdosSidebarList');
    const filtroClientes = document.getElementById('filtroClientesAcuerdo');
    const tabla = document.getElementById('tablaMatrizAcuerdo');
    const tbody = document.getElementById('matrizBodyRows');
    const tituloCliente = document.getElementById('acuerdoTituloCliente');
    const resumenTarifas = document.getElementById('acuerdoResumenTarifas');

    const modalVincularEl = document.getElementById('modalVincularCliente');
    const formVincular = document.getElementById('formVincularCliente');
    const selectCliente = document.getElementById('selectClienteVincular');
    const inputObs = document.getElementById('inputObservacionesAcuerdo');

    const modalAgregarEl = document.getElementById('modalAgregarProducto');
    const formAgregar = document.getElementById('formAgregarProductoAcuerdo');
    const selectPresentacion = document.getElementById('selectPresentacionAcuerdo');
    const inputPrecioInicial = document.getElementById('inputPrecioInicialAcuerdo');

    const modalVolumenEl = document.getElementById('modalAgregarEscalaVolumen');
    const formVolumen = document.getElementById('formAgregarEscalaVolumen');
    const selectItemVolumen = document.getElementById('selectItemVolumen');
    const inputCantidadMinimaVolumen = document.getElementById('inputCantidadMinimaVolumen');
    const inputPrecioUnitarioVolumen = document.getElementById('inputPrecioUnitarioVolumen');

    const btnAgregarProducto = document.getElementById('btnAgregarProducto');
    const btnSuspender = document.getElementById('btnSuspenderAcuerdo');
    const btnActivar = document.getElementById('btnActivarAcuerdo');
    const btnEliminar = document.getElementById('btnEliminarAcuerdo');

    const modalVincular = modalVincularEl ? new bootstrap.Modal(modalVincularEl) : null;
    const modalAgregar = modalAgregarEl ? new bootstrap.Modal(modalAgregarEl) : null;
    const modalVolumen = modalVolumenEl ? new bootstrap.Modal(modalVolumenEl) : null;

    let tsCliente = null;
    let tsPresentacion = null;
    let tsItemVolumen = null;

    const postForm = async (url, payload) => {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => fd.append(k, String(v)));
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const json = await res.json().catch(() => ({ success: false, message: 'Respuesta inválida' }));
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'No se pudo completar la operación.');
        }
        return json;
    };

    const getAcuerdoId = () => (tabla ? parseInt(tabla.dataset.idAcuerdo || '0', 10) || 0 : 0);
    const getModo = () => (tabla ? (tabla.dataset.modo || 'acuerdo') : 'acuerdo');

    const renderEmptyRow = (modo) => {
        if (!tbody) return;
        const msg = modo === 'volumen'
            ? 'Aún no hay escalas por volumen configuradas.'
            : 'Este acuerdo aún no tiene productos tarifados.';
        tbody.innerHTML = `
            <tr id="emptyMatrizRow">
                <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-exclamation-circle text-warning fs-1 d-block mb-2"></i>
                    ${msg}
                </td>
            </tr>
        `;
    };

    const rowTemplate = (item, modo) => {
        if (modo === 'volumen') {
            return `
                <tr data-id-detalle="${item.id}">
                    <td class="ps-4"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                    <td>${item.producto_nombre}</td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width: 120px;">
                            <span class="input-group-text bg-light border-end-0">≥</span>
                            <input type="number" min="0.01" step="0.01" class="form-control border-start-0 px-1 js-cantidad-minima" value="${parseFloat(item.cantidad_minima).toFixed(2)}" data-original="${parseFloat(item.cantidad_minima).toFixed(2)}">
                        </div>
                    </td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width: 130px;">
                            <span class="input-group-text bg-light border-end-0">S/</span>
                            <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-volumen" value="${parseFloat(item.precio_pactado || item.precio_unitario).toFixed(4)}" data-original="${parseFloat(item.precio_pactado || item.precio_unitario).toFixed(4)}">
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-volumen" type="button" title="Eliminar escala">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }

        return `
            <tr data-id-detalle="${item.id}">
                <td class="ps-4"><span class="badge bg-light text-dark border">${item.codigo_presentacion || 'N/A'}</span></td>
                <td class="fw-semibold text-dark">${item.producto_nombre}</td>
                <td>
                    <div class="input-group input-group-sm" style="max-width: 130px;">
                        <span class="input-group-text bg-light border-end-0">S/</span>
                        <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-pactado" value="${parseFloat(item.precio_pactado).toFixed(4)}" data-original="${parseFloat(item.precio_pactado).toFixed(4)}">
                    </div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                        <input class="form-check-input js-estado-precio" type="checkbox" ${parseInt(item.estado, 10) === 1 ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-producto" type="button" title="Eliminar producto">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    };

    const cargarMatriz = async (idAcuerdo) => {
        const url = `${urls.obtenerMatriz}&id_acuerdo=${idAcuerdo}`;
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudo cargar la matriz.');

        const modo = json.modo || 'acuerdo';
        
        // CORRECCIÓN: Actualizar encabezados de tabla según modo
        const thead = tabla.querySelector('thead tr');
        if (thead) {
            if (modo === 'volumen') {
                thead.innerHTML = `
                    <th class="ps-4" style="width: 120px;">Código</th>
                    <th>Producto</th>
                    <th style="width: 180px;">Cantidad Mínima</th>
                    <th style="width: 220px;">Precio Unitario</th>
                    <th class="text-end pe-4" style="width: 90px;">Acciones</th>
                `;
            } else {
                thead.innerHTML = `
                    <th class="ps-4" style="width: 120px;">Código</th>
                    <th>Producto</th>
                    <th style="width: 220px;">Precio Pactado</th>
                    <th class="text-center" style="width: 130px;">Estado</th>
                    <th class="text-end pe-4" style="width: 90px;">Acciones</th>
                `;
            }
        }

        if (tabla) {
            tabla.dataset.idAcuerdo = String(idAcuerdo);
            tabla.dataset.modo = modo;
        }

        if (tituloCliente) tituloCliente.textContent = json.acuerdo.cliente_nombre;
        if (resumenTarifas) {
            if (modo === 'volumen') {
                const uniqueItems = new Set(json.matriz.map(item => item.id_item)).size;
                resumenTarifas.textContent = `${uniqueItems} productos configurados`;
            } else {
                resumenTarifas.textContent = `${json.matriz.length} tarifas configuradas`;
            }
        }
        if (btnAgregarProducto) btnAgregarProducto.innerHTML = `<i class="bi bi-plus-lg me-1"></i>${modo === 'volumen' ? 'Agregar Escala' : 'Agregar Producto'}`;

        // Mostrar/Ocultar dropdown de opciones si es volumen o no
        const dropdownOpciones = document.querySelector('.dropdown .btn-outline-secondary');
        if (dropdownOpciones) dropdownOpciones.closest('.dropdown').style.display = modo === 'volumen' ? 'none' : 'block';

        if (!tbody) return;
        if (!Array.isArray(json.matriz) || json.matriz.length === 0) {
            renderEmptyRow(modo);
            return;
        }
        tbody.innerHTML = json.matriz.map(r => rowTemplate(r, modo)).join('');
    };

    const refrescarPaginaConAcuerdo = (idAcuerdo) => {
        const u = new URL(window.location.href);
        u.searchParams.set('ruta', 'comercial/listas');
        u.searchParams.set('id', String(idAcuerdo));
        window.location.href = u.toString();
    };

    const loadClientesDisponibles = async () => {
        const res = await fetch(urls.clientesDisponibles, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar clientes.');

        const options = (json.data || []).map(c => ({ value: String(c.id), text: `${c.numero_documento ? `${c.numero_documento} - ` : ''}${c.cliente_nombre}` }));
        if (tsCliente) tsCliente.destroy();
        if (selectCliente) {
            selectCliente.innerHTML = '';
            tsCliente = new TomSelect(selectCliente, {
                options,
                maxItems: 1, // <--- AGREGA ESTO
                create: false,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                placeholder: options.length ? 'Seleccione un cliente...' : 'Sin clientes disponibles'
            });
        }
    };

    const loadPresentacionesDisponibles = async () => {
        const idAcuerdo = getAcuerdoId();
        const res = await fetch(`${urls.presentacionesDisponibles}&id_acuerdo=${idAcuerdo}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar presentaciones.');

        const options = (json.data || []).map(p => ({ value: String(p.id), text: `${p.codigo_presentacion || 'N/A'} - ${p.producto_nombre}` }));
        if (tsPresentacion) tsPresentacion.destroy();
        if (selectPresentacion) {
            selectPresentacion.innerHTML = '';
            tsPresentacion = new TomSelect(selectPresentacion, {
                options,
                maxItems: 1, // <--- AGREGA ESTO
                create: false,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                placeholder: options.length ? 'Seleccione un producto...' : 'Sin productos disponibles'
            });
        }
    };

    const loadItemsVolumen = async () => {
        const res = await fetch(urls.itemsVolumen, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar productos.');

        const options = (json.data || []).map(p => ({ value: String(p.id), text: `${p.codigo_presentacion || 'N/A'} - ${p.producto_nombre}` }));
        if (tsItemVolumen) tsItemVolumen.destroy();
        if (selectItemVolumen) {
            selectItemVolumen.innerHTML = '';
            tsItemVolumen = new TomSelect(selectItemVolumen, {
                options,
                maxItems: 1, // <---- AGREGA ESTA LÍNEA AQUÍ
                create: false,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                placeholder: options.length ? 'Seleccione un producto...' : 'Sin productos disponibles'
            });
        }
    };

    if (filtroClientes && sidebarList) {
        filtroClientes.addEventListener('input', () => {
            const term = filtroClientes.value.toLowerCase().trim();
            const items = sidebarList.querySelectorAll('.acuerdo-sidebar-item');
            let visibles = 0;
            items.forEach(item => {
                const show = (item.dataset.search || '').includes(term);
                item.style.display = show ? '' : 'none';
                if (show) visibles += 1;
            });
            const empty = document.getElementById('sidebarNoResults');
            if (empty) empty.style.display = visibles ? 'none' : '';
        });
    }

    if (sidebarList) {
        sidebarList.addEventListener('click', (e) => {
            const item = e.target.closest('.acuerdo-sidebar-item');
            if (!item) return;
            
            const idAcuerdo = parseInt(item.dataset.idAcuerdo || '0', 10);
            
            // Reemplazamos la carga por AJAX (que rompía el acordeón) 
            // por una navegación limpia. PHP se encargará de dibujar todo perfecto.
            window.location.href = '?ruta=comercial/listas&id=' + idAcuerdo;
        });
    }

    if (modalVincularEl) {
        modalVincularEl.addEventListener('show.bs.modal', async () => {
            try { await loadClientesDisponibles(); } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (formVincular) {
        formVincular.addEventListener('submit', async (e) => {
            e.preventDefault();
            const idTercero = tsCliente ? tsCliente.getValue() : '';
            if (!idTercero) return Swal.fire({ icon: 'warning', title: 'Seleccione un cliente' });
            try {
                const resp = await postForm(urls.crearAcuerdo, { id_tercero: idTercero, observaciones: inputObs ? inputObs.value : '' });
                if (modalVincular) modalVincular.hide();
                refrescarPaginaConAcuerdo(resp.id);
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (btnAgregarProducto) {
        btnAgregarProducto.addEventListener('click', async () => {
            try {
                if (getModo() === 'volumen') {
                    await loadItemsVolumen();
                    if (inputCantidadMinimaVolumen) inputCantidadMinimaVolumen.value = '';
                    if (inputPrecioUnitarioVolumen) inputPrecioUnitarioVolumen.value = '';
                    if (modalVolumen) modalVolumen.show();
                } else {
                    await loadPresentacionesDisponibles();
                    if (inputPrecioInicial) inputPrecioInicial.value = '';
                    if (modalAgregar) modalAgregar.show();
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    if (formAgregar) {
        formAgregar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const idAcuerdo = getAcuerdoId();
            const idPresentacion = tsPresentacion ? tsPresentacion.getValue() : '';
            const precio = inputPrecioInicial ? parseFloat(inputPrecioInicial.value || '0') : 0;
            if (!idAcuerdo || !idPresentacion || precio <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos' });
            try {
                await postForm(urls.agregarProducto, { id_acuerdo: idAcuerdo, id_presentacion: idPresentacion, precio_pactado: precio });
                if (modalAgregar) modalAgregar.hide();
                window.location.reload(); // <--- ESTO ARREGLA EL CONTADOR DEL CLIENTE
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (formVolumen) {
        formVolumen.addEventListener('submit', async (e) => {
            e.preventDefault();
            const idItem = tsItemVolumen ? tsItemVolumen.getValue() : '';
            const cantidad = inputCantidadMinimaVolumen ? parseFloat(inputCantidadMinimaVolumen.value || '0') : 0;
            const precio = inputPrecioUnitarioVolumen ? parseFloat(inputPrecioUnitarioVolumen.value || '0') : 0;
            if (!idItem || cantidad <= 0 || precio <= 0) return Swal.fire({ icon: 'warning', title: 'Datos incompletos' });
            try {
                await postForm(urls.agregarVolumen, { id_item: idItem, cantidad_minima: cantidad, precio_unitario: precio });
                if (modalVolumen) modalVolumen.hide();
                window.location.reload(); // <--- ESTO ARREGLA EL CONTADOR
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (tbody) {
        // Blur general para edición Inline (Ambos Modos)
        tbody.addEventListener('blur', async (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;
            const idDetalle = parseInt(tr.dataset.idDetalle || '0', 10);
            if (!idDetalle) return;

            try {
                // ACUERDOS NORMALES
                if (e.target.classList.contains('js-precio-pactado')) {
                    const precio = parseFloat(e.target.value || '0');
                    if (precio < 0) return;
                    await postForm(urls.actualizarPrecio, { id_detalle: idDetalle, precio_pactado: precio });
                    e.target.value = precio.toFixed(4); // Formatear correcto visualmente
                    e.target.setAttribute('data-original', e.target.value);
                }
                
                // TARIFA GENERAL (VOLUMEN)
                if (e.target.classList.contains('js-cantidad-minima') || e.target.classList.contains('js-precio-volumen')) {
                    const cantInput = tr.querySelector('.js-cantidad-minima');
                    const precInput = tr.querySelector('.js-precio-volumen');
                    
                    const cantidad = parseFloat(cantInput.value || '0');
                    const precioVol = parseFloat(precInput.value || '0');
                    
                    if (cantidad <= 0 || precioVol < 0) return;
                    
                    await postForm(urls.actualizarVolumen, { id_detalle: idDetalle, cantidad_minima: cantidad, precio_unitario: precioVol });
                    
                    cantInput.value = cantidad.toFixed(2);
                    precInput.value = precioVol.toFixed(4);
                    cantInput.setAttribute('data-original', cantInput.value);
                    precInput.setAttribute('data-original', precInput.value);
                }
                
                e.target.classList.add('is-valid');
                setTimeout(() => e.target.classList.remove('is-valid'), 900);
            } catch (_err) {
                // Si falla, restaurar el original
                e.target.value = e.target.getAttribute('data-original');
                e.target.classList.add('is-invalid');
                setTimeout(() => e.target.classList.remove('is-invalid'), 1400);
            }
        }, true);

        // Toggle Switch (Solo Acuerdos)
        tbody.addEventListener('change', async (e) => {
            if (!e.target.classList.contains('js-estado-precio')) return;
            const tr = e.target.closest('tr');
            const idDetalle = parseInt((tr?.dataset.idDetalle) || '0', 10);
            try { await postForm(urls.togglePrecio, { id_detalle: idDetalle, estado: e.target.checked ? 1 : 0 }); }
            catch (err) { e.target.checked = !e.target.checked; Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });

        // Eliminar fila (Ambos Modos)
        tbody.addEventListener('click', async (e) => {
            const btnDeleteAcuerdo = e.target.closest('.js-eliminar-producto');
            const btnDeleteVolumen = e.target.closest('.js-eliminar-volumen');
            if (!btnDeleteAcuerdo && !btnDeleteVolumen) return;
            
            const tr = e.target.closest('tr');
            const idDetalle = parseInt((tr?.dataset.idDetalle) || '0', 10);
            const idAcuerdo = getAcuerdoId();

            const confirm = await Swal.fire({
                icon: 'warning',
                title: 'Eliminar registro',
                text: '¿Deseas retirar esta tarifa de la matriz?',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!confirm.isConfirmed) return;

            try {
                if (btnDeleteVolumen) await postForm(urls.eliminarVolumen, { id_detalle: idDetalle });
                else await postForm(urls.eliminarProducto, { id_detalle: idDetalle });
                window.location.reload(); // <--- ESTO ACTUALIZA TODO AL ELIMINAR
            } catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (btnSuspender) {
        btnSuspender.addEventListener('click', async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) return;
            const confirm = await Swal.fire({ icon: 'question', title: 'Suspender acuerdo', text: 'Se dejarán de aplicar estas tarifas.', showCancelButton: true, confirmButtonText: 'Suspender' });
            if (!confirm.isConfirmed) return;
            try { await postForm(urls.suspenderAcuerdo, { id_acuerdo: idAcuerdo }); window.location.reload(); }
            catch (err) { Swal.fire({ icon: 'warning', title: 'No se pudo suspender', text: err.message }); }
        });
    }

    if (btnActivar) {
        btnActivar.addEventListener('click', async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) return;
            try { await postForm(urls.activarAcuerdo, { id_acuerdo: idAcuerdo }); window.location.reload(); }
            catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    if (btnEliminar) {
        btnEliminar.addEventListener('click', async () => {
            const idAcuerdo = getAcuerdoId();
            if (!idAcuerdo) return;
            const confirm = await Swal.fire({ icon: 'warning', title: 'Romper acuerdo', text: 'Esta acción eliminará el acuerdo y su matriz.', showCancelButton: true, confirmButtonText: 'Eliminar acuerdo', confirmButtonColor: '#dc3545' });
            if (!confirm.isConfirmed) return;
            try { await postForm(urls.eliminarAcuerdo, { id_acuerdo: idAcuerdo }); window.location.href = '?ruta=comercial/listas'; }
            catch (err) { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }
        });
    }

    // =========================================================================
    // ACORDEÓN PARA PRECIOS POR VOLUMEN (Modo Estricto)
    // =========================================================================
    window.toggleAcordeonVolumen = function(grupoId, headerRow) {
        
        // 1. CERRAR TODOS LOS DEMÁS GRUPOS
        // Buscamos todas las filas que sean escalas, pero que NO sean del grupo que acabamos de clickear
        document.querySelectorAll('tr[class*="escala-grupo-"]').forEach(fila => {
            if (!fila.classList.contains('escala-' + grupoId)) {
                fila.style.display = 'none'; // Las ocultamos
            }
        });

        // 2. RESTAURAR TODAS LAS FLECHAS A "ABAJO" (Excepto la del grupo actual)
        document.querySelectorAll('.js-chevron').forEach(icono => {
            if (icono !== headerRow.querySelector('.js-chevron')) {
                icono.classList.remove('bi-chevron-up');
                icono.classList.add('bi-chevron-down');
            }
        });

        // 3. ABRIR / CERRAR EL GRUPO SELECCIONADO
        const filas = document.querySelectorAll('.escala-' + grupoId);
        const chevron = headerRow.querySelector('.js-chevron');

        if (filas.length === 0) return;

        // Comprobamos si el grupo actual está oculto o visible
        const estanOcultas = filas[0].style.display === 'none';

        // Lo alternamos
        filas.forEach(fila => {
            fila.style.display = estanOcultas ? 'table-row' : 'none';
        });

        // Giramos la flecha del grupo actual
        if (estanOcultas) {
            chevron.classList.remove('bi-chevron-down');
            chevron.classList.add('bi-chevron-up'); // Flecha arriba (abierto)
        } else {
            chevron.classList.remove('bi-chevron-up');
            chevron.classList.add('bi-chevron-down'); // Flecha abajo (cerrado)
        }
    };
});