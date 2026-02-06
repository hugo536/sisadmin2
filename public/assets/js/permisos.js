(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('permisoSearch');
        const rows = Array.from(document.querySelectorAll('#permisosTable tbody tr'));
        if (!search) return;
        search.addEventListener('input', function () {
            const txt = this.value.toLowerCase().trim();
            rows.forEach(row => {
                row.style.display = (row.dataset.search || '').includes(txt) ? '' : 'none';
            });
        });
    });
})();
