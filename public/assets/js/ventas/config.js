// ==============================================================
// MÓDULO CONFIG VENTAS: config.js (Estado Global y UI Compartida)
// ==============================================================

import { postJson } from '../api.js';

export const app = document.getElementById('ventasApp');

// Exportamos las URLs para que logística, ventas y pagos puedan acceder a ellas
export const urls = app ? {
    index: app.dataset.urlIndex,
    guardar: app.dataset.urlGuardar,
    aprobar: app.dataset.urlAprobar,
    anular: app.dataset.urlAnular,
    despachar: app.dataset.urlDespachar,
} : {};

// Auxiliar para parsear de forma segura el JSON enviado en data-attributes
function parseDatasetJson(value, fallback = []) {
    if (!value) return fallback;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : Object.values(parsed || {});
    } catch (error) {
        console.warn('No se pudo leer datos de tesorería para ventas:', error);
        return fallback;
    }
}

// Exportamos las cuentas y métodos parseados desde el HTML de ventas
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

// Extraemos la lógica de recarga para que pueda ser llamada desde cualquier módulo
export function recargarTabla() {
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

    // Restaurado para funcionar como SPA de forma fluida (igual que Compras)
    if (typeof window.navigateWithoutReload === 'function') {
        window.navigateWithoutReload(nextUrl, true);
        return;
    }

    window.location.href = nextUrl.toString();
}

// Alias para evitar errores si app.js o venta.js intentan importar "recargarPagina"
export { recargarTabla as recargarPagina };