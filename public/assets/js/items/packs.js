(function () {
    const app = document.getElementById('packsApp');
    if (!app) {
        return;
    }

    // --- ELEMENTOS DEL DOM ---
    const listaPacks = Array.from(document.querySelectorAll('.pack-item-btn'));
    const inputBuscar = document.getElementById('buscarPack');
    const btnNuevoPack = document.getElementById('btnNuevoPack');
    const btnCerrarPanelMobile = document.getElementById('btnCerrarPanelMobile');

    const panelVacio = document.getElementById('panelVacio');
    const panelConfiguracion = document.getElementById('panelConfiguracion');

    // 1. Formulario Padre (Pack)
    const formPackPadre = document.getElementById('formPackPadre');
    const idPackSeleccionadoInput = document.getElementById('idPackSeleccionado');
    const inputNombrePack = document.getElementById('inputNombrePack');
    const inputPrecioPack = document.getElementById('inputPrecioPack');
    const lblEstadoPack = document.getElementById('lblEstadoPack');
    
    // 2. Sección de Componentes
    const seccionComponentes = document.getElementById('seccionComponentes');
    const formAgregar = document.getElementById('formAgregarComponente');
    const selectComponente = document.getElementById('selectComponente');
    const inputCantidad = document.getElementById('inputCantidad');
    const checkBonificacion = document.getElementById('checkBonificacion');

    const tbodyComponentes = document.querySelector('#tablaComponentes tbody');
    const filaVacia = document.getElementById('filaVacia');
    const templateFila = document.getElementById('templateFilaComponente');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // --- FUNCIONES DE UTILIDAD (Tus funciones originales) ---
    function buildUrl(action, params = null) {
        const currentUrl = new URL(window.location.href);
        let url;

        // Modo query-string (ej: /?ruta=items/packs)
        if (currentUrl.searchParams.has('ruta')) {
            const rutaActual = currentUrl.searchParams.get('ruta') || 'items/packs';
            const rutaBase = rutaActual.split('/').slice(0, 2).join('/') || 'items/packs';
            url = new URL(currentUrl.origin + currentUrl.pathname);
            url.searchParams.set('ruta', `${rutaBase}/${action}`);
        } else {
            // Modo URL amigable (ej: /items/packs)
            const path = currentUrl.pathname || '';
            const index = path.indexOf('/items/packs');
            const base = index >= 0 ? path.substring(0, index) + '/items/packs' : '/items/packs';
            url = new URL(base + '/' + action, currentUrl.origin);
        }

        if (params && typeof params === 'object') {
            Object.keys(params).forEach((key) => {
                url.searchParams.set(key, String(params[key]));
            });
        }
        return url.toString();
    }

    function toastError(message) {
        if (window.Swal) {
            window.Swal.fire({ icon: 'error', title: 'Error', text: message });
            return;
        }
        window.alert(message);
    }

    function toastSuccess(message) {
        if (window.Swal) {
            window.Swal.fire({ icon: 'success', title: 'Correcto', text: message, timer: 1500, showConfirmButton: false });
            return;
        }
    }

    function renderFilaVacia(mensaje = 'No hay componentes asignados a este pack.') {
        tbodyComponentes.innerHTML = '';
        filaVacia.innerHTML = `<td colspan="4" class="text-center text-muted py-4">${mensaje}</td>`;
        tbodyComponentes.appendChild(filaVacia);
    }

    function limpiarFormulario() {
        inputCantidad.value = '1';
        checkBonificacion.checked = false;
        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery(selectComponente).val('').trigger('change');
        } else {
            selectComponente.value = '';
        }
    }

    // --- LÓGICA DE INTERFAZ ---

    // A. Búsqueda en la lista izquierda
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function () {
            const termino = this.value.trim().toLowerCase();
            listaPacks.forEach((btn) => {
                btn.style.display = btn.textContent.toLowerCase().includes(termino) ? '' : 'none';
            });
        });
    }

    // B. Clic en "Nuevo Pack"
    if (btnNuevoPack) {
        btnNuevoPack.addEventListener('click', () => {
            // Quitar selección de la lista
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));

            // Resetear formulario padre
            idPackSeleccionadoInput.value = '0';
            inputNombrePack.value = '';
            inputPrecioPack.value = '';
            lblEstadoPack.textContent = 'Nuevo Combo';
            lblEstadoPack.className = 'badge bg-success-subtle text-success border border-success-subtle mb-2';

            // Bloquear sección de componentes hasta que se guarde el padre
            seccionComponentes.style.opacity = '0.5';
            seccionComponentes.style.pointerEvents = 'none';
            renderFilaVacia('Guarda el Combo primero para añadir componentes.');

            // Mostrar panel
            panelVacio.classList.add('d-none');
            panelConfiguracion.classList.remove('d-none');
            panelConfiguracion.classList.add('d-flex');
        });
    }

    // C. Clic en un Pack Existente
    listaPacks.forEach((btn) => {
        btn.addEventListener('click', () => {
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));
            btn.classList.add('active', 'bg-primary-subtle', 'border-primary');

            // Cargar datos en el formulario padre
            idPackSeleccionadoInput.value = btn.dataset.id || '0';
            inputNombrePack.value = btn.dataset.nombre || '';
            inputPrecioPack.value = Number(btn.dataset.precio || 0).toFixed(2);
            
            lblEstadoPack.textContent = 'Editando Combo';
            lblEstadoPack.className = 'badge bg-primary-subtle text-primary border border-primary-subtle mb-2';

            // Desbloquear sección de componentes
            seccionComponentes.style.opacity = '1';
            seccionComponentes.style.pointerEvents = 'auto';

            panelVacio.classList.add('d-none');
            panelConfiguracion.classList.remove('d-none');
            panelConfiguracion.classList.add('d-flex');

            cargarComponentesDelPack(idPackSeleccionadoInput.value);
        });
    });

    // D. Botón cerrar panel en móviles
    if (btnCerrarPanelMobile) {
        btnCerrarPanelMobile.addEventListener('click', () => {
            panelConfiguracion.classList.add('d-none');
            panelConfiguracion.classList.remove('d-flex');
            panelVacio.classList.remove('d-none');
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));
        });
    }

    // --- PETICIONES AL SERVIDOR ---

    // 1. Guardar el Pack Padre (Nombre y Precio)
    formPackPadre.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const body = new URLSearchParams({
                id: idPackSeleccionadoInput.value,
                nombre: inputNombrePack.value.trim(),
                precio_venta: inputPrecioPack.value,
                csrf_token: csrfToken
            });

            const response = await fetch(buildUrl('guardar_pack_padre'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            });

            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'Error al guardar el combo');

            toastSuccess('Combo guardado correctamente');
            
            // Si era nuevo, recargamos la página para que aparezca en la lista izquierda
            if (idPackSeleccionadoInput.value === '0') {
                setTimeout(() => window.location.reload(), 1200);
            }
        } catch (error) {
            toastError(error.message);
        }
    });

    // 2. Guardar un Componente (Tu código original)
    formAgregar.addEventListener('submit', async (event) => {
        event.preventDefault();

        const idPack = Number(idPackSeleccionadoInput.value || 0);
        const idItem = Number(selectComponente.value || 0);
        const cantidad = Number(String(inputCantidad.value || '0').replace(',', '.'));

        if (idPack <= 0) {
            toastError('Guarda el pack primero para poder agregar componentes.');
            return;
        }

        if (idItem <= 0 || cantidad <= 0) {
            toastError('Selecciona un componente y cantidad válida.');
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
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            });

            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo guardar el componente.');

            limpiarFormulario();
            await cargarComponentesDelPack(idPack);
            toastSuccess('Componente agregado.');
        } catch (error) {
            toastError(error.message);
        }
    });

    // --- CARGA DE TABLAS Y SELECTS ---

    function dibujarFilaEnTabla(data) {
        if (tbodyComponentes.contains(filaVacia)) {
            tbodyComponentes.removeChild(filaVacia);
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

        const btnEliminar = clon.querySelector('.btn-eliminar-componente');
        btnEliminar.addEventListener('click', async () => {
            const confirmar = window.confirm('¿Estás seguro de quitar este componente del Pack?');
            if (!confirmar) return;

            try {
                const body = new URLSearchParams({
                    id_detalle: String(data.id_detalle),
                    csrf_token: csrfToken,
                });
                const response = await fetch(buildUrl('eliminar_componente'), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: body.toString(),
                });
                const res = await response.json();
                if (!response.ok || !res.ok) throw new Error(res.mensaje || 'No se pudo eliminar.');

                tr.remove();
                if (tbodyComponentes.children.length === 0) renderFilaVacia();
            } catch (error) {
                toastError(error.message);
            }
        });

        tbodyComponentes.appendChild(clon);
    }

    async function cargarComponentesDelPack(idPack) {
        tbodyComponentes.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando...</td></tr>';
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
                return;
            }
            componentes.forEach((item) => dibujarFilaEnTabla(item));
        } catch (error) {
            renderFilaVacia();
            toastError(error.message);
        }
    }

    async function cargarComponentesDisponibles(termino = '') {
        try {
            const response = await fetch(buildUrl('buscar_componentes', { q: termino }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.mensaje || 'Error al cargar ítems.');

            const items = Array.isArray(data.items) ? data.items : [];
            const valorActual = selectComponente.value;

            selectComponente.innerHTML = '<option value="">Buscar producto, envase o insumo...</option>';
            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = `${item.nombre} (${item.sku || 'SIN-SKU'})`;
                selectComponente.appendChild(option);
            });

            if ([...selectComponente.options].some((opt) => opt.value === valorActual)) {
                selectComponente.value = valorActual;
            }

            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(selectComponente).trigger('change.select2');
            }
        } catch (error) {
            console.error('Error al poblar select:', error.message);
        }
    }

    // Inicializar select2 y cargar opciones disponibles
    if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery(selectComponente).select2({
            placeholder: 'Buscar producto, envase o insumo...',
            allowClear: true,
            width: '100%',
        });
    }
    
    // Llamada inicial para poblar el select
    cargarComponentesDisponibles('');
})();
