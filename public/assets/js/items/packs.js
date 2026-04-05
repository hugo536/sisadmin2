(function () {
    const app = document.getElementById('packsApp');
    if (!app) return;

    // --- ELEMENTOS DEL DOM ---
    const listaPacks = Array.from(document.querySelectorAll('.pack-item-btn'));
    const inputBuscar = document.getElementById('buscarPack');
    const btnNuevoPack = document.getElementById('btnNuevoPack');
    const btnCerrarPanelMobile = document.getElementById('btnCerrarPanelMobile');

    const panelVacio = document.getElementById('panelVacio');
    const panelConfiguracion = document.getElementById('panelConfiguracion');

    const formPackPadre = document.getElementById('formPackPadre');
    const idPackSeleccionadoInput = document.getElementById('idPackSeleccionado');
    const inputNombrePack = document.getElementById('inputNombrePack');
    const inputPrecioPack = document.getElementById('inputPrecioPack');
    const lblEstadoPack = document.getElementById('lblEstadoPack');
    
    const seccionComponentes = document.getElementById('seccionComponentes');
    const formAgregar = document.getElementById('formAgregarComponente');
    const selectComponente = document.getElementById('selectComponente');
    const inputCantidad = document.getElementById('inputCantidad');
    const checkBonificacion = document.getElementById('checkBonificacion');

    const tbodyComponentes = document.querySelector('#tablaComponentes tbody');
    const tablaComponentes = document.getElementById('tablaComponentes'); // Referencia a la tabla para ERPTable
    const templateFila = document.getElementById('templateFilaComponente');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Memoria temporal
    let componentesLocales = []; 

    // --- FUNCIONES AUXILIARES ---
    function buildUrl(action, params = null) {
        const currentUrl = new URL(window.location.href);
        let url;
        if (currentUrl.searchParams.has('ruta')) {
            const rutaActual = currentUrl.searchParams.get('ruta') || 'items/packs';
            const rutaBase = rutaActual.split('/').slice(0, 2).join('/') || 'items/packs';
            url = new URL(currentUrl.origin + currentUrl.pathname);
            url.searchParams.set('ruta', `${rutaBase}/${action}`);
        } else {
            const path = currentUrl.pathname || '';
            const index = path.indexOf('/items/packs');
            const base = index >= 0 ? path.substring(0, index) + '/items/packs' : '/items/packs';
            url = new URL(base + '/' + action, currentUrl.origin);
        }
        if (params && typeof params === 'object') {
            Object.keys(params).forEach((key) => url.searchParams.set(key, String(params[key])));
        }
        return url.toString();
    }

    function toastError(message) {
        if (window.Swal) return window.Swal.fire({ icon: 'error', title: 'Error', text: message });
        window.alert(message);
    }

    function toastSuccess(message) {
        if (window.Swal) return window.Swal.fire({ icon: 'success', title: 'Correcto', text: message, timer: 1500, showConfirmButton: false });
    }

    function refrescarUI_Tabla() {
        // Ejecuta las funciones globales de tu sistema para la nueva data
        if (window.ERPTable) {
            window.ERPTable.initTooltips(tbodyComponentes);
            window.ERPTable.applyResponsiveCards(tablaComponentes);
        }
    }

    function renderFilaVacia(mensaje = 'No hay componentes asignados a este pack.') {
        tbodyComponentes.innerHTML = '';
        const tr = document.createElement('tr');
        tr.id = 'filaVacia';
        tr.innerHTML = `<td colspan="4" class="text-center text-muted py-4">${mensaje}</td>`;
        tbodyComponentes.appendChild(tr);
        refrescarUI_Tabla();
    }

    function limpiarFormulario() {
        inputCantidad.value = '1';
        checkBonificacion.checked = false;
        if (window.tomSelectInstance) {
            window.tomSelectInstance.clear(); 
        } else {
            selectComponente.value = '';
        }
    }

    // --- FUNCIONES DE DIBUJADO DE TABLA ---
    
    function renderTablaLocales() {
        if (componentesLocales.length === 0) {
            renderFilaVacia('Agrega productos a la lista. Se guardarán al crear el combo.');
            return;
        }
        
        tbodyComponentes.innerHTML = '';
        componentesLocales.forEach((data, index) => {
            const clon = templateFila.content.cloneNode(true);
            clon.querySelector('.td-nombre').textContent = data.nombre_item;
            clon.querySelector('.td-cantidad').textContent = Number(data.cantidad).toFixed(2);

            const badgeTipo = clon.querySelector('.badge-tipo');
            if (data.es_bonificacion === 1) {
                badgeTipo.textContent = 'Regalo';
                badgeTipo.classList.add('bg-warning-subtle', 'text-warning-emphasis', 'border', 'border-warning-subtle');
            } else {
                badgeTipo.textContent = 'Componente';
                badgeTipo.classList.add('bg-info-subtle', 'text-info', 'border', 'border-info-subtle');
            }

            // Integración de IconosAccion global
            const celdas = clon.querySelectorAll('td');
            const tdAccion = celdas[celdas.length - 1]; // Seleccionamos la última celda (Quitar)
            
            if (window.IconosAccion) {
                tdAccion.innerHTML = window.IconosAccion.crear('eliminar', index, 'btn-eliminar-componente');
            }

            const btnEliminar = clon.querySelector('.btn-eliminar-componente');
            btnEliminar.addEventListener('click', () => {
                componentesLocales.splice(index, 1);
                renderTablaLocales();
            });

            tbodyComponentes.appendChild(clon);
        });
        
        refrescarUI_Tabla();
    }

    function dibujarFilaEnTablaBD(data) {
        if (tbodyComponentes.querySelector('#filaVacia')) {
            tbodyComponentes.innerHTML = '';
        }

        const clon = templateFila.content.cloneNode(true);
        const tr = clon.querySelector('tr');
        tr.dataset.idDetalle = String(data.id_detalle || 0);

        clon.querySelector('.td-nombre').textContent = data.nombre_item || 'Sin nombre';
        clon.querySelector('.td-cantidad').textContent = Number(data.cantidad || 0).toFixed(2);

        const badgeTipo = clon.querySelector('.badge-tipo');
        if (Number(data.es_bonificacion || 0) === 1) {
            badgeTipo.textContent = 'Regalo';
            badgeTipo.classList.add('bg-warning-subtle', 'text-warning-emphasis', 'border', 'border-warning-subtle');
        } else {
            badgeTipo.textContent = 'Componente';
            badgeTipo.classList.add('bg-info-subtle', 'text-info', 'border', 'border-info-subtle');
        }

        // Integración de IconosAccion global
        const celdas = clon.querySelectorAll('td');
        const tdAccion = celdas[celdas.length - 1]; 
        
        if (window.IconosAccion) {
            tdAccion.innerHTML = window.IconosAccion.crear('eliminar', data.id_detalle, 'btn-eliminar-componente');
        }

        const btnEliminar = clon.querySelector('.btn-eliminar-componente');
        btnEliminar.addEventListener('click', async () => {
            const totalComponentes = tbodyComponentes.querySelectorAll('tr[data-id-detalle]').length;
            if (totalComponentes <= 1) {
                toastError('No puedes eliminar el único componente. Para cambiarlo, añade otro primero.');
                return;
            }

            const confirmar = window.confirm('¿Estás seguro de quitar este componente del Pack?');
            if (!confirmar) return;

            try {
                const body = new URLSearchParams({
                    id_detalle: String(data.id_detalle),
                    csrf_token: csrfToken,
                });
                const response = await fetch(buildUrl('eliminar_componente'), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString(),
                });
                const res = await response.json();
                if (!response.ok || !res.ok) throw new Error(res.mensaje || 'No se pudo eliminar.');

                tr.remove();
                
                // Actualizamos tooltips por si la estructura cambió
                if (window.ERPTable && window.bootstrap?.Tooltip) {
                    const tooltipInstance = bootstrap.Tooltip.getInstance(btnEliminar);
                    if (tooltipInstance) tooltipInstance.dispose();
                }

            } catch (error) {
                toastError(error.message);
            }
        });

        tbodyComponentes.appendChild(clon);
    }

    async function cargarComponentesDelPack(idPack) {
        // Usa el spinner de tu sistema si está disponible
        if (window.ERPTable && typeof window.ERPTable.showLoading === 'function') {
            window.ERPTable.showLoading(tablaComponentes);
        } else {
            tbodyComponentes.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando...</td></tr>';
        }

        try {
            const response = await fetch(buildUrl('obtener_componentes', { id_pack: idPack }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo cargar la receta.');

            tbodyComponentes.innerHTML = '';
            const componentes = Array.isArray(data.items) ? data.items : [];
            
            if (componentes.length === 0) {
                renderFilaVacia();
            } else {
                componentes.forEach((item) => dibujarFilaEnTablaBD(item));
                refrescarUI_Tabla();
            }
        } catch (error) {
            renderFilaVacia();
            toastError(error.message);
        } finally {
            if (window.ERPTable && typeof window.ERPTable.hideLoading === 'function') {
                window.ERPTable.hideLoading(tablaComponentes);
            }
        }
    }

    // --- LÓGICA DE INTERFAZ GENERAL ---
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function () {
            const termino = this.value.trim().toLowerCase();
            listaPacks.forEach((btn) => {
                btn.style.display = btn.textContent.toLowerCase().includes(termino) ? '' : 'none';
            });
        });
    }

    if (btnNuevoPack) {
        btnNuevoPack.addEventListener('click', () => {
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));

            idPackSeleccionadoInput.value = '0';
            inputNombrePack.value = '';
            inputPrecioPack.value = '';
            lblEstadoPack.textContent = 'Nuevo Combo';
            lblEstadoPack.className = 'badge bg-success-subtle text-success border border-success-subtle mb-2';

            seccionComponentes.style.opacity = '1';
            seccionComponentes.style.pointerEvents = 'auto';
            
            componentesLocales = []; 
            renderTablaLocales();

            panelVacio.classList.add('d-none');
            panelConfiguracion.classList.remove('d-none');
            panelConfiguracion.classList.add('d-flex');
        });
    }

    listaPacks.forEach((btn) => {
        btn.addEventListener('click', () => {
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));
            btn.classList.add('active', 'bg-primary-subtle', 'border-primary');

            idPackSeleccionadoInput.value = btn.dataset.id || '0';
            inputNombrePack.value = btn.dataset.nombre || '';
            inputPrecioPack.value = Number(btn.dataset.precio || 0).toFixed(2);
            
            lblEstadoPack.textContent = 'Editando Combo';
            lblEstadoPack.className = 'badge bg-primary-subtle text-primary border border-primary-subtle mb-2';

            seccionComponentes.style.opacity = '1';
            seccionComponentes.style.pointerEvents = 'auto';

            panelVacio.classList.add('d-none');
            panelConfiguracion.classList.remove('d-none');
            panelConfiguracion.classList.add('d-flex');

            cargarComponentesDelPack(idPackSeleccionadoInput.value);
        });
    });

    if (btnCerrarPanelMobile) {
        btnCerrarPanelMobile.addEventListener('click', () => {
            panelConfiguracion.classList.add('d-none');
            panelConfiguracion.classList.remove('d-flex');
            panelVacio.classList.remove('d-none');
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));
        });
    }

    // --- PETICIONES DE GUARDADO ---

    formAgregar.addEventListener('submit', async (event) => {
        event.preventDefault();

        const idPack = Number(idPackSeleccionadoInput.value || 0);
        const idItem = Number(selectComponente.value || 0);
        const cantidad = Number(String(inputCantidad.value || '0').replace(',', '.'));

        const optionSelect = window.tomSelectInstance ? window.tomSelectInstance.options[idItem] : null;
        const nombreTomSelect = optionSelect ? optionSelect.text.split(' - [')[0] : 'Componente';

        if (idItem <= 0 || cantidad <= 0) {
            toastError('Selecciona un componente y cantidad válida.');
            return;
        }

        if (idPack === 0) {
            if (componentesLocales.some(c => c.id_item === idItem)) {
                toastError('El componente ya está en la lista temporal.');
                return;
            }
            componentesLocales.push({
                id_item: idItem,
                nombre_item: nombreTomSelect,
                cantidad: cantidad,
                es_bonificacion: checkBonificacion.checked ? 1 : 0
            });
            limpiarFormulario();
            renderTablaLocales();
            return; 
        }

        try {
            const body = new URLSearchParams({
                id_pack: String(idPack),
                id_item: String(idItem),
                cantidad: String(cantidad),
                es_bonificacion: checkBonificacion.checked ? '1' : '0',
                csrf_token: csrfToken,
            });

            const response = await fetch(buildUrl('agregar_componente'), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            });

            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo guardar el componente.');

            limpiarFormulario();
            await cargarComponentesDelPack(idPack);
            toastSuccess('Componente añadido a la base de datos.');
        } catch (error) {
            toastError(error.message);
        }
    });

    formPackPadre.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const isNewPack = idPackSeleccionadoInput.value === '0';

        if (isNewPack && componentesLocales.length === 0) {
            toastError('Debes añadir al menos un componente al combo antes de guardar.');
            return;
        }

        try {
            const body = new URLSearchParams({
                id: idPackSeleccionadoInput.value,
                nombre: inputNombrePack.value.trim(),
                precio_venta: inputPrecioPack.value,
                csrf_token: csrfToken
            });

            const response = await fetch(buildUrl('guardar_pack_padre'), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            });

            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'Error al guardar el combo');

            const idGuardado = String(data.id);

            if (isNewPack && componentesLocales.length > 0) {
                for (const comp of componentesLocales) {
                    const bodyComp = new URLSearchParams({
                        id_pack: idGuardado,
                        id_item: String(comp.id_item),
                        cantidad: String(comp.cantidad),
                        es_bonificacion: String(comp.es_bonificacion),
                        csrf_token: csrfToken,
                    });
                    await fetch(buildUrl('agregar_componente'), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: bodyComp.toString(),
                    });
                }
                componentesLocales = []; 
            }

            toastSuccess('Combo guardado correctamente');

            const nombreGuardado = inputNombrePack.value.trim();
            const precioGuardado = Number(inputPrecioPack.value).toFixed(2);
            let btnLista = listaPacks.find(btn => btn.dataset.id === idGuardado);

            if (btnLista) {
                btnLista.dataset.nombre = nombreGuardado;
                btnLista.dataset.precio = precioGuardado;
                btnLista.querySelector('.fw-bold.text-dark').textContent = nombreGuardado;
                btnLista.querySelector('.badge.bg-primary').textContent = `S/ ${precioGuardado}`;
            } else {
                const listaContenedor = document.getElementById('listaPacks');
                const msjVacio = listaContenedor.querySelector('.text-center.text-muted');
                if(msjVacio) msjVacio.remove();

                btnLista = document.createElement('button');
                btnLista.type = 'button';
                btnLista.className = 'list-group-item list-group-item-action p-3 pack-item-btn';
                btnLista.dataset.id = idGuardado;
                btnLista.dataset.nombre = nombreGuardado;
                btnLista.dataset.precio = precioGuardado;
                btnLista.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="fw-bold text-dark">${nombreGuardado}</div>
                        <span class="badge bg-primary rounded-pill">S/ ${precioGuardado}</span>
                    </div>
                    <div class="small text-muted mt-1"><i class="bi bi-upc-scan me-1"></i>SIN-SKU</div>
                `;

                btnLista.addEventListener('click', () => {
                    listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));
                    btnLista.classList.add('active', 'bg-primary-subtle', 'border-primary');
                    idPackSeleccionadoInput.value = btnLista.dataset.id || '0';
                    inputNombrePack.value = btnLista.dataset.nombre || '';
                    inputPrecioPack.value = Number(btnLista.dataset.precio || 0).toFixed(2);
                    lblEstadoPack.textContent = 'Editando Combo';
                    lblEstadoPack.className = 'badge bg-primary-subtle text-primary border border-primary-subtle mb-2';
                    seccionComponentes.style.opacity = '1';
                    seccionComponentes.style.pointerEvents = 'auto';
                    panelVacio.classList.add('d-none');
                    panelConfiguracion.classList.remove('d-none');
                    panelConfiguracion.classList.add('d-flex');
                    cargarComponentesDelPack(idPackSeleccionadoInput.value);
                });

                listaContenedor.prepend(btnLista);
                listaPacks.push(btnLista);
            }

            btnLista.click();

        } catch (error) {
            toastError(error.message);
        }
    });

    // --- LÓGICA DE TOM SELECT ---
    if (selectComponente) {
        window.tomSelectInstance = new TomSelect(selectComponente, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: 'Buscar producto, envase o insumo...',
            allowEmptyOption: true,
            maxOptions: 100,
            // ¡NUEVO!: Corrección del texto atascado
            onBlur: function() {
                // Al perder el foco, si el usuario no escogió nada (o escribió basura), se limpia el texto
                this.setTextboxValue(''); 
            }
        });
    }

    async function cargarComponentesDisponibles(termino = '') {
        try {
            const response = await fetch(buildUrl('buscar_componentes', { q: termino }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'Error al cargar ítems.');

            const items = Array.isArray(data.items) ? data.items : [];

            if (window.tomSelectInstance) {
                window.tomSelectInstance.clearOptions();
                const opciones = items.map(item => ({
                    value: String(item.id),
                    text: `${item.nombre} - [${(item.tipo_item || 'Ítem').toUpperCase()}]`
                }));
                window.tomSelectInstance.addOptions(opciones);
                window.tomSelectInstance.refreshOptions(false);
            }
        } catch (error) {
            console.error('Error al poblar Tom Select:', error.message);
        }
    }

    cargarComponentesDisponibles('');
})();