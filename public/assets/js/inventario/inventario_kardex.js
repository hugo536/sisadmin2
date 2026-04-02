(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchKardex');
    if (searchInput) {
      searchInput.setAttribute('autocomplete', 'off');
    }
  });
})();
