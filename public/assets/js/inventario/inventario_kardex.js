(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // 1. Desactivar autocompletado en el buscador interno
    const searchInput = document.getElementById('searchKardex');
    if (searchInput) {
      searchInput.setAttribute('autocomplete', 'off');
    }

    // 2. Elementos del formulario de filtros
    const filtrosForm = document.getElementById('kardexFiltrosForm');
    const itemSelect = document.getElementById('kardexItemSelect');
    const dateInputs = document.querySelectorAll('.kardex-auto-submit');

    // --- NUEVA FUNCIÓN AJAX PARA EVITAR PARPADEOS ---
    const cargarKardexAjax = () => {
      if (!filtrosForm) return;

      // Preparamos los datos y la URL
      const formData = new FormData(filtrosForm);
      const searchParams = new URLSearchParams(formData);
      const url = new URL(window.location.href);
      url.search = searchParams.toString();

      // Mostramos el spinner de "Cargando" en la tabla
      const tableBody = document.getElementById('kardexTableBody');
      if (tableBody) {
        tableBody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center py-5 text-muted fade-in">
              <div class="spinner-border text-primary mb-3" role="status" style="width: 2rem; height: 2rem;"></div>
              <div class="small fw-medium">Actualizando kardex...</div>
            </td>
          </tr>
        `;
      }

      // Actualizamos la URL del navegador sin recargar
      window.history.pushState({}, '', url);

      // Traemos los nuevos datos del servidor
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.text())
        .then(html => {
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const nuevoContenedor = doc.getElementById('contenedorResultadosKardex');
          const contenedorActual = document.getElementById('contenedorResultadosKardex');

          if (nuevoContenedor && contenedorActual) {
            // Reemplazamos el HTML de la tarjeta
            contenedorActual.innerHTML = nuevoContenedor.innerHTML;
            
            // ¡NUEVA LÍNEA DE MAGIA AQUÍ! 
            // Le decimos a tu renderizador que reconecte la paginación y el buscador
            if (window.ERPTable && typeof window.ERPTable.autoInitFromDataset === 'function') {
                window.ERPTable.autoInitFromDataset(contenedorActual);
            }
          }
        })
        .catch(error => {
          console.error("Error cargando AJAX:", error);
          filtrosForm.submit(); // Fallback de seguridad: recarga normal
        });
    };
    // ------------------------------------------------

    // 3. Lógica para el Select de Ítems (Patrón "Filter Chips" externo)
    if (itemSelect && typeof window.AppSelects !== 'undefined' && typeof window.TomSelect !== 'undefined') {
      const tomItem = window.AppSelects.initLocal('#kardexItemSelect', {
        placeholder: 'Buscar ítem...',
        allowEmptyOption: true
      });

      const chipsContainer = document.getElementById('kardexChipsContainer');
      let valoresAnteriores = '';

      // Función que "dibuja" las etiquetas debajo del buscador
      const actualizarChips = () => {
        if (!chipsContainer) return;
        chipsContainer.innerHTML = ''; 
        
        const seleccionados = tomItem.items; 
        
        if (seleccionados.length > 0) {
          tomItem.control_input.setAttribute('placeholder', `${seleccionados.length} ítem(s) seleccionado(s)`);
          
          seleccionados.forEach(id => {
            const textoItem = tomItem.options[id].text;
            
            const chip = document.createElement('span');
            chip.className = 'badge bg-white border border-secondary-subtle text-dark d-flex align-items-center px-3 py-2 shadow-sm fade-in mb-1';
            chip.innerHTML = `
              <span class="text-truncate" style="max-width: 250px;" title="${textoItem}">${textoItem}</span>
              <i class="bi bi-x-circle-fill ms-2 text-danger opacity-75 hover-opacity-100" style="cursor: pointer; font-size: 1.1em;" data-id="${id}"></i>
            `;
            
            chipsContainer.appendChild(chip);
          });
        } else {
          tomItem.control_input.setAttribute('placeholder', 'Buscar ítem...');
        }
      };

      tomItem.on('change', actualizarChips);

      tomItem.on('dropdown_open', () => {
        valoresAnteriores = tomItem.getValue().toString();
        tomItem.control_input.setAttribute('placeholder', 'Escribe para buscar...');
      });

      tomItem.on('dropdown_close', () => {
        // APLICAMOS AJAX AQUÍ
        if (filtrosForm && valoresAnteriores !== tomItem.getValue().toString()) {
          setTimeout(cargarKardexAjax, 100); 
        } else {
          actualizarChips(); 
        }
      });

      if (chipsContainer) {
        chipsContainer.addEventListener('click', (e) => {
          if (e.target.tagName === 'I' && e.target.hasAttribute('data-id')) {
            const idAEliminar = e.target.getAttribute('data-id');
            tomItem.removeItem(idAEliminar); 
            
            // APLICAMOS AJAX AQUÍ
            if (filtrosForm) {
              setTimeout(cargarKardexAjax, 100);
            }
          }
        });
      }

      actualizarChips();

    } else if (itemSelect) {
      // Fallback para select nativo
      itemSelect.addEventListener('change', () => {
        if (filtrosForm) cargarKardexAjax();
      });
    }

    // 4. Lógica para los inputs de fecha (Desde / Hasta)
    dateInputs.forEach((input) => {
      input.addEventListener('change', () => {
        if (filtrosForm) cargarKardexAjax();
      });
    });
  });
})();