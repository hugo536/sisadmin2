(function () {
    function loadScript(src, onDone) {
        var tag = document.createElement('script');
        tag.src = src;
        tag.onload = onDone;
        document.head.appendChild(tag);
    }

    function loadCss(href) {
        if (document.querySelector('link[data-dt="1"]')) return;
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.setAttribute('data-dt', '1');
        document.head.appendChild(link);
    }

    function initDataTable() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) return;
        var $ = window.jQuery;
        var tabla = $('#tablaAsistenciaLogs');
        if (!tabla.length) return;

        tabla.DataTable({
            pageLength: 25,
            order: [[2, 'desc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('tablaAsistenciaLogs')) return;

        loadCss('https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css');

        var proceed = function () {
            if (!window.jQuery.fn.DataTable) {
                loadScript('https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', function () {
                    loadScript('https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js', initDataTable);
                });
                return;
            }
            initDataTable();
        };

        if (!window.jQuery) {
            loadScript('https://code.jquery.com/jquery-3.7.1.min.js', proceed);
            return;
        }

        proceed();
    });
})();
