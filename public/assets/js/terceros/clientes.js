(function () {
    'use strict';

    // =========================================================================
    // GESTIÓN DE ZONAS (DISTRIBUIDOR)
    // =========================================================================

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
            validationSeq: 0
        };

        zoneManagers[prefix] = manager;
        return manager;
    }

    // Función auxiliar para resetear inputs
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

    // =========================================================================
    // LÓGICA DE TOGGLES (CLIENTE / PROVEEDOR / DISTRIBUIDOR)
    // =========================================================================

    function toggleComercialFields(clienteEl, proveedorEl, containerEl, clienteSectionEl, proveedorSectionEl, distribuidorSectionEl) {
        // Esta función se mantiene por compatibilidad si se usa, pero la lógica principal
        // de pestañas ahora reside en terceros.js (syncRoleTabs).
        // Sin embargo, es útil para resetear campos internos si se desmarca una opción.
        
        if (!containerEl) return;
        const showCliente = Boolean(clienteEl?.checked);
        const showProveedor = Boolean(proveedorEl?.checked);
        
        // La visibilidad del contenedor principal 'containerEl' ahora se maneja por pestañas,
        // pero podemos usar esto para limpiar datos cuando se ocultan.

        if (clienteSectionEl && !showCliente) {
            resetFields(clienteSectionEl);
        }

        if (proveedorSectionEl && !showProveedor) {
            resetFields(proveedorSectionEl);
        }
        
        // Distribuidor suele ser una sub-característica o un rol aparte.
        // Si tienes un checkbox "Es Distribuidor" separado, su lógica va en toggleDistribuidorFields.
    }

    function toggleDistribuidorFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.classList.toggle('d-none', !show);
        
        const prefix = checkboxEl.id.replace('EsDistribuidor', '').replace('es_distribuidor', ''); // Ajuste para ID flexible
        
        // Si se oculta, limpiamos la lista de zonas visualmente (opcional, o reseteamos manager)
        if (!show) {
            // resetDistribuidorZones(prefix); // Descomentar si se desea borrar zonas al desmarcar
        } else {
            // Inicializar manager si no existe o refrescar vista
            const manager = createManager(prefix);
            renderZones(manager);
        }
    }

    // =========================================================================
    // API UBIGEO Y ZONAS INTERNAS
    // =========================================================================

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
        .then(r => (r.ok && Array.isArray(r.data)) ? r.data : [])
        .catch(err => { console.error("Error ubigeo zonas", err); return []; });
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

    function addZone(manager, zona, status = ZONE_STATUS.TEMP) {
        const key = zoneKey(zona.departamento_id, zona.provincia_id, zona.distrito_id);
        if (!zona.departamento_id || manager.zones.has(key)) return;

        manager.zones.set(key, {
            ...zona,
            valor: key, // Identificador compuesto para el backend
            label: zona.label || buildLabel(zona),
            status
        });

        renderZones(manager);
    }

    function removeZone(manager, key) {
        manager.zones.delete(key);
        manager.conflicts.delete(key); // Limpiar conflicto si existía
        renderZones(manager);
    }

    function renderZones(manager) {
        if (!manager.list) return;
        manager.list.innerHTML = '';
        
        if (manager.zones.size === 0) {
            manager.list.innerHTML = '<tr><td colspan="3" class="text-center text-muted small">No hay zonas asignadas</td></tr>';
            return;
        }

        manager.zones.forEach((zona, key) => {
            const status = manager.conflicts.has(key) ? ZONE_STATUS.CONFLICT : zona.status;
            const tr = document.createElement('tr');
            
            let badgeClass = 'bg-secondary';
            let badgeText = 'Temporal';
            
            if (status === ZONE_STATUS.SAVED) { badgeClass = 'bg-success'; badgeText = 'Guardada'; }
            else if (status === ZONE_STATUS.CONFLICT) { badgeClass = 'bg-danger'; badgeText = 'Conflicto'; }
            else { badgeClass = 'bg-info text-dark'; badgeText = 'Nueva'; }

            tr.innerHTML = `
                <td>
                    <span class="d-block text-truncate" style="max-width: 250px;" title="${zona.label}">${zona.label}</span>
                    <input type="hidden" name="zonas_exclusivas[${key}][dep]" value="${zona.departamento_id}">
                    <input type="hidden" name="zonas_exclusivas[${key}][prov]" value="${zona.provincia_id}">
                    <input type="hidden" name="zonas_exclusivas[${key}][dist]" value="${zona.distrito_id}">
                </td>
                <td><span class="badge ${badgeClass}">${badgeText}</span></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0" title="Quitar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>`;
            
            tr.querySelector('button')?.addEventListener('click', () => removeZone(manager, key));
            manager.list.appendChild(tr);
        });
    }

    // =========================================================================
    // EXPORTABLE: INICIALIZACIÓN DE ZONAS
    // =========================================================================

    function initDistribuidorZones(prefix) {
        const manager = createManager(prefix);
        const { dep, prov, dist, btn } = manager;
        
        if (!dep || !prov || !dist || !btn) return; // Si no existen los elementos en el DOM, salir

        // Evitar doble inicialización
        if (dep.dataset.zonesInit === 'true') return;
        dep.dataset.zonesInit = 'true';

        // Cargar departamentos iniciales
        fetchUbigeo('departamentos', '').then(items => fillSelect(dep, items));

        dep.addEventListener('change', () => {
            prov.innerHTML = '<option value="">Seleccionar...</option>'; prov.disabled = true;
            dist.innerHTML = '<option value="">Seleccionar...</option>'; dist.disabled = true;
            if (dep.value) fetchUbigeo('provincias', dep.value).then(items => fillSelect(prov, items));
        });

        prov.addEventListener('change', () => {
            dist.innerHTML = '<option value="">Seleccionar...</option>'; dist.disabled = true;
            if (prov.value) fetchUbigeo('distritos', prov.value).then(items => fillSelect(dist, items));
        });

        btn.addEventListener('click', () => {
            if (!dep.value) return; // Mínimo departamento requerido

            const zona = {
                departamento_id: dep.value,
                provincia_id: prov.value || '',
                distrito_id: dist.value || '',
                departamento_nombre: dep.options[dep.selectedIndex]?.text || '',
                provincia_nombre: prov.value ? prov.options[prov.selectedIndex]?.text : '',
                distrito_nombre: dist.value ? dist.options[dist.selectedIndex]?.text : ''
            };
            
            zona.label = buildLabel(zona);
            addZone(manager, zona, ZONE_STATUS.TEMP);
            
            // Opcional: Resetear selectores inferiores para facilitar nueva entrada
            dist.value = '';
            // prov.value = ''; // Si se quiere mantener la provincia seleccionada, comentar esta línea
        });
    }

    function setDistribuidorZones(prefix, zonas, status = ZONE_STATUS.SAVED) {
        const manager = createManager(prefix);
        manager.zones.clear();
        manager.conflicts.clear();

        (zonas || []).forEach(z => {
            addZone(manager, {
                departamento_id: z.departamento_id || z.dep,
                provincia_id: z.provincia_id || z.prov || '',
                distrito_id: z.distrito_id || z.dist || '',
                departamento_nombre: z.departamento_nombre || '',
                provincia_nombre: z.provincia_nombre || '',
                distrito_nombre: z.distrito_nombre || '',
                label: z.label || '' // Si viene del backend, idealmente traer el label armado
            }, status);
        });
        
        renderZones(manager);
    }

    // =========================================================================
    // EXPORTACIÓN AL SCOPE GLOBAL
    // =========================================================================

    window.TercerosClientes = {
        toggleComercialFields,
        toggleDistribuidorFields,
        initDistribuidorZones,
        setDistribuidorZones,
        // Funciones wrapper para mantener compatibilidad si terceros.js las llama
        bindDistribuidorToggle: (prefix) => {
            const chk = document.getElementById(`${prefix}EsDistribuidor`);
            const box = document.getElementById(`${prefix}DistribuidorFields`);
            if(chk && box) {
                chk.addEventListener('change', () => toggleDistribuidorFields(chk, box));
                // Estado inicial
                toggleDistribuidorFields(chk, box);
            }
        },
        loadSavedZones: (prefix, id) => {
            // Simulación de carga, en producción esto haría fetch al backend si el ID existe
            // Para edición, los datos suelen venir en el atributo data-zonas del botón editar
            return Promise.resolve(); 
        }
    };

})();