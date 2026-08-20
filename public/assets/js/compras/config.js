// ==============================================================
// MÓDULO CONFIG COMPRAS: config.js (Estado Global y UI Compartida)
// ==============================================================

import { postJson } from '../api.js';

export const app = document.getElementById('comprasApp');

// Exportamos las URLs para que logística, compras y pagos puedan acceder a ellas
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

function parseDatasetJson(value, fallback = []) {
    if (!value) return fallback;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : Object.values(parsed || {});
    } catch (error) {
        console.warn('No se pudo leer datos para compras:', error);
        return fallback;
    }
}

export const cuentasDisponibles = parseDatasetJson(app?.dataset.cuentas, []);
export const metodosDisponibles = parseDatasetJson(app?.dataset.metodos, []);

// ==============================================================
// UTILIDADES ESPECÍFICAS DE COMPRAS
// ==============================================================
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
    const urlParams = nextUrl.searchParams;

    urlParams.delete('accion');

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');
    const filtroOrdenFecha = document.getElementById('filtroOrdenFecha');

    if (filtroBusqueda && filtroBusqueda.value.trim()) urlParams.set('q', filtroBusqueda.value.trim()); else urlParams.delete('q');
    if (filtroEstado && filtroEstado.value !== '') urlParams.set('estado', filtroEstado.value); else urlParams.delete('estado');
    if (filtroFechaDesde && filtroFechaDesde.value) urlParams.set('fecha_desde', filtroFechaDesde.value); else urlParams.delete('fecha_desde');
    if (filtroFechaHasta && filtroFechaHasta.value) urlParams.set('fecha_hasta', filtroFechaHasta.value); else urlParams.delete('fecha_hasta');
    if (filtroOrdenFecha && filtroOrdenFecha.value) urlParams.set('orden_fecha', filtroOrdenFecha.value); else urlParams.delete('orden_fecha');

    const tbodyActual = document.querySelector('#tablaCompras tbody');
    if (tbodyActual) tbodyActual.style.opacity = '0.4';

    try {
        const response = await fetch(nextUrl.toString());
        if (!response.ok) throw new Error('Error en red');
        const html = await response.text();
        
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // 1. Extraemos y reemplazamos SOLO las filas
        const nuevoTbody = doc.querySelector('#tablaCompras tbody');
        if (tbodyActual && nuevoTbody) {
            tbodyActual.innerHTML = nuevoTbody.innerHTML;
            tbodyActual.style.opacity = '1';
        }
        
        // 2. Le avisamos a renderizadores.js que las filas cambiaron para que recalcule la paginación
        if (window.comprasManager) {
            window.comprasManager.refresh();
        }

        window.history.pushState({}, '', nextUrl.toString());
        
        // Disparador global opcional (heredado de compras)
        document.dispatchEvent(new Event('erp-table:reloaded'));
        
    } catch (error) {
        console.error('Fallo en recarga silenciosa, aplicando recarga tradicional:', error);
        window.location.href = nextUrl.toString();
    }
}