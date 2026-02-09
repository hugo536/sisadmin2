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

    function toggleComercialFields(clienteEl, proveedorEl, containerEl) {
        if (!containerEl) return;
        const show = Boolean(clienteEl?.checked || proveedorEl?.checked);
        containerEl.classList.toggle('d-none', !show);
        if (!show) resetFields(containerEl);
    }

    function toggleDistribuidorFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.classList.toggle('d-none', !show);
        if (!show) resetFields(containerEl);
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
        bindDistribuidorToggle
    };
})();
