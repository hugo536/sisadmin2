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
    const btnEliminarPack = document.getElementById('btnEliminarPack');
    
    // Elementos del Switch Inteligente
    const checkIncluyeEnvase = document.getElementById('combo_incluye_envase');
    const contenedorSwitchEnvase = document.getElementById('contenedorSwitchEnvase');
    
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

    async function confirmarAccion({ title = '¿Confirmar acción?', text = '', icon = 'question', confirmButtonText = 'Aceptar', cancelButtonText = 'Cancelar', confirmButtonColor = '#0d6efd' } = {}) {
        if (window.Swal) {
            const result = await window.Swal.fire({ icon, title, text, showCancelButton: true, confirmButtonText, cancelButtonText, confirmButtonColor, reverseButtons: true });
            return !!result.isConfirmed;
        }
        return window.confirm(`${title}\n${text}`.trim());
    }

    function setEstadoBotonEliminar(idPack) {
        if (!btnEliminarPack) return;
        const visible = Number(idPack || 0) > 0;
        btnEliminarPack.classList.toggle('d-none', !visible);
    }

    function refrescarUI_Tabla() {
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

    // MAGIA VISUAL: Evalúa si muestra el switch o lo oculta
    function evaluarVisibilidadSwitchEnvase() {
        if (!contenedorSwitchEnvase) return;
        
        // Busca si hay al menos un componente en memoria o en la tabla que requiera envase
        const algunRetornableLocales = componentesLocales.some(c => Number(c.requiere_envase) === 1);
        const algunRetornableTabla = Array.from(tbodyComponentes.querySelectorAll('tr')).some(tr => tr.dataset.requiereEnvase === '1');

        if (algunRetornableLocales || algunRetornableTabla) {
            contenedorSwitchEnvase.classList.remove('d-none'); // Mostrar
        } else {
            contenedorSwitchEnvase.classList.add('d-none'); // Ocultar
            if (checkIncluyeEnvase) checkIncluyeEnvase.checked = false; // Apagar por seguridad
        }
    }

    // --- FUNCIONES DE DIBUJADO DE TABLA ---
    
    function renderTablaLocales() {
        if (componentesLocales.length === 0) {
            renderFilaVacia('Agrega productos a la lista. Se guardarán al crear el combo.');
            evaluarVisibilidadSwitchEnvase();
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

            const celdas = clon.querySelectorAll('td');
            const tdAccion = celdas[celdas.length - 1]; 
            
            if (window.IconosAccion) {
                tdAccion.innerHTML = window.IconosAccion.crear('eliminar', index, 'btn-eliminar-componente');
            }

            const btnEliminar = clon.querySelector('.btn-eliminar-componente');
            btnEliminar.addEventListener('click', async () => {
                const confirmar = await confirmarAccion({ title: '¿Quitar componente temporal?', text: 'Este componente se eliminará de la lista antes de guardar el combo.', icon: 'warning', confirmButtonText: 'Sí, quitar', confirmButtonColor: '#dc3545' });
                if (!confirmar) return;

                componentesLocales.splice(index, 1);
                renderTablaLocales();
                evaluarVisibilidadSwitchEnvase(); // Reevaluar al borrar
            });

            tbodyComponentes.appendChild(clon);
        });
        
        refrescarUI_Tabla();
        evaluarVisibilidadSwitchEnvase();
    }

    function dibujarFilaEnTablaBD(data) {
        if (tbodyComponentes.querySelector('#filaVacia')) {
            tbodyComponentes.innerHTML = '';
        }

        const clon = templateFila.content.cloneNode(true);
        const tr = clon.querySelector('tr');
        tr.dataset.idDetalle = String(data.id_detalle || 0);
        tr.dataset.requiereEnvase = String(data.requiere_envase || 0); // Guardar si exige envase

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

            const confirmar = await confirmarAccion({ title: '¿Quitar componente del combo?', text: 'Este componente dejará de formar parte del combo guardado.', icon: 'warning', confirmButtonText: 'Sí, quitar', confirmButtonColor: '#dc3545' });
            if (!confirmar) return;

            try {
                const body = new URLSearchParams({ id_detalle: String(data.id_detalle), csrf_token: csrfToken });
                const response = await fetch(buildUrl('eliminar_componente'), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString(),
                });
                const res = await response.json();
                if (!response.ok || !res.ok) throw new Error(res.mensaje || 'No se pudo eliminar.');

                tr.remove();
                
                if (window.ERPTable && window.bootstrap?.Tooltip) {
                    const tooltipInstance = bootstrap.Tooltip.getInstance(btnEliminar);
                    if (tooltipInstance) tooltipInstance.dispose();
                }
                evaluarVisibilidadSwitchEnvase(); // Reevaluar al borrar

            } catch (error) {
                toastError(error.message);
            }
        });

        tbodyComponentes.appendChild(clon);
    }

    async function cargarComponentesDelPack(idPack) {
        if (window.ERPTable && typeof window.ERPTable.showLoading === 'function') {
            window.ERPTable.showLoading(tablaComponentes);
        } else {
            tbodyComponentes.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando...</td></tr>';
        }

        try {
            const response = await fetch(buildUrl('obtener_componentes', { id_pack: idPack }), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
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
        evaluarVisibilidadSwitchEnvase();
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
            if (checkIncluyeEnvase) checkIncluyeEnvase.checked = false;
            
            lblEstadoPack.textContent = 'Nuevo Combo';
            lblEstadoPack.className = 'badge bg-success-subtle text-success border border-success-subtle mb-2';
            setEstadoBotonEliminar(0);

            seccionComponentes.style.opacity = '1';
            seccionComponentes.style.pointerEvents = 'auto';
            
            componentesLocales = []; 
            renderTablaLocales();
            evaluarVisibilidadSwitchEnvase();

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
            
            if (checkIncluyeEnvase) checkIncluyeEnvase.checked = (btn.dataset.incluyeEnvase === '1');
            
            lblEstadoPack.textContent = 'Editando Combo';
            lblEstadoPack.className = 'badge bg-primary-subtle text-primary border border-primary-subtle mb-2';
            setEstadoBotonEliminar(idPackSeleccionadoInput.value);

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
                es_bonificacion: checkBonificacion.checked ? 1 : 0,
                requiere_envase: optionSelect ? Number(optionSelect.requiere_envase || 0) : 0 // Atrapar el dato
            });
            limpiarFormulario();
            renderTablaLocales();
            evaluarVisibilidadSwitchEnvase(); // Evaluar si debe mostrarse
            return; 
        }

        try {
            const body = new URLSearchParams({ id_pack: String(idPack), id_item: String(idItem), cantidad: String(cantidad), es_bonificacion: checkBonificacion.checked ? '1' : '0', csrf_token: csrfToken });
            const response = await fetch(buildUrl('agregar_componente'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() });
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
            const incluyeEnvaseValor = (checkIncluyeEnvase && !contenedorSwitchEnvase.classList.contains('d-none') && checkIncluyeEnvase.checked) ? '1' : '0';

            const body = new URLSearchParams({
                id: idPackSeleccionadoInput.value,
                nombre: inputNombrePack.value.trim(),
                precio_venta: inputPrecioPack.value,
                incluye_envase: incluyeEnvaseValor, // Mandamos el valor al backend
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
                    const bodyComp = new URLSearchParams({ id_pack: idGuardado, id_item: String(comp.id_item), cantidad: String(comp.cantidad), es_bonificacion: String(comp.es_bonificacion), csrf_token: csrfToken });
                    await fetch(buildUrl('agregar_componente'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: bodyComp.toString() });
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
                btnLista.dataset.incluyeEnvase = incluyeEnvaseValor;
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
                btnLista.dataset.incluyeEnvase = incluyeEnvaseValor;
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
                    
                    if (checkIncluyeEnvase) checkIncluyeEnvase.checked = (btnLista.dataset.incluyeEnvase === '1');
                    
                    lblEstadoPack.textContent = 'Editando Combo';
                    lblEstadoPack.className = 'badge bg-primary-subtle text-primary border border-primary-subtle mb-2';
                    setEstadoBotonEliminar(idPackSeleccionadoInput.value);
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

    if (btnEliminarPack) {
        btnEliminarPack.addEventListener('click', async () => {
            const idPack = Number(idPackSeleccionadoInput.value || 0);
            if (idPack <= 0) return;

            const confirmado = await confirmarAccion({ title: '¿Eliminar combo?', text: 'Se ocultará del catálogo.', icon: 'warning', confirmButtonText: 'Sí, eliminar', confirmButtonColor: '#dc3545' });
            if (!confirmado) return;

            try {
                const body = new URLSearchParams({ id_pack: String(idPack), csrf_token: csrfToken });
                const response = await fetch(buildUrl('eliminar_pack'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo eliminar.');

                const idx = listaPacks.findIndex((btn) => Number(btn.dataset.id || 0) === idPack);
                if (idx >= 0) {
                    listaPacks[idx].remove();
                    listaPacks.splice(idx, 1);
                }

                idPackSeleccionadoInput.value = '0';
                inputNombrePack.value = '';
                inputPrecioPack.value = '';
                panelConfiguracion.classList.add('d-none');
                panelConfiguracion.classList.remove('d-flex');
                panelVacio.classList.remove('d-none');
                setEstadoBotonEliminar(0);
                renderFilaVacia('Guarda el Combo primero para añadir componentes.');

                toastSuccess(data.mensaje || 'Combo eliminado correctamente.');
            } catch (error) {
                toastError(error.message);
            }
        });
    }

    async function cargarComponentesDisponibles(termino = '') {
        try {
            const response = await fetch(buildUrl('buscar_componentes', { q: termino }), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'Error al cargar ítems.');

            const items = Array.isArray(data.items) ? data.items : [];
            return items.map(item => ({
                value: String(item.id),
                text: `${item.nombre} - [${(item.tipo_item || 'Ítem').toUpperCase()}]`,
                requiere_envase: item.requiere_envase // Guardamos si el item pide envase
            }));
        } catch (error) {
            console.error('Error al poblar Tom Select:', error.message);
            return [];
        }
    }

    if (selectComponente) {
        window.tomSelectInstance = new TomSelect(selectComponente, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: 'Buscar producto, envase o insumo...',
            allowEmptyOption: true,
            maxOptions: 100,
            preload: true,
            loadThrottle: 250,
            load: async function(query, callback) {
                const opciones = await cargarComponentesDisponibles(query || '');
                callback(opciones);
            },
            onBlur: function() {
                this.setTextboxValue('');
            }
        });
    }

    setEstadoBotonEliminar(idPackSeleccionadoInput?.value || 0);
})();