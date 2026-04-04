(function () {
    const app = document.getElementById('packsApp');
    if (!app) {
        return;
    }

    const listaPacks = Array.from(document.querySelectorAll('.pack-item-btn'));
    const inputBuscar = document.getElementById('buscarPack');
    const panelVacio = document.getElementById('panelVacio');
    const panelConfiguracion = document.getElementById('panelConfiguracion');

    const lblNombrePack = document.getElementById('lblNombrePack');
    const lblPrecioPack = document.getElementById('lblPrecioPack');
    const idPackSeleccionadoInput = document.getElementById('idPackSeleccionado');

    const formAgregar = document.getElementById('formAgregarComponente');
    const selectComponente = document.getElementById('selectComponente');
    const inputCantidad = document.getElementById('inputCantidad');
    const checkBonificacion = document.getElementById('checkBonificacion');

    const tbodyComponentes = document.querySelector('#tablaComponentes tbody');
    const filaVacia = document.getElementById('filaVacia');
    const templateFila = document.getElementById('templateFilaComponente');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function getBaseUrl() {
        const path = window.location.pathname || '';
        const index = path.indexOf('/items/packs');
        return index >= 0 ? path.substring(0, index) + '/items/packs' : '/items/packs';
    }

    function buildUrl(action, params = null) {
        const base = getBaseUrl();
        const url = new URL(base + '/' + action, window.location.origin);
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
            window.Swal.fire({ icon: 'success', title: 'Correcto', text: message, timer: 1200, showConfirmButton: false });
            return;
        }
    }

    function renderFilaVacia() {
        tbodyComponentes.innerHTML = '';
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
            badgeTipo.textContent = 'Bonificación / Regalo';
            badgeTipo.classList.add('bg-warning-subtle', 'text-warning-emphasis', 'border', 'border-warning-subtle');
        } else {
            badgeTipo.textContent = 'Componente Base';
            badgeTipo.classList.add('bg-info-subtle', 'text-info', 'border', 'border-info-subtle');
        }

        const btnEliminar = clon.querySelector('.btn-eliminar-componente');
        btnEliminar.addEventListener('click', async () => {
            const confirmar = window.confirm('¿Estás seguro de quitar este componente del Pack?');
            if (!confirmar) {
                return;
            }

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
                if (!response.ok || !res.ok) {
                    throw new Error(res.mensaje || 'No se pudo eliminar el componente.');
                }

                tr.remove();
                if (tbodyComponentes.children.length === 0) {
                    renderFilaVacia();
                }
            } catch (error) {
                toastError(error.message || 'No se pudo eliminar el componente.');
            }
        });

        tbodyComponentes.appendChild(clon);
    }

    async function cargarComponentesDelPack(idPack) {
        tbodyComponentes.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Cargando componentes...</td></tr>';

        try {
            const response = await fetch(buildUrl('obtener_componentes', { id_pack: idPack }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.mensaje || 'No se pudo cargar la receta del pack.');
            }

            tbodyComponentes.innerHTML = '';
            const componentes = Array.isArray(data.items) ? data.items : [];
            if (componentes.length === 0) {
                renderFilaVacia();
                return;
            }

            componentes.forEach((item) => dibujarFilaEnTabla(item));
        } catch (error) {
            renderFilaVacia();
            toastError(error.message || 'No se pudo cargar la receta del pack.');
        }
    }

    async function cargarComponentesDisponibles(termino = '') {
        try {
            const response = await fetch(buildUrl('buscar_componentes', { q: termino }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.mensaje || 'No se pudo cargar la lista de componentes.');
            }

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
            toastError(error.message || 'No se pudo cargar la lista de componentes.');
        }
    }

    if (inputBuscar) {
        inputBuscar.addEventListener('input', function () {
            const termino = this.value.trim().toLowerCase();
            listaPacks.forEach((btn) => {
                btn.style.display = btn.textContent.toLowerCase().includes(termino) ? '' : 'none';
            });
        });
    }

    listaPacks.forEach((btn) => {
        btn.addEventListener('click', () => {
            listaPacks.forEach((b) => b.classList.remove('active', 'bg-primary-subtle', 'border-primary'));
            btn.classList.add('active', 'bg-primary-subtle', 'border-primary');

            idPackSeleccionadoInput.value = btn.dataset.id || '0';
            lblNombrePack.textContent = btn.dataset.nombre || 'Pack';
            lblPrecioPack.textContent = `S/ ${Number(btn.dataset.precio || 0).toFixed(2)}`;

            panelVacio.classList.add('d-none');
            panelConfiguracion.classList.remove('d-none');

            cargarComponentesDelPack(idPackSeleccionadoInput.value);
        });
    });

    if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery(selectComponente).select2({
            placeholder: 'Buscar producto, envase o insumo...',
            allowClear: true,
            width: '100%',
        });
    }

    formAgregar.addEventListener('submit', async (event) => {
        event.preventDefault();

        const idPack = Number(idPackSeleccionadoInput.value || 0);
        const idItem = Number(selectComponente.value || 0);
        const cantidad = Number(String(inputCantidad.value || '0').replace(',', '.'));

        if (idPack <= 0) {
            toastError('Selecciona un pack para continuar.');
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
            if (!response.ok || !data.ok) {
                throw new Error(data.mensaje || 'No se pudo guardar el componente.');
            }

            limpiarFormulario();
            await cargarComponentesDelPack(idPack);
            toastSuccess('Componente agregado correctamente.');
        } catch (error) {
            toastError(error.message || 'No se pudo guardar el componente.');
        }
    });

    cargarComponentesDisponibles('');
})();
