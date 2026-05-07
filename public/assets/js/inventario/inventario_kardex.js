(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchKardex');
    if (searchInput) {
      searchInput.setAttribute('autocomplete', 'off');
    }

    const filtrosForm = document.getElementById('kardexFiltrosForm');
    const itemSelect = document.getElementById('kardexItemSelect');
    const dateInputs = document.querySelectorAll('.kardex-auto-submit');

    if (itemSelect && typeof window.AppSelects !== 'undefined' && typeof window.TomSelect !== 'undefined') {
      const tomItem = window.AppSelects.initLocal('#kardexItemSelect', {
        placeholder: 'Buscar ítem...'
      });

      tomItem.on('change', () => {
        if (filtrosForm) filtrosForm.submit();
      });
    } else if (itemSelect) {
      itemSelect.addEventListener('change', () => {
        if (filtrosForm) filtrosForm.submit();
      });
    }

    dateInputs.forEach((input) => {
      input.addEventListener('change', () => {
        if (filtrosForm) filtrosForm.submit();
      });
    });
  });
})();
