(function () {
    'use strict';

    function resetFields(containerEl) {
        if (!containerEl) return;
        containerEl.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = false;
            } else {
                el.value = '';
            }
            el.classList.remove('is-invalid');
            el.disabled = false;
        });
    }

    function toggleComercialFields(clienteEl, proveedorEl, containerEl, clienteSectionEl, proveedorSectionEl) {
        if (!containerEl) return;
        const showCliente = Boolean(clienteEl?.checked);
        const showProveedor = Boolean(proveedorEl?.checked);
        const showAny = showCliente || showProveedor;

        containerEl.classList.toggle('d-none', !showAny);
        if (!showAny) {
            resetFields(containerEl);
            return;
        }

        if (clienteSectionEl) {
            clienteSectionEl.classList.toggle('d-none', !showCliente);
            if (!showCliente) resetFields(clienteSectionEl);
        }

        if (proveedorSectionEl) {
            proveedorSectionEl.classList.toggle('d-none', !showProveedor);
            if (!showProveedor) resetFields(proveedorSectionEl);
        }
    }

    function addZonaRow(prefix, zona, listEl) {
        const key = zona.valor;
        if (!key || !listEl || listEl.querySelector(`tr[data-zona="${CSS.escape(key)}"]`)) return;

        const tr = document.createElement('tr');
        tr.dataset.zona = key;
        tr.innerHTML = `
            <td>
                <span>${zona.label}</span>
                <input type="hidden" name="zonas_exclusivas[]" value="${key}">
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" title="Quitar zona">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;

        tr.querySelector('button')?.addEventListener('click', () => tr.remove());
        listEl.appendChild(tr);
    }

    function fetchUbigeo(tipo, padreId) {
        const fd = new FormData();
        fd.append('accion', 'cargar_ubigeo');
        fd.append('tipo', tipo);
        fd.append('padre_id', padreId || '');

        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
            .then(r => r.json())
            .then(r => (r.ok && Array.isArray(r.data)) ? r.data : []);
    }

    function fillSelect(selectEl, items, placeholder = 'Seleccionar...') {
        if (!selectEl) return;
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nombre;
            selectEl.appendChild(opt);
        });
        selectEl.disabled = false;
    }

    function initDistribuidorZones(prefix) {
        const dep = document.getElementById(`${prefix}ZonaDepartamento`);
        const prov = document.getElementById(`${prefix}ZonaProvincia`);
        const dist = document.getElementById(`${prefix}ZonaDistrito`);
        const btn = document.getElementById(`${prefix}AgregarZonaBtn`);
        const list = document.getElementById(`${prefix}ZonasList`);
        if (!dep || !prov || !dist || !btn || !list) return;

        fetchUbigeo('departamentos', '').then(items => fillSelect(dep, items));

        dep.addEventListener('change', () => {
            prov.innerHTML = '<option value="">Seleccionar...</option>';
            dist.innerHTML = '<option value="">Seleccionar...</option>';
            prov.disabled = true;
            dist.disabled = true;
            if (!dep.value) return;
            fetchUbigeo('provincias', dep.value).then(items => fillSelect(prov, items));
        });

        prov.addEventListener('change', () => {
            dist.innerHTML = '<option value="">Seleccionar...</option>';
            dist.disabled = true;
            if (!prov.value) return;
            fetchUbigeo('distritos', prov.value).then(items => fillSelect(dist, items));
        });

        btn.addEventListener('click', () => {
            const depText = dep.options[dep.selectedIndex]?.text || '';
            const provText = prov.options[prov.selectedIndex]?.text || '';
            const distText = dist.options[dist.selectedIndex]?.text || '';
            if (!dep.value || !prov.value || !dist.value) return;

            addZonaRow(prefix, {
                valor: `${dep.value}|${prov.value}|${dist.value}`,
                label: `${depText} - ${provText} - ${distText}`
            }, list);
        });
    }

    function resetDistribuidorZones(prefix) {
        const list = document.getElementById(`${prefix}ZonasList`);
        if (list) list.innerHTML = '';
    }

    function setDistribuidorZones(prefix, zonas) {
        const list = document.getElementById(`${prefix}ZonasList`);
        if (!list) return;
        list.innerHTML = '';
        (zonas || []).forEach(z => addZonaRow(prefix, {
            valor: z.valor || `${z.departamento_id}|${z.provincia_id}|${z.distrito_id}`,
            label: z.label || `${z.departamento_nombre || ''} - ${z.provincia_nombre || ''} - ${z.distrito_nombre || ''}`
        }, list));
    }

    function toggleDistribuidorFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.classList.toggle('d-none', !show);
        if (!show) {
            const prefix = checkboxEl.id.replace('EsDistribuidor', '');
            resetDistribuidorZones(prefix);
        }
    }

    function bindDistribuidorToggle(prefix) {
        const checkboxEl = document.getElementById(`${prefix}EsDistribuidor`);
        const containerEl = document.getElementById(`${prefix}DistribuidorFields`);
        if (!checkboxEl || !containerEl) return;
        checkboxEl.addEventListener('change', () => toggleDistribuidorFields(checkboxEl, containerEl));
    }

    window.TercerosClientes = {
        toggleComercialFields,
        toggleDistribuidorFields,
        bindDistribuidorToggle,
        initDistribuidorZones,
        resetDistribuidorZones,
        setDistribuidorZones
    };
})();
