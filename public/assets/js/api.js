// ==============================================================
// CORE GLOBAL: api.js 
// Utilidades de red y funciones compartidas para todo el ERP
// ==============================================================

/**
 * Realiza una petición GET esperando un JSON como respuesta.
 * @param {string} url - Ruta del endpoint
 * @returns {Promise<Object>} - Payload de la respuesta
 */
export async function getJson(url) {
    const res = await fetch(url, { 
        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
    });
    const payload = await res.json();
    if (!res.ok || !payload.ok) throw new Error(payload.mensaje || 'Error del servidor');
    return payload;
}

/**
 * Realiza una petición POST enviando y esperando un JSON.
 * @param {string} url - Ruta del endpoint
 * @param {Object} data - Objeto de datos a enviar
 * @returns {Promise<Object>} - Payload de la respuesta
 */
export async function postJson(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json', 
            'X-Requested-With': 'XMLHttpRequest' 
        },
        body: JSON.stringify(data),
    });
    const payload = await res.json();
    if (!res.ok || !payload.ok) throw new Error(payload.mensaje || 'Error al procesar');
    return payload;
}

/**
 * Obtiene la fecha actual del cliente en formato YYYY-MM-DD
 * @returns {string} Fecha local
 */
export function obtenerFechaLocalISO() {
    const ahora = new Date();
    const year = ahora.getFullYear();
    const month = String(ahora.getMonth() + 1).padStart(2, '0');
    const day = String(ahora.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Espera a que la librería TomSelect esté cargada en el DOM
 * @param {number} maxIntentos - Número máximo de verificaciones
 * @param {number} esperaMs - Milisegundos entre verificaciones
 * @returns {Promise<boolean>}
 */
export async function esperarTomSelect(maxIntentos = 20, esperaMs = 150) {
    for (let i = 0; i < maxIntentos; i++) {
        if (typeof TomSelect !== 'undefined') return true;
        await new Promise((resolve) => setTimeout(resolve, esperaMs));
    }
    return false;
}

/**
 * Inicializa un TomSelect con configuración AJAX estándar para el sistema
 * @param {string|HTMLElement} target - Selector o elemento DOM
 * @param {string} urlBackend - Endpoint de búsqueda
 * @param {Object} options - Opciones de configuración adicionales
 * @returns {TomSelect} Instancia de TomSelect
 */
export function initSelectAjax(target, urlBackend, options = {}) {
    // Si existe tu configurador global de selects, lo usa
    if (typeof window !== 'undefined' && window.AppSelects && typeof window.AppSelects.initAjax === 'function') {
        return window.AppSelects.initAjax(target, urlBackend, options);
    }
    
    console.warn('AppSelects no detectado a tiempo. Usando configuración de emergencia.');
    const fallbackOptions = Object.assign({
        valueField: 'id',
        labelField: 'text',
        searchField: ['text', 'value']
    }, options);

    return new TomSelect(target, fallbackOptions);
}