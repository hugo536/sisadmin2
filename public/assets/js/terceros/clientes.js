(function () {
    'use strict';

    const ZONE_STATUS = {
        TEMP: 'temp',
        SAVED: 'saved',
        CONFLICT: 'conflict'
    };

    const zoneManagers = {};

    function buildLabel(zona) {
        return [zona.departamento_nombre, zona.provincia_nombre, zona.distrito_nombre].filter(Boolean).join(' - ');
    }

    function createManager(prefix) {
        if (zoneManagers[prefix]) return zoneManagers[prefix];

        const manager = {
            prefix,
            zones: new Map(),
            dep: document.getElementById(`${prefix}ZonaDepartamento`),
            prov: document.getElementById(`${prefix}ZonaProvincia`),
            dist: document.getElementById(`${prefix}ZonaDistrito`),
            btn: document.getElementById(`${prefix}AgregarZonaBtn`),
            list: document.getElementById(`${prefix}ZonasList`),
            conflicts: new Set(),
            focusKey: '',
            validationSeq: 0
        };

        zoneManagers[prefix] = manager;
        return manager;
    }

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

    function toggleComercialFields(clienteEl, proveedorEl, containerEl, clienteSectionEl, proveedorSectionEl, distribuidorSectionEl) {
        if (!containerEl) return;
        const showCliente = Boolean(clienteEl?.checked);
        const showProveedor = Boolean(proveedorEl?.checked);
        const showAny = showCliente || showProveedor;

        containerEl.classList.toggle('d-none', !showAny);
        if (!showAny) {
            resetFields(containerEl);
            if (distribuidorSectionEl) {
                const checkboxEl = distribuidorSectionEl.querySelector('[name="es_distribuidor"]');
                const distribuidorFieldsEl = distribuidorSectionEl.querySelector('[id$="DistribuidorFields"]');
                if (checkboxEl) checkboxEl.checked = false;
                if (distribuidorFieldsEl) toggleDistribuidorFields(checkboxEl, distribuidorFieldsEl);
            }
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

        if (distribuidorSectionEl) {
            distribuidorSectionEl.classList.toggle('d-none', !showCliente);
            if (!showCliente) {
                const checkboxEl = distribuidorSectionEl.querySelector('[name="es_distribuidor"]');
                const distribuidorFieldsEl = distribuidorSectionEl.querySelector('[id$="DistribuidorFields"]');
                if (checkboxEl) checkboxEl.checked = false;
                if (distribuidorFieldsEl) toggleDistribuidorFields(checkboxEl, distribuidorFieldsEl);
            }
        }
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

    function zoneKey(dep, prov, dist) {
        return `${dep || ''}|${prov || ''}|${dist || ''}`;
    }

    function addZone(manager, zona, status = ZONE_STATUS.TEMP, options = {}) {
        const key = zoneKey(zona.departamento_id, zona.provincia_id, zona.distrito_id);
        if (!zona.departamento_id || manager.zones.has(key)) return;

        manager.zones.set(key, {
            ...zona,
            valor: key,
            label: zona.label || buildLabel(zona),
            status
        });

        if (options.focus !== false) {
            manager.focusKey = key;
        }
        renderZones(manager);
        if (options.validate !== false) {
            validateConflicts(manager);
        }
    }

    function removeZone(manager, key) {
        manager.zones.delete(key);
        manager.conflicts.delete(key);
        renderZones(manager);
        validateConflicts(manager);
    }

    function renderZones(manager) {
        manager.list.innerHTML = '';
        manager.zones.forEach((zona, key) => {
            const status = manager.conflicts.has(key) ? ZONE_STATUS.CONFLICT : zona.status;
            const tr = document.createElement('tr');
            tr.dataset.zona = key;
            tr.innerHTML = `
                <td>
                    <span>${zona.label}</span>
                    <input type="hidden" name="zonas_exclusivas[]" value="${key}">
                </td>
                <td>
                    <span class="badge ${status === ZONE_STATUS.SAVED ? 'bg-success' : status === ZONE_STATUS.CONFLICT ? 'bg-danger' : 'bg-primary'}">
                        ${status === ZONE_STATUS.SAVED ? 'Guardada' : status === ZONE_STATUS.CONFLICT ? 'Conflicto' : 'Temporal'}
                    </span>
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" title="Quitar zona">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>`;
            tr.querySelector('button')?.addEventListener('click', () => removeZone(manager, key));
            manager.list.appendChild(tr);
        });
    }

    function getDistribuidorId(manager) {
        return manager.prefix === 'edit' ? (document.getElementById('editId')?.value || '') : '';
    }

    function validateConflicts(manager) {
        const zones = Array.from(manager.zones.keys());
        const fd = new FormData();
        fd.append('accion', 'validar_conflictos_zonas');
        fd.append('distribuidor_id', getDistribuidorId(manager));
        zones.forEach(z => fd.append('zonas[]', z));

        const currentSeq = ++manager.validationSeq;

        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
            .then(r => r.json())
            .then(r => {
                if (currentSeq !== manager.validationSeq) return;
                manager.conflicts.clear();
                (r.conflictos || []).forEach(item => manager.conflicts.add(item.valor));
                renderZones(manager);
            })
            .catch(() => undefined);
    }

    function setDistribuidorZones(prefix, zonas, status = ZONE_STATUS.SAVED) {
        const manager = createManager(prefix);
        manager.zones.clear();
        manager.conflicts.clear();

        (zonas || []).forEach(z => addZone(manager, {
            departamento_id: z.departamento_id,
            provincia_id: z.provincia_id || '',
            distrito_id: z.distrito_id || '',
            departamento_nombre: z.departamento_nombre || '',
            provincia_nombre: z.provincia_nombre || '',
            distrito_nombre: z.distrito_nombre || '',
            label: z.label || buildLabel(z)
        }, status, { validate: false, focus: false }));

        if (manager.zones.size > 0) {
            manager.focusKey = Array.from(manager.zones.keys())[0];
        }

        renderZones(manager);
        validateConflicts(manager);
    }

    function resetDistribuidorZones(prefix) {
        const manager = createManager(prefix);
        manager.zones.clear();
        manager.conflicts.clear();
        renderZones(manager);
    }

    function initDistribuidorZones(prefix) {
        const manager = createManager(prefix);
        const { dep, prov, dist, btn } = manager;
        if (!dep || !prov || !dist || !btn || !manager.list) return;

        if (!dep.dataset.bound) {
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
                // 1. VALIDACIÓN: Asegurar que los 3 campos tengan valor
                if (!dep.value || !prov.value || !dist.value) {
                    // Si tienes SweetAlert cargado (como vi en tu otro archivo):
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Datos incompletos',
                            text: 'Por favor seleccione Departamento, Provincia y Distrito.'
                        });
                    } else {
                        console.warn('Por favor seleccione Departamento, Provincia y Distrito.');
                    }
                    return; // Detiene la ejecución aquí
                }

                const zona = {
                    departamento_id: dep.value,
                    provincia_id: prov.value,
                    distrito_id: dist.value,
                    // Al validar arriba, ya nos aseguramos de que no tome el texto "Seleccionar..."
                    departamento_nombre: dep.options[dep.selectedIndex]?.text || '',
                    provincia_nombre: prov.options[prov.selectedIndex]?.text || '',
                    distrito_nombre: dist.options[dist.selectedIndex]?.text || ''
                };
                
                zona.label = buildLabel(zona);
                addZone(manager, zona, ZONE_STATUS.TEMP);
                
                // Opcional: Limpiar los selectores después de agregar para evitar duplicados rápidos
                // prov.value = ""; 
                // dist.value = "";
                // dist.disabled = true;
            });

            dep.dataset.bound = '1';
        }
    }

    function toggleDistribuidorFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.classList.toggle('d-none', !show);
        const prefix = checkboxEl.id.replace('EsDistribuidor', '');
        const manager = createManager(prefix);

        if (show) {
            manager.focusKey = '';
        } else {
            resetDistribuidorZones(prefix);
        }
    }

    function bindDistribuidorToggle(prefix) {
        const checkboxEl = document.getElementById(`${prefix}EsDistribuidor`);
        const containerEl = document.getElementById(`${prefix}DistribuidorFields`);
        if (!checkboxEl || !containerEl) return;
        checkboxEl.addEventListener('change', () => toggleDistribuidorFields(checkboxEl, containerEl));
    }

    function loadSavedZones(prefix, distribuidorId) {
        if (!distribuidorId) {
            setDistribuidorZones(prefix, [], ZONE_STATUS.SAVED);
            return Promise.resolve();
        }

        const fd = new FormData();
        fd.append('accion', 'cargar_zonas_distribuidor');
        fd.append('distribuidor_id', distribuidorId);

        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
            .then(r => r.json())
            .then(r => {
                setDistribuidorZones(prefix, r.data || [], ZONE_STATUS.SAVED);
            });
    }

    window.TercerosClientes = {
        toggleComercialFields,
        toggleDistribuidorFields,
        bindDistribuidorToggle,
        initDistribuidorZones,
        resetDistribuidorZones,
        setDistribuidorZones,
        loadSavedZones
    };
})();
