/**
 * LIBRERÍA GLOBAL DE ICONOS DE ACCIÓN PARA TABLAS
 * Ubicación: public/assets/js/tablas/iconos_accion.js
 */

window.IconosAccion = window.IconosAccion || {};

// 1. AUTO-INYECTOR DE ESTILOS (Puro JS, sin tocar archivos CSS)
(function inyectarEstilosIconos() {
    if (document.getElementById('estilos-iconos-accion')) return;

    const estilo = document.createElement('style');
    estilo.id = 'estilos-iconos-accion';
    estilo.innerHTML = `
        /* Estructura base del botón */
        .btn-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; padding: 0; border: none;
            background-color: transparent; border-radius: 6px;
            transition: all 0.2s ease-in-out; cursor: pointer;
        }
        .btn-icon:disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* Colores persistentes y efectos Hover sutiles */
        .btn-icon-primary { color: #0d6efd; }
        .btn-icon-primary:hover:not(:disabled) { background-color: rgba(13, 110, 253, 0.1); }
        
        .btn-icon-danger { color: #dc3545; }
        .btn-icon-danger:hover:not(:disabled) { background-color: rgba(220, 53, 69, 0.1); }
        
        .btn-icon-info { color: #0dcaf0; }
        .btn-icon-info:hover:not(:disabled) { background-color: rgba(13, 202, 240, 0.1); }

        .btn-icon-success { color: #198754; }
        .btn-icon-success:hover:not(:disabled) { background-color: rgba(25, 135, 84, 0.1); }

        .btn-icon-warning { color: #ffc107; }
        .btn-icon-warning:hover:not(:disabled) { background-color: rgba(255, 193, 7, 0.1); }
        
        .btn-icon-secondary { color: #6c757d; }
        .btn-icon-secondary:hover:not(:disabled) { background-color: rgba(108, 117, 125, 0.1); }
    `;
    document.head.appendChild(estilo);
})();

// 2. EL CATÁLOGO DE ICONOS (Añade más aquí si los necesitas en el futuro)
const catalogoIconos = {
    'editar':    { claseColor: 'btn-icon-primary',   icono: 'bi bi-pencil-square', tooltip: 'Editar' },
    'eliminar':  { claseColor: 'btn-icon-danger',    icono: 'bi bi-trash3',        tooltip: 'Eliminar' },
    'ver':       { claseColor: 'btn-icon-info',      icono: 'bi bi-eye',           tooltip: 'Ver detalle' },
    'gestionar': { claseColor: 'btn-icon-primary',   icono: 'bi bi-gear-fill',     tooltip: 'Gestionar' },
    'aprobar':   { claseColor: 'btn-icon-success',   icono: 'bi bi-check-circle',  tooltip: 'Aprobar' },
    'anular':    { claseColor: 'btn-icon-warning',   icono: 'bi bi-x-circle',      tooltip: 'Anular' },
    'imprimir':  { claseColor: 'btn-icon-secondary', icono: 'bi bi-printer',       tooltip: 'Imprimir' }
};

// 3. FUNCIONES PARA USAR EN TUS OTROS ARCHIVOS JS
window.IconosAccion = {
    
    /**
     * Genera el HTML de un solo botón.
     * @param {string} tipo - Nombre exacto según el catálogo (ej. 'editar').
     * @param {number|string} id - El ID de la fila en la base de datos.
     * @param {string} clasesJs - (Opcional) Clase para capturar el click (ej. 'js-btn-editar').
     * @param {string} atributosExtra - (Opcional) Ej. 'data-nombre="Bolsa"'.
     */
    crear: function (tipo, id, clasesJs = '', atributosExtra = '') {
        const config = catalogoIconos[tipo];
        
        if (!config) {
            console.error(`Error: El icono tipo "${tipo}" no existe en catalogoIconos.`);
            return '';
        }

        return `<button type="button" class="btn-icon ${config.claseColor} ${clasesJs}" data-id="${id}" ${atributosExtra} title="${config.tooltip}">
                    <i class="${config.icono}"></i>
                </button>`;
    },

    /**
     * Envuelve los botones en el div flexbox correcto para que se vean alineados.
     */
    agrupar: function (...botonesHtml) {
        // Une todos los botones que pases separados por coma
        return `<div class="d-inline-flex gap-1">${botonesHtml.join('')}</div>`;
    }
};