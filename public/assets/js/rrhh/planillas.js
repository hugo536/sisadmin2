document.addEventListener('DOMContentLoaded', () => {

    const formFiltros = document.getElementById('formFiltrosPlanillas');
    const searchInput = document.getElementById('searchPlanilla');

    // 1. CANDADO AL BUSCADOR
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });
    }

    if (!formFiltros) return;

    // 2. CANDADO AL FORMULARIO
    formFiltros.addEventListener('submit', (e) => {
        e.preventDefault();
        triggerAutoSubmit();
    });

    const inputDesde = formFiltros.querySelector('input[name="desde"]');
    const inputHasta = formFiltros.querySelector('input[name="hasta"]');
    const selectEmpleado = formFiltros.querySelector('select[name="id_tercero"]');

    // --- EL DEBOUNCE (Retraso inteligente) ---
    let debounceTimer;

    const autoSubmitForm = async () => {
        
        const paginationInfo = document.getElementById('planillasPaginationInfo');
        if (paginationInfo) paginationInfo.textContent = 'Calculando nómina...';

        // Usamos nuestro NUEVO superpoder global para mostrar el Spinner
        if (window.planillasManager) window.planillasManager.showLoading();

        const url = new URL(window.location.href);
        if (inputDesde) url.searchParams.set('desde', inputDesde.value);
        if (inputHasta) url.searchParams.set('hasta', inputHasta.value);
        if (selectEmpleado) url.searchParams.set('id_tercero', selectEmpleado.value);

        try {
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Error al conectar con el servidor');
            const html = await response.text();

            const parser = new DOMParser();
            const docVirtual = parser.parseFromString(html, 'text/html');

            // 1. Reemplazamos los Totales
            ['totalPlanilla', 'totalExtras', 'totalDescuentos'].forEach(id => {
                const el = document.getElementById(id);
                const virtualEl = docVirtual.getElementById(id);
                if (el && virtualEl) el.innerHTML = virtualEl.innerHTML;
            });

            // 2. Reemplazamos la Tabla y el Badge
            const currentTbody = document.querySelector('#planillasTable tbody');
            const newTbody = docVirtual.querySelector('#planillasTable tbody');
            if (currentTbody && newTbody) currentTbody.innerHTML = newTbody.innerHTML;

            const badge = document.querySelector('.badge.bg-primary-subtle');
            const newBadge = docVirtual.querySelector('.badge.bg-primary-subtle');
            if (badge && newBadge) badge.innerHTML = newBadge.innerHTML;

            // 3. Refrescamos la paginación global (Esto vuelve a leer las filas y quita el spinner de paso si se reinicia)
            if (window.planillasManager && typeof window.planillasManager.refresh === 'function') {
                window.planillasManager.refresh();
            }

            if (window.ERPTable && typeof window.ERPTable.initTooltips === 'function') {
                window.ERPTable.initTooltips();
            }

            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Error en la actualización AJAX:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Aviso', 'No se pudo actualizar en tiempo real.', 'warning');
            }
        } finally {
            // Usamos nuestro NUEVO superpoder global para ocultar el Spinner
            if (window.planillasManager) window.planillasManager.hideLoading();
        }
    };

    // Función que dispara el AJAX con un retraso de 500ms
    const triggerAutoSubmit = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            autoSubmitForm(); 
        }, 150); 
    };

    // Escuchar cambios en los filtros y activar el temporizador
    if (inputDesde) inputDesde.addEventListener('change', triggerAutoSubmit);
    if (inputHasta) inputHasta.addEventListener('change', triggerAutoSubmit);
    if (selectEmpleado) selectEmpleado.addEventListener('change', triggerAutoSubmit);

});