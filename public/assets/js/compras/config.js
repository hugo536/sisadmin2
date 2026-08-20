// ==============================================================
// MÓDULO CONFIG COMPRAS: config.js (Estado Global y UI Compartida)
// ==============================================================

import { postJson } from '../api.js';

export const app = document.getElementById('comprasApp');

export const urls = app ? {
    index: app.dataset.urlIndex,
    guardar: app.dataset.urlGuardar,
    aprobar: app.dataset.urlAprobar,
    revertirBorrador: app.dataset.urlRevertirBorrador,
    anular: app.dataset.urlAnular,
    recepcionar: app.dataset.urlRecepcionar,
    unidadesItem: app.dataset.urlUnidadesItem,
    precioSugerido: app.dataset.urlPrecioSugerido,
} : {};

// Auxiliar para parsear de forma segura el JSON enviado en data-attributes
function parseDatasetJson(value, fallback = []) {
    if (!value) return fallback;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : Object.values(parsed || {});
    } catch (error) {
        console.warn('No se pudo leer datos de tesorería para compras:', error);
        return fallback;
    }
}

// Exportamos las cuentas y métodos parseados desde el HTML de compras
export const cuentasDisponibles = parseDatasetJson(app?.dataset.cuentas, []);
export const metodosDisponibles = parseDatasetJson(app?.dataset.metodos, []);

export async function postJsonConCarga(url, data, btnElement = null) {
    let originalText = '';
    if (btnElement) {
        originalText = btnElement.innerHTML;
        btnElement.disabled = true;
        btnElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
    }

    try {
        return await postJson(url, data);
    } finally {
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.innerHTML = originalText;
        }
    }
}

// ==============================================================
// RECARGA SILENCIOSA (Evita el parpadeo de la pantalla)
// ==============================================================
export async function recargarPagina() {
    const nextUrl = new URL(window.location.href);
    const params = nextUrl.searchParams;

    params.delete('accion');
    
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');
    const filtroOrdenFecha = document.getElementById('filtroOrdenFecha'); // Asumiendo que también lo agregarás

    if (filtroBusqueda && filtroBusqueda.value.trim()) params.set('q', filtroBusqueda.value.trim()); else params.delete('q');
    if (filtroEstado && filtroEstado.value !== '') params.set('estado', filtroEstado.value); else params.delete('estado');
    if (filtroFechaDesde && filtroFechaDesde.value) params.set('fecha_desde', filtroFechaDesde.value); else params.delete('fecha_desde');
    if (filtroFechaHasta && filtroFechaHasta.value) params.set('fecha_hasta', filtroFechaHasta.value); else params.delete('fecha_hasta');
    if (filtroOrdenFecha && filtroOrdenFecha.value) params.set('orden_fecha', filtroOrdenFecha.value); else params.delete('orden_fecha');

    // Atenuamos la tabla para que el usuario sepa que está cargando
    const tbodyActual = document.querySelector('#tablaCompras tbody');
    if (tbodyActual) tbodyActual.style.opacity = '0.4';

    try {
        // 1. Pedimos la página actualizada en silencio al servidor
        const response = await fetch(nextUrl.toString());
        if (!response.ok) throw new Error('Error en red');
        const html = await response.text();
        
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // 2. Extraemos y reemplazamos SOLO las filas de la tabla de compras
        const nuevoTbody = doc.querySelector('#tablaCompras tbody');
        if (tbodyActual && nuevoTbody) {
            tbodyActual.innerHTML = nuevoTbody.innerHTML;
            tbodyActual.style.opacity = '1';
        }
        
        // 3. Le avisamos a renderizadores.js que las filas cambiaron para la paginación
        // Ojo: asumo que en compras.php pusiste data-manager-global="comprasManager"
        if (window.comprasManager) {
            window.comprasManager.refresh();
        }

        // 4. Actualizamos la URL en el navegador
        window.history.pushState({}, '', nextUrl.toString());
        
        document.dispatchEvent(new Event('erp-table:reloaded'));
        
    } catch (error) {
        console.error('Fallo en recarga silenciosa, aplicando recarga tradicional:', error);
        window.location.href = nextUrl.toString();
    }
}