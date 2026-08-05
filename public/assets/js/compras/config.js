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

export function recargarPagina() {
    const nextUrl = new URL(window.location.href);
    const params = nextUrl.searchParams;

    params.delete('accion');
    
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    if (filtroBusqueda && filtroBusqueda.value.trim()) params.set('q', filtroBusqueda.value.trim()); else params.delete('q');
    if (filtroEstado && filtroEstado.value !== '') params.set('estado', filtroEstado.value); else params.delete('estado');
    if (filtroFechaDesde && filtroFechaDesde.value) params.set('fecha_desde', filtroFechaDesde.value); else params.delete('fecha_desde');
    if (filtroFechaHasta && filtroFechaHasta.value) params.set('fecha_hasta', filtroFechaHasta.value); else params.delete('fecha_hasta');

    if (typeof window.navigateWithoutReload === 'function') {
        window.navigateWithoutReload(nextUrl, true);
        return;
    }

    window.location.href = nextUrl.toString();
}