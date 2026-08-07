// ==============================================================
// MÓDULO CONFIG VENTAS: config.js (Estado Global y UI Compartida)
// ==============================================================

export const app = document.getElementById('ventasApp');

// Exportamos las URLs para que logística, ventas y pagos puedan acceder a ellas
export const urls = app ? {
    index: app.dataset.urlIndex,
    guardar: app.dataset.urlGuardar,
    aprobar: app.dataset.urlAprobar,
    anular: app.dataset.urlAnular,
    despachar: app.dataset.urlDespachar,
} : {};


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

export const cuentasDisponibles = parseDatasetJson(app?.dataset.cuentas, []);
export const metodosDisponibles = parseDatasetJson(app?.dataset.metodos, []);

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

    // SOLUCIÓN APLICADA: Comentamos la recarga silenciosa (SPA) para forzar la recarga nativa
    // Esto asegura que los módulos ES6 se reinicien y los botones nunca pierdan sus eventos.
    /*
    if (typeof window.navigateWithoutReload === 'function') {
        window.navigateWithoutReload(nextUrl, false);
        return;
    }
    */

    window.location.href = nextUrl.toString();
}